<?php
/**
 * Newsletter campaigns: HTML rendering, test sends, scheduled sending through the ESP (whole list)
 * or WordPress mail in background batches (topic audiences, or no ESP), with per-recipient
 * unsubscribe links and RFC 8058 List-Unsubscribe headers.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Newsletter;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Settings\Options;

/**
 * Campaign sending.
 */
final class Campaigns {

	public const BATCH_HOOK = 'techvenza_campaign_batch';
	public const BATCH_SIZE = 40;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( self::BATCH_HOOK, array( self::class, 'run_batch' ), 10, 2 );
		add_action( 'techvenza_campaign_start', array( self::class, 'start_scheduled' ) );
	}

	/**
	 * Cron: start a scheduled campaign (errors are kept on the campaign for the admin list).
	 *
	 * @param int $id Campaign.
	 */
	public static function start_scheduled( int $id ): void {
		update_post_meta( $id, 'tv_status', 'draft' );
		$result = self::start( $id );
		if ( is_wp_error( $result ) ) {
			update_post_meta( $id, 'tv_stats', array( 'error' => $result->get_error_message() ) );
		}
	}

	/**
	 * Campaign fields.
	 *
	 * @param int $id Campaign.
	 * @return array<string,mixed>
	 */
	public static function get( int $id ): array {
		$m = static fn( string $k ) => get_post_meta( $id, $k, true );
		return array(
			'id'        => $id,
			'name'      => get_the_title( $id ),
			'subject'   => (string) $m( 'tv_subject' ),
			'preheader' => (string) $m( 'tv_preheader' ),
			'intro'     => (string) $m( 'tv_intro' ),
			'posts'     => array_values( array_filter( array_map( 'intval', (array) $m( 'tv_posts' ) ) ) ),
			'audience'  => (string) ( $m( 'tv_audience' ) ? $m( 'tv_audience' ) : 'all' ),
			'status'    => (string) ( $m( 'tv_status' ) ? $m( 'tv_status' ) : 'draft' ),
			'send_at'   => (string) $m( 'tv_send_at' ),
			'sent_at'   => (string) $m( 'tv_sent_at' ),
			'stats'     => (array) $m( 'tv_stats' ),
		);
	}

	/**
	 * Audience options.
	 *
	 * @return array<string,string>
	 */
	public static function audiences(): array {
		return array( 'all' => __( 'All subscribers', 'techvenza-core' ) ) + array_map(
			/* translators: %s: topic */
			static fn( $name ) => sprintf( __( '%s topic', 'techvenza-core' ), $name ),
			Subscribers::topics()
		);
	}

	/**
	 * Email HTML.
	 *
	 * @param array<string,mixed> $c     Campaign.
	 * @param string              $unsub Unsubscribe URL (or the {unsubscribe_url} placeholder for ESPs).
	 */
	public static function html( array $c, string $unsub ): string {
		$items = '';
		foreach ( (array) $c['posts'] as $pid ) {
			$post = get_post( (int) $pid );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$cat    = CategoryMeta::for_post( $post->ID );
			$items .= sprintf(
				'<tr><td style="padding:18px 0;border-top:1px solid #E2E8F0">%s<h2 style="font-size:18px;line-height:1.35;margin:8px 0 6px"><a href="%s" style="color:#111827;text-decoration:none">%s</a></h2><p style="margin:0;font-size:14px;line-height:1.6;color:#475569">%s</p></td></tr>',
				$cat ? '<span style="display:inline-block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:' . esc_attr( (string) ( $cat['ink'] ?? $cat['color'] ) ) . '">' . esc_html( (string) $cat['short'] ) . '</span>' : '',
				esc_url(
					add_query_arg(
						array(
							'utm_source'   => 'newsletter',
							'utm_medium'   => 'email',
							'utm_campaign' => sanitize_title( (string) $c['name'] ),
						),
						(string) get_permalink( $post )
					)
				),
				esc_html( get_the_title( $post ) ),
				esc_html( wp_trim_words( get_the_excerpt( $post ), 32 ) )
			);
		}
		$site = get_bloginfo( 'name' );
		return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( (string) $c['subject'] ) . '</title></head>'
			. '<body style="margin:0;background:#F1F5F9;font-family:Inter,Segoe UI,Arial,sans-serif;color:#334155">'
			. '<div style="display:none;max-height:0;overflow:hidden">' . esc_html( (string) $c['preheader'] ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px">'
			. '<tr><td style="padding:20px 28px;border-bottom:1px solid #E2E8F0"><span style="display:inline-block;width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,#2563EB,#7C3AED 55%,#F97362);vertical-align:middle"></span> <strong style="font-size:18px;color:#111827;vertical-align:middle">' . esc_html( $site ) . '</strong></td></tr>'
			. '<tr><td style="padding:28px"><p style="margin:0;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#2563EB">' . esc_html( Newsletter::name() ) . '</p>'
			. '<h1 style="font-family:Georgia,serif;font-size:26px;line-height:1.25;color:#111827;margin:10px 0 14px">' . esc_html( (string) $c['subject'] ) . '</h1>'
			. '<p style="font-size:15px;line-height:1.65;margin:0 0 8px">' . nl2br( esc_html( (string) $c['intro'] ) ) . '</p>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $items . '</table></td></tr>'
			. '<tr><td style="padding:16px 28px;border-top:1px solid #E2E8F0;font-size:12px;line-height:1.6;color:#64748B">'
			/* translators: %s: site name */
			. esc_html( sprintf( __( 'You are receiving this because you subscribed to %s.', 'techvenza-core' ), $site ) )
			. ' <a href="' . ( '{unsubscribe_url}' === $unsub ? '{unsubscribe_url}' : esc_url( $unsub ) ) . '" style="color:#334155">' . esc_html__( 'Unsubscribe', 'techvenza-core' ) . '</a>'
			. ' · <a href="' . esc_url( Newsletter::url( 'manage' ) ) . '" style="color:#334155">' . esc_html__( 'Manage preferences', 'techvenza-core' ) . '</a>'
			. ' · <a href="' . esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy-policy/' ) ) . '" style="color:#334155">' . esc_html__( 'Privacy policy', 'techvenza-core' ) . '</a></td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * Send one email to one subscriber.
	 *
	 * @param array<string,mixed> $c   Campaign.
	 * @param string              $to  Email.
	 * @param string              $tok Unsubscribe token ('' for tests).
	 */
	public static function deliver( array $c, string $to, string $tok ): bool {
		$unsub   = $tok ? Newsletter::url( 'unsubscribe', $tok ) : Newsletter::url( 'manage' );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . Newsletter::name() . ' <' . ( Options::get( 'newsletter_from_email' ) ? Options::get( 'newsletter_from_email' ) : get_option( 'admin_email' ) ) . '>',
		);
		if ( $tok ) {
			$headers[] = 'List-Unsubscribe: <' . rest_url( Routes::NS . '/newsletter/unsubscribe/' . $tok ) . '>, <' . $unsub . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		return (bool) wp_mail( $to, wp_specialchars_decode( (string) $c['subject'] ), self::html( $c, $unsub ), $headers );
	}

	/**
	 * Send a test to one address.
	 *
	 * @param int    $id Campaign.
	 * @param string $to Email.
	 */
	public static function send_test( int $id, string $to ): bool {
		$c            = self::get( $id );
		$c['subject'] = '[TEST] ' . $c['subject'];
		return self::deliver( $c, $to, '' );
	}

	/**
	 * Start sending now (or at send_at, via schedule()).
	 *
	 * @param int $id Campaign.
	 * @return string|\WP_Error Human summary.
	 */
	public static function start( int $id ) {
		$c = self::get( $id );
		if ( 'sent' === $c['status'] || 'sending' === $c['status'] ) {
			return new \WP_Error( 'tv_sent', __( 'This campaign has already been sent.', 'techvenza-core' ) );
		}
		if ( ! $c['subject'] || ! $c['posts'] ) {
			return new \WP_Error( 'tv_invalid', __( 'Add a subject line and at least one story before sending.', 'techvenza-core' ) );
		}
		if ( 'all' === $c['audience'] && 'local' !== Esp::provider() ) {
			$esp = Esp::send_campaign( (string) $c['name'], (string) $c['subject'], (string) $c['preheader'], self::html( $c, '{unsubscribe_url}' ) );
			if ( is_wp_error( $esp ) ) {
				return $esp;
			}
			update_post_meta( $id, 'tv_esp_id', $esp );
			update_post_meta( $id, 'tv_status', 'sent' );
			update_post_meta( $id, 'tv_sent_at', current_time( 'mysql' ) );
			update_post_meta( $id, 'tv_stats', array( 'via' => Esp::provider() ) );
			/* translators: %s: provider */
			return sprintf( __( 'Sent through %s.', 'techvenza-core' ), ucfirst( Esp::provider() ) );
		}
		$count = count( Subscribers::recipients( (string) $c['audience'] ) );
		if ( ! $count ) {
			return new \WP_Error( 'tv_empty', __( 'No confirmed subscribers match this audience.', 'techvenza-core' ) );
		}
		update_post_meta( $id, 'tv_status', 'sending' );
		update_post_meta(
			$id,
			'tv_stats',
			array(
				'via'        => 'wordpress',
				'recipients' => $count,
				'sent'       => 0,
				'failed'     => 0,
			)
		);
		wp_schedule_single_event( time(), self::BATCH_HOOK, array( $id, 0 ) );
		/* translators: %d: number of recipients */
		return sprintf( _n( 'Sending to %d subscriber in the background.', 'Sending to %d subscribers in the background.', $count, 'techvenza-core' ), $count );
	}

	/**
	 * Schedule for later.
	 *
	 * @param int    $id   Campaign.
	 * @param string $when Local datetime (Y-m-d H:i).
	 * @return true|\WP_Error
	 */
	public static function schedule( int $id, string $when ) {
		$ts = strtotime( get_gmt_from_date( $when . ':00' ) . ' UTC' );
		if ( ! $ts || $ts < time() + 60 ) {
			return new \WP_Error( 'tv_date', __( 'Choose a time in the future.', 'techvenza-core' ) );
		}
		wp_clear_scheduled_hook( 'techvenza_campaign_start', array( $id ) );
		wp_schedule_single_event( $ts, 'techvenza_campaign_start', array( $id ) );
		update_post_meta( $id, 'tv_status', 'scheduled' );
		update_post_meta( $id, 'tv_send_at', $when . ':00' );
		return true;
	}

	/**
	 * Background batch.
	 *
	 * @param int $id     Campaign.
	 * @param int $offset Offset.
	 */
	public static function run_batch( int $id, int $offset ): void {
		$c     = self::get( $id );
		$all   = Subscribers::recipients( (string) $c['audience'] );
		$batch = array_slice( $all, $offset, self::BATCH_SIZE );
		$stats = (array) $c['stats'] + array(
			'sent'   => 0,
			'failed' => 0,
		);
		foreach ( $batch as $sub ) {
			if ( self::deliver( $c, (string) $sub['email'], (string) $sub['unsubscribe_token'] ) ) {
				++$stats['sent'];
			} else {
				++$stats['failed'];
			}
		}
		update_post_meta( $id, 'tv_stats', $stats );
		if ( $offset + self::BATCH_SIZE < count( $all ) ) {
			wp_schedule_single_event( time() + 30, self::BATCH_HOOK, array( $id, $offset + self::BATCH_SIZE ) );
			return;
		}
		update_post_meta( $id, 'tv_status', 'sent' );
		update_post_meta( $id, 'tv_sent_at', current_time( 'mysql' ) );
	}

	/**
	 * Save from the compose form.
	 *
	 * @param int                 $id   Campaign (0 = new).
	 * @param array<string,mixed> $data Fields.
	 * @return int|\WP_Error
	 */
	public static function save( int $id, array $data ) {
		$name    = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$subject = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
		if ( '' === $name || '' === $subject || mb_strlen( $subject ) > 70 ) {
			return new \WP_Error( 'tv_invalid', __( 'Add a campaign name and a subject line under 70 characters.', 'techvenza-core' ) );
		}
		$posts = array_values( array_filter( array_map( 'absint', (array) ( $data['posts'] ?? array() ) ) ) );
		if ( ! $posts ) {
			return new \WP_Error( 'tv_invalid', __( 'Choose at least one story.', 'techvenza-core' ) );
		}
		$id = wp_insert_post(
			array(
				'ID'          => $id,
				'post_type'   => PostTypes::CAMPAIGN,
				'post_status' => 'private',
				'post_title'  => $name,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$aud = sanitize_key( (string) ( $data['audience'] ?? 'all' ) );
		update_post_meta( $id, 'tv_subject', $subject );
		update_post_meta( $id, 'tv_preheader', mb_substr( sanitize_text_field( (string) ( $data['preheader'] ?? '' ) ), 0, 110 ) );
		update_post_meta( $id, 'tv_intro', sanitize_textarea_field( (string) ( $data['intro'] ?? '' ) ) );
		update_post_meta( $id, 'tv_posts', $posts );
		update_post_meta( $id, 'tv_audience', isset( self::audiences()[ $aud ] ) ? $aud : 'all' );
		if ( ! get_post_meta( $id, 'tv_status', true ) ) {
			update_post_meta( $id, 'tv_status', 'draft' );
		}
		return (int) $id;
	}
}
