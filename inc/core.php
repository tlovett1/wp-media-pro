<?php
/**
 * Core functionality
 *
 * @package wpmp
 */

namespace WPMP\Core;

/**
 * Setup hooks
 */
function setup() {
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\setup_lang' );
}

/**
 * Enqueue common scripts/styles
 */
function enqueue_scripts() {
	wp_enqueue_style( 'wpmp-admin', WPMP_URL . '/dist/css/admin-styles.css', [], WPMP_VERSION );
}

/**
 * Setup i10n
 *
 * @return void
 */
function setup_lang() {
	load_plugin_textdomain( 'wpmp', false, WPMP_PATH . 'lang' );
}
