<?php
/**
 * WP-CLI commands: `wp techvenza seed|reset|migrate|aggregate|illustrations|import-csv`.
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

	/**
	 * Import the bundled illustration library into the Media Library.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Re-import illustrations that already exist.
	 *
	 * @param array<int,string>    $args  Positional.
	 * @param array<string,string> $assoc Flags.
	 */
	public function illustrations( array $args, array $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI signature.
		$n = \Techvenza\Core\Media\Images::install_library( ! empty( $assoc['force'] ) );
		\WP_CLI::success( $n . ' illustrations imported.' );
	}

	/**
	 * Import articles from a Bulk Blog CSV (same format as Editorial → Bulk Blog).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--images=<zip>]
	 * : ZIP of images referenced by file name.
	 *
	 * [--status=<status>]
	 * : Default status for rows without one (draft, pending, publish). Default: draft.
	 *
	 * [--duplicates=<mode>]
	 * : skip, update or new when the slug already exists. Default: skip.
	 *
	 * [--no-create-terms]
	 * : Do not create missing categories.
	 *
	 * [--inline-images]
	 * : Copy images used inside the content into the Media Library.
	 *
	 * [--dry-run]
	 * : Validate only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp techvenza import-csv articles.csv --status=publish --user=admin
	 *
	 * @subcommand import-csv
	 * @param array<int,string>    $args  Positional.
	 * @param array<string,string> $assoc Flags.
	 */
	public function import_csv( array $args, array $assoc ): void {
		$file = (string) ( $args[0] ?? '' );
		if ( ! is_readable( $file ) ) {
			\WP_CLI::error( 'CSV file not found: ' . $file );
		}
		$job = \Techvenza\Core\Import\Importer::create(
			$file,
			(string) ( $assoc['images'] ?? '' ),
			basename( $file ),
			array(
				'status'        => (string) ( $assoc['status'] ?? 'draft' ),
				'duplicates'    => (string) ( $assoc['duplicates'] ?? 'skip' ),
				'create_terms'  => \WP_CLI\Utils\get_flag_value( $assoc, 'create-terms', true ),
				'inline_images' => ! empty( $assoc['inline-images'] ),
			)
		);
		if ( is_wp_error( $job ) ) {
			\WP_CLI::error( $job->get_error_message() );
		}
		foreach ( \Techvenza\Core\Import\Importer::rows( (string) $job['id'] ) as $row ) {
			foreach ( (array) $row['errors'] as $e ) {
				\WP_CLI::warning( 'Line ' . $row['line'] . ': ' . $e );
			}
			foreach ( (array) $row['warnings'] as $w ) {
				\WP_CLI::log( 'Line ' . $row['line'] . ': ' . $w );
			}
		}
		\WP_CLI::log( sprintf( '%d rows, %d ready.', $job['total'], $job['valid'] ) );
		if ( ! empty( $assoc['dry-run'] ) ) {
			\Techvenza\Core\Import\Importer::delete( (string) $job['id'] );
			\WP_CLI::success( 'Dry run complete — nothing was imported.' );
			return;
		}
		do {
			$job = \Techvenza\Core\Import\Importer::run( (string) $job['id'], 60 );
			if ( is_wp_error( $job ) ) {
				\WP_CLI::error( $job->get_error_message() );
			}
			\WP_CLI::log( sprintf( '%d/%d', $job['cursor'], $job['total'] ) );
		} while ( 'done' !== $job['status'] );
		$c = $job['counts'];
		\WP_CLI::success( sprintf( '%d created, %d updated, %d skipped, %d failed.', $c['created'], $c['updated'], $c['skipped'], $c['failed'] ) );
	}
}
