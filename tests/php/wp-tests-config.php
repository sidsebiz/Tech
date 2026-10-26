<?php
/**
 * WordPress test configuration (values come from the environment).
 *
 * @package Techvenza\Tests
 */

$techvenza_env = static function ( array $keys, string $fallback ): string {
	foreach ( $keys as $key ) {
		$value = getenv( $key );
		if ( false !== $value && '' !== $value ) {
			return $value;
		}
	}
	return $fallback;
};

if ( ! defined( 'ABSPATH' ) ) {
	$techvenza_abspath = getenv( 'WP_TESTS_ABSPATH' );
	define( 'ABSPATH', $techvenza_abspath ? rtrim( $techvenza_abspath, '/' ) . '/' : dirname( __DIR__, 2 ) . '/vendor/johnpbloch/wordpress-core/' );
}

define( 'DB_NAME', $techvenza_env( array( 'WP_TESTS_DB_NAME' ), 'wordpress_tests' ) );
define( 'DB_USER', $techvenza_env( array( 'WP_TESTS_DB_USER', 'WORDPRESS_DB_USER' ), 'root' ) );
define( 'DB_PASSWORD', $techvenza_env( array( 'WP_TESTS_DB_PASSWORD', 'WORDPRESS_DB_PASSWORD' ), '' ) );
define( 'DB_HOST', $techvenza_env( array( 'WP_TESTS_DB_HOST', 'WORDPRESS_DB_HOST' ), 'localhost' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Techvenza Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
