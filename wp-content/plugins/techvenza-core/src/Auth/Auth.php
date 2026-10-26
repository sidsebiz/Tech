<?php
/**
 * Branded accounts: login, registration with email verification, forgot/reset password
 * (core key-based flows), rate limiting, honeypot and captcha.
 *
 * Every form posts to admin-post.php without JavaScript and to the REST API with it;
 * both paths share the same handlers below.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Auth;

use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render;

/**
 * Account flows.
 */
final class Auth {

	/**
	 * Pages used by the branded flows (slug => purpose).
	 */
	public const PAGES = array(
		'login'           => 'login',
		'register'        => 'register',
		'forgot-password' => 'forgot',
		'reset-password'  => 'reset',
	);

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		foreach ( array( 'login', 'register', 'forgot', 'reset', 'resend' ) as $action ) {
			add_action( 'admin_post_nopriv_techvenza_' . $action, array( self::class, 'handle_post' ) );
			add_action( 'admin_post_techvenza_' . $action, array( self::class, 'handle_post' ) );
		}
		add_filter( 'login_url', array( self::class, 'filter_login_url' ), 10, 2 );
		add_filter( 'register_url', array( self::class, 'filter_register_url' ) );
		add_filter( 'lostpassword_url', array( self::class, 'filter_lostpassword_url' ) );
		add_action( 'login_init', array( self::class, 'redirect_wp_login' ) );
		add_action( 'template_redirect', array( self::class, 'template_redirect' ) );
		add_filter( 'authenticate', array( self::class, 'throttle' ), 1, 3 );
		add_filter( 'authenticate', array( self::class, 'require_verified' ), 99, 1 );
		add_action( 'wp_login_failed', array( self::class, 'record_failure' ) );
		add_filter( 'retrieve_password_notification_email', array( self::class, 'reset_email' ), 10, 4 );
		add_action( 'after_password_reset', array( self::class, 'after_reset' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
	}

	/* --------------------------------------------------------------------- URLs */

	/**
	 * URL of a branded page, or '' when the page does not exist.
	 *
	 * @param string $slug Page slug.
	 */
	public static function page( string $slug ): string {
		$page = get_page_by_path( $slug );
		return $page && 'publish' === $page->post_status ? (string) get_permalink( $page ) : '';
	}

	/**
	 * Branded login URL.
	 *
	 * @param string $login_url Default.
	 * @param string $redirect  Redirect target.
	 */
	public static function filter_login_url( string $login_url, string $redirect = '' ): string {
		$url = self::page( 'login' );
		if ( ! $url ) {
			return $login_url;
		}
		return $redirect ? add_query_arg( 'next', rawurlencode( $redirect ), $url ) : $url;
	}

	/**
	 * Branded registration URL.
	 *
	 * @param string $url Default.
	 */
	public static function filter_register_url( string $url ): string {
		$page = self::page( 'register' );
		return $page ? $page : $url;
	}

	/**
	 * Branded lost-password URL.
	 *
	 * @param string $url Default.
	 */
	public static function filter_lostpassword_url( string $url ): string {
		$page = self::page( 'forgot-password' );
		return $page ? $page : $url;
	}

	/**
	 * Send GET visits to wp-login.php to the branded pages (POSTs and special actions keep working).
	 */
	public static function redirect_wp_login(): void {
		if ( 'get' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || isset( $_GET['interim-login'] ) || isset( $_GET['tv-core-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		if ( ! Options::enabled( 'branded_login' ) ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map    = array(
			'login'        => 'login',
			'register'     => 'register',
			'lostpassword' => 'forgot-password',
			'rp'           => 'reset-password',
			'resetpass'    => 'reset-password',
		);
		if ( ! isset( $map[ $action ] ) ) {
			return;
		}
		$url = self::page( $map[ $action ] );
		if ( ! $url ) {
			return;
		}
		$args = array();
		foreach ( array(
			'redirect_to' => 'next',
			'key'         => 'key',
			'login'       => 'login',
		) as $from => $to ) {
			if ( isset( $_GET[ $from ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args[ $to ] = rawurlencode( sanitize_text_field( wp_unslash( $_GET[ $from ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * Where a user lands after signing in.
	 *
	 * @param \WP_User $user User.
	 */
	public static function home_for( \WP_User $user ): string {
		if ( user_can( $user, 'tv_review_posts' ) || user_can( $user, 'manage_options' ) ) {
			return admin_url( 'admin.php?page=techvenza' );
		}
		if ( user_can( $user, 'tv_use_portal' ) ) {
			$portal = self::page( 'author-portal' );
			return $portal ? $portal : admin_url( 'edit.php' );
		}
		$dash = self::page( 'dashboard' );
		return $dash ? $dash : home_url( '/' );
	}

	/**
	 * Core login redirect: send people to their home unless a specific target was requested.
	 *
	 * @param string             $redirect  Target.
	 * @param string             $requested Requested target.
	 * @param \WP_User|\WP_Error $user      User.
	 */
	public static function login_redirect( string $redirect, string $requested, $user ): string {
		if ( $user instanceof \WP_User && ( '' === $requested || admin_url() === $requested ) ) {
			return self::home_for( $user );
		}
		return $redirect;
	}

	/**
	 * Safe "next" target.
	 *
	 * @param string   $next Requested.
	 * @param \WP_User $user User.
	 */
	private static function next_url( string $next, \WP_User $user ): string {
		$next = $next ? wp_validate_redirect( esc_url_raw( $next ), '' ) : '';
		return $next ? $next : self::home_for( $user );
	}

	/* --------------------------------------------------------------------- Page-level handling */

	/**
	 * Verification links, signed-in visitors on auth pages.
	 */
	public static function template_redirect(): void {
		if ( ! is_page() ) {
			return;
		}
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		if ( ! isset( self::PAGES[ $slug ] ) ) {
			return;
		}
		nocache_headers();
		if ( 'login' === $slug && isset( $_GET['verify'], $_GET['u'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ok = self::verify_email( absint( $_GET['u'] ), sanitize_text_field( wp_unslash( $_GET['verify'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( add_query_arg( 'tv_msg', $ok ? 'verified' : 'verify_failed', self::page( 'login' ) ) );
			exit;
		}
		if ( is_user_logged_in() && in_array( $slug, array( 'login', 'register', 'forgot-password' ), true ) ) {
			wp_safe_redirect( self::home_for( wp_get_current_user() ) );
			exit;
		}
	}

	/* --------------------------------------------------------------------- REST + admin-post */

	/**
	 * REST routes.
	 */
	public static function routes(): void {
		foreach ( array( 'login', 'register', 'forgot', 'reset', 'resend' ) as $action ) {
			register_rest_route(
				Routes::NS,
				'/auth/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rest' ),
					'permission_callback' => array( self::class, 'same_origin' ),
				)
			);
		}
	}

	/**
	 * Reject cross-site form posts (login CSRF) when the browser sends an Origin header.
	 */
	public static function same_origin(): bool {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		if ( '' === $origin ) {
			return true;
		}
		return wp_parse_url( $origin, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * REST dispatcher.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest( \WP_REST_Request $request ) {
		$action = basename( $request->get_route() );
		$data   = (array) $request->get_json_params() + (array) $request->get_body_params();
		$result = self::dispatch( $action, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Handler for admin-post.php submissions (no JavaScript).
	 */
	public static function handle_post(): void {
		$action = isset( $_REQUEST['action'] ) ? substr( sanitize_key( wp_unslash( $_REQUEST['action'] ) ), 10 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Login CSRF is mitigated by same_origin(); state-changing flows verify keys/tokens.
		$back   = wp_get_referer();
		$back   = $back ? $back : home_url( '/' );
		if ( ! self::same_origin() ) {
			wp_safe_redirect( add_query_arg( 'tv_err', 'origin', $back ) );
			exit;
		}
		$result = self::dispatch( $action, is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			$back = remove_query_arg( array( 'tv_err', 'tv_msg' ), $back );
			wp_safe_redirect( add_query_arg( 'tv_err', rawurlencode( $result->get_error_code() ), $back ) );
			exit;
		}
		if ( ! empty( $result['redirect'] ) ) {
			wp_safe_redirect( (string) $result['redirect'] );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'tv_msg', rawurlencode( (string) ( $result['code'] ?? 'ok' ) ), remove_query_arg( array( 'tv_err', 'tv_msg' ), $back ) ) );
		exit;
	}

	/**
	 * Run one flow.
	 *
	 * @param string              $action login|register|forgot|reset|resend.
	 * @param array<string,mixed> $data   Submitted data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function dispatch( string $action, array $data ) {
		if ( ! Captcha::honeypot_ok( $data ) ) {
			// Pretend success so bots learn nothing.
			return array(
				'code'    => 'ok',
				'message' => __( 'Thanks.', 'techvenza-core' ),
			);
		}
		if ( in_array( $action, array( 'register', 'forgot', 'resend' ), true ) && Routes::rate_limited( 'auth_' . $action, 5, 15 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'tv_rate', __( 'Too many attempts. Please wait a few minutes and try again.', 'techvenza-core' ), array( 'status' => 429 ) );
		}
		if ( in_array( $action, array( 'login', 'register', 'forgot' ), true ) ) {
			$captcha = Captcha::verify( $data );
			if ( is_wp_error( $captcha ) ) {
				return $captcha;
			}
		}
		switch ( $action ) {
			case 'login':
				return self::login( $data );
			case 'register':
				return self::create_account( $data );
			case 'forgot':
				return self::forgot( $data );
			case 'reset':
				return self::reset( $data );
			case 'resend':
				return self::resend( $data );
		}
		return new \WP_Error( 'tv_invalid', __( 'Unknown request.', 'techvenza-core' ), array( 'status' => 400 ) );
	}

	/* --------------------------------------------------------------------- Flows */

	/**
	 * Sign in.
	 *
	 * @param array<string,mixed> $data email, password, remember, next.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function login( array $data ) {
		$login = trim( (string) ( $data['email'] ?? $data['log'] ?? '' ) );
		$pass  = (string) ( $data['password'] ?? $data['pwd'] ?? '' );
		if ( '' === $login || '' === $pass ) {
			return self::field_error( 'tv_required', __( 'Enter your email and password.', 'techvenza-core' ), array( 'email' => __( 'Enter your email address.', 'techvenza-core' ) ) );
		}
		if ( is_email( $login ) ) {
			$by_email = get_user_by( 'email', $login );
			if ( $by_email ) {
				$login = $by_email->user_login;
			}
		}
		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => ! empty( $data['remember'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			$code = $user->get_error_code();
			if ( in_array( $code, array( 'tv_locked', 'tv_unverified' ), true ) ) {
				return new \WP_Error( $code, wp_strip_all_tags( $user->get_error_message() ), array( 'status' => 'tv_locked' === $code ? 429 : 403 ) );
			}
			return self::field_error( 'tv_credentials', __( 'That email and password combination is not right.', 'techvenza-core' ), array( 'password' => __( 'Check your password and try again.', 'techvenza-core' ) ), 401 );
		}
		wp_set_current_user( $user->ID );
		return array(
			'code'     => 'signed_in',
			/* translators: %s: display name */
			'message'  => sprintf( __( 'Welcome back, %s.', 'techvenza-core' ), $user->display_name ),
			'redirect' => self::next_url( (string) ( $data['next'] ?? '' ), $user ),
		);
	}

	/**
	 * Register a reader account and send the verification email.
	 *
	 * @param array<string,mixed> $data name, email, password, confirm, terms.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_account( array $data ) {
		if ( ! Options::enabled( 'registration_open' ) ) {
			return new \WP_Error( 'tv_closed', __( 'Registration is currently closed.', 'techvenza-core' ), array( 'status' => 403 ) );
		}
		$name   = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$email  = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$pass   = (string) ( $data['password'] ?? '' );
		$errors = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Enter your name.', 'techvenza-core' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 'techvenza-core' );
		}
		if ( ! self::strong( $pass ) ) {
			$errors['password'] = __( 'Use at least 10 characters including a number.', 'techvenza-core' );
		}
		if ( (string) ( $data['confirm'] ?? '' ) !== $pass ) {
			$errors['confirm'] = __( 'Passwords do not match.', 'techvenza-core' );
		}
		if ( empty( $data['terms'] ) ) {
			$errors['terms'] = __( 'You need to accept the terms to continue.', 'techvenza-core' );
		}
		if ( $errors ) {
			return self::field_error( 'tv_invalid', __( 'Please fix the highlighted fields.', 'techvenza-core' ), $errors );
		}
		if ( email_exists( $email ) ) {
			// Do not reveal which addresses have accounts: respond as if a verification email was sent.
			return self::registered_response( $email );
		}
		$login = sanitize_user( strtolower( strtok( $email, '@' ) ), true );
		$base  = $login ? $login : 'reader';
		$n     = 1;
		while ( username_exists( $login ) || '' === $login ) {
			$login = $base . ( ++$n );
		}
		$parts = explode( ' ', $name, 2 );
		$id    = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'nickname'     => $name,
				'first_name'   => $parts[0],
				'last_name'    => $parts[1] ?? '',
				'role'         => (string) get_option( 'default_role', 'subscriber' ) === 'administrator' ? 'subscriber' : (string) get_option( 'default_role', 'subscriber' ),
			)
		);
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( 'tv_register', $id->get_error_message(), array( 'status' => 400 ) );
		}
		update_user_meta( $id, 'tv_email_verified', '0' );
		update_user_meta( $id, 'tv_terms_accepted_at', current_time( 'mysql' ) );
		self::send_verification( (int) $id );
		/**
		 * Fires after a reader registers through the branded form.
		 *
		 * @param int $user_id New user ID.
		 */
		do_action( 'techvenza/auth/registered', (int) $id );
		return self::registered_response( $email );
	}

	/**
	 * Response after registration (identical for new and existing emails).
	 *
	 * @param string $email Email.
	 * @return array<string,mixed>
	 */
	private static function registered_response( string $email ): array {
		return array(
			'code'    => 'registered',
			'message' => __( 'Check your email to confirm your account.', 'techvenza-core' ),
			'html'    => self::success(
				'mail',
				__( 'Check your email', 'techvenza-core' ),
				/* translators: %s: email address */
				sprintf( __( 'We sent a confirmation link to %s. Open it to activate your account, then log in.', 'techvenza-core' ), '<b>' . esc_html( $email ) . '</b>' ),
				sprintf( '<a class="btn btn-primary" href="%s">%s</a>', esc_url( self::page( 'login' ) ), esc_html__( 'Go to log in', 'techvenza-core' ) )
			),
		);
	}

	/**
	 * Send (or resend) the verification email.
	 *
	 * @param int $user_id User.
	 */
	public static function send_verification( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$token = wp_generate_password( 32, false );
		update_user_meta( $user_id, 'tv_verify_hash', wp_hash_password( $token ) );
		update_user_meta( $user_id, 'tv_verify_expires', time() + 2 * DAY_IN_SECONDS );
		$login = self::page( 'login' );
		$url   = add_query_arg(
			array(
				'verify' => $token,
				'u'      => $user_id,
			),
			$login ? $login : home_url( '/' )
		);
		Mailer::send_template(
			'verify_email',
			$user->user_email,
			array(
				'first_name' => (string) ( $user->first_name ? $user->first_name : $user->display_name ),
				'verify_url' => $url,
			)
		);
	}

	/**
	 * Confirm an email address from the link.
	 *
	 * @param int    $user_id User.
	 * @param string $token   Token.
	 */
	public static function verify_email( int $user_id, string $token ): bool {
		$hash    = (string) get_user_meta( $user_id, 'tv_verify_hash', true );
		$expires = (int) get_user_meta( $user_id, 'tv_verify_expires', true );
		if ( '' === $hash || $expires < time() || ! wp_check_password( $token, $hash, $user_id ) ) {
			return false;
		}
		update_user_meta( $user_id, 'tv_email_verified', '1' );
		delete_user_meta( $user_id, 'tv_verify_hash' );
		delete_user_meta( $user_id, 'tv_verify_expires' );
		return true;
	}

	/**
	 * Resend verification.
	 *
	 * @param array<string,mixed> $data email.
	 * @return array<string,mixed>
	 */
	public static function resend( array $data ): array {
		$user = get_user_by( 'email', sanitize_email( (string) ( $data['email'] ?? '' ) ) );
		if ( $user && '0' === get_user_meta( $user->ID, 'tv_email_verified', true ) ) {
			self::send_verification( $user->ID );
		}
		return array(
			'code'    => 'resent',
			'message' => __( 'If that account needs confirming, a new link is on its way.', 'techvenza-core' ),
		);
	}

	/**
	 * Request a reset link (core key flow; email is branded in reset_email()).
	 *
	 * @param array<string,mixed> $data email.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function forgot( array $data ) {
		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return self::field_error( 'tv_invalid', __( 'Enter a valid email address.', 'techvenza-core' ), array( 'email' => __( 'Enter a valid email address.', 'techvenza-core' ) ) );
		}
		if ( get_user_by( 'email', $email ) ) {
			retrieve_password( $email );
		}
		return array(
			'code'    => 'reset_sent',
			'message' => __( 'Check your email for a reset link.', 'techvenza-core' ),
			'html'    => self::success(
				'mail',
				__( 'Check your email', 'techvenza-core' ),
				/* translators: %s: email */
				sprintf( __( 'If an account exists for %s, a reset link will arrive shortly. For security, we show this message either way.', 'techvenza-core' ), '<b>' . esc_html( $email ) . '</b>' ),
				sprintf( '<a class="btn btn-secondary" href="%s">%s</a>', esc_url( self::page( 'login' ) ), esc_html__( 'Back to log in', 'techvenza-core' ) )
			),
		);
	}

	/**
	 * Set a new password from a reset key.
	 *
	 * @param array<string,mixed> $data key, login, password, confirm.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function reset( array $data ) {
		$user = check_password_reset_key( (string) ( $data['key'] ?? '' ), (string) ( $data['login'] ?? '' ) );
		if ( is_wp_error( $user ) ) {
			return new \WP_Error( 'tv_expired', __( 'This reset link has expired or was already used. Request a new one.', 'techvenza-core' ), array( 'status' => 400 ) );
		}
		$pass = (string) ( $data['password'] ?? '' );
		if ( ! self::strong( $pass ) ) {
			return self::field_error( 'tv_invalid', __( 'Please fix the highlighted fields.', 'techvenza-core' ), array( 'password' => __( 'Use at least 10 characters including a number.', 'techvenza-core' ) ) );
		}
		if ( (string) ( $data['confirm'] ?? '' ) !== $pass ) {
			return self::field_error( 'tv_invalid', __( 'Please fix the highlighted fields.', 'techvenza-core' ), array( 'confirm' => __( 'Passwords do not match.', 'techvenza-core' ) ) );
		}
		reset_password( $user, $pass );
		// Resetting via an emailed link proves the address.
		update_user_meta( $user->ID, 'tv_email_verified', '1' );
		return array(
			'code'    => 'password_updated',
			'message' => __( 'Password updated. You can log in now.', 'techvenza-core' ),
			'html'    => self::success(
				'check',
				__( 'Password updated', 'techvenza-core' ),
				esc_html__( 'Other sessions were signed out. Log in with your new password.', 'techvenza-core' ),
				sprintf( '<a class="btn btn-primary" href="%s">%s</a>', esc_url( self::page( 'login' ) ), esc_html__( 'Log in', 'techvenza-core' ) )
			),
		);
	}

	/* --------------------------------------------------------------------- Core filters */

	/**
	 * Lock out an IP+login pair after too many failures.
	 *
	 * @param \WP_User|\WP_Error|null $user     Result so far.
	 * @param string                  $username Login.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function throttle( $user, $username, $password ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( '' === (string) $username ) {
			return $user;
		}
		$max = max( 3, (int) Options::get( 'login_attempts', 5 ) );
		if ( (int) get_transient( self::failure_key( (string) $username ) ) >= $max ) {
			return new \WP_Error( 'tv_locked', __( 'Too many failed attempts. Please wait 15 minutes or reset your password.', 'techvenza-core' ) );
		}
		return $user;
	}

	/**
	 * Count a failed login.
	 *
	 * @param string $username Login.
	 */
	public static function record_failure( string $username ): void {
		$key = self::failure_key( $username );
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Transient key for failures.
	 *
	 * @param string $username Login or email.
	 */
	private static function failure_key( string $username ): string {
		return 'tv_lf_' . substr( hash( 'sha256', strtolower( $username ) . '|' . Routes::ip_hash() ), 0, 32 );
	}

	/**
	 * Unverified accounts cannot sign in.
	 *
	 * @param \WP_User|\WP_Error|null $user Result.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function require_verified( $user ) {
		if ( $user instanceof \WP_User && '0' === get_user_meta( $user->ID, 'tv_email_verified', true ) ) {
			return new \WP_Error( 'tv_unverified', __( 'Please confirm your email address first. We can send a new confirmation link.', 'techvenza-core' ) );
		}
		return $user;
	}

	/**
	 * Branded reset email pointing at the branded reset page.
	 *
	 * @param array<string,mixed> $defaults   Email args.
	 * @param string              $key        Reset key.
	 * @param string              $user_login Login.
	 * @param \WP_User            $user       User.
	 * @return array<string,mixed>
	 */
	public static function reset_email( array $defaults, string $key, string $user_login, \WP_User $user ): array {
		$page = self::page( 'reset-password' );
		$url  = $page
			? add_query_arg(
				array(
					'key'   => $key,
					'login' => rawurlencode( $user_login ),
				),
				$page
			)
			: network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' );
		$vars = array(
			'first_name' => (string) ( $user->first_name ? $user->first_name : $user->display_name ),
			'reset_url'  => $url,
			'site_name'  => get_bloginfo( 'name' ),
		);
		$tpl  = \Techvenza\Core\Settings\Fields::email_events()['password_reset'];
		$text = Mailer::merge( (string) Options::get( 'tpl_password_reset_body', $tpl['body'] ), $vars );

		$defaults['subject'] = Mailer::merge( (string) Options::get( 'tpl_password_reset_subject', $tpl['subject'] ), $vars );
		$defaults['message'] = Mailer::wrap( (string) $defaults['subject'], $text );
		$defaults['headers'] = array( 'Content-Type: text/html; charset=UTF-8' );
		return $defaults;
	}

	/**
	 * After a reset: sign out other sessions and confirm by email.
	 *
	 * @param \WP_User $user User.
	 */
	public static function after_reset( \WP_User $user ): void {
		\WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		$forgot = self::page( 'forgot-password' );
		Mailer::send_template(
			'password_changed',
			$user->user_email,
			array(
				'first_name' => (string) ( $user->first_name ? $user->first_name : $user->display_name ),
				'forgot_url' => $forgot ? $forgot : wp_lostpassword_url(),
			)
		);
	}

	/* --------------------------------------------------------------------- Helpers */

	/**
	 * Password policy (mirrors the client-side strength meter).
	 *
	 * @param string $pass Password.
	 */
	public static function strong( string $pass ): bool {
		return mb_strlen( $pass ) >= 10 && (bool) preg_match( '/\d/', $pass );
	}

	/**
	 * Validation error with per-field messages (forms.js highlights them).
	 *
	 * @param string               $code    Code.
	 * @param string               $message Message.
	 * @param array<string,string> $fields  Field => message.
	 * @param int                  $status  HTTP status.
	 */
	private static function field_error( string $code, string $message, array $fields, int $status = 400 ): \WP_Error {
		return new \WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
				'errors' => $fields,
			)
		);
	}

	/**
	 * Success state markup (shared with the server-rendered no-JS states).
	 *
	 * @param string $icon   Icon.
	 * @param string $title  Title.
	 * @param string $text   Pre-escaped text.
	 * @param string $action Pre-escaped actions.
	 */
	public static function success( string $icon, string $title, string $text, string $action = '' ): string {
		return sprintf(
			'<div class="success-state" role="status" style="padding-inline:0"><span class="icon-circle">%s</span><h2 class="h3">%s</h2><p>%s</p><div class="row" style="justify-content:center">%s</div></div>',
			Render::icon( $icon ),
			esc_html( $title ),
			$text,
			$action
		);
	}

	/**
	 * Messages for ?tv_msg / ?tv_err after no-JS submissions.
	 *
	 * @param string $code Code.
	 * @return array{0:string,1:string}|null [type, message]
	 */
	public static function notice( string $code ): ?array {
		$ok  = array(
			'verified'         => __( 'Your email is confirmed. You can log in now.', 'techvenza-core' ),
			'registered'       => __( 'Check your email to confirm your account.', 'techvenza-core' ),
			'reset_sent'       => __( 'If an account exists for that email, a reset link is on its way.', 'techvenza-core' ),
			'password_updated' => __( 'Password updated. You can log in now.', 'techvenza-core' ),
			'resent'           => __( 'If that account needs confirming, a new link is on its way.', 'techvenza-core' ),
			'logged_out'       => __( 'You have been signed out.', 'techvenza-core' ),
			'email_changed'    => __( 'Your email address was updated.', 'techvenza-core' ),
		);
		$err = array(
			'email_failed'   => __( 'That email confirmation link is invalid or has expired.', 'techvenza-core' ),
			'verify_failed'  => __( 'That confirmation link is invalid or has expired. Log in to request a new one.', 'techvenza-core' ),
			'tv_credentials' => __( 'That email and password combination is not right.', 'techvenza-core' ),
			'tv_locked'      => __( 'Too many failed attempts. Please wait 15 minutes or reset your password.', 'techvenza-core' ),
			'tv_unverified'  => __( 'Please confirm your email address first. We can send a new confirmation link.', 'techvenza-core' ),
			'tv_captcha'     => __( 'Please complete the security check.', 'techvenza-core' ),
			'tv_rate'        => __( 'Too many attempts. Please wait a few minutes and try again.', 'techvenza-core' ),
			'tv_expired'     => __( 'This reset link has expired or was already used. Request a new one.', 'techvenza-core' ),
			'tv_closed'      => __( 'Registration is currently closed.', 'techvenza-core' ),
			'tv_invalid'     => __( 'Please check the form and try again.', 'techvenza-core' ),
			'tv_required'    => __( 'Enter your email and password.', 'techvenza-core' ),
			'origin'         => __( 'The form was submitted from another site and was ignored.', 'techvenza-core' ),
			'google'         => __( 'Google sign-in did not complete. Please try again or use your password.', 'techvenza-core' ),
		);
		if ( isset( $ok[ $code ] ) ) {
			return array( 'ok', $ok[ $code ] );
		}
		if ( isset( $err[ $code ] ) ) {
			return array( 'warn', $err[ $code ] );
		}
		return null;
	}
}
