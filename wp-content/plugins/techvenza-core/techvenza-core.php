<?php
/**
 * Plugin Name:       Techvenza Core
 * Plugin URI:        https://www.techvenza.example/
 * Description:       Content model, dynamic blocks, editorial workflow, author portal, newsletter, SEO and settings for the Techvenza publication. Theme-independent.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            eSparkBiz Technologies
 * Author URI:        https://www.esparkinfo.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       techvenza-core
 * Domain Path:       /languages
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TECHVENZA_CORE_VERSION', '1.0.0' );
define( 'TECHVENZA_CORE_FILE', __FILE__ );
define( 'TECHVENZA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TECHVENZA_CORE_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4 autoloader for Techvenza\Core\* → src/*. No Composer runtime dependency,
 * so the plugin can be uploaded as a ZIP to any host.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Techvenza\\Core\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = TECHVENZA_CORE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( \Techvenza\Core\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Techvenza\Core\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Techvenza\Core\Plugin::class, 'boot_plugin' ) );
