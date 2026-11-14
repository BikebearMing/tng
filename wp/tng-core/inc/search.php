<?php
/**
 * Site search (native WP_Query, no plugin). One engine for the results page
 * (tng/search-results on the tng-search template) and the REST endpoint
 * GET /wp-json/tng/v1/search?q=&type=&per_page=&page=
 *
 * Groups:
 *  - professionals: name matches (title), then everyone tagged with a
 *                   practice / industry / location / position whose name
 *                   contains the query ("intellectual property" = the IP team)
 *  - insights, awards: WP's own search (title, excerpt, content; relevance)
 *  - pages: service / location term pages whose name contains the query,
 *           then published pages matching WP's search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** type key => [post type, label] */
function tng_search_types() {
	return array(
		'professionals' => array( 'professional', 'Professionals' ),
		'insights'      => array( 'insight', 'Insights' ),
		'awards'        => array( 'award', 'Awards & Rankings' ),
		'pages'         => array( 'page', 'Pages' ),
	);
}

/** Terms of $taxonomies whose name contains $q. */
function tng_search_terms( $q, $taxonomies ) {
	$terms = get_terms( array( 'taxonomy' => $taxonomies, 'hide_empty' => false, 'name__like' => $q, 'orderby' => 'name' ) );
	return is_wp_error( $terms ) ? array() : $terms;
}

/** All matching professional IDs: name matches first, then term matches. */
function tng_search_professional_ids( $q ) {
	$by_name = get_posts(
		array(
			'post_type'      => 'professional',
			'post_status'    => 'publish',
			's'              => $q,
			'search_columns' => array( 'post_title' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$terms = tng_search_terms( $q, array( 'practice', 'industry', 'location', 'position' ) );
	$by_term = array();
	if ( $terms ) {
		$tax = array( 'relation' => 'OR' );
		foreach ( $terms as $t ) {
			$tax[] = array( 'taxonomy' => $t->taxonomy, 'terms' => $t->term_id );
		}
		$by_term = get_posts(
			array(
				'post_type'      => 'professional',
				'post_status'    => 'publish',
				'tax_query'      => $tax,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
	return array_values( array_unique( array_merge( $by_name, $by_term ) ) );
}

/**
 * Page rows: [label, url, path label] for term pages, then pages.
 * Text pages are skipped when their nav says nothing more than the query.
 */
function tng_search_page_rows( $q ) {
	$rows   = array();
	$labels = array( 'industry' => 'Industries', 'practice' => 'Practices', 'location' => 'Locations' );
	foreach ( tng_search_terms( $q, array( 'industry', 'practice', 'location' ) ) as $t ) {
		$path = $labels[ $t->taxonomy ];
		if ( $t->parent ) {
			$parent = get_term( $t->parent, $t->taxonomy );
			if ( 'location' === $t->taxonomy ) {
				continue; // office cities redirect to their country
			}
			$path .= $parent && ! is_wp_error( $parent ) ? ' / ' . $parent->name : '';
		}
		$rows[] = array( html_entity_decode( $t->name ), tng_term_link( $t ), $path );
	}
	$front = (int) get_option( 'page_on_front' );
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => $q, 'posts_per_page' => 50, 'post__not_in' => array( $front ) ) ) as $p ) {
		$trail  = array_map( 'get_the_title', array_reverse( get_post_ancestors( $p ) ) );
		$rows[] = array( html_entity_decode( get_the_title( $p ) ), get_permalink( $p ), $trail ? html_entity_decode( implode( ' / ', $trail ) ) : 'Pages' );
	}
	return $rows;
}

/**
 * Run the search. $type '' = every group (first $per_page items each),
 * else one group, page $page. Returns [type => ['label','count','items']],
 * items: post IDs (pages: rows as above). Memoised per request.
 */
function tng_search( $q, $type = '', $per_page = 4, $page = 1 ) {
	static $memo = array();
	$q   = trim( (string) $q );
	$key = md5( $q . '|' . $type . '|' . $per_page . '|' . $page );
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	$out = array();
	if ( '' === $q ) {
		return $memo[ $key ] = $out;
	}
	foreach ( tng_search_types() as $t => $def ) {
		if ( $type && $type !== $t ) {
			continue;
		}
		$n      = max( 1, (int) $per_page );
		$offset = ( max( 1, (int) $page ) - 1 ) * $n;
		if ( 'professionals' === $t ) {
			$all   = tng_search_professional_ids( $q );
			$count = count( $all );
			$items = array_slice( $all, $offset, $n );
		} elseif ( 'pages' === $t ) {
			$all   = tng_search_page_rows( $q );
			$count = count( $all );
			$items = array_slice( $all, $offset, $n );
		} else {
			$query = new WP_Query(
				array(
					'post_type'           => $def[0],
					'post_status'         => 'publish',
					's'                   => $q,
					'posts_per_page'      => $n,
					'paged'               => max( 1, (int) $page ),
					'fields'              => 'ids',
					'ignore_sticky_posts' => true,
					'tng_all_langs'       => true, // ja / zh insights are found here (inc/archive.php)
				)
			);
			$count = (int) $query->found_posts;
			$items = $query->posts;
		}
		if ( $count ) {
			$out[ $t ] = array( 'label' => $def[1], 'count' => $count, 'items' => $items );
		}
	}
	return $memo[ $key ] = $out;
}

/** The query string of the current search request (?s=), cleaned. */
function tng_search_query() {
	return trim( sanitize_text_field( wp_unslash( (string) get_query_var( 's' ) ) ) );
}

// The results page renders its own groups; keep WP's main search query cheap.
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( $query->is_main_query() && $query->is_search() && ! is_admin() ) {
			$query->set( 'posts_per_page', 1 );
			$query->set( 'no_found_rows', true );
		}
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'tng/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array( 'type' => 'string', 'default' => '' ),
					'type'     => array( 'type' => 'string', 'default' => '', 'enum' => array_merge( array( '' ), array_keys( tng_search_types() ) ) ),
					'per_page' => array( 'type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 50 ),
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$q      = sanitize_text_field( (string) $req['q'] );
					$groups = tng_search( $q, (string) $req['type'], (int) $req['per_page'], (int) $req['page'] );
					$out    = array( 'q' => $q, 'total' => 0, 'url' => tng_search_url( $q ), 'groups' => array() );
					foreach ( $groups as $t => $g ) {
						$items = array();
						foreach ( $g['items'] as $item ) {
							if ( 'pages' === $t ) {
								$items[] = array( 'title' => $item[0], 'url' => $item[1], 'path' => $item[2] );
							} elseif ( 'professionals' === $t ) {
								$items[] = array(
									'title'    => html_entity_decode( get_the_title( $item ) ),
									'url'      => get_permalink( $item ),
									'position' => wp_strip_all_tags( str_replace( array( '<br />', '<br>' ), ' ', tng_pro_position( $item ) ) ),
									'photo'    => tng_pro_photo( $item ),
								);
							} else {
								$items[] = array(
									'title' => html_entity_decode( get_the_title( $item ) ),
									'url'   => get_permalink( $item ),
									'date'  => get_the_date( 'Y-m-d', $item ),
								);
							}
						}
						$out['total']       += $g['count'];
						$out['groups'][ $t ] = array(
							'label' => $g['label'],
							'count' => $g['count'],
							'url'   => tng_search_url( $q, $t ),
							'items' => $items,
						);
					}
					$out['groups'] = (object) $out['groups'];
					$res           = rest_ensure_response( $out );
					$res->header( 'Cache-Control', 'public, max-age=300' );
					return $res;
				},
			)
		);
	}
);
