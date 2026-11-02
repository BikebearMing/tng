<?php
/**
 * tng/module-insights — 3 related insight cards; empty relationship = the
 * latest insights sharing this insight's industry terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_module_insights', $tng_id ) ) {
	return;
}

$tng_ids = array_slice( tng_rel_ids( 'module_insights', $tng_id ), 0, 3 );
if ( $tng_ids ) {
	$tng_posts = get_posts( array( 'post_type' => 'insight', 'post__in' => $tng_ids, 'orderby' => 'post__in', 'posts_per_page' => 3 ) );
} else {
	$tng_args = array(
		'post_type'      => 'insight',
		'posts_per_page' => 3,
		'post__not_in'   => array( $tng_id ),
	);
	$tng_industries = get_the_terms( $tng_id, 'industry' );
	if ( is_array( $tng_industries ) && $tng_industries ) {
		$tng_args['tax_query'] = array(
			array(
				'taxonomy' => 'industry',
				'terms'    => wp_list_pluck( $tng_industries, 'term_id' ),
			),
		);
	}
	$tng_posts = get_posts( $tng_args );
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
		'class'        => 'pro-related in-related-insights',
		'data_section' => 'in-related-insights',
		'data_toggle'  => 'show_module_insights',
		'heading'      => tng_field( 'module_insights_heading', 'Related Insights', $tng_id ),
		'cards'        => $tng_cards,
	)
);
