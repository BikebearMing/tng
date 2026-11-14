<?php
/**
 * Block templates (Site Editor). Registered from the plugin so they are
 * version-controlled; the Site Editor can still override them per site.
 *
 *  - tng-blank:               pages (Home, Professionals, Contact, Careers) —
 *                             header + raw post content + footer, no theme layout.
 *  - single-professional:     professional single.
 *  - single-insight:          insight single, Standard (+ the download banner when it has a PDF).
 *  - single-insight-module:   insight single, Module (assigned per post).
 *  - taxonomy-location:       country page (city terms redirect to it).
 *  - taxonomy-industry /
 *    taxonomy-practice:       service pages (a child term = subpractice).
 *  - single-job:              job single (light header).
 *  - tng-text:                legal / text pages (assigned per page).
 *  - single-award:            award single.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tng_template_shell( $inner, $light_header = false ) {
	$header = $light_header ? '<!-- wp:tng/site-header {"light":true} /-->' : '<!-- wp:tng/site-header /-->';
	return $header
		. "\n" . '<!-- wp:group {"tagName":"main","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->'
		. "\n" . '<main class="wp-block-group">' . $inner . '</main>'
		. "\n" . '<!-- /wp:group -->'
		. "\n" . '<!-- wp:tng/site-footer /-->';
}

add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		register_block_template(
			'tng-core//tng-blank',
			array(
				'title'       => 'TNG Page',
				'description' => 'Header + page blocks + footer, no theme layout. Use for every TNG page.',
				'post_types'  => array( 'page' ),
				'content'     => tng_template_shell( '<!-- wp:post-content {"layout":{"type":"default"}} /-->' ),
			)
		);

		register_block_template(
			'tng-core//single-professional',
			array(
				'title'       => 'Professional',
				'description' => 'Professional single: hero, tabs, overview, experience, insights, awards.',
				'content'     => tng_template_shell(
					'<!-- wp:tng/pro-hero /-->'
					. '<!-- wp:tng/pro-tabs /-->'
					. '<!-- wp:tng/pro-overview /-->'
					. '<!-- wp:tng/pro-experience /-->'
					. '<!-- wp:tng/pro-insights /-->'
					. '<!-- wp:tng/pro-awards /-->',
					true // light header over the pale hero
				),
			)
		);

		register_block_template(
			'tng-core//single-insight',
			array(
				'title'       => 'Insight — Standard',
				'description' => 'Insight single: navy hero, reading bar, sidebar + article.',
				'content'     => tng_template_shell(
					'<!-- wp:tng/insight-hero /-->'
					. '<!-- wp:tng/insight-bar /-->'
					. '<!-- wp:tng/insight-article /-->'
					. '<!-- wp:tng/module-download /-->' // the article's PDF (module_report); hides without one
				),
			)
		);

		register_block_template(
			'tng-core//tng-text',
			array(
				'title'       => 'TNG Text page',
				'description' => 'Legal / text page: navy title hero, "Legal information" nav (every page on this template) and the page content.',
				'post_types'  => array( 'page' ),
				'content'     => tng_template_shell( '<!-- wp:tng/text-page /-->' ),
			)
		);

		// Everything without a design yet renders the under-development page.
		// 404/search use plugin-only slugs pushed to the top of the hierarchy,
		// because the theme ships its own 404/search templates and a theme
		// template always beats a plugin template with the same slug.
		register_block_template(
			'tng-core//tng-404',
			array(
				'title'       => 'TNG Not found',
				'description' => '404: the navy hero with a search field and links.',
				'content'     => tng_template_shell( '<!-- wp:tng/not-found /-->' ),
			)
		);
		register_block_template(
			'tng-core//tng-search',
			array(
				'title'       => 'TNG Search results',
				'description' => 'Site search: query field, type pills, grouped results (inc/search.php).',
				'content'     => tng_template_shell( '<!-- wp:tng/search-results /-->' ),
			)
		);

		$tng_ud = tng_template_shell( '<!-- wp:tng/under-development /-->' );
		foreach ( array(
			'taxonomy'     => 'Taxonomy term',
			'archive'      => 'Archive',
		) as $tng_slug => $tng_title ) {
			register_block_template(
				'tng-core//' . $tng_slug,
				array(
					'title'       => 'TNG ' . $tng_title,
					'description' => 'Under-development placeholder until this design exists.',
					'content'     => $tng_ud,
				)
			);
		}

		foreach ( array( '404', 'search' ) as $tng_type ) {
			add_filter(
				$tng_type . '_template_hierarchy',
				function ( $templates ) use ( $tng_type ) {
					array_unshift( $templates, 'tng-' . $tng_type );
					return $templates;
				}
			);
		}

		register_block_template(
			'tng-core//taxonomy-location',
			array(
				'title'       => 'Location (country)',
				'description' => 'Country page: hero + office, tabs, overview, professionals, insights, client successes, experience, awards, office strip.',
				'content'     => tng_template_shell(
					'<!-- wp:tng/loc-hero /-->'
					. '<!-- wp:tng/term-tabs /-->'
					. '<!-- wp:tng/term-overview /-->'
					. '<!-- wp:tng/term-professionals /-->'
					. '<!-- wp:tng/term-insights /-->'
					. '<!-- wp:tng/term-successes /-->'
					. '<!-- wp:tng/term-experience /-->'
					. '<!-- wp:tng/term-awards /-->'
					. '<!-- wp:tng/loc-office /-->'
				),
			)
		);

		$tng_service = tng_template_shell(
			'<!-- wp:tng/svc-hero /-->'
			. '<!-- wp:tng/term-tabs /-->'
			. '<!-- wp:tng/term-overview /-->'
			. '<!-- wp:tng/term-subs /-->'
			. '<!-- wp:tng/term-successes /-->'
			. '<!-- wp:tng/term-experience /-->'
			. '<!-- wp:tng/term-professionals /-->'
			. '<!-- wp:tng/term-insights /-->'
			. '<!-- wp:tng/term-awards /-->'
		);
		foreach ( array(
			'industry' => 'Industry',
			'practice' => 'Practice',
		) as $tng_tax => $tng_title ) {
			register_block_template(
				'tng-core//taxonomy-' . $tng_tax,
				array(
					'title'       => $tng_title . ' page',
					'description' => 'Service page: hero + contacts by country, tabs, overview, subpractices, client successes, experience, professionals, insights, awards. A child term shows hero, overview and experience only.',
					'content'     => $tng_service,
				)
			);
		}

		register_block_template(
			'tng-core//single-award',
			array(
				'title'       => 'Award',
				'description' => 'Award single: the no-image insight hero, reading bar, related professionals + article, more awards.',
				'content'     => tng_template_shell(
					'<!-- wp:tng/insight-hero /-->'
					. '<!-- wp:tng/insight-bar /-->'
					. '<!-- wp:tng/insight-article /-->'
					. '<!-- wp:tng/award-more /-->'
				),
			)
		);

				register_block_template(
			'tng-core//single-job',
			array(
				'title'       => 'Job',
				'description' => 'Job single: hero + apply card, summary, position summary, call to action.',
				'content'     => tng_template_shell(
					'<!-- wp:tng/job-hero /-->'
					. '<!-- wp:tng/job-intro /-->'
					. '<!-- wp:tng/job-summary /-->'
					. '<!-- wp:tng/job-cta /-->',
					true // navy header over the pale hero
				),
			)
		);

		register_block_template(
			'tng-core//single-insight-module',
			array(
				'title'       => 'Insight — Module',
				'description' => 'Insight single made of stackable module sections. Assign per insight.',
				'post_types'  => array( 'insight' ),
				'content'     => tng_template_shell(
					'<!-- wp:tng/module-hero /-->'
					. '<!-- wp:tng/module-panels /-->'
					. '<!-- wp:tng/module-findings /-->'
					. '<!-- wp:tng/module-professionals /-->'
					. '<!-- wp:tng/module-download /-->'
					. '<!-- wp:tng/module-share /-->'
					. '<!-- wp:tng/module-insights /-->'
				),
			)
		);
	}
);

// A page or post written in another language (_tng_lang: the desks, imported
// ja / zh insights) gets <main lang>, so fonts and screen readers switch and
// main.js leaves its CJK copy out of the SplitText reveals.
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		if ( empty( $block['attrs']['tagName'] ) || 'main' !== $block['attrs']['tagName'] || ! is_singular() ) {
			return $html;
		}
		$lang = (string) get_post_meta( get_queried_object_id(), '_tng_lang', true );
		if ( '' === $lang || 'en' === $lang ) {
			return $html;
		}
		return preg_replace( '/<main\b/', '<main lang="' . esc_attr( $lang ) . '"', $html, 1 );
	},
	10,
	2
);
