<?php
/**
 * Newsletter sign-up page: topics, frequency, consent, double opt-in; also shows confirmation,
 * unsubscribe and error states from email links.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Newsletter\Newsletter;
use Techvenza\Core\Newsletter\Subscribers;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

$tv_topics = Subscribers::topics();
$tv_pick   = Options::enabled( 'newsletter_topics' );
$tv_user   = wp_get_current_user();
$tv_mine   = $tv_user->exists() ? array_map( 'strval', (array) get_user_meta( $tv_user->ID, 'tv_topics', true ) ) : array();
$tv_mine   = $tv_mine ? $tv_mine : array_values( array_intersect( array( 'ai', 'software' ), array_keys( $tv_topics ) ) );

$tv_topics_html = $tv_pick ? sprintf(
	'<h2 class="h3">%1$s</h2><div class="field" data-field="topics" data-required-group>%2$s<span class="error" role="alert">%3$s <span>%4$s</span></span></div>',
	esc_html__( 'Choose your topics', 'techvenza-core' ),
	R::checks( 'topics', array_slice( $tv_topics, 0, 10, true ), $tv_mine, array( 'label' => __( 'Topics', 'techvenza-core' ) ) ),
	R::icon( 'alert' ),
	esc_html__( 'Select at least one topic.', 'techvenza-core' )
) : '';

$tv_form = sprintf(
	'<div class="form-note warn" data-form-error hidden role="alert">%1$s<span></span></div><form class="form" method="post" action="%2$s" data-tv-form data-endpoint="newsletter/subscribe" novalidate><input type="hidden" name="action" value="techvenza_subscribe"><input type="hidden" name="source" value="newsletter-page"><input type="hidden" name="full" value="1">%3$s%4$s
	<fieldset style="border:0;padding:0;margin:0"><legend class="label mb-4">%5$s</legend><div class="row" style="gap:var(--s5)"><label class="check"><input type="radio" name="freq" value="weekly" checked> %6$s</label><label class="check"><input type="radio" name="freq" value="monthly"> %7$s</label></div></fieldset>
	%8$s<div class="field" data-field="consent" data-required-group><label class="check"><input type="checkbox" name="consent" value="1" required> <span>%9$s</span></label><span class="error" role="alert">%1$s <span>%10$s</span></span></div>%11$s
	<button class="btn btn-primary btn-lg btn-block" type="submit">%12$s</button><p class="nl-fine">%13$s</p></form>',
	R::icon( 'alert' ),
	esc_url( admin_url( 'admin-post.php' ) ),
	Captcha::honeypot(),
	$tv_topics_html,
	esc_html__( 'Frequency', 'techvenza-core' ),
	esc_html__( 'Weekly brief', 'techvenza-core' ),
	esc_html__( 'Monthly digest', 'techvenza-core' ),
	R::field(
		array(
			'id'           => 'nlEmail',
			'name'         => 'email',
			'label'        => __( 'Email address', 'techvenza-core' ),
			'type'         => 'email',
			'required'     => true,
			'autocomplete' => 'email',
			'value'        => $tv_user->exists() ? $tv_user->user_email : '',
			'error'        => __( 'Enter a valid email address.', 'techvenza-core' ),
		)
	),
	esc_html__( 'I agree to receive the selected newsletter(s) and understand I can unsubscribe at any time.', 'techvenza-core' ),
	esc_html__( 'Consent is required to subscribe.', 'techvenza-core' ),
	Captcha::widget( 'newsletter' ),
	esc_html__( 'Subscribe', 'techvenza-core' ),
	sprintf(
		/* translators: %s: privacy policy link */
		esc_html__( 'We use your email only to send the newsletter. Every issue contains an unsubscribe link. Read our %s.', 'techvenza-core' ),
		'<a class="link" href="' . esc_url( R::page_url( 'privacy-policy' ) ) . '">' . esc_html__( 'privacy policy', 'techvenza-core' ) . '</a>'
	)
);

$tv_points = '';
foreach ( array( __( 'Curated by editors, not algorithms', 'techvenza-core' ), __( 'Pick your topics; skip the rest', 'techvenza-core' ), __( 'Unsubscribe with one click, any time', 'techvenza-core' ) ) as $tv_point ) {
	$tv_points .= '<li>' . esc_html( $tv_point ) . '</li>';
}

printf(
	'<section %1$s><div class="container"><div class="hero-inner" style="align-items:start"><div>%2$s<span class="eyebrow" style="margin-top:var(--s6);--accent:var(--blue)">%3$s</span><h1 class="display mt-5" style="font-size:clamp(2.5rem,1.5rem + 3.5vw,4.5rem)">%4$s</h1><p class="lead">%5$s</p><ul class="prose mt-6" style="--fs:1rem;padding-left:1.2em">%6$s</ul>%7$s</div><div class="panel" style="padding:clamp(20px,3vw,36px)" data-form-box>%8$s</div></div></div></section>',
	get_block_wrapper_attributes( array( 'class' => 'tv-block hero' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	R::crumbs( array( array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ), array( get_the_title() ? get_the_title() : __( 'Newsletter', 'techvenza-core' ), '' ) ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html( (string) $attributes['eyebrow'] ),
	esc_html( (string) $attributes['title'] ),
	esc_html( (string) $attributes['lead'] ),
	$tv_points, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	Newsletter::page_notice(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_form // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
