<?php
/**
 * tng/term-awards — 3 latest awards tagged with the term; "View all" = the
 * Awards & Rankings page filtered to the term.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'awards' ) ) {
	return;
}

$tng_prefix = tng_term_prefix( $tng_term );
$tng_cards  = '';
foreach ( tng_term_posts( 'award', $tng_term, 3 ) as $tng_p ) {
	$tng_cards .= tng_post_card( $tng_p );
}

echo tng_related_section( // phpcs:ignore
	array(
		'id'           => 'awards',
		'class'        => 'pro-related' . ( 'loc' === $tng_prefix ? ' loc-awards' : '' ),
		'data_section' => $tng_prefix . '-awards',
		'data_toggle'  => 'show_' . $tng_prefix . '_awards',
		'heading'      => 'Awards & Rankings',
		'link_url'     => tng_archive_url( 'award', $tng_term ),
		'cards'        => $tng_cards,
	)
);
