<?php
/**
 * Public forms: contact (routed by inquiry type) and author applications. Each works over REST
 * (in-place success state) and as a plain POST to admin-post.php without JavaScript. Both use
 * same-origin checks, a honeypot, per-IP rate limits and the configured captcha.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Forms;

use Techvenza\Core\Auth\Auth;
use Techvenza\Core\Content\PostTypes;
use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Newsletter\Subscribers;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

/**
 * Forms module.
 */
final class Forms {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		foreach ( array( 'contact', 'apply' ) as $form ) {
			add_action( 'admin_post_nopriv_techvenza_' . $form, array( self::class, 'handle_post' ) );
			add_action( 'admin_post_techvenza_' . $form, array( self::class, 'handle_post' ) );
		}
	}

	/**
	 * Inquiry types.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return array(
			'editorial'   => __( 'Editorial — story feedback or tips', 'techvenza-core' ),
			'correction'  => __( 'Correction request', 'techvenza-core' ),
			'partnership' => __( 'Partnerships', 'techvenza-core' ),
			'contributor' => __( 'Contributor support', 'techvenza-core' ),
			'other'       => __( 'Something else', 'techvenza-core' ),
		);
	}

	/**
	 * REST routes.
	 */
	public static function routes(): void {
		foreach ( array( 'contact', 'apply' ) as $form ) {
			register_rest_route(
				Routes::NS,
				'/forms/' . $form,
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest' ),
					'permission_callback' => array( Auth::class, 'same_origin' ),
				)
			);
		}
	}

	/**
	 * REST handler.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest( \WP_REST_Request $request ) {
		$result = self::dispatch( basename( $request->get_route() ), (array) $request->get_json_params() + (array) $request->get_body_params() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * No-JS handler.
	 */
	public static function handle_post(): void {
		$form = isset( $_REQUEST['action'] ) ? substr( sanitize_key( wp_unslash( $_REQUEST['action'] ) ), 10 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$back = wp_get_referer();
		$back = $back ? remove_query_arg( array( 'tv_form', 'tv_form_err', 'tv_ref' ), $back ) : home_url( '/' );
		if ( ! Auth::same_origin() ) {
			wp_safe_redirect( add_query_arg( 'tv_form_err', 'origin', $back ) );
			exit;
		}
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public form; protected by same-origin, honeypot, rate limit and captcha.
		$data = is_array( $data ) ? $data : array();
		foreach ( array( 'consent', 'agree', 'privacy' ) as $box ) {
			$data[ $box ] = ! empty( $data[ $box ] );
		}
		$result = self::dispatch( $form, $data );
		if ( is_wp_error( $result ) ) {
			$errors = (array) ( $result->get_error_data()['errors'] ?? array() );
			set_transient(
				'tv_form_' . substr( Routes::ip_hash(), 0, 20 ),
				array(
					'errors' => $errors,
					'data'   => array_map( static fn( $v ) => is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_textarea_field( (string) $v ), $data ),
				),
				10 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( add_query_arg( 'tv_form_err', rawurlencode( $result->get_error_code() ), $back ) );
			exit;
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'tv_form' => $form,
					'tv_ref'  => rawurlencode( (string) ( $result['reference'] ?? '' ) ),
				),
				$back
			)
		);
		exit;
	}

	/**
	 * Previous submission after a failed no-JS round trip.
	 *
	 * @return array{errors:array<string,string>,data:array<string,mixed>}
	 */
	public static function old(): array {
		static $old = null;
		if ( null === $old ) {
			$key  = 'tv_form_' . substr( Routes::ip_hash(), 0, 20 );
			$old  = isset( $_GET['tv_form_err'] ) ? (array) get_transient( $key ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$old += array(
				'errors' => array(),
				'data'   => array(),
			);
		}
		return $old;
	}

	/**
	 * Mark fields that failed server-side validation in a no-JS round trip.
	 *
	 * @param string $html Form HTML.
	 */
	public static function mark_errors( string $html ): string {
		foreach ( self::old()['errors'] as $key => $msg ) {
			$key  = preg_quote( (string) $key, '/' );
			$html = (string) preg_replace( '/class="field( full)?" data-field="' . $key . '"/', 'class="field$1 invalid" data-field="' . $key . '"', $html, 1 );
		}
		return $html;
	}

	/**
	 * Prefill value from a failed no-JS submission.
	 *
	 * @param string $key Field.
	 * @return mixed
	 */
	public static function value( string $key ) {
		return self::old()['data'][ $key ] ?? '';
	}

	/**
	 * Success state or error note for no-JS round trips.
	 *
	 * @param string $form contact|apply.
	 * @return array{success:string,note:string}
	 */
	public static function round_trip( string $form ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$done = isset( $_GET['tv_form'] ) && sanitize_key( wp_unslash( $_GET['tv_form'] ) ) === $form;
		$err  = isset( $_GET['tv_form_err'] ) ? sanitize_key( wp_unslash( $_GET['tv_form_err'] ) ) : '';
		$ref  = isset( $_GET['tv_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['tv_ref'] ) ) : '';
		// phpcs:enable
		if ( $done ) {
			return array(
				'success' => 'apply' === $form
					/* translators: %s: reference */
					? self::success( 'check', __( 'Application received', 'techvenza-core' ), $ref && 'TV-' === substr( $ref, 0, 3 ) ? sprintf( __( 'Thanks. Your reference is <b>%s</b>. We sent you a confirmation email.', 'techvenza-core' ), esc_html( $ref ) ) : __( 'Thanks. We sent you a confirmation email.', 'techvenza-core' ) )
					: self::success( 'check', __( 'Message sent', 'techvenza-core' ), __( 'Thanks — we sent you a copy and usually reply within two working days.', 'techvenza-core' ) ),
				'note'    => '',
			);
		}
		$texts = array(
			'tv_rate'    => __( 'Too many submissions from your connection. Please try again later.', 'techvenza-core' ),
			'tv_captcha' => __( 'The security check failed. Please try again.', 'techvenza-core' ),
			'origin'     => __( 'Your submission could not be verified. Please try again.', 'techvenza-core' ),
		);
		$msg   = $err ? ( $texts[ $err ] ?? __( 'Please fix the highlighted fields.', 'techvenza-core' ) ) : '';
		return array(
			'success' => '',
			'note'    => sprintf( '<div class="form-note warn mb-5" data-form-error role="alert"%s>%s<span>%s</span></div>', $msg ? '' : ' hidden', R::icon( 'alert' ), esc_html( $msg ) ),
		);
	}

	/**
	 * Run a form.
	 *
	 * @param string              $form contact|apply.
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function dispatch( string $form, array $data ) {
		if ( ! Captcha::honeypot_ok( $data ) ) {
			return array(
				'code' => 'ok',
				'html' => self::success( 'check', __( 'Thanks', 'techvenza-core' ), __( 'Your message was received.', 'techvenza-core' ) ),
			);
		}
		if ( Routes::rate_limited( 'form_' . $form, 'apply' === $form ? 3 : 5, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'tv_rate', __( 'Too many submissions from your connection. Please try again later.', 'techvenza-core' ), array( 'status' => 429 ) );
		}
		$captcha = Captcha::verify( $data );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}
		if ( 'contact' === $form ) {
			return self::contact( $data );
		}
		if ( 'apply' === $form ) {
			return self::apply( $data );
		}
		return new \WP_Error( 'tv_invalid', __( 'Unknown form.', 'techvenza-core' ), array( 'status' => 400 ) );
	}

	/**
	 * Validation error.
	 *
	 * @param array<string,string> $errors Field => message.
	 */
	private static function invalid( array $errors ): \WP_Error {
		return new \WP_Error(
			'tv_invalid',
			__( 'Please fix the highlighted fields.', 'techvenza-core' ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}

	/**
	 * Truthy checkbox.
	 *
	 * @param mixed $v Value.
	 */
	private static function checked( $v ): bool {
		return true === $v || '1' === $v || 1 === $v || 'true' === $v || 'on' === $v;
	}

	/**
	 * Absolute http(s) URL with a dotted host (no DNS lookup, so it works for any public site).
	 *
	 * @param mixed $url URL.
	 */
	public static function is_url( $url ): bool {
		$url  = (string) $url;
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return '' !== $url && false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) && str_contains( $host, '.' );
	}

	/**
	 * Success state.
	 *
	 * @param string $icon   Icon.
	 * @param string $title  Title.
	 * @param string $text   Text (may contain <b>).
	 * @param string $action Pre-escaped actions.
	 */
	public static function success( string $icon, string $title, string $text, string $action = '' ): string {
		return sprintf(
			'<div class="success-state" role="status"><span class="icon-circle">%s</span><h2 class="h2" tabindex="-1">%s</h2><p style="max-width:52ch">%s</p>%s</div>',
			R::icon( $icon ),
			esc_html( $title ),
			wp_kses( $text, array( 'b' => array() ) ),
			$action
		);
	}

	/* ------------------------------------------------------------------ Contact */

	/**
	 * Contact form.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function contact( array $data ) {
		$type    = sanitize_key( (string) ( $data['type'] ?? '' ) );
		$name    = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$email   = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$subject = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
		$message = sanitize_textarea_field( (string) ( $data['message'] ?? '' ) );
		$errors  = array();
		if ( ! isset( self::types()[ $type ] ) ) {
			$errors['type'] = __( 'Choose an inquiry type.', 'techvenza-core' );
		}
		if ( '' === $name ) {
			$errors['name'] = __( 'Enter your name.', 'techvenza-core' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'techvenza-core' );
		}
		if ( '' === $subject || mb_strlen( $subject ) > 150 ) {
			$errors['subject'] = __( 'Add a subject (up to 150 characters).', 'techvenza-core' );
		}
		$len = mb_strlen( trim( $message ) );
		if ( $len < 20 || $len > 2000 ) {
			$errors['message'] = __( 'Write a message between 20 and 2,000 characters.', 'techvenza-core' );
		}
		if ( ! self::checked( $data['consent'] ?? false ) ) {
			$errors['consent'] = __( 'Please confirm so we can respond.', 'techvenza-core' );
		}
		if ( $errors ) {
			return self::invalid( $errors );
		}
		$to = (string) Options::get( 'contact_email_' . $type, '' );
		$to = is_email( $to ) ? $to : (string) get_option( 'admin_email' );
		$id = wp_insert_post(
			array(
				'post_type'    => PostTypes::MESSAGE,
				'post_status'  => 'private',
				'post_title'   => $subject,
				'post_content' => $message,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, 'tv_name', $name );
		update_post_meta( $id, 'tv_email', $email );
		update_post_meta( $id, 'tv_type', $type );
		update_post_meta( $id, 'tv_routed_to', $to );
		update_post_meta( $id, 'tv_consent_at', current_time( 'mysql' ) );
		update_post_meta( $id, 'tv_ip_hash', Routes::ip_hash() );
		update_post_meta( $id, 'tv_handled', '0' );

		$label = explode( ' —', self::types()[ $type ] )[0];
		$vars  = array(
			'name'       => $name,
			'first_name' => explode( ' ', $name )[0],
			'email'      => $email,
			'subject'    => $subject,
			'message'    => $message,
			'type_label' => $label,
			'team'       => strtolower( $label ),
			'inbox_url'  => admin_url( 'admin.php?page=techvenza-messages&id=' . $id ),
		);
		self::send_with_reply_to( 'contact_team', $to, $vars, $name, $email );
		Mailer::send_template( 'contact_ack', $email, $vars );
		wp_cache_delete( 'tv_messages_unread', 'techvenza' );

		return array(
			'code'      => 'sent',
			'reference' => (string) $id,
			'html'      => self::success(
				'check',
				__( 'Message sent', 'techvenza-core' ),
				sprintf(
					/* translators: 1: team, 2: email */
					__( 'Thanks — your message is with our <b>%1$s</b> team. We sent a copy to <b>%2$s</b> and usually reply within two working days.', 'techvenza-core' ),
					esc_html( $label ),
					esc_html( $email )
				),
				'<a class="btn btn-primary" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to home', 'techvenza-core' ) . '</a>'
			),
		);
	}

	/**
	 * Template email with Reply-To set to the visitor.
	 *
	 * @param string               $event Event.
	 * @param string               $to    Recipient.
	 * @param array<string,string> $vars  Vars.
	 * @param string               $name  Reply-to name.
	 * @param string               $email Reply-to email.
	 */
	private static function send_with_reply_to( string $event, string $to, array $vars, string $name, string $email ): void {
		$add = static function ( $phpmailer ) use ( $name, $email ): void {
			$phpmailer->addReplyTo( $email, $name );
		};
		add_action( 'phpmailer_init', $add );
		Mailer::send_template( $event, $to, $vars );
		remove_action( 'phpmailer_init', $add );
	}

	/* ------------------------------------------------------------------ Apply */

	/**
	 * Author application.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function apply( array $data ) {
		$f      = array(
			'name'      => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'email'     => sanitize_email( (string) ( $data['email'] ?? '' ) ),
			'title'     => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'org'       => sanitize_text_field( (string) ( $data['org'] ?? '' ) ),
			'expertise' => array_values( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) ( $data['expertise'] ?? '' ) ) ) ) ) ),
			'cats'      => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $data['cats'] ?? array() ) ), array_keys( Subscribers::topics() ) ) ),
			'bio'       => sanitize_textarea_field( (string) ( $data['bio'] ?? '' ) ),
			'link'      => esc_url_raw( trim( (string) ( $data['link'] ?? '' ) ), array( 'http', 'https' ) ),
			'samples'   => array_values( array_filter( array_map( static fn( $u ) => esc_url_raw( $u, array( 'http', 'https' ) ), (array) preg_split( '/[\s,]+/', (string) ( $data['samples'] ?? '' ) ) ), array( self::class, 'is_url' ) ) ),
			'reason'    => sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) ),
		);
		$errors = array();
		foreach ( array( 'name', 'title' ) as $k ) {
			if ( '' === $f[ $k ] ) {
				$errors[ $k ] = __( 'This field is required.', 'techvenza-core' );
			}
		}
		if ( ! is_email( $f['email'] ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'techvenza-core' );
		}
		if ( ! $f['expertise'] ) {
			$errors['expertise'] = __( 'List at least one area of expertise.', 'techvenza-core' );
		}
		if ( ! $f['cats'] ) {
			$errors['cats'] = __( 'Choose at least one category.', 'techvenza-core' );
		}
		$bio = mb_strlen( trim( $f['bio'] ) );
		if ( $bio < 80 || $bio > 600 ) {
			$errors['bio'] = __( 'Add a biography between 80 and 600 characters.', 'techvenza-core' );
		}
		if ( ! self::is_url( $f['link'] ) ) {
			$errors['link'] = __( 'Enter a full URL starting with https://', 'techvenza-core' );
		}
		if ( ! $f['samples'] ) {
			$errors['samples'] = __( 'Add at least one valid link.', 'techvenza-core' );
		}
		$reason = mb_strlen( trim( $f['reason'] ) );
		if ( $reason < 40 || $reason > 800 ) {
			$errors['reason'] = __( 'Tell us a little more (40–800 characters).', 'techvenza-core' );
		}
		if ( ! self::checked( $data['agree'] ?? false ) ) {
			$errors['agree'] = __( 'You must agree to the editorial guidelines.', 'techvenza-core' );
		}
		if ( ! self::checked( $data['privacy'] ?? false ) ) {
			$errors['privacy'] = __( 'Please acknowledge the privacy notice.', 'techvenza-core' );
		}
		if ( $errors ) {
			return self::invalid( $errors );
		}

		// One open application per address.
		$open = get_posts(
			array(
				'post_type'   => PostTypes::APPLICATION,
				'post_status' => 'any',
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'tv_email',
						'value' => strtolower( $f['email'] ),
					),
					array(
						'key'     => 'tv_status',
						'value'   => array( 'pending', 'info_requested' ),
						'compare' => 'IN',
					),
				),
			)
		);
		if ( $open ) {
			return self::invalid( array( 'email' => __( 'There is already an open application for this email address. We will be in touch soon.', 'techvenza-core' ) ) );
		}

		$ref = 'TV-' . strtoupper( substr( wp_hash( $f['email'] . microtime() ), 0, 6 ) );
		$id  = wp_insert_post(
			array(
				'post_type'   => PostTypes::APPLICATION,
				'post_status' => 'private',
				'post_title'  => $f['name'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$user = get_user_by( 'email', $f['email'] );
		$meta = array(
			'tv_email'      => strtolower( $f['email'] ),
			'tv_title'      => $f['title'],
			'tv_org'        => $f['org'],
			'tv_expertise'  => $f['expertise'],
			'tv_categories' => $f['cats'],
			'tv_bio'        => $f['bio'],
			'tv_link'       => $f['link'],
			'tv_samples'    => array_slice( $f['samples'], 0, 5 ),
			'tv_reason'     => $f['reason'],
			'tv_status'     => 'pending',
			'tv_reference'  => $ref,
			'tv_user_id'    => $user ? $user->ID : 0,
			'tv_consent_at' => current_time( 'mysql' ),
			'tv_history'    => array(
				array(
					'status' => 'pending',
					'user'   => $user ? $user->ID : 0,
					'note'   => '',
					'at'     => current_time( 'mysql' ),
				),
			),
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		wp_cache_delete( 'tv_apps_pending', 'techvenza' );

		$first = explode( ' ', $f['name'] )[0];
		Mailer::send_template(
			'application_received',
			$f['email'],
			array(
				'applicant_first_name' => $first,
				'reference'            => $ref,
			)
		);
		$editors = get_users(
			array(
				'capability' => 'tv_manage_applications',
				'fields'     => array( 'user_email' ),
				'number'     => 20,
			)
		);
		$names   = array_intersect_key( Subscribers::topics(), array_flip( $f['cats'] ) );
		foreach ( $editors as $ed ) {
			Mailer::send_template(
				'application_team',
				(string) $ed->user_email,
				array(
					'applicant_name'  => $f['name'],
					'applicant_title' => $f['title'],
					'reference'       => $ref,
					'categories'      => implode( ', ', $names ),
					'review_url'      => admin_url( 'admin.php?page=techvenza-applications&id=' . $id ),
				)
			);
		}

		return array(
			'code'      => 'applied',
			'reference' => $ref,
			'html'      => self::success(
				'check',
				__( 'Application received', 'techvenza-core' ),
				sprintf(
					/* translators: 1: first name, 2: reference, 3: email */
					__( 'Thanks, %1$s. Your reference is <b>%2$s</b>. We sent a confirmation to <b>%3$s</b>, and an editor will reply within about two weeks.', 'techvenza-core' ),
					esc_html( $first ),
					esc_html( $ref ),
					esc_html( $f['email'] )
				),
				sprintf( '<div class="row" style="justify-content:center"><a class="btn btn-primary" href="%s">%s</a><a class="btn btn-secondary" href="%s">%s</a></div>', esc_url( home_url( '/' ) ), esc_html__( 'Back to home', 'techvenza-core' ), esc_url( R::page_url( 'editorial-guidelines' ) ), esc_html__( 'Read the guidelines', 'techvenza-core' ) )
			),
		);
	}
}
