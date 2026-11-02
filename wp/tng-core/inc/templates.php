<?php
/**
 * Block templates (Site Editor). Registered from the plugin so they are
 * version-controlled; the Site Editor can still override them per site.
 *
 *  - tng-blank:               pages (Home, Professionals, Contact, Careers) —
 *                             header + raw post content + footer, no theme layout.
 *  - single-professional:     professional single.
 *  - single-insight:          insight single, Standard.
 *  - single-insight-module:   insight single, Module (assigned per post).
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
				),
			)
		);

		// Everything without a design yet renders the under-development page.
		// 404/search use plugin-only slugs pushed to the top of the hierarchy,
		// because the theme ships its own 404/search templates and a theme
		// template always beats a plugin template with the same slug.
		$tng_ud = tng_template_shell( '<!-- wp:tng/under-development /-->' );
		foreach ( array(
			'tng-404'      => 'Not found',
			'tng-search'   => 'Search results',
			'taxonomy'     => 'Taxonomy term',
			'archive'      => 'Archive',
			'single-award' => 'Award',
			'single-job'   => 'Job',
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
