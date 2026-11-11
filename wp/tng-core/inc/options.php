<?php
/**
 * SCF options pages for the site-wide chrome. The pages are registered in
 * code (stable); their FIELDS come from the imported field groups
 * (group_tng_site_header / group_tng_site_footer) and their values are
 * editable under "Site Chrome" in wp-admin.
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
