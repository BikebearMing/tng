<?php
/**
 * Block registration.
 *
 * Two families:
 *  - acf/* page blocks: carry their own SCF field group (location: block),
 *    used on the Home / Professionals / Contact / Careers pages. The client
 *    edits fields per block instance.
 *  - tng/* template blocks: no block fields; they render from the current
 *    post's SCF fields (professional and insight singles) or from the
 *    options pages (header / footer). They live in the plugin-registered
 *    Site Editor templates.
 *
 * Every render template outputs the exact section markup of the static HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// acf/* page blocks: [name => title]
function tng_acf_blocks() {
	return array(
		'home-featured-banner' => 'TNG: Featured banner',
		'home-intro-stats'     => 'TNG: Intro + stats + regional presence',
		'home-insights'        => 'TNG: Insights by industry',
		'home-careers'         => 'TNG: Careers strip',
		'home-awards'          => 'TNG: Awards & Rankings',
		'page-hero'            => 'TNG: Page hero',
		'pro-filter'           => 'TNG: Professionals filter',
		'pro-grid'             => 'TNG: Professionals grid',
		'contact-offices'      => 'TNG: Contact offices',
		'careers-hero'         => 'TNG: Careers hero',
		'careers-paths'        => 'TNG: Career paths',
		'careers-values'       => 'TNG: Careers values slider',
		'careers-people'       => 'TNG: Careers people',
		'careers-cta'          => 'TNG: Careers CTA',
		'careers-jobs'         => 'TNG: Job opportunities',
	);
}

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}
		foreach ( tng_acf_blocks() as $name => $title ) {
			acf_register_block_type(
				array(
					'name'            => $name,
					'title'           => $title,
					'category'        => 'design',
					'icon'            => 'layout',
					'mode'            => 'edit',
					'render_template' => TNG_CORE_DIR . 'blocks/' . $name . '.php',
					'supports'        => array(
						'align'  => false,
						'anchor' => false,
						'mode'   => true,
						'jsx'    => false,
					),
				)
			);
		}
	}
);

// tng/* template blocks (render from post fields / options).
add_action(
	'init',
	function () {
		$render = function ( $file ) {
			return function ( $attributes, $content, $wp_block ) use ( $file ) {
				ob_start();
				include TNG_CORE_DIR . 'blocks/' . $file . '.php';
				return ob_get_clean();
			};
		};

		register_block_type(
			'tng/site-header',
			array(
				'attributes'      => array(
					'light' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'render_callback' => $render( 'site-header' ),
			)
		);

		foreach ( array(
			'site-footer',
			'under-development',
			'pro-hero',
			'pro-tabs',
			'pro-overview',
			'pro-experience',
			'pro-insights',
			'pro-awards',
			'insight-hero',
			'insight-bar',
			'insight-article',
			'module-hero',
			'module-panels',
			'module-findings',
			'module-professionals',
			'module-download',
			'module-share',
			'module-insights',
		) as $name ) {
			register_block_type(
				'tng/' . $name,
				array( 'render_callback' => $render( $name ) )
			);
		}
	}
);
