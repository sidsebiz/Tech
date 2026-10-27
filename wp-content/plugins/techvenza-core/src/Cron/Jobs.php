<?php
/**
 * Scheduled maintenance: hourly view aggregates (trending), daily retention clean-up
 * (unconfirmed newsletter sign-ups, old contact messages, stale rate-limit data).
 *
 * WP-Cron is triggered by traffic; on production set DISABLE_WP_CRON and run
 * `wp cron event run --due-now` from a system cron every 5 minutes (see docs/DEPLOY.md).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Cron;

use Techvenza\Core\Admin\Messages;
use Techvenza\Core\Database\Views;
use Techvenza\Core\Newsletter\Subscribers;

/**
 * Cron jobs.
 */
final class Jobs {

	public const HOURLY = 'techvenza_hourly';
	public const DAILY  = 'techvenza_daily';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( self::HOURLY, array( self::class, 'hourly' ) );
		add_action( self::DAILY, array( self::class, 'daily' ) );
		// Self-heal when the plugin was updated in place (activation hook not re-run).
		add_action( 'init', array( self::class, 'schedule' ) );
	}

	/**
	 * Schedule events if missing.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOURLY ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOURLY );
		}
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			$at = strtotime( 'tomorrow 03:15', time() );
			wp_schedule_event( $at ? $at : time() + DAY_IN_SECONDS, 'daily', self::DAILY );
		}
	}

	/**
	 * Remove events.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOURLY );
		wp_clear_scheduled_hook( self::DAILY );
	}

	/**
	 * Hourly.
	 */
	public static function hourly(): void {
		Views::aggregate();
	}

	/**
	 * Daily.
	 */
	public static function daily(): void {
		/**
		 * Days before an unconfirmed newsletter sign-up is deleted.
		 *
		 * @param int $days Days.
		 */
		Subscribers::purge_unconfirmed( (int) apply_filters( 'techvenza/newsletter/unconfirmed_days', 14 ) );
		Messages::purge();
		delete_expired_transients();
	}
}
