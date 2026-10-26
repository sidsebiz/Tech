<?php
/**
 * Author application form.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Forms\Forms;
use Techvenza\Core\Newsletter\Subscribers;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

$tv_rt   = Forms::round_trip( 'apply' );
$tv_form = $tv_rt['success'];
if ( ! $tv_form ) {
	$tv_v     = static fn( string $k ): string => (string) Forms::value( $k );
	$tv_req   = '<span aria-hidden="true" style="color:var(--rose)">*</span>';
	$tv_check = static fn( string $name, string $text, string $error ): string => sprintf(
		'<div class="field" data-field="%1$s" data-required-group><label class="check"><input type="checkbox" name="%1$s" value="1" required%2$s> <span>%3$s %4$s</span></label><span class="error" role="alert">%5$s <span>%6$s</span></span></div>',
		esc_attr( $name ),
		Forms::value( $name ) ? ' checked' : '',
		$text,
		$tv_req,
		R::icon( 'alert' ),
		esc_html( $error )
	);
	$tv_form  = $tv_rt['note'] . Forms::mark_errors(
		sprintf(
			'<form class="form" method="post" action="%1$s" data-tv-form data-endpoint="forms/apply" novalidate><input type="hidden" name="action" value="techvenza_apply">%2$s
			<fieldset class="form" style="border:0;padding:0;margin:0"><legend class="h3 mb-4">%3$s</legend><div class="form-grid">%4$s%5$s%6$s%7$s</div></fieldset>
			<fieldset class="form" style="border:0;padding:0;margin:0"><legend class="h3 mb-4">%8$s</legend>%9$s<div class="field" data-field="cats" data-required-group><span class="label" id="apCatsL">%10$s %11$s</span>%12$s<span class="error" role="alert">%13$s <span>%14$s</span></span></div>%15$s</fieldset>
			<fieldset class="form" style="border:0;padding:0;margin:0"><legend class="h3 mb-4">%16$s</legend><div class="form-grid">%17$s%18$s</div>%19$s</fieldset>
			%20$s%21$s%22$s<div class="row"><button class="btn btn-primary btn-lg" type="submit">%23$s</button><span class="hint">%24$s</span></div></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			Captcha::honeypot(),
			esc_html__( 'About you', 'techvenza-core' ),
			R::field( array( 'id' => 'apName', 'name' => 'name', 'label' => __( 'Full name', 'techvenza-core' ), 'required' => true, 'autocomplete' => 'name', 'value' => $tv_v( 'name' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			R::field( array( 'id' => 'apEmail', 'name' => 'email', 'label' => __( 'Email', 'techvenza-core' ), 'type' => 'email', 'required' => true, 'autocomplete' => 'email', 'value' => $tv_v( 'email' ), 'error' => __( 'Enter a valid email address.', 'techvenza-core' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			R::field( array( 'id' => 'apTitle', 'name' => 'title', 'label' => __( 'Professional title', 'techvenza-core' ), 'required' => true, 'placeholder' => __( 'e.g. Staff Engineer', 'techvenza-core' ), 'value' => $tv_v( 'title' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			R::field( array( 'id' => 'apOrg', 'name' => 'org', 'label' => __( 'Organisation', 'techvenza-core' ), 'opt' => true, 'autocomplete' => 'organization', 'value' => $tv_v( 'org' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			esc_html__( 'Your expertise', 'techvenza-core' ),
			R::field( array( 'id' => 'apExp', 'name' => 'expertise', 'label' => __( 'Areas of expertise', 'techvenza-core' ), 'required' => true, 'hint' => __( 'Comma-separated, e.g. Platform engineering, Kubernetes, FinOps', 'techvenza-core' ), 'value' => $tv_v( 'expertise' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			esc_html__( 'Preferred categories', 'techvenza-core' ),
			$tv_req,
			str_replace( 'role="group"', 'role="group" aria-labelledby="apCatsL"', R::checks( 'cats', Subscribers::topics(), array_map( 'strval', (array) Forms::value( 'cats' ) ), array( 'box' => true ) ) ),
			R::icon( 'alert' ),
			esc_html__( 'Choose at least one category.', 'techvenza-core' ),
			str_replace( '<textarea ', '<textarea data-min="80" ', R::field( array( 'id' => 'apBio', 'name' => 'bio', 'label' => __( 'Short biography', 'techvenza-core' ), 'type' => 'textarea', 'required' => true, 'max' => 600, 'hint' => __( '2–4 sentences, written in the third person. 600 characters max.', 'techvenza-core' ), 'value' => $tv_v( 'bio' ), 'error' => __( 'Add a biography between 80 and 600 characters.', 'techvenza-core' ) ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			esc_html__( 'Your work', 'techvenza-core' ),
			R::field( array( 'id' => 'apLink', 'name' => 'link', 'label' => __( 'LinkedIn or website', 'techvenza-core' ), 'type' => 'url', 'required' => true, 'placeholder' => 'https://', 'value' => $tv_v( 'link' ), 'error' => __( 'Enter a full URL starting with https://', 'techvenza-core' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			str_replace( 'type="text"', 'type="text" data-urls', R::field( array( 'id' => 'apSamples', 'name' => 'samples', 'label' => __( 'Writing samples', 'techvenza-core' ), 'required' => true, 'placeholder' => 'https://…, https://…', 'hint' => __( 'One to three links to published writing.', 'techvenza-core' ), 'value' => $tv_v( 'samples' ), 'error' => __( 'Add at least one valid link.', 'techvenza-core' ) ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			str_replace( '<textarea ', '<textarea data-min="40" ', R::field( array( 'id' => 'apReason', 'name' => 'reason', 'label' => __( 'Why do you want to write for Techvenza?', 'techvenza-core' ), 'type' => 'textarea', 'required' => true, 'max' => 800, 'rows' => 4, 'value' => $tv_v( 'reason' ), 'error' => __( 'Tell us a little more (40–800 characters).', 'techvenza-core' ) ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			$tv_check(
				'agree',
				sprintf(
					/* translators: %s: guidelines link */
					esc_html__( 'I have read and agree to follow the %s, including originality and AI-disclosure requirements.', 'techvenza-core' ),
					'<a class="link" href="' . esc_url( R::page_url( 'editorial-guidelines' ) ) . '" target="_blank">' . esc_html__( 'editorial guidelines', 'techvenza-core' ) . '</a>'
				),
				__( 'You must agree to the editorial guidelines.', 'techvenza-core' )
			),
			$tv_check(
				'privacy',
				sprintf(
					/* translators: %s: privacy link */
					esc_html__( 'I understand how my application data will be used, as described in the %s.', 'techvenza-core' ),
					'<a class="link" href="' . esc_url( R::page_url( 'privacy-policy' ) ) . '" target="_blank">' . esc_html__( 'privacy policy', 'techvenza-core' ) . '</a>'
				),
				__( 'Please acknowledge the privacy notice.', 'techvenza-core' )
			),
			Captcha::widget( 'apply' ),
			esc_html__( 'Submit application', 'techvenza-core' ),
			esc_html__( 'Publication of any article remains subject to editorial approval.', 'techvenza-core' )
		)
	);
	if ( ! empty( $attributes['intro'] ) ) {
		$tv_form = '<p class="muted mb-5">' . esc_html( (string) $attributes['intro'] ) . '</p>' . $tv_form;
	}
}

echo R::section( '<div class="narrow"><div class="panel" style="padding:clamp(20px,3vw,40px)" data-form-box>' . $tv_form . '</div></div>', $attributes + array( 'spacing' => 'tight' ), 'tv-pt-0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
