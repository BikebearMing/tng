<?php
/**
 * tng/pro-awards — the 3 latest Awards whose related professionals include
 * this professional. Hidden when there are none.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_pro_awards', $tng_id ) ) {
	return;
}

$tng_posts = tng_posts_related_to_professional( 'award', $tng_id, 3 );
if ( ! $tng_posts ) {
	return;
}

$tng_cards = '';
foreach ( $tng_posts as $tng_p ) {
	$tng_cards .= tng_post_card( $tng_p );
}

echo tng_related_section( // phpcs:ignore
	array(
		'id'           => 'awards',
		'data_section' => 'pro-awards',
		'data_toggle'  => 'show_pro_awards',
		'heading'      => tng_field( 'awards_heading', 'Awards & Rankings', $tng_id ),
		'link_url'     => add_query_arg( 'pro', get_post_field( 'post_name', $tng_id ), tng_page_url( 'about/awards-rankings' ) ) . '#results',
		'cards'        => $tng_cards,
	)
);
