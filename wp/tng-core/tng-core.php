<?php
/**
 * Plugin Name: TNG Core
 * Description: Tilleke & Gibbins site core: section blocks, templates, assets, roster endpoint and content seeder. Pairs with the SCF field groups (wp/scf-import.json in the repo).
 * Version: 1.0.7
 * Author: TNG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TNG_CORE_VERSION', '1.0.7' );
define( 'TNG_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TNG_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once TNG_CORE_DIR . 'inc/helpers.php';
require_once TNG_CORE_DIR . 'inc/enqueue.php';
require_once TNG_CORE_DIR . 'inc/blocks.php';
require_once TNG_CORE_DIR . 'inc/templates.php';
require_once TNG_CORE_DIR . 'inc/rest.php';
require_once TNG_CORE_DIR . 'inc/options.php';
require_once TNG_CORE_DIR . 'inc/seed.php';

// The frontend header is fixed at the top; the admin bar would sit on top of
// it and shift every sticky offset by 32px. Hide it on the front end.
add_filter( 'show_admin_bar', '__return_false' );
