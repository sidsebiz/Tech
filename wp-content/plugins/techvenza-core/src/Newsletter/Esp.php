<?php
/**
 * Email service provider adapters (Brevo, Mailchimp). Double opt-in happens in WordPress, so
 * contacts are pushed to the provider only once confirmed; unsubscribes and erasures follow.
 * Syncs run in the background (single cron event) so forms stay fast.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Newsletter;

use Techvenza\Core\Settings\Options;

/**
 * ESP gateway.
 */
final class Esp {

	public const HOOK = 'techvenza_esp_sync';

	/**
	 * Active provider: local|brevo|mailchimp (falls back to local when keys are missing).
	 */
	public static function provider(): string {
		$p = (string) Options::get( 'esp_provider', 'local' );
		if ( 'brevo' === $p && Options::get( 'brevo_api_key' ) && (int) Options::get( 'brevo_list_id' ) ) {
			return 'brevo';
		}
		if ( 'mailchimp' === $p && Options::get( 'mailchimp_api_key' ) && Options::get( 'mailchimp_audience_id' ) ) {
			return 'mailchimp';
		}
		return 'local';
	}

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'run_sync' ), 10, 2 );
	}

	/**
	 * Queue a sync for a subscriber.
	 *
	 * @param int $id Subscriber.
	 */
	public static function sync( int $id ): void {
		if ( 'local' === self::provider() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK, array( 'sync', $id ) ) ) {
			wp_schedule_single_event( time(), self::HOOK, array( 'sync', $id ) );
		}
	}

	/**
	 * Queue removal of an address (subscriber row is already gone).
	 *
	 * @param string $email Email.
	 */
	public static function remove( string $email ): void {
		if ( 'local' === self::provider() ) {
			return;
		}
		wp_schedule_single_event( time(), self::HOOK, array( 'remove', $email ) );
	}

	/**
	 * Cron worker.
	 *
	 * @param string     $op  sync|remove.
	 * @param int|string $arg Subscriber id or email.
	 */
	public static function run_sync( string $op, $arg ): void {
		$provider = self::provider();
		if ( 'remove' === $op ) {
			'brevo' === $provider ? self::brevo_remove( (string) $arg ) : self::mailchimp_remove( (string) $arg );
			return;
		}
		$sub = Subscribers::get( (int) $arg );
		if ( ! $sub || 'pending' === $sub['status'] ) {
			return;
		}
		$result = 'brevo' === $provider ? self::brevo_upsert( $sub ) : self::mailchimp_upsert( $sub );
		if ( is_wp_error( $result ) ) {
			// Logged and shown on the Newsletter screen; saving the subscriber again re-queues the sync.
			update_option( 'techvenza_esp_last_error', array( current_time( 'mysql' ), $result->get_error_message() ), false );
		}
	}

	/* ------------------------------------------------------------------ HTTP */

	/**
	 * JSON request.
	 *
	 * @param string               $method  Method.
	 * @param string               $url     URL.
	 * @param array<string,string> $headers Headers.
	 * @param array<string,mixed>  $body    Body.
	 * @return array<string,mixed>|\WP_Error Decoded body.
	 */
	private static function request( string $method, string $url, array $headers, array $body = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => $headers + array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);
		if ( $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = (array) json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) {
			$msg = (string) ( $data['message'] ?? $data['detail'] ?? $data['title'] ?? wp_remote_retrieve_response_message( $res ) );
			return new \WP_Error( 'tv_esp', sprintf( 'HTTP %d: %s', $code, $msg ), array( 'status' => $code ) );
		}
		return $data;
	}

	/* ------------------------------------------------------------------ Brevo */

	/**
	 * Brevo headers.
	 *
	 * @return array<string,string>
	 */
	private static function brevo_headers(): array {
		return array( 'api-key' => (string) Options::get( 'brevo_api_key' ) );
	}

	/**
	 * Create/update a Brevo contact.
	 *
	 * @param array<string,mixed> $sub Subscriber.
	 * @return true|\WP_Error
	 */
	private static function brevo_upsert( array $sub ) {
		$res = self::request(
			'POST',
			'https://api.brevo.com/v3/contacts',
			self::brevo_headers(),
			array(
				'email'            => $sub['email'],
				'listIds'          => array( (int) Options::get( 'brevo_list_id' ) ),
				'updateEnabled'    => true,
				'emailBlacklisted' => 'unsubscribed' === $sub['status'],
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! empty( $res['id'] ) ) {
			Subscribers::set_esp_id( (int) $sub['id'], (string) $res['id'] );
		}
		return true;
	}

	/**
	 * Delete a Brevo contact.
	 *
	 * @param string $email Email.
	 */
	private static function brevo_remove( string $email ): void {
		self::request( 'DELETE', 'https://api.brevo.com/v3/contacts/' . rawurlencode( $email ), self::brevo_headers() );
	}

	/* ------------------------------------------------------------------ Mailchimp */

	/**
	 * Mailchimp API base from the key's data-centre suffix.
	 *
	 * @param string $key API key.
	 */
	private static function mc_base( string $key ): string {
		$dc = substr( (string) strrchr( $key, '-' ), 1 );
		return 'https://' . ( preg_match( '/^[a-z]+\d+$/', $dc ) ? $dc : 'us1' ) . '.api.mailchimp.com/3.0';
	}

	/**
	 * Mailchimp headers.
	 *
	 * @param string $key API key.
	 * @return array<string,string>
	 */
	private static function mc_headers( string $key ): array {
		return array( 'Authorization' => 'Basic ' . base64_encode( 'techvenza:' . $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
	}

	/**
	 * Create/update a Mailchimp member and tag their topics.
	 *
	 * @param array<string,mixed> $sub Subscriber.
	 * @return true|\WP_Error
	 */
	private static function mailchimp_upsert( array $sub ) {
		$key    = (string) Options::get( 'mailchimp_api_key' );
		$list   = rawurlencode( (string) Options::get( 'mailchimp_audience_id' ) );
		$hash   = md5( strtolower( (string) $sub['email'] ) );
		$status = 'unsubscribed' === $sub['status'] ? 'unsubscribed' : 'subscribed';
		$res    = self::request(
			'PUT',
			self::mc_base( $key ) . "/lists/{$list}/members/{$hash}",
			self::mc_headers( $key ),
			array(
				'email_address' => $sub['email'],
				'status_if_new' => $status,
				'status'        => $status,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		Subscribers::set_esp_id( (int) $sub['id'], (string) ( $res['id'] ?? $hash ) );
		$tags   = array_map(
			static fn( $t ) => array(
				'name'   => 'topic:' . $t,
				'status' => 'active',
			),
			(array) $sub['topics']
		);
		$tags[] = array(
			'name'   => 'freq:' . $sub['frequency'],
			'status' => 'active',
		);
		$tag    = self::request( 'POST', self::mc_base( $key ) . "/lists/{$list}/members/{$hash}/tags", self::mc_headers( $key ), array( 'tags' => $tags ) );
		return is_wp_error( $tag ) ? $tag : true;
	}

	/**
	 * Permanently delete a Mailchimp member.
	 *
	 * @param string $email Email.
	 */
	private static function mailchimp_remove( string $email ): void {
		$key  = (string) Options::get( 'mailchimp_api_key' );
		$list = rawurlencode( (string) Options::get( 'mailchimp_audience_id' ) );
		self::request( 'POST', self::mc_base( $key ) . "/lists/{$list}/members/" . md5( strtolower( $email ) ) . '/actions/delete-permanent', self::mc_headers( $key ) );
	}

	/* ------------------------------------------------------------------ Campaigns */

	/**
	 * Create and send a campaign to the whole list through the provider.
	 *
	 * @param string $name    Internal name.
	 * @param string $subject Subject.
	 * @param string $pre     Preheader.
	 * @param string $html    HTML (provider merge tag for unsubscribe already inserted).
	 * @return string|\WP_Error Provider campaign id.
	 */
	public static function send_campaign( string $name, string $subject, string $pre, string $html ) {
		$from_name  = (string) Options::get( 'newsletter_from_name', get_bloginfo( 'name' ) );
		$from_email = (string) Options::get( 'newsletter_from_email', get_option( 'admin_email' ) );
		$from_email = $from_email ? $from_email : (string) get_option( 'admin_email' );
		if ( 'brevo' === self::provider() ) {
			$res = self::request(
				'POST',
				'https://api.brevo.com/v3/emailCampaigns',
				self::brevo_headers(),
				array(
					'name'        => $name,
					'subject'     => $subject,
					'previewText' => $pre,
					'sender'      => array(
						'name'  => $from_name,
						'email' => $from_email,
					),
					'htmlContent' => str_replace( '{unsubscribe_url}', '{{ unsubscribe }}', $html ),
					'recipients'  => array( 'listIds' => array( (int) Options::get( 'brevo_list_id' ) ) ),
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$id   = (string) ( $res['id'] ?? '' );
			$send = self::request( 'POST', 'https://api.brevo.com/v3/emailCampaigns/' . rawurlencode( $id ) . '/sendNow', self::brevo_headers() );
			return is_wp_error( $send ) ? $send : $id;
		}
		if ( 'mailchimp' === self::provider() ) {
			$key = (string) Options::get( 'mailchimp_api_key' );
			$res = self::request(
				'POST',
				self::mc_base( $key ) . '/campaigns',
				self::mc_headers( $key ),
				array(
					'type'       => 'regular',
					'recipients' => array( 'list_id' => (string) Options::get( 'mailchimp_audience_id' ) ),
					'settings'   => array(
						'subject_line' => $subject,
						'preview_text' => $pre,
						'title'        => $name,
						'from_name'    => $from_name,
						'reply_to'     => $from_email,
					),
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$id      = (string) ( $res['id'] ?? '' );
			$content = self::request( 'PUT', self::mc_base( $key ) . '/campaigns/' . rawurlencode( $id ) . '/content', self::mc_headers( $key ), array( 'html' => str_replace( '{unsubscribe_url}', '*|UNSUB|*', $html ) ) );
			if ( is_wp_error( $content ) ) {
				return $content;
			}
			$send = self::request( 'POST', self::mc_base( $key ) . '/campaigns/' . rawurlencode( $id ) . '/actions/send', self::mc_headers( $key ) );
			return is_wp_error( $send ) ? $send : $id;
		}
		return new \WP_Error( 'tv_esp', __( 'No email service provider is configured.', 'techvenza-core' ) );
	}

	/**
	 * Connection test for Settings.
	 *
	 * @param string $provider brevo|mailchimp.
	 * @param string $key      API key.
	 * @return string|\WP_Error Account description on success.
	 */
	public static function test( string $provider, string $key ) {
		if ( 'brevo' === $provider ) {
			$res = self::request( 'GET', 'https://api.brevo.com/v3/account', array( 'api-key' => $key ) );
			return is_wp_error( $res ) ? $res : sprintf( /* translators: %s: account email */ __( 'Connected to Brevo as %s.', 'techvenza-core' ), (string) ( $res['email'] ?? '' ) );
		}
		$res = self::request( 'GET', self::mc_base( $key ) . '/ping', self::mc_headers( $key ) );
		return is_wp_error( $res ) ? $res : __( 'Connected to Mailchimp.', 'techvenza-core' );
	}
}
