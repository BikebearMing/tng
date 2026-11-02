<?php
/**
 * GET /wp-json/tng/v1/roster
 *
 * The whole professionals roster as compact JSON, cached in a transient and
 * busted whenever a professional (or its terms) changes. The professionals
 * page filters it entirely client-side (assets/js/tng-wp.js) — no
 * per-keystroke queries, per the plan for a ~700-lawyer roster.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TNG_ROSTER_TRANSIENT = 'tng_roster_v1';

function tng_build_roster() {
	$posts  = get_posts(
		array(
			'post_type'      => 'professional',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$roster = array();

	foreach ( $posts as $p ) {
		$terms = array();
		$names = array();
		foreach ( array( 'industry', 'practice', 'location', 'position', 'language' ) as $tax ) {
			$t             = get_the_terms( $p->ID, $tax );
			$t             = is_array( $t ) ? $t : array();
			$terms[ $tax ] = wp_list_pluck( $t, 'term_id' );
			$names         = array_merge( $names, wp_list_pluck( $t, 'name' ) );
		}

		// Alphabet filter is by surname: last word of the name.
		$words   = preg_split( '/\s+/', trim( $p->post_title ) );
		$surname = $words ? end( $words ) : '';

		$roster[] = array(
			'id'       => $p->ID,
			'name'     => $p->post_title,
			'url'      => get_permalink( $p ),
			'position' => tng_pro_position( $p->ID ),
			'photo'    => tng_pro_photo( $p->ID ),
			'letter'   => strtoupper( mb_substr( $surname, 0, 1 ) ),
			'terms'    => $terms,
			'search'   => strtolower( $p->post_title . ' ' . wp_strip_all_tags( tng_pro_position( $p->ID ) ) . ' ' . implode( ' ', $names ) ),
		);
	}

	// Same order as the server-rendered first page (title ASC); the surname
	// letter is only used by the A–Z filter.
	usort(
		$roster,
		static function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);

	return $roster;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'tng/v1',
			'/roster',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$roster = get_transient( TNG_ROSTER_TRANSIENT );
					if ( false === $roster ) {
						$roster = tng_build_roster();
						set_transient( TNG_ROSTER_TRANSIENT, $roster, DAY_IN_SECONDS );
					}
					$response = rest_ensure_response( $roster );
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
			)
		);
	}
);

function tng_bust_roster() {
	delete_transient( TNG_ROSTER_TRANSIENT );
}
add_action( 'save_post_professional', 'tng_bust_roster' );
add_action( 'deleted_post', 'tng_bust_roster' );
foreach ( array( 'industry', 'practice', 'location', 'position', 'language' ) as $tax ) {
	add_action( "edited_{$tax}", 'tng_bust_roster' );
	add_action( "delete_{$tax}", 'tng_bust_roster' );
	add_action( "created_{$tax}", 'tng_bust_roster' );
}
