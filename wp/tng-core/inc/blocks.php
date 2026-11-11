<?php
/**
 * Block registration.
 *
 * Two families:
 *  - acf/* page blocks: carry their own SCF field group (location: block),
 *    used on the Home / Professionals / Contact / Careers / About / Insights /
 *    Awards / career path / About child (Pro Bono, CSR, museum) pages.
 *    The client edits fields per block instance.
 *  - tng/* template blocks: no block fields; they render from the current
 *    post's / term's SCF fields (professional, insight and job singles;
 *    location, industry and practice pages) or from the options pages
 *    (header / footer / job settings). They live in the plugin-registered
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
		'about-hero'           => 'TNG: About hero',
		'page-tabs'            => 'TNG: On this page tabs',
		'overview'             => 'TNG: Overview (label + heading + text)',
		'about-values'         => 'TNG: About values (accordion)',
		'about-numbers'        => 'TNG: Our numbers',
		'about-vision'         => 'TNG: Vision and mission',
		'about-creed'          => 'TNG: Creed',
		'more-from'            => 'TNG: More from',
		'archive-hero'         => 'TNG: Archive hero',
		'archive-featured'     => 'TNG: Featured post',
		'archive-filter'       => 'TNG: Archive filter',
		'archive-grid'         => 'TNG: Archive grid',
		'path-hero'            => 'TNG: Career path hero',
		'team-stories'         => 'TNG: Hear it from our team',
		'path-jobs'            => 'TNG: Opportunities for (jobs)',
		'info-hero'            => 'TNG: About child page hero (contact)',
		'mus-audience'         => 'TNG: Museum audiences (2 x 2)',
		'mus-collection'       => 'TNG: Museum collection',
		'mus-cases'            => 'TNG: Museum case studies (slider)',
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
			'loc-hero',
			'loc-office',
			'svc-hero',
			'term-tabs',
			'term-overview',
			'term-subs',
			'term-successes',
			'term-experience',
			'term-professionals',
			'term-insights',
			'term-awards',
			'job-hero',
			'job-intro',
			'job-summary',
			'job-cta',
		) as $name ) {
			register_block_type(
				'tng/' . $name,
				array( 'render_callback' => $render( $name ) )
			);
		}
	}
);
