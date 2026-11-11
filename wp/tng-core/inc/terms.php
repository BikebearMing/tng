<?php
/**
 * Term page helpers: location (country) pages and industry / practice
 * (service) pages. The template blocks in blocks/loc-*.php, svc-hero.php and
 * term-*.php all read the queried term through these, so the "On this page"
 * tabs and the sections always agree on what is shown.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The queried term on a term page, else null (e.g. the Site Editor preview). */
function tng_current_term() {
	$term = get_queried_object();
	return ( $term instanceof WP_Term ) ? $term : null;
}

/** ACF object reference for a term ("location_12"). */
function tng_term_ref( $term ) {
	return $term->taxonomy . '_' . $term->term_id;
}

/** A child industry / practice term = a subpractice (hero, overview, experience only). */
function tng_is_subterm( $term ) {
	return $term && $term->parent && in_array( $term->taxonomy, array( 'industry', 'practice' ), true );
}

/** Field-name prefix of the term's toggles: show_loc_* or show_svc_*. */
function tng_term_prefix( $term ) {
	return 'location' === $term->taxonomy ? 'loc' : 'svc';
}

/** Query-string key the insights / awards archives filter this taxonomy by. */
function tng_term_param( $term ) {
	$map = array(
		'location'         => 'loc',
		'industry'         => 'ind',
		'practice'         => 'prac',
		'insight_category' => 'res',
	);
	return isset( $map[ $term->taxonomy ] ) ? $map[ $term->taxonomy ] : '';
}

/** First city office under a country term (city child with office details), else null. */
function tng_country_office( $country ) {
	if ( ! $country || 'location' !== $country->taxonomy ) {
		return null;
	}
	$kids = get_terms(
		array(
			'taxonomy'   => 'location',
			'parent'     => $country->term_id,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $kids ) ) {
		return null;
	}
	foreach ( $kids as $kid ) {
		$ref = 'location_' . $kid->term_id;
		if ( tng_field( 'office_name', '', $ref ) || tng_field( 'address', '', $ref ) ) {
			return $kid;
		}
	}
	return null;
}

/** "tel:" href from a display phone number. */
function tng_tel( $phone ) {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', (string) $phone );
}

/** Latest posts of a type tagged with the term (children included). */
function tng_term_posts( $post_type, $term, $count = 3 ) {
	return get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => $count,
			'tax_query'      => array(
				array(
					'taxonomy'         => $term->taxonomy,
					'terms'            => $term->term_id,
					'include_children' => true,
				),
			),
		)
	);
}

/** Professionals tagged with the term, ordered by position (partners first) then name. */
function tng_term_professionals( $term, $count = 4 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'professional',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy'         => $term->taxonomy,
					'terms'            => $term->term_id,
					'include_children' => true,
				),
			),
		)
	);
	$rank = array( 'partner' => 0, 'counsel' => 1, 'consultant' => 2, 'senior-associate' => 3, 'associate' => 4 );
	$keyed = array();
	foreach ( $ids as $i => $id ) {
		$best = 9;
		$positions = get_the_terms( $id, 'position' );
		foreach ( is_array( $positions ) ? $positions : array() as $p ) {
			if ( isset( $rank[ $p->slug ] ) ) {
				$best = min( $best, $rank[ $p->slug ] );
			}
		}
		$keyed[] = array( $best, $i, $id );
	}
	sort( $keyed ); // position rank, then the title order from the query
	return array_slice( wp_list_pluck( $keyed, 2 ), 0, $count );
}

/** Rows of the term's client successes repeater that have a title. */
function tng_term_successes( $term ) {
	$rows = tng_field( 'successes', array(), tng_term_ref( $term ) );
	return array_values(
		array_filter(
			is_array( $rows ) ? $rows : array(),
			static function ( $row ) {
				return ! empty( $row['title'] );
			}
		)
	);
}

/** Experience label: field, else "Representative Matters" on a subpractice. */
function tng_term_experience_label( $term ) {
	if ( 'location' !== $term->taxonomy ) {
		$label = tng_field( 'experience_label', '', tng_term_ref( $term ) );
		if ( $label ) {
			return $label;
		}
	}
	return tng_is_subterm( $term ) ? 'Representative Matters' : 'Experience';
}

/**
 * Whether a section renders on the term page. One source of truth for the
 * section blocks and the "On this page" tabs. Results are cached per request.
 */
function tng_term_section_visible( $term, $section ) {
	static $cache = array();
	$key = $term->term_id . ':' . $section;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$ref    = tng_term_ref( $term );
	$prefix = tng_term_prefix( $term );
	$sub    = tng_is_subterm( $term );
	$on     = static function ( $name ) use ( $prefix, $ref ) {
		return tng_show( 'show_' . $prefix . '_' . $name, $ref );
	};

	switch ( $section ) {
		case 'overview':
			$v = $on( 'overview' ) && ( tng_field( 'overview_heading', '', $ref ) || tng_field( 'overview_text', '', $ref ) || tng_field( 'quote', '', $ref ) );
			break;
		case 'experience':
			$v = $on( 'experience' ) && tng_field( 'experience', '', $ref );
			break;
		case 'successes':
			$v = ! $sub && $on( 'successes' ) && tng_term_successes( $term );
			break;
		case 'professionals':
			$v = ! $sub && $on( 'professionals' ) && tng_term_professionals( $term, 1 );
			break;
		case 'insights':
			$v = ! $sub && $on( 'insights' ) && tng_term_posts( 'insight', $term, 1 );
			break;
		case 'awards':
			$v = ! $sub && $on( 'awards' ) && tng_term_posts( 'award', $term, 1 );
			break;
		case 'subpractices':
			$kids = get_terms( array( 'taxonomy' => $term->taxonomy, 'parent' => $term->term_id, 'hide_empty' => false, 'fields' => 'ids' ) );
			$v    = ! $sub && 'location' !== $term->taxonomy && tng_show( 'show_svc_subpractices', $ref ) && ! is_wp_error( $kids ) && $kids;
			break;
		case 'office':
			$v = 'location' === $term->taxonomy && tng_show( 'show_loc_office', $ref ) && tng_country_office( $term );
			break;
		default:
			$v = false;
	}

	$cache[ $key ] = (bool) $v;
	return $cache[ $key ];
}

/** Archive page URL for a post type ("View all" targets), filtered to a term. */
function tng_archive_url( $post_type, $term = null ) {
	$path = 'award' === $post_type ? 'about/awards-rankings' : 'insights';
	$url  = tng_page_url( $path );
	if ( '#' === $url ) {
		return '#';
	}
	if ( $term && tng_term_param( $term ) ) {
		$url = add_query_arg( tng_term_param( $term ), $term->slug, $url );
	}
	return $url . '#results';
}

/** City (child location) terms have no page of their own: send them to their country. */
add_action(
	'template_redirect',
	function () {
		if ( ! is_tax( 'location' ) ) {
			return;
		}
		$term = get_queried_object();
		if ( $term instanceof WP_Term && $term->parent ) {
			$parent = get_term( $term->parent, 'location' );
			if ( $parent && ! is_wp_error( $parent ) ) {
				wp_safe_redirect( tng_term_link( $parent ), 301 );
				exit;
			}
		}
	}
);
