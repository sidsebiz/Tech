<?php
/**
 * Email delivery: SMTP configuration from settings, branded HTML wrapper, editable templates.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Email;

use Techvenza\Core\Settings\Fields;
use Techvenza\Core\Settings\Options;

/**
 * Sends Techvenza emails through wp_mail().
 */
final class Mailer {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'phpmailer_init', array( self::class, 'smtp' ), 20 );
		add_filter( 'wp_mail_from', array( self::class, 'from_email' ) );
		add_filter( 'wp_mail_from_name', array( self::class, 'from_name' ) );
	}

	/**
	 * Configure SMTP when enabled (constants override settings, see docs/DEPLOY.md).
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer.
	 */
	public static function smtp( $phpmailer ): void {
		if ( ! Options::enabled( 'smtp_enabled' ) || ! Options::get( 'smtp_host' ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host        = (string) Options::get( 'smtp_host' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port        = (int) Options::get( 'smtp_port', 587 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$enc                    = (string) Options::get( 'smtp_encryption', 'tls' );
		$phpmailer->SMTPSecure  = 'none' === $enc ? '' : $enc; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAutoTLS = 'none' !== $enc; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$user                   = (string) Options::get( 'smtp_user' );
		if ( $user ) {
			$phpmailer->SMTPAuth = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Username = $user; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Password = (string) Options::get( 'smtp_pass' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}

	/**
	 * From address.
	 *
	 * @param string $email Default.
	 */
	public static function from_email( string $email ): string {
		$custom = (string) Options::get( 'mail_from' );
		return is_email( $custom ) ? $custom : $email;
	}

	/**
	 * From name.
	 *
	 * @param string $name Default.
	 */
	public static function from_name( string $name ): string {
		$custom = (string) Options::get( 'mail_from_name' );
		return '' !== $custom && 'WordPress' === $name ? $custom : ( '' !== $custom ? $custom : $name );
	}

	/**
	 * Replace {merge_tags}.
	 *
	 * @param string               $text Text.
	 * @param array<string,string> $vars Vars.
	 */
	public static function merge( string $text, array $vars ): string {
		$pairs = array();
		foreach ( $vars as $k => $v ) {
			$pairs[ '{' . $k . '}' ] = (string) $v;
		}
		return strtr( $text, $pairs );
	}

	/**
	 * Send an editable template.
	 *
	 * @param string               $event Event key (see Fields::email_events()).
	 * @param string               $to    Recipient.
	 * @param array<string,string> $vars  Merge tags.
	 */
	public static function send_template( string $event, string $to, array $vars ): bool {
		$events = Fields::email_events();
		if ( ! isset( $events[ $event ] ) ) {
			return false;
		}
		$vars   += array(
			'site_name' => get_bloginfo( 'name' ),
			'sla_days'  => (string) Options::get( 'review_sla_days', 5 ),
		);
		$subject = self::merge( (string) Options::get( 'tpl_' . $event . '_subject', $events[ $event ]['subject'] ), $vars );
		$body    = self::merge( (string) Options::get( 'tpl_' . $event . '_body', $events[ $event ]['body'] ), $vars );
		return self::send( $to, $subject, $body );
	}

	/**
	 * Send a branded email (plain-text body is converted to HTML; links auto-linked).
	 *
	 * @param string               $to      Recipient.
	 * @param string               $subject Subject.
	 * @param string               $text    Plain-text body.
	 * @param array<int,string>    $headers Extra headers.
	 * @param array<string,string> $o       Options: cta_label, cta_url, footer.
	 */
	public static function send( string $to, string $subject, string $text, array $headers = array(), array $o = array() ): bool {
		$html      = self::wrap( $subject, $text, $o );
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		return (bool) wp_mail( $to, wp_specialchars_decode( $subject ), $html, $headers );
	}

	/**
	 * Branded HTML wrapper.
	 *
	 * @param string               $title Title.
	 * @param string               $text  Plain text.
	 * @param array<string,string> $o     Options.
	 */
	public static function wrap( string $title, string $text, array $o = array() ): string {
		$body = make_clickable( nl2br( esc_html( $text ) ) );
		$site = get_bloginfo( 'name' );
		$cta  = ! empty( $o['cta_url'] ) ? sprintf( '<p style="margin:24px 0"><a href="%s" style="background:#111827;color:#fff;padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:600;display:inline-block">%s</a></p>', esc_url( $o['cta_url'] ), esc_html( $o['cta_label'] ?? __( 'Open', 'techvenza-core' ) ) ) : '';
		$foot = $o['footer'] ?? sprintf( /* translators: %s: site name */ __( 'You are receiving this email because of your account or activity on %s.', 'techvenza-core' ), $site );
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $title ) . '</title></head>'
			. '<body style="margin:0;background:#F1F5F9;font-family:Inter,Segoe UI,Arial,sans-serif;color:#334155">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">'
			. '<tr><td style="padding:20px 28px;border-bottom:1px solid #E2E8F0"><span style="display:inline-block;width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,#2563EB,#7C3AED 55%,#F97362);vertical-align:middle"></span> <strong style="font-size:18px;color:#111827;vertical-align:middle">' . esc_html( $site ) . '</strong></td></tr>'
			. '<tr><td style="padding:28px;font-size:15px;line-height:1.6"><h1 style="font-size:20px;line-height:1.3;color:#111827;margin:0 0 16px">' . esc_html( $title ) . '</h1>' . $body . $cta . '</td></tr>'
			. '<tr><td style="padding:16px 28px;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B">' . wp_kses_post( $foot ) . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}
}
