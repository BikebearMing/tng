<?php
/**
 * tng/pro-insights — the 3 latest Insights whose related professionals
 * include this professional. Hidden when there are none.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_pro_insights', $tng_id ) ) {
	return;
}

$tng_posts = tng_posts_related_to_professional( 'insight', $tng_id, 3 );
if ( ! $tng_posts ) {
	return;
}

$tng_cards = '';
foreach ( $tng_posts as $tng_p ) {
	$tng_cards .= tng_post_card( $tng_p );
}

echo tng_related_section( // phpcs:ignore
	array(
		'id'           => 'insights',
		'data_section' => 'pro-insights',
		'data_toggle'  => 'show_pro_insights',
		'heading'      => tng_field( 'insights_heading', 'Insights', $tng_id ),
		'link_url'     => add_query_arg( 'pro', get_post_field( 'post_name', $tng_id ), tng_page_url( 'insights' ) ) . '#results', // the archive's professional filter
		'cards'        => $tng_cards,
	)
);
