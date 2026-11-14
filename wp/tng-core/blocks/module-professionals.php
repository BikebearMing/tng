<?php
/**
 * tng/module-professionals — up to 4 grid cards from the insight's
 * related_professionals relationship. Hidden when none are picked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_module_professionals', $tng_id ) ) {
	return;
}

// Published people only (a trashed or draft professional leaves no gap); none = no section.
$tng_ids = array_slice(
	array_filter(
		tng_rel_ids( 'related_professionals', $tng_id ),
		static function ( $pid ) {
			return 'publish' === get_post_status( $pid );
		}
	),
	0,
	4
);
if ( ! $tng_ids ) {
	return;
}

$tng_cards = '';
foreach ( $tng_ids as $tng_pid ) {
	$tng_cards .= tng_pro_card( $tng_pid );
}

$tng_pros_page = get_page_by_path( 'professionals' );

echo tng_related_section( // phpcs:ignore
	array(
		'class'        => 'pro-related pro-related--people',
		'data_section' => 'in-professionals',
		'data_toggle'  => 'show_module_professionals',
		'heading'      => tng_field( 'module_professionals_heading', 'Related Professionals', $tng_id ),
		'link_url'     => $tng_pros_page ? get_permalink( $tng_pros_page ) : '#',
		'cards'        => $tng_cards,
	)
);
