<?php
/**
 * WP-CLI commands: `wp techvenza seed|reset|migrate|aggregate`.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Cli;

use Techvenza\Core\Database\Schema;
use Techvenza\Core\Database\Views;
use Techvenza\Core\Workflow\Roles;

/**
 * Manage Techvenza demo content and maintenance tasks.
 */
final class Command {

	/**
	 * Register the command when WP-CLI is running.
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'techvenza', self::class );
		}
	}

	/**
	 * Import all prototype sample content (categories, authors, articles, workflow items, pages, menus…).
	 *
	 * Safe to re-run: existing seeded items are updated in place. Demo account passwords are
	 * randomly generated and printed once — store them now; they are not saved anywhere else.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-images]
	 * : Do not download cover images from Unsplash (cards fall back to gradient placeholders).
	 *
	 * [--demo-password=<password>]
	 * : Use one fixed password for every demo account (automated tests only — never on a public site).
	 *
	 * ## EXAMPLES
	 *
	 *     wp techvenza seed
	 *     wp techvenza seed --skip-images
	 *
	 * @param array<int,string>    $args  Positional.
	 * @param array<string,string> $assoc Flags.
	 */
	public function seed( array $args, array $assoc ): void {
		$seeder = new Seeder( static fn( string $m ) => \WP_CLI::log( $m ) );
		$seeder->run(
			array(
				'skip-images'   => (bool) \WP_CLI\Utils\get_flag_value( $assoc, 'skip-images', false ),
				'demo-password' => (string) \WP_CLI\Utils\get_flag_value( $assoc, 'demo-password', '' ),
			)
		);
		if ( $seeder->credentials ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Demo accounts (passwords shown once — store them securely):' );
			\WP_CLI\Utils\format_items( 'table', $seeder->credentials, array( 'role', 'login', 'email', 'password' ) );
		}
		\WP_CLI::success( 'Techvenza sample content imported.' );
	}

	/**
	 * Remove everything created by `seed` (posts, pages, media, terms, menus, users, subscribers).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param array<int,string>    $args  Positional.
	 * @param array<string,string> $assoc Flags.
	 */
	public function reset( array $args, array $assoc ): void {
		\WP_CLI::confirm( 'Delete all seeded Techvenza content?', $assoc );
		( new Seeder( static fn( string $m ) => \WP_CLI::log( $m ) ) )->reset();
		\WP_CLI::success( 'Seeded content removed.' );
	}

	/**
	 * Create or upgrade custom tables and roles.
	 *
	 * @param array<int,string>    $args  Positional.
	 * @param array<string,string> $assoc Flags.
	 */
	public function migrate( array $args, array $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI signature.
		Schema::install();
		Roles::install();
		\WP_CLI::success( 'Schema ' . Schema::VERSION . ' installed; roles refreshed.' );
	}

	/**
	 * Recompute trending/views aggregates now (normally hourly via cron).
	 *
	 * @param array<int,string>    $args  Positional.
	 * @param array<string,string> $assoc Flags.
	 */
	public function aggregate( array $args, array $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI signature.
		Views::aggregate();
		\WP_CLI::success( 'View aggregates updated.' );
	}
}
