<?php
/**
 * tng/award-more — "More Awards & Rankings" under an award: the latest 3
 * other awards, those sharing one of this award's locations first, topped up
 * with the latest others. "View all" = the Awards & Rankings archive page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id    = get_the_ID();
$tng_posts = array();
$tng_locs  = get_the_terms( $tng_id, 'location' );
if ( is_array( $tng_locs ) && $tng_locs ) {
	$tng_posts = get_posts(
		array(
			'post_type'      => 'award',
			'posts_per_page' => 3,
			'post__not_in'   => array( $tng_id ),
			'tax_query'      => array(
				array(
					'taxonomy' => 'location',
					'terms'    => wp_list_pluck( $tng_locs, 'term_id' ),
				),
			),
		)
	);
}
if ( count( $tng_posts ) < 3 ) {
	$tng_posts = array_merge(
		$tng_posts,
		get_posts(
			array(
				'post_type'      => 'award',
				'posts_per_page' => 3 - count( $tng_posts ),
				'post__not_in'   => array_merge( array( $tng_id ), wp_list_pluck( $tng_posts, 'ID' ) ),
			)
		)
	);
}
if ( ! $tng_posts ) {
	return;
}

$tng_cards = '';
foreach ( $tng_posts as $tng_p ) {
	$tng_cards .= tng_post_card( $tng_p );
}

echo tng_related_section( // phpcs:ignore
	array(
		'class'        => 'pro-related in-related-insights award-more',
		'data_section' => 'award-related',
		'data_toggle'  => 'show_award_related',
		'heading'      => 'More Awards & Rankings',
		'link_url'     => tng_page_url( 'about/awards-rankings' ),
		'cards'        => $tng_cards,
	)
);
