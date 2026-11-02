<?php
/**
 * Front-end and editor assets. Order mirrors the static HTML exactly:
 * fonts, blaze.css, lenis.css, style.css (+ WP overrides), then
 * gsap → ScrollTrigger → SplitText → blaze-slider → lenis → main.js → tng-wp.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TNG_FONTS_URL', 'https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,100..900;1,100..900&family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:wght@400;500;600;700&display=swap' );

add_action(
	'wp_enqueue_scripts',
	function () {
		$v = TNG_CORE_VERSION;

		wp_enqueue_style( 'tng-fonts', TNG_FONTS_URL, array(), null );
		wp_enqueue_style( 'tng-blaze', tng_asset( 'vendor/blaze.css' ), array(), $v );
		wp_enqueue_style( 'tng-lenis', tng_asset( 'vendor/lenis.css' ), array(), $v );
		wp_enqueue_style( 'tng-style', tng_asset( 'css/style.css' ), array( 'tng-blaze', 'tng-lenis' ), $v );
		wp_enqueue_style( 'tng-wp-overrides', tng_asset( 'css/wp-overrides.css' ), array( 'tng-style' ), $v );

		foreach ( array(
			'tng-gsap'          => 'vendor/gsap.min.js',
			'tng-scrolltrigger' => 'vendor/ScrollTrigger.min.js',
			'tng-splittext'     => 'vendor/SplitText.min.js',
			'tng-blaze-js'      => 'vendor/blaze-slider.min.js',
			'tng-lenis-js'      => 'vendor/lenis.min.js',
			'tng-main'          => 'js/main.js',
			'tng-wp'            => 'js/tng-wp.js',
		) as $handle => $path ) {
			wp_enqueue_script( $handle, tng_asset( $path ), array(), $v, true );
		}

		wp_localize_script(
			'tng-wp',
			'TNG',
			array(
				'roster' => esc_url_raw( rest_url( 'tng/v1/roster' ) ),
			)
		);
	}
);

// Preconnect for the font files.
add_filter(
	'wp_resource_hints',
	function ( $urls, $relation ) {
		if ( 'preconnect' === $relation ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => true,
			);
		}
		return $urls;
	},
	10,
	2
);

// Twenty Twenty-Five's global styles (spacing presets, blockGap, root padding,
// typography) would fight style.css; the site's look lives entirely in our CSS.
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'twentytwentyfive-style' );
	},
	100
);

// Editor: load the site CSS so block previews look right — but never main.js
// (its reveals hide text until scrolled and SplitText would mangle editing).
add_action(
	'enqueue_block_assets',
	function () {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style( 'tng-fonts-editor', TNG_FONTS_URL, array(), null );
		wp_enqueue_style( 'tng-style-editor', tng_asset( 'css/style.css' ), array(), TNG_CORE_VERSION );
		wp_enqueue_style( 'tng-editor-tweaks', tng_asset( 'css/editor.css' ), array( 'tng-style-editor' ), TNG_CORE_VERSION );
	}
);
