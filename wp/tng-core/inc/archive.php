<?php
/**
 * Insights / awards archive: one query builder shared by the grid block (first
 * page, server-rendered) and GET /wp-json/tng/v1/archive ("View more").
 *
 * Filters come from the query string, so a filtered list is a plain URL
 * (shareable, cacheable, works without JS):
 *   q    title search ("Search by Name")
 *   pro  professional name ("Search by Professionals": posts whose related
 *        professionals include a match)
 *   res  insight category slug   ind  industry slug
 *   prac practice slug           loc  location slug
 * The keys avoid the taxonomy query vars (location, industry, practice) on
 * purpose: WordPress would treat those as a taxonomy request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Allowed filter keys => [chip label, taxonomy or ''] */
function tng_archive_filters() {
	return array(
		'q'    => array( 'Name', '' ),
		'pro'  => array( 'Professional', '' ),
		'res'  => array( 'Resources', 'insight_category' ),
		'ind'  => array( 'Industries', 'industry' ),
		'prac' => array( 'Practices', 'practice' ),
		'loc'  => array( 'Locations', 'location' ),
	);
}

/** Clean filter params from an array (e.g. $_GET or REST params). */
function tng_archive_params( $src ) {
	$out = array();
	foreach ( array_keys( tng_archive_filters() ) as $key ) {
		if ( isset( $src[ $key ] ) && is_scalar( $src[ $key ] ) ) {
			$value = trim( sanitize_text_field( wp_unslash( (string) $src[ $key ] ) ) );
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
		$args['s']              = $params['q'];
		$args['search_columns'] = array( 'post_title' );
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
		$pros = get_posts(
			array(
				'post_type'      => 'professional',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				's'              => $params['pro'],
				'search_columns' => array( 'post_title' ),
			)
		);
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
		if ( empty( $params[ $key ] ) ) {
			continue;
		}
		$value = $params[ $key ];
		if ( $def[1] ) {
			$term = get_term_by( 'slug', sanitize_title( $value ), $def[1] );
			if ( $term ) {
				$value = $term->name;
			}
		}
		$rest    = $params;
		unset( $rest[ $key ] );
		$chips[] = array( $def[0], $value, add_query_arg( array_map( 'rawurlencode', $rest ), $base ) . '#results' );
	}
	return $chips;
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
