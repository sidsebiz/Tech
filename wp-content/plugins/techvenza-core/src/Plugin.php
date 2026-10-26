<?php
/**
 * Plugin bootstrap and module registry.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core;

/**
 * Wires every module's hooks. Each module exposes a static register() method.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Modules in load order. Missing classes are skipped so phases can land incrementally.
	 *
	 * @var array<int,string>
	 */
	private const MODULES = array(
		'Techvenza\\Core\\Settings\\Options',
		'Techvenza\\Core\\Database\\Schema',
		'Techvenza\\Core\\Content\\PostMeta',
		'Techvenza\\Core\\Content\\CategoryMeta',
		'Techvenza\\Core\\Content\\Series',
		'Techvenza\\Core\\Content\\UserMeta',
		'Techvenza\\Core\\Content\\PostTypes',
		'Techvenza\\Core\\Content\\ReadingTime',
		'Techvenza\\Core\\Workflow\\Roles',
		'Techvenza\\Core\\Workflow\\Statuses',
		'Techvenza\\Core\\Workflow\\Capabilities',
		'Techvenza\\Core\\Workflow\\Workflow',
		'Techvenza\\Core\\Notifications\\Notifier',
		'Techvenza\\Core\\Email\\Mailer',
		'Techvenza\\Core\\Blocks\\Registry',
		'Techvenza\\Core\\Frontend\\Assets',
		'Techvenza\\Core\\Frontend\\GlobalUi',
		'Techvenza\\Core\\Frontend\\Menus',
		'Techvenza\\Core\\Frontend\\Views',
		'Techvenza\\Core\\Frontend\\Consent',
		'Techvenza\\Core\\Rest\\Routes',
		'Techvenza\\Core\\Auth\\Auth',
		'Techvenza\\Core\\Auth\\AdminLockdown',
		'Techvenza\\Core\\Auth\\GoogleOAuth',
		'Techvenza\\Core\\Newsletter\\Newsletter',
		'Techvenza\\Core\\Forms\\Forms',
		'Techvenza\\Core\\Seo\\Seo',
		'Techvenza\\Core\\Cron\\Jobs',
		'Techvenza\\Core\\Admin\\Admin',
		'Techvenza\\Core\\Admin\\Settings',
		'Techvenza\\Core\\Cli\\Command',
	);

	/**
	 * Hook callback for plugins_loaded (actions must not return a value).
	 */
	public static function boot_plugin(): void {
		self::instance();
	}

	/**
	 * Get (and boot) the plugin.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Register all module hooks.
	 */
	private function boot(): void {
		load_plugin_textdomain( 'techvenza-core', false, dirname( plugin_basename( TECHVENZA_CORE_FILE ) ) . '/languages' );

		foreach ( self::MODULES as $module ) {
			if ( class_exists( $module ) && method_exists( $module, 'register' ) ) {
				$module::register();
			}
		}

		/**
		 * Fires after all Techvenza Core modules registered their hooks.
		 */
		do_action( 'techvenza/loaded' );
	}

	/**
	 * Activation: tables, roles, rewrite rules, cron.
	 */
	public static function activate(): void {
		Database\Schema::install();
		if ( class_exists( Workflow\Roles::class ) ) {
			Workflow\Roles::install();
		}
		if ( class_exists( Cron\Jobs::class ) ) {
			Cron\Jobs::schedule();
		}
		update_option( 'techvenza_flush_rewrites', 1 );
	}

	/**
	 * Deactivation: clear scheduled events (data and roles are kept).
	 */
	public static function deactivate(): void {
		if ( class_exists( Cron\Jobs::class ) ) {
			Cron\Jobs::unschedule();
		}
		flush_rewrite_rules();
	}
}
