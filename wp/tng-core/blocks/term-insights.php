<?php
/**
 * tng/term-insights — 3 latest insights tagged with the term; "View all" =
 * the insights archive filtered to the term.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'insights' ) ) {
	return;
}

$tng_prefix = tng_term_prefix( $tng_term );
$tng_cards  = '';
foreach ( tng_term_posts( 'insight', $tng_term, 3 ) as $tng_p ) {
	$tng_cards .= tng_post_card( $tng_p );
}

echo tng_related_section( // phpcs:ignore
	array(
		'id'           => 'insights',
		'class'        => 'pro-related' . ( 'loc' === $tng_prefix ? ' loc-insights' : '' ),
		'data_section' => $tng_prefix . '-insights',
		'data_toggle'  => 'show_' . $tng_prefix . '_insights',
		'heading'      => 'Insights',
		'link_url'     => tng_archive_url( 'insight', $tng_term ),
		'cards'        => $tng_cards,
	)
);
