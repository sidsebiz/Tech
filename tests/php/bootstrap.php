<?php
/**
 * PHPUnit bootstrap: WordPress test library + Techvenza Core.
 *
 * Works in CI (MySQL service, vendor/wp-phpunit) and inside `wp-env run tests-cli`.
 *
 * @package Techvenza\Tests
 */

$techvenza_root = dirname( __DIR__, 2 );
require_once $techvenza_root . '/vendor/autoload.php';

$techvenza_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $techvenza_tests_dir || ! file_exists( $techvenza_tests_dir . '/includes/functions.php' ) ) {
	$techvenza_tests_dir = $techvenza_root . '/vendor/wp-phpunit/wp-phpunit';
}
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $techvenza_root . '/vendor/yoast/phpunit-polyfills' );
}

require_once $techvenza_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $techvenza_root ): void {
		require $techvenza_root . '/wp-content/plugins/techvenza-core/techvenza-core.php';
	}
);
tests_add_filter(
	'init',
	static function (): void {
		\Techvenza\Core\Database\Schema::install();
		\Techvenza\Core\Workflow\Roles::install();
	},
	1
);

require $techvenza_tests_dir . '/includes/bootstrap.php';
