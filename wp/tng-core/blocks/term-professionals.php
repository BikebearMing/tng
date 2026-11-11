<?php
/**
 * tng/term-professionals — 4 professionals tagged with the term (partners
 * first), the Module template's related-people row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'professionals' ) ) {
	return;
}

$tng_prefix = tng_term_prefix( $tng_term );
$tng_cards  = '';
foreach ( tng_term_professionals( $tng_term, 4 ) as $tng_pid ) {
	$tng_cards .= tng_pro_card( $tng_pid );
}

echo tng_related_section( // phpcs:ignore
	array(
		'id'           => 'professionals',
		'class'        => 'pro-related pro-related--people' . ( 'svc' === $tng_prefix ? ' svc-people' : '' ),
		'data_section' => $tng_prefix . '-professionals',
		'data_toggle'  => 'show_' . $tng_prefix . '_professionals',
		'heading'      => 'Professionals',
		'link_url'     => tng_page_url( 'professionals' ),
		'cards'        => $tng_cards,
	)
);
