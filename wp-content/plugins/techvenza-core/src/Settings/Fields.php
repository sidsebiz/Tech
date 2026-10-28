<?php
/**
 * Settings schema: every option shown on Techvenza → Settings.
 *
 * Any key can be overridden with a wp-config.php constant named TECHVENZA_ + the key in
 * upper case (e.g. smtp_pass → TECHVENZA_SMTP_PASS). Constants win over stored values.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Settings;

/**
 * Declarative settings definition.
 */
final class Fields {

	/**
	 * Notification events with editable templates.
	 *
	 * @return array<string,array{label:string,subject:string,body:string,audience:string}>
	 */
	public static function email_events(): array {
		return array(
			'submitted'            => array(
				'label'    => __( 'Article submitted (to editors)', 'techvenza-core' ),
				'audience' => 'editors',
				'subject'  => __( 'New submission: {post_title}', 'techvenza-core' ),
				'body'     => __( "{author_name} submitted “{post_title}” for review.\n\nOpen the review page: {review_url}", 'techvenza-core' ),
			),
			'in_review'            => array(
				'label'    => __( 'Review started (to author)', 'techvenza-core' ),
				'audience' => 'author',
				'subject'  => __( 'Your article is in review: {post_title}', 'techvenza-core' ),
				'body'     => __( "Hi {author_first_name},\n\n{editor_name} has started reviewing “{post_title}”.\n\nTrack progress: {submission_url}", 'techvenza-core' ),
			),
			'changes_requested'    => array(
				'label'    => __( 'Changes requested (to author)', 'techvenza-core' ),
				'audience' => 'author',
				'subject'  => __( 'Changes requested: {post_title}', 'techvenza-core' ),
				'body'     => __( "Hi {author_first_name},\n\n{editor_name} reviewed “{post_title}” and requested a few changes:\n\n{note}\n\nOpen your submission: {submission_url}", 'techvenza-core' ),
			),
			'resubmitted'          => array(
				'label'    => __( 'Article resubmitted (to editor)', 'techvenza-core' ),
				'audience' => 'editors',
				'subject'  => __( 'Resubmitted: {post_title}', 'techvenza-core' ),
				'body'     => __( "{author_name} resubmitted “{post_title}”.\n\nAuthor notes: {note}\n\nReview: {review_url}", 'techvenza-core' ),
			),
			'approved'             => array(
				'label'    => __( 'Article approved (to author)', 'techvenza-core' ),
				'audience' => 'author',
				'subject'  => __( 'Approved: {post_title}', 'techvenza-core' ),
				'body'     => __( "Good news, {author_first_name} — “{post_title}” was approved by {editor_name}. We will let you know when it is scheduled.\n\n{submission_url}", 'techvenza-core' ),
			),
			'scheduled'            => array(
				'label'    => __( 'Article scheduled (to author)', 'techvenza-core' ),
				'audience' => 'author',
				'subject'  => __( 'Scheduled: {post_title}', 'techvenza-core' ),
				'body'     => __( "“{post_title}” is scheduled to publish on {scheduled_date}.\n\n{submission_url}", 'techvenza-core' ),
			),
			'published'            => array(
				'label'    => __( 'Article published (to author)', 'techvenza-core' ),
				'audience' => 'author',
				'subject'  => __( 'Published: {post_title}', 'techvenza-core' ),
				'body'     => __( "“{post_title}” is now live on {site_name}:\n\n{post_url}\n\nThank you for writing with us.", 'techvenza-core' ),
			),
			'rejected'             => array(
				'label'    => __( 'Article not accepted (to author)', 'techvenza-core' ),
				'audience' => 'author',
				'subject'  => __( 'Decision on “{post_title}”', 'techvenza-core' ),
				'body'     => __( "Hi {author_first_name},\n\nThank you for submitting “{post_title}”. After review we will not be publishing it.\n\n{note}\n\nWe would welcome another pitch.", 'techvenza-core' ),
			),
			'application_received' => array(
				'label'    => __( 'Application received (to applicant)', 'techvenza-core' ),
				'audience' => 'applicant',
				'subject'  => __( 'We received your application to write for {site_name}', 'techvenza-core' ),
				'body'     => __( "Hi {applicant_first_name},\n\nThanks for applying to write for {site_name}. Your reference is {reference}. An editor will reply within about {sla_days} working days.", 'techvenza-core' ),
			),
			'application_approved' => array(
				'label'    => __( 'Application approved (to applicant)', 'techvenza-core' ),
				'audience' => 'applicant',
				'subject'  => __( 'Welcome to the {site_name} contributor community', 'techvenza-core' ),
				'body'     => __( "Hi {applicant_first_name},\n\nYour application was approved. You now have access to the author portal:\n\n{portal_url}\n\n{set_password_line}\n\n{note}", 'techvenza-core' ),
			),
			'application_rejected' => array(
				'label'    => __( 'Application declined (to applicant)', 'techvenza-core' ),
				'audience' => 'applicant',
				'subject'  => __( 'Your application to {site_name}', 'techvenza-core' ),
				'body'     => __( "Hi {applicant_first_name},\n\nThank you for your interest in writing for {site_name}. We are not able to accept your application at this time.\n\n{note}", 'techvenza-core' ),
			),
			'application_info'     => array(
				'label'    => __( 'More information requested (to applicant)', 'techvenza-core' ),
				'audience' => 'applicant',
				'subject'  => __( 'A question about your {site_name} application', 'techvenza-core' ),
				'body'     => __( "Hi {applicant_first_name},\n\nAn editor has a question about your application:\n\n{note}\n\nPlease reply to this email.", 'techvenza-core' ),
			),
			'application_team'     => array(
				'label'    => __( 'New application (to editors)', 'techvenza-core' ),
				'audience' => 'editors',
				'subject'  => __( 'New author application: {applicant_name}', 'techvenza-core' ),
				'body'     => __( "{applicant_name} ({applicant_title}) applied to write for {site_name}.\n\nReference: {reference}\nCategories: {categories}\n\nReview the application: {review_url}", 'techvenza-core' ),
			),
			'newsletter_confirm'   => array(
				'label'    => __( 'Confirm newsletter subscription (double opt-in)', 'techvenza-core' ),
				'audience' => 'subscriber',
				'subject'  => __( 'Confirm your subscription to {newsletter_name}', 'techvenza-core' ),
				'body'     => __( "Hi,\n\nPlease confirm that you want to receive {newsletter_name} ({frequency}) at this address:\n\n{confirm_url}\n\nIf you did not sign up, ignore this email and you will not hear from us again.", 'techvenza-core' ),
			),
			'newsletter_welcome'   => array(
				'label'    => __( 'Newsletter welcome (after confirming)', 'techvenza-core' ),
				'audience' => 'subscriber',
				'subject'  => __( 'You are subscribed to {newsletter_name}', 'techvenza-core' ),
				'body'     => __( "Thanks for confirming. Your first issue arrives with the next edition.\n\nTopics: {topics}\n\nChange your topics or unsubscribe at any time: {manage_url}", 'techvenza-core' ),
			),
			'contact_ack'          => array(
				'label'    => __( 'Contact form acknowledgement (to sender)', 'techvenza-core' ),
				'audience' => 'visitor',
				'subject'  => __( 'We received your message: {subject}', 'techvenza-core' ),
				'body'     => __( "Hi {first_name},\n\nThanks for contacting {site_name}. Your message has been passed to our {team} team, who usually reply within two working days.\n\nYour message:\n{message}", 'techvenza-core' ),
			),
			'contact_team'         => array(
				'label'    => __( 'New contact message (to the routed team)', 'techvenza-core' ),
				'audience' => 'editors',
				'subject'  => __( '[{type_label}] {subject}', 'techvenza-core' ),
				'body'     => __( "From: {name} <{email}>\nType: {type_label}\n\n{message}\n\nReply directly to this email, or open the inbox: {inbox_url}", 'techvenza-core' ),
			),
			'verify_email'         => array(
				'label'    => __( 'Confirm email address (new account)', 'techvenza-core' ),
				'audience' => 'reader',
				'subject'  => __( 'Confirm your {site_name} account', 'techvenza-core' ),
				'body'     => __( "Hi {first_name},\n\nPlease confirm your email address to activate your account:\n\n{verify_url}\n\nThe link expires in 48 hours. If you did not create an account, you can ignore this email.", 'techvenza-core' ),
			),
			'password_reset'       => array(
				'label'    => __( 'Password reset link', 'techvenza-core' ),
				'audience' => 'reader',
				'subject'  => __( 'Reset your {site_name} password', 'techvenza-core' ),
				'body'     => __( "Hi {first_name},\n\nSomeone asked to reset the password for your account. If this was you, choose a new password here:\n\n{reset_url}\n\nThe link works once and expires after 24 hours. If you did not ask for this, you can ignore this email — your password will not change.", 'techvenza-core' ),
			),
			'password_changed'     => array(
				'label'    => __( 'Password changed confirmation', 'techvenza-core' ),
				'audience' => 'reader',
				'subject'  => __( 'Your {site_name} password was changed', 'techvenza-core' ),
				'body'     => __( "Hi {first_name},\n\nThe password for your account was just changed and other sessions were signed out.\n\nIf you did not do this, reset your password immediately: {forgot_url}", 'techvenza-core' ),
			),
		);
	}

	/**
	 * Settings tabs → fields.
	 *
	 * Field keys: type (text|textarea|url|email|number|toggle|select|color|media|secret|post|lines),
	 * label, default, options, help, test (connection test id).
	 *
	 * @return array<string,array{label:string,fields:array<string,array<string,mixed>>}>
	 */
	public static function tabs(): array {
		$tabs = array(
			'brand'      => array(
				'label'  => __( 'Brand', 'techvenza-core' ),
				'fields' => array(
					'logo_light'    => array(
						'type'  => 'media',
						'label' => __( 'Logo for light backgrounds', 'techvenza-core' ),
						'help'  => __( 'SVG or PNG. Leave empty to use the built-in Techvenza wordmark.', 'techvenza-core' ),
					),
					'logo_dark'     => array(
						'type'  => 'media',
						'label' => __( 'Logo for dark backgrounds', 'techvenza-core' ),
						'help'  => __( 'Used in the footer and admin sidebar.', 'techvenza-core' ),
					),
					'favicon'       => array(
						'type'  => 'media',
						'label' => __( 'Favicon / site icon', 'techvenza-core' ),
						'help'  => __( 'Square, at least 512×512. Saved as the WordPress Site Icon.', 'techvenza-core' ),
					),
					'default_cover' => array(
						'type'  => 'media',
						'label' => __( 'Default article cover', 'techvenza-core' ),
						'help'  => __( 'Used for articles without a featured image when their category has no image. Leave empty to use the built-in illustration for each category.', 'techvenza-core' ),
					),
					'tagline'       => array(
						'type'    => 'text',
						'label'   => __( 'Tagline', 'techvenza-core' ),
						'default' => 'Technology, business and industry, explained.',
					),
					'wordmark_text' => array(
						'type'    => 'text',
						'label'   => __( 'Wordmark text', 'techvenza-core' ),
						'default' => 'Techvenza',
						'help'    => __( 'The last five letters are drawn in the brand gradient when no logo image is set.', 'techvenza-core' ),
					),
					'color_ink'     => array(
						'type'    => 'color',
						'label'   => __( 'Ink (headings, primary buttons)', 'techvenza-core' ),
						'default' => '',
					),
					'color_blue'    => array(
						'type'    => 'color',
						'label'   => __( 'Primary blue', 'techvenza-core' ),
						'default' => '',
					),
					'color_violet'  => array(
						'type'    => 'color',
						'label'   => __( 'Violet accent', 'techvenza-core' ),
						'default' => '',
					),
					'color_coral'   => array(
						'type'    => 'color',
						'label'   => __( 'Coral accent', 'techvenza-core' ),
						'default' => '',
					),
					'color_teal'    => array(
						'type'    => 'color',
						'label'   => __( 'Teal accent', 'techvenza-core' ),
						'default' => '',
					),
					'color_amber'   => array(
						'type'    => 'color',
						'label'   => __( 'Amber accent', 'techvenza-core' ),
						'default' => '',
					),
				),
			),
			'header'     => array(
				'label'  => __( 'Header & Footer', 'techvenza-core' ),
				'fields' => array(
					'header_cta_label'    => array(
						'type'    => 'text',
						'label'   => __( 'Header button label', 'techvenza-core' ),
						'default' => 'Become an Author',
					),
					'header_cta_url'      => array(
						'type'    => 'url',
						'label'   => __( 'Header button link', 'techvenza-core' ),
						'default' => '/become-an-author/',
					),
					'announce_enabled'    => array(
						'type'    => 'toggle',
						'label'   => __( 'Show announcement strip', 'techvenza-core' ),
						'default' => true,
					),
					'announce_pill'       => array(
						'type'    => 'text',
						'label'   => __( 'Announcement label', 'techvenza-core' ),
						'default' => 'New',
					),
					'announce_text'       => array(
						'type'    => 'text',
						'label'   => __( 'Announcement text', 'techvenza-core' ),
						'default' => 'Deep dive: what changes when software starts taking actions.',
					),
					'announce_link_label' => array(
						'type'    => 'text',
						'label'   => __( 'Announcement link label', 'techvenza-core' ),
						'default' => 'Read the analysis',
					),
					'announce_link_url'   => array(
						'type'    => 'url',
						'label'   => __( 'Announcement link', 'techvenza-core' ),
						'default' => '/the-agentic-enterprise/',
					),
					'announce_start'      => array(
						'type'    => 'datetime',
						'label'   => __( 'Show from', 'techvenza-core' ),
						'default' => '',
						'help'    => __( 'Optional. Site time zone.', 'techvenza-core' ),
					),
					'announce_end'        => array(
						'type'    => 'datetime',
						'label'   => __( 'Show until', 'techvenza-core' ),
						'default' => '',
					),
					'footer_about'        => array(
						'type'    => 'textarea',
						'label'   => __( 'Footer about text', 'techvenza-core' ),
						'default' => 'Independent, expert-led reporting on the technologies reshaping how organisations build, compete and work.',
					),
					'footer_copyright'    => array(
						'type'    => 'text',
						'label'   => __( 'Footer copyright line', 'techvenza-core' ),
						'default' => '© {year} {site}. All rights reserved.',
						'help'    => __( 'Merge tags: {year}, {site}, {company}.', 'techvenza-core' ),
					),
					'social_linkedin'     => array(
						'type'    => 'url',
						'label'   => 'LinkedIn',
						'default' => '',
					),
					'social_x'            => array(
						'type'    => 'url',
						'label'   => 'X (Twitter)',
						'default' => '',
					),
					'social_youtube'      => array(
						'type'    => 'url',
						'label'   => 'YouTube',
						'default' => '',
					),
					'social_facebook'     => array(
						'type'    => 'url',
						'label'   => 'Facebook',
						'default' => '',
					),
					'social_instagram'    => array(
						'type'    => 'url',
						'label'   => 'Instagram',
						'default' => '',
					),
					'social_rss'          => array(
						'type'    => 'toggle',
						'label'   => __( 'Show RSS icon', 'techvenza-core' ),
						'default' => true,
					),
				),
			),
			'homepage'   => array(
				'label'  => __( 'Homepage', 'techvenza-core' ),
				'fields' => array(
					'home_lead_post' => array(
						'type'    => 'post',
						'label'   => __( 'Lead featured story', 'techvenza-core' ),
						'default' => 0,
						'help'    => __( 'Leave empty to use the most recent post marked “Featured”.', 'techvenza-core' ),
					),
					'home_hidden'    => array(
						'type'    => 'checks',
						'label'   => __( 'Hide homepage sections', 'techvenza-core' ),
						'default' => array(),
						'options' => array(
							'announcement'       => __( 'Announcement strip', 'techvenza-core' ),
							'hero'               => __( 'Hero', 'techvenza-core' ),
							'featured-grid'      => __( 'Featured stories', 'techvenza-core' ),
							'trending-list'      => __( 'Trending', 'techvenza-core' ),
							'latest-posts'       => __( 'Latest', 'techvenza-core' ),
							'category-tiles'     => __( 'Explore by category', 'techvenza-core' ),
							'category-spotlight' => __( 'Category spotlights', 'techvenza-core' ),
							'editors-picks'      => __( "Editor's picks", 'techvenza-core' ),
							'quote-cards'        => __( 'Deep dives', 'techvenza-core' ),
							'popular'            => __( 'Popular this week', 'techvenza-core' ),
							'featured-authors'   => __( 'Featured authors', 'techvenza-core' ),
							'feature-items'      => __( 'Community', 'techvenza-core' ),
							'cta-band'           => __( 'Become an Author CTA', 'techvenza-core' ),
							'newsletter'         => __( 'Newsletter', 'techvenza-core' ),
						),
						'help'    => __( 'Quick switches. Reorder or edit sections in Appearance → Editor → Templates → Front page.', 'techvenza-core' ),
					),
				),
			),
			'email'      => array(
				'label'  => __( 'Email / SMTP', 'techvenza-core' ),
				'fields' => array(
					'smtp_enabled'    => array(
						'type'    => 'toggle',
						'label'   => __( 'Send email through SMTP', 'techvenza-core' ),
						'default' => false,
						'help'    => __( 'Disable if another plugin (e.g. WP Mail SMTP) handles delivery.', 'techvenza-core' ),
					),
					'smtp_host'       => array(
						'type'    => 'text',
						'label'   => __( 'SMTP host', 'techvenza-core' ),
						'default' => '',
					),
					'smtp_port'       => array(
						'type'    => 'number',
						'label'   => __( 'SMTP port', 'techvenza-core' ),
						'default' => 587,
					),
					'smtp_encryption' => array(
						'type'    => 'select',
						'label'   => __( 'Encryption', 'techvenza-core' ),
						'default' => 'tls',
						'options' => array(
							'tls'  => 'STARTTLS',
							'ssl'  => 'SSL/TLS',
							'none' => __( 'None', 'techvenza-core' ),
						),
					),
					'smtp_user'       => array(
						'type'    => 'text',
						'label'   => __( 'SMTP username', 'techvenza-core' ),
						'default' => '',
					),
					'smtp_pass'       => array(
						'type'    => 'secret',
						'label'   => __( 'SMTP password / API key', 'techvenza-core' ),
						'default' => '',
						'test'    => 'smtp',
					),
					'mail_from'       => array(
						'type'    => 'email',
						'label'   => __( 'From email', 'techvenza-core' ),
						'default' => '',
					),
					'mail_from_name'  => array(
						'type'    => 'text',
						'label'   => __( 'From name', 'techvenza-core' ),
						'default' => 'Techvenza',
					),
				),
			),
			'newsletter' => array(
				'label'  => __( 'Newsletter / ESP', 'techvenza-core' ),
				'fields' => array(
					'esp_provider'          => array(
						'type'    => 'select',
						'label'   => __( 'Email service provider', 'techvenza-core' ),
						'default' => 'local',
						'options' => array(
							'local'     => __( 'Local (WordPress mail, subscribers table only)', 'techvenza-core' ),
							'brevo'     => 'Brevo',
							'mailchimp' => 'Mailchimp',
						),
					),
					'brevo_api_key'         => array(
						'type'    => 'secret',
						'label'   => __( 'Brevo API key', 'techvenza-core' ),
						'default' => '',
						'test'    => 'brevo',
					),
					'brevo_list_id'         => array(
						'type'    => 'number',
						'label'   => __( 'Brevo list ID', 'techvenza-core' ),
						'default' => 0,
					),
					'mailchimp_api_key'     => array(
						'type'    => 'secret',
						'label'   => __( 'Mailchimp API key', 'techvenza-core' ),
						'default' => '',
						'test'    => 'mailchimp',
					),
					'mailchimp_audience_id' => array(
						'type'    => 'text',
						'label'   => __( 'Mailchimp audience ID', 'techvenza-core' ),
						'default' => '',
					),
					'newsletter_from_name'  => array(
						'type'    => 'text',
						'label'   => __( 'Sender name', 'techvenza-core' ),
						'default' => 'Techvenza Weekly Brief',
					),
					'newsletter_from_email' => array(
						'type'    => 'email',
						'label'   => __( 'Sender email', 'techvenza-core' ),
						'default' => '',
					),
					'newsletter_topics'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Ask subscribers to choose topics', 'techvenza-core' ),
						'default' => true,
					),
				),
			),
			'forms'      => array(
				'label'  => __( 'Forms & Spam', 'techvenza-core' ),
				'fields' => array(
					'captcha_provider'          => array(
						'type'    => 'select',
						'label'   => __( 'Bot protection', 'techvenza-core' ),
						'default' => 'none',
						'options' => array(
							'none'      => __( 'Honeypot + rate limit only', 'techvenza-core' ),
							'turnstile' => 'Cloudflare Turnstile',
							'recaptcha' => 'Google reCAPTCHA v3',
						),
					),
					'turnstile_site_key'        => array(
						'type'    => 'text',
						'label'   => __( 'Turnstile site key', 'techvenza-core' ),
						'default' => '',
					),
					'turnstile_secret'          => array(
						'type'    => 'secret',
						'label'   => __( 'Turnstile secret key', 'techvenza-core' ),
						'default' => '',
						'test'    => 'turnstile',
					),
					'recaptcha_site_key'        => array(
						'type'    => 'text',
						'label'   => __( 'reCAPTCHA site key', 'techvenza-core' ),
						'default' => '',
					),
					'recaptcha_secret'          => array(
						'type'    => 'secret',
						'label'   => __( 'reCAPTCHA secret key', 'techvenza-core' ),
						'default' => '',
						'test'    => 'recaptcha',
					),
					'contact_email_editorial'   => array(
						'type'    => 'email',
						'label'   => __( 'Editorial inquiries go to', 'techvenza-core' ),
						'default' => '',
					),
					'contact_email_correction'  => array(
						'type'    => 'email',
						'label'   => __( 'Correction requests go to', 'techvenza-core' ),
						'default' => '',
					),
					'contact_email_partnership' => array(
						'type'    => 'email',
						'label'   => __( 'Partnership inquiries go to', 'techvenza-core' ),
						'default' => '',
					),
					'contact_email_contributor' => array(
						'type'    => 'email',
						'label'   => __( 'Contributor support goes to', 'techvenza-core' ),
						'default' => '',
					),
					'contact_email_other'       => array(
						'type'    => 'email',
						'label'   => __( 'Everything else goes to', 'techvenza-core' ),
						'default' => '',
						'help'    => __( 'Empty routes fall back to the site admin email.', 'techvenza-core' ),
					),
					'contact_retention_days'    => array(
						'type'    => 'number',
						'label'   => __( 'Delete contact messages after (days)', 'techvenza-core' ),
						'default' => 90,
					),
				),
			),
			'login'      => array(
				'label'  => __( 'Login', 'techvenza-core' ),
				'fields' => array(
					'registration_open'    => array(
						'type'    => 'toggle',
						'label'   => __( 'Allow reader registration', 'techvenza-core' ),
						'default' => true,
					),
					'branded_login'        => array(
						'type'    => 'toggle',
						'label'   => __( 'Send wp-login.php visitors to the branded pages', 'techvenza-core' ),
						'default' => true,
						'help'    => __( 'Add ?tv-core-login=1 to wp-login.php to reach the core screen if you ever need it.', 'techvenza-core' ),
					),
					'admin_lockdown'       => array(
						'type'    => 'toggle',
						'label'   => __( 'Keep readers out of wp-admin and hide their admin bar', 'techvenza-core' ),
						'default' => true,
					),
					'login_attempts'       => array(
						'type'    => 'number',
						'label'   => __( 'Failed logins allowed per 15 minutes', 'techvenza-core' ),
						'default' => 5,
					),
					'google_login_enabled' => array(
						'type'    => 'toggle',
						'label'   => __( 'Enable “Sign in with Google”', 'techvenza-core' ),
						'default' => false,
					),
					'google_client_id'     => array(
						'type'    => 'text',
						'label'   => __( 'Google OAuth client ID', 'techvenza-core' ),
						'default' => '',
					),
					'google_client_secret' => array(
						'type'    => 'secret',
						'label'   => __( 'Google OAuth client secret', 'techvenza-core' ),
						'default' => '',
						'test'    => 'google',
					),
				),
			),
			'analytics'  => array(
				'label'  => __( 'Analytics', 'techvenza-core' ),
				'fields' => array(
					'analytics_provider' => array(
						'type'    => 'select',
						'label'   => __( 'Analytics', 'techvenza-core' ),
						'default' => 'none',
						'options' => array(
							'none'      => __( 'None', 'techvenza-core' ),
							'ga4'       => 'Google Analytics 4',
							'plausible' => 'Plausible',
						),
					),
					'ga4_id'             => array(
						'type'    => 'text',
						'label'   => __( 'GA4 measurement ID', 'techvenza-core' ),
						'default' => '',
						'help'    => 'G-XXXXXXXXXX',
					),
					'plausible_domain'   => array(
						'type'    => 'text',
						'label'   => __( 'Plausible domain', 'techvenza-core' ),
						'default' => '',
					),
					'plausible_src'      => array(
						'type'    => 'url',
						'label'   => __( 'Plausible script URL', 'techvenza-core' ),
						'default' => 'https://plausible.io/js/script.js',
					),
					'gsc_verification'   => array(
						'type'    => 'text',
						'label'   => __( 'Google Search Console verification code', 'techvenza-core' ),
						'default' => '',
					),
				),
			),
			'seo'        => array(
				'label'  => __( 'SEO defaults', 'techvenza-core' ),
				'fields' => array(
					'seo_title_sep'       => array(
						'type'    => 'text',
						'label'   => __( 'Title separator', 'techvenza-core' ),
						'default' => '—',
					),
					'seo_description'     => array(
						'type'    => 'textarea',
						'label'   => __( 'Default meta description', 'techvenza-core' ),
						'default' => 'Independent, expert-led reporting on AI, software, business and Industry 4.0.',
					),
					'seo_default_image'   => array(
						'type'    => 'media',
						'label'   => __( 'Default social image', 'techvenza-core' ),
						'default' => 0,
					),
					'seo_org_name'        => array(
						'type'    => 'text',
						'label'   => __( 'Organisation name (structured data)', 'techvenza-core' ),
						'default' => 'Techvenza',
					),
					'seo_twitter'         => array(
						'type'    => 'text',
						'label'   => __( 'X / Twitter handle', 'techvenza-core' ),
						'default' => '',
						'help'    => '@techvenza',
					),
					'seo_noindex_tags'    => array(
						'type'    => 'toggle',
						'label'   => __( 'Noindex tag archives', 'techvenza-core' ),
						'default' => true,
					),
					'seo_noindex_authors' => array(
						'type'    => 'toggle',
						'label'   => __( 'Noindex author archives', 'techvenza-core' ),
						'default' => false,
					),
				),
			),
			'ai'         => array(
				'label'  => __( 'AI & answer engines', 'techvenza-core' ),
				'fields' => array(
					'llms_enabled'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Publish /llms.txt and /llms-full.txt (a curated site map for AI assistants)', 'techvenza-core' ),
						'default' => true,
					),
					'llms_intro'       => array(
						'type'    => 'textarea',
						'label'   => __( 'llms.txt introduction', 'techvenza-core' ),
						'default' => 'Techvenza publishes independent, expert-written analysis of artificial intelligence, software, cloud, cybersecurity, data, business leadership and Industry 4.0. Every article is reviewed by an editor; authors are practitioners named on their profile pages. Please cite articles by title and link.',
						'help'    => __( 'Plain text or Markdown, shown under the site description.', 'techvenza-core' ),
					),
					'markdown_enabled' => array(
						'type'    => 'toggle',
						'label'   => __( 'Offer a Markdown version of every article at /article-slug/md/', 'techvenza-core' ),
						'default' => true,
					),
					'ai_search_bots'   => array(
						'type'    => 'toggle',
						'label'   => __( 'Allow AI search assistants (ChatGPT search, Perplexity, Claude, DuckAssist…) to read and cite pages', 'techvenza-core' ),
						'default' => true,
					),
					'ai_training_bots' => array(
						'type'    => 'toggle',
						'label'   => __( 'Allow AI model training crawlers (GPTBot, ClaudeBot, Google-Extended, CCBot…)', 'techvenza-core' ),
						'default' => true,
						'help'    => __( 'Allowing training increases how often models know and mention the publication; switch off to opt out.', 'techvenza-core' ),
					),
					'indexnow_enabled' => array(
						'type'    => 'toggle',
						'label'   => __( 'Notify search engines instantly via IndexNow when content is published or updated (Bing, Copilot, Yandex, Seznam, Naver)', 'techvenza-core' ),
						'default' => true,
					),
				),
			),
			'links'      => array(
				'label'  => __( 'Links & partners', 'techvenza-core' ),
				'fields' => array(
					'external_new_tab' => array(
						'type'    => 'toggle',
						'label'   => __( 'Open external links in a new tab', 'techvenza-core' ),
						'default' => true,
					),
					'external_rel'     => array(
						'type'    => 'select',
						'label'   => __( 'Default for external links in articles', 'techvenza-core' ),
						'default' => '',
						'options' => array(
							''         => __( 'Follow (editorial links pass ranking signals)', 'techvenza-core' ),
							'nofollow' => __( 'Nofollow every external link', 'techvenza-core' ),
						),
						'help'    => __( 'Partner links use the type set on each partner; sponsored articles always use rel="sponsored".', 'techvenza-core' ),
					),
					'autolink_enabled' => array(
						'type'    => 'toggle',
						'label'   => __( 'Auto-link partner keywords in articles', 'techvenza-core' ),
						'default' => true,
					),
					'autolink_max'     => array(
						'type'    => 'number',
						'label'   => __( 'Maximum auto-links per article', 'techvenza-core' ),
						'default' => 3,
					),
				),
			),
			'privacy'    => array(
				'label'  => __( 'Privacy & Cookies', 'techvenza-core' ),
				'fields' => array(
					'consent_enabled' => array(
						'type'    => 'toggle',
						'label'   => __( 'Show consent banner', 'techvenza-core' ),
						'default' => true,
					),
					'consent_title'   => array(
						'type'    => 'text',
						'label'   => __( 'Banner title', 'techvenza-core' ),
						'default' => 'Your privacy choices',
					),
					'consent_text'    => array(
						'type'    => 'textarea',
						'label'   => __( 'Banner text', 'techvenza-core' ),
						'default' => 'We use essential storage to run this site. With your permission we also remember preferences and measure anonymous usage to improve our journalism.',
					),
					'company_name'    => array(
						'type'    => 'text',
						'label'   => __( 'Company name (footer & legal)', 'techvenza-core' ),
						'default' => '',
					),
					'company_address' => array(
						'type'    => 'textarea',
						'label'   => __( 'Company address', 'techvenza-core' ),
						'default' => '',
					),
					'legal_draft'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Show “draft — requires legal review” banner on legal pages', 'techvenza-core' ),
						'default' => true,
					),
				),
			),
			'editorial'  => array(
				'label'  => __( 'Editorial', 'techvenza-core' ),
				'fields' => array(
					'review_sla_days' => array(
						'type'    => 'number',
						'label'   => __( 'Target first response (working days)', 'techvenza-core' ),
						'default' => 5,
					),
					'checklist'       => array(
						'type'    => 'lines',
						'label'   => __( 'Review checklist items', 'techvenza-core' ),
						'default' => "Original work — not published elsewhere\nClaims are sourced and accurate\nAI assistance disclosed (if used)\nImages have rights and alt text\nLinks follow the link policy\nHeadings, excerpt and formatting meet guidelines\nConflicts of interest disclosed",
						'help'    => __( 'One item per line.', 'techvenza-core' ),
					),
					'min_words'       => array(
						'type'    => 'number',
						'label'   => __( 'Minimum words to submit', 'techvenza-core' ),
						'default' => 150,
					),
				),
			),
			'advanced'   => array(
				'label'  => __( 'Advanced', 'techvenza-core' ),
				'fields' => array(
					'delete_on_uninstall' => array(
						'type'    => 'toggle',
						'label'   => __( 'Delete all Techvenza data when the plugin is uninstalled', 'techvenza-core' ),
						'default' => false,
					),
				),
			),
		);

		foreach ( self::email_events() as $event => $tpl ) {
			$tabs['editorial']['fields'][ 'tpl_' . $event . '_subject' ] = array(
				'type'    => 'text',
				'label'   => $tpl['label'] . ' — ' . __( 'subject', 'techvenza-core' ),
				'default' => $tpl['subject'],
				'group'   => 'templates',
			);
			$tabs['editorial']['fields'][ 'tpl_' . $event . '_body' ]    = array(
				'type'    => 'textarea',
				'label'   => $tpl['label'] . ' — ' . __( 'body', 'techvenza-core' ),
				'default' => $tpl['body'],
				'group'   => 'templates',
			);
		}

		/**
		 * Filter the settings schema.
		 *
		 * @param array $tabs Tabs and fields.
		 */
		return apply_filters( 'techvenza/settings/tabs', $tabs );
	}

	/**
	 * Flat map of key → field definition.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		static $flat = null;
		if ( null === $flat ) {
			$flat = array();
			foreach ( self::tabs() as $tab ) {
				$flat += $tab['fields'];
			}
		}
		return $flat;
	}
}
