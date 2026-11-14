<?php
/**
 * acf/lang-insights — the desks' insights row: the latest 3 insights written
 * in the page's language (imported posts carry _tng_lang = ja / zh). Hidden
 * when there are none yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_lang_insights' ) ) {
	return;
}

$tng_lang  = 'zh' === tng_field( 'lang', 'ja' ) ? 'zh' : 'ja';
$tng_posts = get_posts(
	array(
		'post_type'      => 'insight',
		'posts_per_page' => 3,
		'tng_lang'       => $tng_lang, // inc/archive.php: only this language
	)
);
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
		'class'        => 'pro-related desk-insights',
		'data_section' => 'desk-insights',
		'data_toggle'  => 'show_lang_insights',
		'heading'      => tng_field( 'heading', 'Insights' ),
		'link_label'   => tng_field( 'link_label', 'View all' ),
		'link_url'     => tng_field( 'link_url', add_query_arg( 'lang', $tng_lang, tng_page_url( 'insights' ) ) . '#results' ),
		'cards'        => $tng_cards,
	)
);
