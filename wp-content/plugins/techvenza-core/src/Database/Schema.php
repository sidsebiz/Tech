<?php
/**
 * Custom tables and versioned migrations.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Database;

/**
 * Creates / upgrades {prefix}tv_* tables with dbDelta.
 */
final class Schema {

	/**
	 * Bump when table definitions change; add a step to migrate() for data changes.
	 */
	public const VERSION = '1.0.0';

	private const OPTION = 'techvenza_db_version';

	/**
	 * Run pending migrations on load (cheap option check).
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 1 );
	}

	/**
	 * Install or upgrade when the stored version differs.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION ) !== self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Table name helper.
	 *
	 * @param string $name Short name, e.g. subscribers.
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'tv_' . $name;
	}

	/**
	 * Create / alter tables, then run data migrations.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sub     = self::table( 'subscribers' );
		$views   = self::table( 'post_views' );
		$history = self::table( 'reading_history' );
		$notes   = self::table( 'notifications' );

		dbDelta(
			"CREATE TABLE {$sub} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			topics text NULL,
			frequency varchar(20) NOT NULL DEFAULT 'weekly',
			status varchar(20) NOT NULL DEFAULT 'pending',
			consent_at datetime NULL,
			consent_ip_hash char(64) NOT NULL DEFAULT '',
			consent_text text NULL,
			confirmed_at datetime NULL,
			unsubscribed_at datetime NULL,
			confirm_token char(40) NOT NULL DEFAULT '',
			unsubscribe_token char(40) NOT NULL DEFAULT '',
			esp_id varchar(100) NOT NULL DEFAULT '',
			source varchar(50) NOT NULL DEFAULT '',
			is_seed tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY confirm_token (confirm_token),
			KEY unsubscribe_token (unsubscribe_token)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$views} (
			post_id bigint(20) unsigned NOT NULL,
			view_date date NOT NULL,
			views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (post_id,view_date),
			KEY view_date (view_date)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$history} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			viewed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_post (user_id,post_id),
			KEY user_viewed (user_id,viewed_at)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(40) NOT NULL DEFAULT 'status',
			title varchar(255) NOT NULL DEFAULT '',
			message text NULL,
			link varchar(255) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_read (user_id,is_read),
			KEY user_created (user_id,created_at)
			) {$charset};"
		);

		self::migrate( (string) get_option( self::OPTION, '0' ) );
		update_option( self::OPTION, self::VERSION, true );
	}

	/**
	 * Data migrations between versions.
	 *
	 * @param string $from Previously installed version.
	 */
	private static function migrate( string $from ): void {
		/**
		 * Fires after tables are up to date, before the version is stored.
		 *
		 * @param string $from Previous schema version ('0' on fresh installs).
		 * @param string $to   New schema version.
		 */
		do_action( 'techvenza/db/migrate', $from, self::VERSION );
	}

	/**
	 * Drop tables (uninstall only).
	 */
	public static function drop(): void {
		global $wpdb;
		foreach ( array( 'subscribers', 'post_views', 'reading_history', 'notifications' ) as $t ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $t ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from internal list.
		}
		delete_option( self::OPTION );
	}
}
