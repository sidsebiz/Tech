<?php
/**
 * Local-only helpers. Mapped into wp-content/mu-plugins by wp-env / Docker Compose.
 * Never deploy this file: it routes all mail to Mailpit.
 *
 * @package Techvenza
 */

if ( 'local' !== wp_get_environment_type() ) {
	return;
}

add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$host = getenv( 'MAILPIT_HOST' ) ? getenv( 'MAILPIT_HOST' ) : 'mailpit';
		// When running under wp-env, Mailpit runs via `npm run mailpit` on the host.
		if ( defined( 'TECHVENZA_SMTP_HOST' ) && ! getenv( 'MAILPIT_HOST' ) ) {
			$host = TECHVENZA_SMTP_HOST;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host     = $host;
		$phpmailer->Port     = 1025;
		$phpmailer->SMTPAuth = false;
	},
	99
);
