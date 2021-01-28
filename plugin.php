<?php
/**
 * Plugin Name: WP Media Pro
 * Plugin URI: https://wordpress.org/plugins/wp-media-pro
 * Description: The must have media toolkit for WordPress. Organize media and images into folders, media tags, image credits, and much more.
 * Version:     1.1.2
 * Author:      Taylor Lovett
 * Author URI:  https://taylorlovett.com
 * Text Domain: wpmp
 * Domain Path: /languages
 *
 * @package wpmp
 */

namespace WPMP;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Useful global constants.
define( 'WPMP_VERSION', '1.1.2' );
define( 'WPMP_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPMP_INC', WPMP_PATH . 'inc/' );

// Require Composer autoloader if it exists.
if ( file_exists( WPMP_PATH . '/vendor/autoload.php' ) ) {
	require_once WPMP_PATH . 'vendor/autoload.php';
}

require_once WPMP_INC . 'utils.php';
require_once WPMP_INC . 'core.php';
require_once WPMP_INC . 'dashboard.php';

/**
 * PSR-4-ish autoloading
 *
 * @since 1.0
 */
spl_autoload_register(
	function( $class ) {
			// project-specific namespace prefix.
			$prefix = 'WPMP\\';

			// base directory for the namespace prefix.
			$base_dir = __DIR__ . '/inc/classes/';

			// does the class use the namespace prefix?
			$len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

			$relative_class = substr( $class, $len );

			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

Modules\Taxonomies\Taxonomies::instance();
Modules\Folders\Folders::instance();
Modules\Credits\Credits::instance();
Modules\SingleView\SingleView::instance();
Modules\Edit\Edit::instance();

Core\setup();
Dashboard\setup();
