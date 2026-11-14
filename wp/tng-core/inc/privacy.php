<?php
/**
 * No user enumeration for visitors (fix round 2026-11-14): the REST users
 * routes, ?author=N, /author/<name>/, author names in feeds and oEmbed, and the
 * users sitemap. Logged-in users (wp-admin, the block editor's REST calls with
 * their nonce) are untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}
);

// ?author=N and /author/<name>/ → 404, before redirect_canonical would print
// the username in a Location header.
add_filter(
	'request',
	function ( $vars ) {
		if ( ! is_admin() && ( isset( $vars['author'] ) || isset( $vars['author_name'] ) ) && ! is_user_logged_in() ) {
			unset( $vars['author'], $vars['author_name'] );
			$vars['error'] = '404';
		}
		return $vars;
	}
);
add_action(
	'template_redirect',
	function () {
		if ( is_author() && ! is_user_logged_in() ) { // e.g. ?author_name= set some other way
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	},
	0
);

add_filter( 'wp_sitemaps_add_provider', static function ( $provider, $name ) { return 'users' === $name ? false : $provider; }, 10, 2 );

add_filter(
	'oembed_response_data',
	function ( $data ) {
		$data['author_name'] = get_bloginfo( 'name' );
		$data['author_url']  = home_url( '/' );
		return $data;
	}
);

// Feeds: the firm, not the account that imported the post.
add_filter( 'the_author', static function ( $name ) { return is_feed() ? get_bloginfo( 'name' ) : $name; } );
