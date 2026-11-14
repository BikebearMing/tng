<?php
/**
 * SCF options pages for the site-wide chrome. The pages are registered in
 * code (stable); their FIELDS come from the imported field groups
 * (group_tng_site_header / group_tng_site_footer) and their values are
 * editable under "Site Chrome" in wp-admin. Capability edit_posts (SCF's
 * default for the sub pages too), so the Editor role reaches them.
 * Also: wp-admin screen tweaks for editors (bottom of the file).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		acf_add_options_page(
			array(
				'page_title' => 'Site Chrome',
				'menu_title' => 'Site Chrome',
				'menu_slug'  => 'tng-site-chrome',
				'capability' => 'edit_posts',
				'icon_url'   => 'dashicons-layout',
				'redirect'   => true,
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Header',
				'menu_title'  => 'Header',
				'menu_slug'   => 'tng-site-header',
				'parent_slug' => 'tng-site-chrome',
			)
		);
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Footer',
				'menu_title'  => 'Footer',
				'menu_slug'   => 'tng-site-footer',
				'parent_slug' => 'tng-site-chrome',
			)
		);
		// Apply card + call-to-action texts shared by every job page
		acf_add_options_sub_page(
			array(
				'page_title'  => 'Job page settings',
				'menu_title'  => 'Job page settings',
				'menu_slug'   => 'tng-job-settings',
				'parent_slug' => 'edit.php?post_type=job',
			)
		);
	}
);

// Insights, awards and jobs: the classic edit screen, so their field boxes
// (authors, PDF, language, job details) sit in plain view instead of the block
// editor's collapsed "Meta Boxes" drawer. Their body is one Classic block anyway.
add_filter(
	'use_block_editor_for_post_type',
	function ( $use, $post_type ) {
		return in_array( $post_type, array( 'insight', 'award', 'job' ), true ) ? false : $use;
	},
	10,
	2
);

// Core Posts and Comments are unused (content lives in the TNG post types).
add_action(
	'admin_menu',
	function () {
		remove_menu_page( 'edit.php' );
		remove_menu_page( 'edit-comments.php' );
	}
);
