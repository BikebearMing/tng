<?php
/**
 * Insights / awards archive: one query builder shared by the grid block (first
 * page, server-rendered) and GET /wp-json/tng/v1/archive ("View more").
 *
 * Filters come from the query string, so a filtered list is a plain URL
 * (shareable, cacheable, works without JS; main.js swaps the grid in place
 * through the REST route and keeps the URL in step with pushState):
 *   q    search ("Search by Name": titles, then excerpts and content)
 *   pro  professional slug or typed name ("Search by Professionals": posts
 *        whose related professionals include them)
 *   date publication year ("Date"; not `year`: WordPress's own query var would 404 the page)
 *   res  insight category slug   ind  industry slug
 *   prac practice slug           loc  location slug
 *   lang ja / zh (the posts the English lists leave out)
 *   sort relevance (default; = newest without q) / newest / oldest
 * Filters combine with AND, one value per key. The keys avoid the taxonomy
 * query vars (location, industry, practice) on purpose: WordPress would
 * treat those as a taxonomy request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Allowed filter keys => [chip label, taxonomy or ''] */
function tng_archive_filters() {
	return array(
		'q'    => array( 'Name', '' ),
		'pro'  => array( 'Professional', '' ),
		'date' => array( 'Date', '' ),
		'res'  => array( 'Resources', 'insight_category' ),
		'ind'  => array( 'Industries', 'industry' ),
		'prac' => array( 'Practices', 'practice' ),
		'loc'  => array( 'Locations', 'location' ),
		'lang' => array( 'Language', '' ), // ja / zh: the posts the English lists leave out
		'sort' => array( 'Sort', '' ),     // not a filter: never a chip
	);
}

/* ---------------- Languages kept out of the English lists ---------------- */

/**
 * Imported Japanese / Chinese insights and awards (_tng_lang) stay out of every
 * list (Home, archives and their counts, related / more rows, term and
 * professional pages). They keep their own URLs and show in site search, on
 * the desks' insights rows and on the archive with ?lang=ja|zh.
 * Default chosen 2026-11-14, not yet confirmed by the user: return array()
 * from this filter to list them everywhere.
 */
function tng_hidden_langs() {
	return (array) apply_filters( 'tng_hidden_langs', array( 'ja', 'zh' ) );
}

/** Names for the ?lang= chip. */
function tng_lang_label( $lang ) {
	$names = array( 'ja' => 'Japanese', 'zh' => 'Chinese', 'en' => 'English' );
	return isset( $names[ $lang ] ) ? $names[ $lang ] : strtoupper( $lang );
}

/** IDs of posts written in a hidden language (one query per request). */
function tng_hidden_lang_ids() {
	static $ids = null;
	if ( null === $ids ) {
		global $wpdb;
		$langs = tng_hidden_langs();
		$ids   = $langs ? array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tng_lang' AND meta_value IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . ')', $langs ) ) ) : array(); // phpcs:ignore
	}
	return $ids;
}

/**
 * Query vars: tng_lang = 'ja' lists only that language; tng_all_langs = true
 * (site search) lists every language. Main queries (a post at its own URL),
 * explicit picks (post__in), wp-admin, sitemaps and core REST are untouched.
 */
add_action(
	'pre_get_posts',
	function ( $q ) {
		$lang = (string) $q->get( 'tng_lang' );
		if ( '' !== $lang ) {
			$clause = array( 'key' => '_tng_lang', 'value' => $lang );
			$mq     = $q->get( 'meta_query' );
			$q->set( 'meta_query', $mq ? array( 'relation' => 'AND', $mq, $clause ) : array( $clause ) );
			return;
		}
		if ( is_admin() || $q->is_main_query() || $q->get( 'tng_all_langs' ) || $q->get( 'post__in' ) || get_query_var( 'sitemap' ) ) {
			return;
		}
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( '' !== $route && 0 !== strpos( $route, '/tng/v1/' ) ) {
			return; // the block editor's own REST lists keep every post
		}
		if ( ! array_intersect( (array) $q->get( 'post_type' ), array( 'insight', 'award' ) ) ) {
			return;
		}
		$ids = tng_hidden_lang_ids();
		if ( $ids ) {
			$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $ids ) );
		}
	}
);

/**
 * Professionals a ?pro= value means: the one with that slug (the "View all"
 * links pass the slug), else those named exactly that, else a word search on
 * names (typed in the filter). Word search alone let "Tra Thi Thanh Tran"
 * also match "Truc Thi Thanh Tran" ("Tra" is inside "Tran").
 */
function tng_archive_pros( $value ) {
	$base = array( 'post_type' => 'professional', 'posts_per_page' => 10, 'fields' => 'ids' ); // each match is one LIKE over every related_professionals row
	if ( sanitize_title( $value ) === $value ) {
		$ids = get_posts( $base + array( 'name' => $value ) );
		if ( $ids ) {
			return $ids;
		}
	}
	$ids = get_posts( $base + array( 'title' => $value ) );
	return $ids ? $ids : get_posts( $base + array( 's' => $value, 'search_columns' => array( 'post_title' ) ) );
}

/** What to show for a ?pro= value: the professional's name when it is a slug. */
function tng_archive_pro_label( $value ) {
	$pro = sanitize_title( $value ) === $value ? get_page_by_path( $value, OBJECT, 'professional' ) : null;
	return $pro ? html_entity_decode( get_the_title( $pro ), ENT_QUOTES ) : $value;
}

/** Clean filter params from an array (e.g. $_GET or REST params). */
function tng_archive_params( $src ) {
	$out = array();
	foreach ( array_keys( tng_archive_filters() ) as $key ) {
		if ( isset( $src[ $key ] ) && is_scalar( $src[ $key ] ) ) {
			$value = trim( sanitize_text_field( wp_unslash( (string) $src[ $key ] ) ) );
			if ( 'date' === $key && ! preg_match( '/^(19|20)\d\d$/', $value ) ) {
				continue;
			}
			if ( 'sort' === $key && ! in_array( $value, array( 'relevance', 'newest', 'oldest' ), true ) ) {
				continue;
			}
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}
	}
	return $out;
}

/** WP_Query for the archive grid. */
function tng_archive_query( $post_type, $params, $page = 1, $per_page = 12 ) {
	$post_type = in_array( $post_type, array( 'insight', 'award' ), true ) ? $post_type : 'insight';
	$args      = array(
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, min( 60, (int) $per_page ) ),
		'paged'               => max( 1, (int) $page ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	);

	if ( ! empty( $params['q'] ) ) {
		$args['s'] = $params['q']; // title, excerpt, content
	}
	$sort = isset( $params['sort'] ) ? $params['sort'] : '';
	if ( 'oldest' === $sort ) {
		$args['order'] = 'ASC';
	} elseif ( ! empty( $params['q'] ) && 'newest' !== $sort ) {
		$args['orderby'] = 'relevance'; // WP: phrase in title, all words in title, any word in title, excerpt, content; then newest
	}
	if ( ! empty( $params['date'] ) ) {
		$args['date_query'] = array( array( 'year' => (int) $params['date'] ) );
	}
	if ( ! empty( $params['lang'] ) && preg_match( '/^[a-z]{2}$/', $params['lang'] ) ) {
		$args['tng_lang'] = $params['lang'];
	}

	$tax = array();
	foreach ( tng_archive_filters() as $key => $def ) {
		if ( empty( $params[ $key ] ) || ! $def[1] || ! is_object_in_taxonomy( $post_type, $def[1] ) ) {
			continue;
		}
		$tax[] = array(
			'taxonomy'         => $def[1],
			'field'            => 'slug',
			'terms'            => sanitize_title( $params[ $key ] ),
			'include_children' => true,
		);
	}
	if ( $tax ) {
		$args['tax_query'] = $tax;
	}

	if ( ! empty( $params['pro'] ) ) {
		$pros = tng_archive_pros( $params['pro'] );
		if ( ! $pros ) {
			$args['post__in'] = array( 0 ); // no such professional: no results
		} else {
			$meta = array( 'relation' => 'OR' );
			foreach ( $pros as $pid ) {
				$meta[] = array(
					'key'     => 'related_professionals',
					'value'   => '"' . (int) $pid . '"',
					'compare' => 'LIKE',
				);
			}
			$args['meta_query'] = $meta;
		}
	}

	return new WP_Query( $args );
}

/**
 * Active-filter chips: [key label, value label, URL without this filter].
 * $base = the archive page URL (no query string).
 */
function tng_archive_chips( $params, $base ) {
	$chips = array();
	foreach ( tng_archive_filters() as $key => $def ) {
		if ( empty( $params[ $key ] ) || 'sort' === $key ) {
			continue;
		}
		$value = 'lang' === $key ? tng_lang_label( $params[ $key ] ) : $params[ $key ];
		$value = 'pro' === $key ? tng_archive_pro_label( $value ) : $value;
		if ( $def[1] ) {
			$term = get_term_by( 'slug', sanitize_title( $value ), $def[1] );
			if ( $term ) {
				$value = $term->name;
			}
		}
		$rest    = $params;
		unset( $rest[ $key ] );
		$chips[] = array( $def[0], $value, add_query_arg( array_map( 'rawurlencode', $rest ), $base ) . '#results', $key );
	}
	return $chips;
}

/* ---------------- Filter bar: facet options + markup ---------------- */

/**
 * Terms of $taxonomy that published posts of $post_type use (Japanese /
 * Chinese posts left out), A–Z: [ [id, slug, name, ids], … ], ids = the term
 * and its children (the archive query includes children). Cached for an
 * hour and dropped whenever a post of that type is saved.
 */
function tng_filter_terms( $taxonomy, $post_type ) {
	$key = "tng_fterms_{$post_type}_{$taxonomy}";
	$out = get_transient( $key );
	if ( false !== $out ) {
		return $out;
	}
	global $wpdb;
	$hidden = 'professional' === $post_type ? array() : tng_hidden_lang_ids();
	$not    = $hidden ? ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $hidden ) ) . ')' : '';
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.slug, t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = 'publish'{$not} GROUP BY t.term_id, t.slug, t.name ORDER BY t.name", $taxonomy, $post_type ) ); // phpcs:ignore
	$out    = array();
	foreach ( $rows as $r ) {
		$kids  = get_term_children( (int) $r->term_id, $taxonomy );
		$out[] = array( (int) $r->term_id, $r->slug, html_entity_decode( $r->name, ENT_QUOTES ), array_merge( array( (int) $r->term_id ), is_array( $kids ) ? array_map( 'intval', $kids ) : array() ) );
	}
	set_transient( $key, $out, HOUR_IN_SECONDS );
	return $out;
}

/** Years with published posts of $post_type, newest first (cached like the terms). */
function tng_filter_years( $post_type ) {
	$key = "tng_fyears_{$post_type}";
	$out = get_transient( $key );
	if ( false === $out ) {
		global $wpdb;
		$out = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY 1 DESC", $post_type ) ) );
		set_transient( $key, $out, HOUR_IN_SECONDS );
	}
	return $out;
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( ! in_array( $post->post_type, array( 'insight', 'award', 'professional' ), true ) ) {
			return;
		}
		delete_transient( "tng_fyears_{$post->post_type}" );
		foreach ( array( 'insight_category', 'industry', 'practice', 'location', 'position', 'language' ) as $tax ) {
			delete_transient( "tng_fterms_{$post->post_type}_{$tax}" );
		}
	},
	10,
	2
);

/** Post type the page's archive grid lists (the filter block has no field of its own). */
function tng_archive_page_type( $post_id = null ) {
	$post = get_post( $post_id );
	foreach ( $post ? parse_blocks( $post->post_content ) : array() as $b ) {
		if ( 'acf/archive-grid' === $b['blockName'] ) {
			return ( isset( $b['attrs']['data']['source'] ) && 'award' === $b['attrs']['data']['source'] ) ? 'award' : 'insight';
		}
	}
	return 'insight';
}

/**
 * A filter-bar dropdown: the trigger button (facet cell, or the Date cell)
 * and its panel right after it, so Tab runs trigger → options → next
 * trigger. main.js opens / positions the panel and fires `tng:facet`.
 *   $key      query key (data-key)          $label  closed label ("Industries")
 *   $options  [ value => name ] ('' = the "all" row, listed first)
 *   $current  selected value ('' = none)    $args   class (trigger), prefix
 *             ("Sort by "), panel_class, ids ([ value => "1,2" ], professionals),
 *             tax (professionals), icon (trigger's trailing markup)
 */
function tng_filter_dropdown( $key, $label, $options, $current, $args = array() ) {
	$args   = wp_parse_args( $args, array( 'class' => 'pro-filter__facet', 'prefix' => '', 'panel_class' => '', 'ids' => array(), 'tax' => '', 'icon' => '' ) );
	$id     = 'flt-' . $key;
	$chosen = ( '' !== $current && isset( $options[ $current ] ) ) ? $current : '';
	$text   = '' === $chosen ? $label : $args['prefix'] . $options[ $chosen ];
	$sr     = ( '' === $chosen || $args['prefix'] ) ? '' : '<span class="visually-hidden">' . esc_html( $label ) . ': </span>';
	$tick   = '<span class="flt-option__tick" aria-hidden="true"><svg viewBox="0 0 10 10" fill="none"><path d="M2 5.2l2 2L8 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
	$html   = '<button type="button" class="' . esc_attr( $args['class'] . ( '' === $chosen ? '' : ' is-set' ) ) . '" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" data-label="' . esc_attr( $label ) . '" data-prefix="' . esc_attr( $args['prefix'] ) . '">'
		. '<span>' . $sr . esc_html( $text ) . '</span>' . $args['icon'] . '</button>';
	$html  .= '<div class="flt-panel' . ( $args['panel_class'] ? ' ' . esc_attr( $args['panel_class'] ) : '' ) . '" id="' . esc_attr( $id ) . '" data-key="' . esc_attr( $key ) . '"' . ( $args['tax'] ? ' data-tax="' . esc_attr( $args['tax'] ) . '"' : '' ) . ' role="group" aria-label="' . esc_attr( $label ) . '" data-lenis-prevent hidden><ul class="flt-panel__list">';
	foreach ( $options as $value => $name ) {
		$value = (string) $value;
		$ids   = isset( $args['ids'][ $value ] ) ? ' data-ids="' . esc_attr( $args['ids'][ $value ] ) . '"' : '';
		$html .= '<li><button type="button" class="flt-option" data-value="' . esc_attr( $value ) . '"' . $ids . ' aria-pressed="' . ( $value === $chosen ? 'true' : 'false' ) . '">' . esc_html( $name ) . $tick . '</button></li>';
	}
	return $html . '</ul></div>';
}

/** [ value => name ] for a taxonomy facet, '' row first; $ids gets [ slug => "id,child ids" ]. */
function tng_filter_term_options( $taxonomy, $post_type, $all, &$ids = null ) {
	$opts = array( '' => $all );
	$ids  = array();
	foreach ( tng_filter_terms( $taxonomy, $post_type ) as $t ) {
		$opts[ $t[1] ] = $t[2];
		$ids[ $t[1] ]  = implode( ',', $t[3] );
	}
	return $opts;
}

/** Rendered post cards of a query. */
function tng_archive_cards( $query ) {
	$html = '';
	foreach ( $query->posts as $p ) {
		$html .= tng_post_card( $p );
	}
	return $html;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'tng/v1',
			'/archive',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$params = tng_archive_params( $request->get_params() );
					$page   = max( 1, (int) $request->get_param( 'page' ) );
					$size   = (int) $request->get_param( 'per_page' );
					$size   = $size > 0 ? $size : 12;
					$query  = tng_archive_query( (string) $request->get_param( 'type' ), $params, $page, $size );
					$response = rest_ensure_response(
						array(
							'html'  => tng_archive_cards( $query ),
							'total' => (int) $query->found_posts,
							'more'  => $page < (int) $query->max_num_pages,
						)
					);
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
			)
		);

		// Visitor country for the service hero's default contact tab. Pages are
		// edge-cached, so the tab is picked client-side from this uncached call.
		// Cloudflare adds CF-IPCountry; without it the answer is empty (= Thailand).
		register_rest_route(
			'tng/v1',
			'/geo',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$country  = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) ) : '';
					$response = rest_ensure_response( array( 'country' => preg_replace( '/[^A-Z]/', '', $country ) ) );
					$response->header( 'Cache-Control', 'no-store, private' );
					return $response;
				},
			)
		);
	}
);
