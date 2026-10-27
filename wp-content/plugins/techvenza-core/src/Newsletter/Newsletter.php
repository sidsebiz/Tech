<?php
/**
 * Newsletter: sign-up endpoints (REST + no-JS fallback), double opt-in confirmation,
 * scanner-safe unsubscribe (GET shows a button, POST unsubscribes; RFC 8058 one-click POST),
 * and the transactional emails around them.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Newsletter;

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

/**
 * Newsletter module.
 */
final class Newsletter {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		Esp::register();
		Campaigns::register();
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'admin_post_nopriv_techvenza_subscribe', array( self::class, 'handle_post' ) );
		add_action( 'admin_post_techvenza_subscribe', array( self::class, 'handle_post' ) );
		add_action( 'admin_post_nopriv_techvenza_unsubscribe', array( self::class, 'handle_unsubscribe' ) );
		add_action( 'admin_post_techvenza_unsubscribe', array( self::class, 'handle_unsubscribe' ) );
		add_action( 'template_redirect', array( self::class, 'confirm_link' ) );
	}

	/**
	 * Display name of the newsletter.
	 */
	public static function name(): string {
		return (string) Options::get( 'newsletter_from_name', __( 'The Weekly Brief', 'techvenza-core' ) );
	}

	/**
	 * Public URLs.
	 *
	 * @param string $kind  confirm|unsubscribe|manage.
	 * @param string $token Token.
	 */
	public static function url( string $kind, string $token = '' ): string {
		$base = R::page_url( 'newsletter' );
		return match ( $kind ) {
			'confirm'     => add_query_arg( 'tv_nl_confirm', $token, home_url( '/' ) ),
			'unsubscribe' => add_query_arg( 'tv_unsub', $token, $base ),
			default       => $base,
		};
	}

	/**
	 * REST routes.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/newsletter/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_subscribe' ),
				'permission_callback' => array( Auth::class, 'same_origin' ),
			)
		);
		// RFC 8058 one-click unsubscribe (mail clients POST "List-Unsubscribe=One-Click").
		register_rest_route(
			Routes::NS,
			'/newsletter/unsubscribe/(?P<token>[A-Za-z0-9]{40})',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_unsubscribe' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Validate and store a sign-up.
	 *
	 * @param array<string,mixed> $data Submitted data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function process( array $data ) {
		if ( ! Captcha::honeypot_ok( $data ) ) {
			return array( 'html' => self::success_html( 'you@example.com', array() ) );
		}
		if ( Routes::rate_limited( 'subscribe', 6, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'tv_rate', __( 'Too many sign-ups from your connection. Please try again later.', 'techvenza-core' ), array( 'status' => 429 ) );
		}
		$captcha = Captcha::verify( $data );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}
		$email  = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$full   = ! empty( $data['full'] );
		$topics = array_map( 'strval', (array) ( $data['topics'] ?? array() ) );
		$errors = array();
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'techvenza-core' );
		}
		if ( $full && Options::enabled( 'newsletter_topics' ) && ! $topics ) {
			$errors['topics'] = __( 'Select at least one topic.', 'techvenza-core' );
		}
		if ( $full && ! filter_var( $data['consent'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
			$errors['consent'] = __( 'Consent is required to subscribe.', 'techvenza-core' );
		}
		if ( $errors ) {
			return new \WP_Error(
				'tv_invalid',
				__( 'Please fix the highlighted fields.', 'techvenza-core' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}
		if ( ! $topics ) {
			$topics = array_keys( Subscribers::topics() );
		}
		$freq    = sanitize_key( (string) ( $data['freq'] ?? 'weekly' ) );
		$consent = $full
			? __( 'I agree to receive the selected newsletter(s) and understand I can unsubscribe at any time.', 'techvenza-core' )
			: __( 'We will email you to confirm before sending anything. Every issue has a one-click unsubscribe.', 'techvenza-core' );
		$result  = Subscribers::subscribe( $email, $topics, $freq, (string) ( $data['source'] ?? 'form' ), $consent );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'code' => 'subscribed',
			'html' => self::success_html( $email, $full ? $topics : array() ),
		);
	}

	/**
	 * Success state (identical for new and existing addresses, so nothing is disclosed).
	 *
	 * @param string            $email  Email.
	 * @param array<int,string> $topics Topics chosen.
	 */
	public static function success_html( string $email, array $topics ): string {
		$names = array_intersect_key( Subscribers::topics(), array_flip( $topics ) );
		return sprintf(
			'<div class="success-state" role="status"><span class="icon-circle">%1$s</span><h2 class="h3" tabindex="-1">%2$s</h2><p>%3$s</p>%4$s<p class="nl-fine">%5$s</p></div>',
			R::icon( 'mail' ),
			esc_html__( 'Check your inbox', 'techvenza-core' ),
			wp_kses(
				sprintf(
					/* translators: %s: email address */
					__( 'We sent a confirmation link to <b>%s</b>. Your subscription starts after you confirm.', 'techvenza-core' ),
					esc_html( $email )
				),
				array( 'b' => array() )
			),
			$names ? '<p>' . esc_html( sprintf( /* translators: %s: topics */ __( 'Topics: %s', 'techvenza-core' ), implode( ', ', $names ) ) ) . '</p>' : '',
			esc_html__( 'Nothing arrived after a few minutes? Check your spam folder. Already subscribed? Your preferences have been updated.', 'techvenza-core' )
		);
	}

	/**
	 * REST sign-up.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_subscribe( \WP_REST_Request $request ) {
		$result = self::process( (array) $request->get_json_params() + (array) $request->get_body_params() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * No-JS sign-up.
	 */
	public static function handle_post(): void {
		$back = wp_get_referer();
		$back = $back ? remove_query_arg( array( 'tv_nl', 'tv_nl_err' ), $back ) : R::page_url( 'newsletter' );
		if ( ! Auth::same_origin() ) {
			wp_safe_redirect( add_query_arg( 'tv_nl_err', 'origin', $back ) );
			exit;
		}
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public sign-up; protected by same-origin, honeypot, rate limit and double opt-in.
		if ( isset( $data['consent'] ) ) {
			$data['consent'] = '1' === (string) $data['consent'];
		}
		$result = self::process( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'tv_nl_err', rawurlencode( $result->get_error_code() ), $back ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'tv_nl', 'check', $back ) );
		exit;
	}

	/**
	 * Confirmation link: /?tv_nl_confirm=TOKEN.
	 */
	public static function confirm_link(): void {
		if ( empty( $_GET['tv_nl_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['tv_nl_confirm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sub   = Subscribers::confirm( $token );
		nocache_headers();
		wp_safe_redirect( add_query_arg( 'tv_nl', $sub ? 'confirmed' : 'invalid', R::page_url( 'newsletter' ) ) );
		exit;
	}

	/**
	 * Unsubscribe form submit.
	 */
	public static function handle_unsubscribe(): void {
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The 40-char secret token is the credential.
		$sub   = Subscribers::by_token( 'unsubscribe_token', $token );
		if ( $sub ) {
			Subscribers::unsubscribe( (int) $sub['id'] );
		}
		wp_safe_redirect( add_query_arg( 'tv_nl', $sub ? 'unsubscribed' : 'invalid', R::page_url( 'newsletter' ) ) );
		exit;
	}

	/**
	 * One-click unsubscribe.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public static function rest_unsubscribe( \WP_REST_Request $request ): \WP_REST_Response {
		$sub = Subscribers::by_token( 'unsubscribe_token', (string) $request['token'] );
		if ( $sub ) {
			Subscribers::unsubscribe( (int) $sub['id'] );
		}
		return rest_ensure_response( array( 'unsubscribed' => (bool) $sub ) );
	}

	/**
	 * Status note for the newsletter page (?tv_nl=…, ?tv_nl_err=…, ?tv_unsub=TOKEN).
	 */
	public static function page_notice(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! empty( $_GET['tv_unsub'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['tv_unsub'] ) );
			$sub   = Subscribers::by_token( 'unsubscribe_token', $token );
			if ( ! $sub ) {
				return self::note( 'warn', __( 'That unsubscribe link is not valid. If you keep receiving emails, reply to one and we will remove you.', 'techvenza-core' ) );
			}
			if ( 'unsubscribed' === $sub['status'] ) {
				return self::note( 'ok', __( 'This address is already unsubscribed.', 'techvenza-core' ) );
			}
			return sprintf(
				'<form class="form-note warn mt-5" method="post" action="%1$s" style="align-items:center;flex-wrap:wrap"><input type="hidden" name="action" value="techvenza_unsubscribe"><input type="hidden" name="token" value="%2$s">%3$s<span>%4$s</span><button class="btn btn-primary btn-sm" type="submit">%5$s</button></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr( $token ),
				R::icon( 'mail' ),
				esc_html( sprintf( /* translators: %s: email */ __( 'Unsubscribe %s from all Techvenza newsletters?', 'techvenza-core' ), (string) $sub['email'] ) ),
				esc_html__( 'Unsubscribe', 'techvenza-core' )
			);
		}
		$code = isset( $_GET['tv_nl'] ) ? sanitize_key( wp_unslash( $_GET['tv_nl'] ) ) : '';
		$err  = isset( $_GET['tv_nl_err'] ) ? sanitize_key( wp_unslash( $_GET['tv_nl_err'] ) ) : '';
		// phpcs:enable
		$texts = array(
			'check'        => array( 'ok', __( 'Check your inbox — we sent a confirmation link. Your subscription starts after you confirm.', 'techvenza-core' ) ),
			'confirmed'    => array( 'ok', __( 'You are subscribed. Thanks for confirming — your first issue arrives with the next edition.', 'techvenza-core' ) ),
			'unsubscribed' => array( 'ok', __( 'You have been unsubscribed and will not receive further newsletters.', 'techvenza-core' ) ),
			'invalid'      => array( 'warn', __( 'That link is not valid or has expired. Please sign up again.', 'techvenza-core' ) ),
		);
		if ( $err ) {
			return self::note( 'warn', 'tv_rate' === $err ? __( 'Too many sign-ups from your connection. Please try again later.', 'techvenza-core' ) : __( 'Please check your email address, choose at least one topic and tick the consent box.', 'techvenza-core' ) );
		}
		return isset( $texts[ $code ] ) ? self::note( $texts[ $code ][0], $texts[ $code ][1] ) : '';
	}

	/**
	 * Note markup.
	 *
	 * @param string $kind ok|warn.
	 * @param string $text Text.
	 */
	private static function note( string $kind, string $text ): string {
		return sprintf( '<div class="form-note %1$s mt-5" role="%2$s">%3$s<span>%4$s</span></div>', esc_attr( $kind ), 'warn' === $kind ? 'alert' : 'status', R::icon( 'warn' === $kind ? 'alert' : 'checkCircle' ), esc_html( $text ) );
	}

	/**
	 * Frequency label.
	 *
	 * @param string $freq weekly|monthly.
	 */
	public static function frequency_label( string $freq ): string {
		return 'monthly' === $freq ? __( 'monthly digest', 'techvenza-core' ) : __( 'weekly brief', 'techvenza-core' );
	}

	/**
	 * Send the double opt-in email.
	 *
	 * @param int $id Subscriber.
	 */
	public static function send_confirmation( int $id ): void {
		$sub = Subscribers::get( $id );
		if ( ! $sub ) {
			return;
		}
		Mailer::send_template(
			'newsletter_confirm',
			(string) $sub['email'],
			array(
				'newsletter_name' => self::name(),
				'frequency'       => self::frequency_label( (string) $sub['frequency'] ),
				'confirm_url'     => self::url( 'confirm', (string) $sub['confirm_token'] ),
			)
		);
	}

	/**
	 * Welcome email after confirmation.
	 *
	 * @param int $id Subscriber.
	 */
	public static function send_welcome( int $id ): void {
		$sub = Subscribers::get( $id );
		if ( ! $sub ) {
			return;
		}
		Mailer::send_template(
			'newsletter_welcome',
			(string) $sub['email'],
			array(
				'newsletter_name' => self::name(),
				'topics'          => implode( ', ', array_intersect_key( Subscribers::topics(), array_flip( (array) $sub['topics'] ) ) ),
				'manage_url'      => self::url( 'unsubscribe', (string) $sub['unsubscribe_token'] ),
			)
		);
	}
}
