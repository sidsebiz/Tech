<?php
/**
 * Techvenza → Settings: every option from the Fields schema, grouped in tabs, with
 * wp-config.php constant locks, write-only secrets and connection tests.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Email\Mailer;
use Techvenza\Core\Newsletter\Esp;
use Techvenza\Core\Settings\Fields;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Captcha;

/**
 * Settings screen.
 */
final class Settings {

	public const SLUG = 'techvenza-settings';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_techvenza_settings', array( self::class, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * Media/colour pickers on the settings screen.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'techvenza-term-meta', TECHVENZA_CORE_URL . 'assets/js/admin-term.js', array( 'jquery', 'wp-color-picker' ), TECHVENZA_CORE_VERSION, true );
		wp_localize_script( 'techvenza-term-meta', 'techvenzaTerm', array( 'choose' => __( 'Choose image', 'techvenza-core' ) ) );
	}

	/**
	 * Current tab.
	 */
	private static function tab(): string {
		$tabs = Fields::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'brand'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$tabs = Fields::tabs();
		$tab  = self::tab();
		$nav  = '';
		foreach ( $tabs as $key => $def ) {
			$nav .= sprintf( '<a class="tab" href="%s"%s>%s</a>', esc_url( Admin::url( self::SLUG, array( 'tab' => $key ) ) ), $key === $tab ? ' aria-current="page"' : '', esc_html( (string) $def['label'] ) );
		}
		$main = '';
		$tpl  = '';
		foreach ( $tabs[ $tab ]['fields'] as $key => $field ) {
			$row = self::row( (string) $key, $field );
			if ( 'templates' === ( $field['group'] ?? '' ) ) {
				$tpl .= $row;
			} else {
				$main .= $row;
			}
		}
		$tests = array_values( array_unique( array_filter( array_map( static fn( $f ) => (string) ( $f['test'] ?? '' ), $tabs[ $tab ]['fields'] ) ) ) );
		if ( 'email' === $tab ) {
			array_unshift( $tests, 'email' );
		}
		$test_buttons = '';
		$labels       = array(
			'email'     => __( 'Save & send a test email', 'techvenza-core' ),
			'brevo'     => __( 'Save & test Brevo', 'techvenza-core' ),
			'mailchimp' => __( 'Save & test Mailchimp', 'techvenza-core' ),
			'turnstile' => __( 'Save & test Turnstile', 'techvenza-core' ),
			'recaptcha' => __( 'Save & test reCAPTCHA', 'techvenza-core' ),
			'google'    => __( 'Save & check Google sign-in', 'techvenza-core' ),
		);
		foreach ( $tests as $t ) {
			$test_buttons .= sprintf( '<button class="button" type="submit" name="op" value="test:%s">%s</button>', esc_attr( $t ), esc_html( $labels[ $t ] ?? $t ) );
		}
		$tpl_html = $tpl ? sprintf(
			'<details class="panel" style="margin-top:20px"><summary class="panel-title" style="cursor:pointer">%s</summary><p class="meta">%s</p><table class="form-table" role="presentation">%s</table></details>',
			esc_html__( 'Email templates', 'techvenza-core' ),
			esc_html__( 'Merge tags in braces, e.g. {first_name}, {post_title}, {site_name}. Leave a field unchanged to keep the default wording.', 'techvenza-core' ),
			$tpl
		) : '';
		$extra    = in_array( 'google', $tests, true ) ? '<p class="meta">' . esc_html( sprintf( /* translators: %s: URL */ __( 'Authorised redirect URI for your Google OAuth client: %s', 'techvenza-core' ), \Techvenza\Core\Auth\GoogleOAuth::callback_url() ) ) . '</p>' : '';

		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Settings', 'techvenza-core' ), '' ),
			),
			__( 'Site settings', 'techvenza-core' ),
			esc_html__( 'Brand, navigation, integrations and policies. Values pinned in wp-config.php (TECHVENZA_<KEY> constants) are shown as locked; secrets are stored encrypted and never displayed.', 'techvenza-core' )
		) . sprintf(
			'<div class="table-wrap"><nav class="tabs" aria-label="%1$s">%2$s</nav><form method="post" action="%3$s" style="padding:8px 20px 20px">%4$s<input type="hidden" name="action" value="techvenza_settings"><input type="hidden" name="tab" value="%5$s"><table class="form-table" role="presentation">%6$s</table>%7$s%8$s<p class="submit row" style="gap:8px;flex-wrap:wrap"><button class="button button-primary" type="submit" name="op" value="save">%9$s</button>%10$s</p></form></div>',
			esc_attr__( 'Settings sections', 'techvenza-core' ),
			$nav,
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'techvenza_settings', '_wpnonce', true, false ),
			esc_attr( $tab ),
			$main,
			$extra,
			$tpl_html,
			esc_html__( 'Save changes', 'techvenza-core' ),
			$test_buttons
		);
	}

	/**
	 * One form row.
	 *
	 * @param string              $key   Key.
	 * @param array<string,mixed> $field Definition.
	 */
	private static function row( string $key, array $field ): string {
		$id     = 'tvs-' . $key;
		$name   = 'tv[' . $key . ']';
		$type   = (string) ( $field['type'] ?? 'text' );
		$locked = Options::is_constant( $key );
		$value  = Options::get( $key, $field['default'] ?? '' );
		$dis    = $locked ? ' disabled' : '';
		$label  = sprintf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( (string) $field['label'] ) );
		switch ( $type ) {
			case 'textarea':
			case 'lines':
				$ctrl = sprintf( '<textarea class="large-text" rows="%d" id="%s" name="%s"%s>%s</textarea>', 'lines' === $type ? 7 : 4, esc_attr( $id ), esc_attr( $name ), $dis, esc_textarea( is_array( $value ) ? implode( "\n", $value ) : (string) $value ) );
				break;
			case 'toggle':
				$label = '';
				$ctrl  = sprintf( '<input type="hidden" name="%2$s" value="0"><label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s> %5$s</label>', esc_attr( $id ), esc_attr( $name ), checked( filter_var( $value, FILTER_VALIDATE_BOOLEAN ), true, false ), $dis, esc_html( (string) $field['label'] ) );
				break;
			case 'select':
				$opts = '';
				foreach ( (array) $field['options'] as $v => $l ) {
					$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( (string) $v ), selected( (string) $value, (string) $v, false ), esc_html( (string) $l ) );
				}
				$ctrl = sprintf( '<select id="%s" name="%s"%s>%s</select>', esc_attr( $id ), esc_attr( $name ), $dis, $opts );
				break;
			case 'checks':
				$label = '<span>' . esc_html( (string) $field['label'] ) . '</span>';
				$boxes = '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
				foreach ( (array) $field['options'] as $v => $l ) {
					$boxes .= sprintf( '<label style="display:inline-block;min-width:220px;margin:0 12px 6px 0"><input type="checkbox" name="%s[]" value="%s"%s%s> %s</label>', esc_attr( $name ), esc_attr( (string) $v ), checked( in_array( (string) $v, (array) $value, true ), true, false ), $dis, esc_html( (string) $l ) );
				}
				$ctrl = '<fieldset><legend class="screen-reader-text">' . esc_html( (string) $field['label'] ) . '</legend>' . $boxes . '</fieldset>';
				break;
			case 'secret':
				$has  = Options::has_secret( $key );
				$ctrl = sprintf(
					'<input class="regular-text" type="password" id="%1$s" name="%2$s" value="" autocomplete="new-password" placeholder="%3$s"%4$s>%5$s',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $has ? __( 'Stored — leave empty to keep', 'techvenza-core' ) : '' ),
					$dis,
					$has && ! $locked ? sprintf( ' <label><input type="checkbox" name="tv_clear[]" value="%s"> %s</label>', esc_attr( $key ), esc_html__( 'Remove', 'techvenza-core' ) ) : ''
				);
				break;
			case 'media':
				$src  = $value ? (string) wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
				$ctrl = sprintf(
					'<div class="tv-media-field"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><img src="%4$s" alt="" style="max-width:160px;max-height:80px;%5$s"><p><button type="button" class="button tv-media-pick"%6$s>%7$s</button> <button type="button" class="button-link tv-media-clear"%6$s>%8$s</button></p></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_url( $src ),
					$src ? '' : 'display:none',
					$dis,
					esc_html__( 'Choose image', 'techvenza-core' ),
					esc_html__( 'Remove', 'techvenza-core' )
				);
				break;
			case 'post':
				$opts = '<option value="0">' . esc_html__( '— Automatic —', 'techvenza-core' ) . '</option>';
				foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 50 ) ) as $p ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
					$opts .= sprintf( '<option value="%d"%s>%s</option>', $p->ID, selected( (int) $value, $p->ID, false ), esc_html( get_the_title( $p ) ) );
				}
				$ctrl = sprintf( '<select id="%s" name="%s"%s>%s</select>', esc_attr( $id ), esc_attr( $name ), $dis, $opts );
				break;
			case 'color':
				$ctrl = sprintf( '<input class="tv-color" type="text" id="%s" name="%s" value="%s" data-default-color="%s"%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( (string) ( $field['default'] ?? '' ) ), $dis );
				break;
			default:
				$html_type = array(
					'url'      => 'url',
					'email'    => 'email',
					'number'   => 'number',
					'datetime' => 'datetime-local',
				)[ $type ] ?? 'text';
				$ctrl      = sprintf( '<input class="%s" type="%s" id="%s" name="%s" value="%s"%s>', 'number' === $type ? 'small-text' : 'regular-text', esc_attr( $html_type ), esc_attr( $id ), esc_attr( $name ), esc_attr( is_scalar( $value ) ? (string) $value : '' ), $dis );
		}
		$help = '';
		if ( ! empty( $field['help'] ) ) {
			$help .= '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
		}
		if ( $locked ) {
			/* translators: %s: constant name */
			$help .= '<p class="description">🔒 ' . esc_html( sprintf( __( 'Set by the %s constant in wp-config.php.', 'techvenza-core' ), Options::constant_name( $key ) ) ) . '</p>';
		}
		return sprintf( '<tr><th scope="row">%s</th><td>%s%s</td></tr>', $label, $ctrl, $help );
	}

	/**
	 * Sanitize one value by field type.
	 *
	 * @param array<string,mixed> $field Definition.
	 * @param mixed               $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( array $field, $value ) {
		$type = (string) ( $field['type'] ?? 'text' );
		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'lines':
				return implode( "\n", array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', (string) $value ) ? preg_split( '/\R/', (string) $value ) : array() ) ) );
			case 'toggle':
				return '1' === (string) $value ? '1' : '0';
			case 'number':
			case 'media':
			case 'post':
				return absint( $value );
			case 'url':
				$v = trim( (string) $value );
				return str_starts_with( $v, '/' ) ? sanitize_text_field( $v ) : esc_url_raw( $v );
			case 'email':
				return sanitize_email( (string) $value );
			case 'color':
				return (string) sanitize_hex_color( (string) $value );
			case 'select':
				return isset( $field['options'][ (string) $value ] ) ? (string) $value : (string) ( $field['default'] ?? '' );
			case 'checks':
				return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), array_map( 'strval', array_keys( (array) $field['options'] ) ) ) );
			case 'secret':
				return trim( (string) $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Save / test (admin-post).
	 */
	public static function handle(): void {
		check_admin_referer( 'techvenza_settings' );
		if ( ! current_user_can( 'tv_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to change settings.', 'techvenza-core' ), 403 );
		}
		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$tabs   = Fields::tabs();
		$fields = $tabs[ $tab ]['fields'] ?? array();
		$posted = isset( $_POST['tv'] ) && is_array( $_POST['tv'] ) ? wp_unslash( $_POST['tv'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.
		$values = array();
		foreach ( $fields as $key => $field ) {
			if ( Options::is_constant( (string) $key ) || ! array_key_exists( $key, $posted ) ) {
				continue;
			}
			$values[ $key ] = self::sanitize( $field, $posted[ $key ] );
		}
		foreach ( isset( $_POST['tv_clear'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['tv_clear'] ) ) : array() as $key ) {
			if ( isset( $fields[ $key ] ) && 'secret' === ( $fields[ $key ]['type'] ?? '' ) ) {
				$values[ $key ] = '__clear__';
			}
		}
		Options::update( $values );
		if ( array_key_exists( 'favicon', $values ) ) {
			// The favicon doubles as the WordPress Site Icon (browser tabs, app icons, RSS).
			update_option( 'site_icon', (int) $values['favicon'] );
		}

		$back = Admin::url( self::SLUG, array( 'tab' => $tab ) );
		$op   = isset( $_POST['op'] ) ? sanitize_text_field( wp_unslash( $_POST['op'] ) ) : 'save';
		if ( str_starts_with( $op, 'test:' ) ) {
			$result = self::test( substr( $op, 5 ) );
			Admin::back( is_wp_error( $result ) ? 'error' : 'done', is_wp_error( $result ) ? $result->get_error_message() : $result, $back );
		}
		Admin::back( 'done', '', $back );
	}

	/**
	 * Run a connection test with the saved values.
	 *
	 * @param string $id Test id.
	 * @return string|\WP_Error Success message.
	 */
	public static function test( string $id ) {
		switch ( $id ) {
			case 'email':
				$to = wp_get_current_user()->user_email;
				$ok = Mailer::send( $to, __( 'Techvenza test email', 'techvenza-core' ), __( 'If you can read this, outgoing email works.', 'techvenza-core' ) );
				/* translators: %s: email */
				return $ok ? sprintf( __( 'Test email sent to %s.', 'techvenza-core' ), $to ) : new \WP_Error( 'tv_mail', __( 'WordPress could not send the email. Check the SMTP host, port, encryption and credentials.', 'techvenza-core' ) );
			case 'brevo':
			case 'mailchimp':
				$key = (string) Options::get( $id . '_api_key', '' );
				return $key ? Esp::test( $id, $key ) : new \WP_Error( 'tv_key', __( 'Enter an API key first.', 'techvenza-core' ) );
			case 'turnstile':
			case 'recaptcha':
				$secret = (string) Options::get( $id . '_secret', '' );
				if ( ! $secret ) {
					return new \WP_Error( 'tv_key', __( 'Enter the secret key first.', 'techvenza-core' ) );
				}
				$res = Captcha::test( $id, $secret );
				return is_wp_error( $res ) ? $res : __( 'The provider accepted the secret key.', 'techvenza-core' );
			case 'google':
				$client = (string) Options::get( 'google_client_id', '' );
				$secret = (string) Options::get( 'google_client_secret', '' );
				if ( ! $client || ! $secret ) {
					return new \WP_Error( 'tv_key', __( 'Enter the client ID and secret first.', 'techvenza-core' ) );
				}
				$res = wp_remote_post(
					'https://oauth2.googleapis.com/token',
					array(
						'timeout' => 8,
						'body'    => array(
							'client_id'     => $client,
							'client_secret' => $secret,
							'grant_type'    => 'authorization_code',
							'code'          => 'techvenza-connection-test',
							'redirect_uri'  => \Techvenza\Core\Auth\GoogleOAuth::callback_url(),
						),
					)
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$err = (string) ( json_decode( (string) wp_remote_retrieve_body( $res ), true )['error'] ?? '' );
				// A valid client gets "invalid_grant" for the dummy code; a wrong one gets "invalid_client".
				return 'invalid_client' === $err || 'unauthorized_client' === $err
					? new \WP_Error( 'tv_google', __( 'Google rejected the client ID or secret.', 'techvenza-core' ) )
					: __( 'Google recognised the OAuth client. Finish by signing in with Google once.', 'techvenza-core' );
		}
		return new \WP_Error( 'tv_test', __( 'Unknown test.', 'techvenza-core' ) );
	}
}
