<?php
/**
 * Contact form with inquiry routing and side panels.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Forms\Forms;
use Techvenza\Core\Support\Captcha;
use Techvenza\Core\Support\Render as R;

$tv_rt   = Forms::round_trip( 'contact' );
$tv_form = $tv_rt['success'];
if ( ! $tv_form ) {
	$tv_form = $tv_rt['note'] . Forms::mark_errors(
		sprintf(
			'<form class="form" method="post" action="%1$s" data-tv-form data-endpoint="forms/contact" novalidate><input type="hidden" name="action" value="techvenza_contact">%2$s%3$s<div class="form-grid">%4$s%5$s</div>%6$s%7$s<div class="field" data-field="consent" data-required-group><label class="check"><input type="checkbox" name="consent" value="1" required%8$s> <span>%9$s</span></label><span class="error" role="alert">%10$s <span>%11$s</span></span></div>%12$s<div class="row"><button class="btn btn-primary btn-lg" type="submit">%13$s</button></div></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			Captcha::honeypot(),
			R::field(
				array(
					'id'       => 'ctType',
					'name'     => 'type',
					'label'    => __( 'Inquiry type', 'techvenza-core' ),
					'type'     => 'select',
					'required' => true,
					'value'    => (string) Forms::value( 'type' ),
					'options'  => array( '' => __( 'Select a topic', 'techvenza-core' ) ) + Forms::types(),
					'error'    => __( 'Choose an inquiry type.', 'techvenza-core' ),
				)
			),
			R::field(
				array(
					'id'           => 'ctName',
					'name'         => 'name',
					'label'        => __( 'Name', 'techvenza-core' ),
					'required'     => true,
					'autocomplete' => 'name',
					'value'        => (string) Forms::value( 'name' ),
				)
			),
			R::field(
				array(
					'id'           => 'ctEmail',
					'name'         => 'email',
					'label'        => __( 'Email', 'techvenza-core' ),
					'type'         => 'email',
					'required'     => true,
					'autocomplete' => 'email',
					'value'        => (string) Forms::value( 'email' ),
					'error'        => __( 'Enter a valid email address.', 'techvenza-core' ),
				)
			),
			R::field(
				array(
					'id'       => 'ctSubject',
					'name'     => 'subject',
					'label'    => __( 'Subject', 'techvenza-core' ),
					'required' => true,
					'value'    => (string) Forms::value( 'subject' ),
				)
			),
			str_replace(
				'<textarea ',
				'<textarea data-min="20" ',
				R::field(
					array(
						'id'       => 'ctMsg',
						'name'     => 'message',
						'label'    => __( 'Message', 'techvenza-core' ),
						'type'     => 'textarea',
						'required' => true,
						'max'      => 2000,
						'rows'     => 6,
						'value'    => (string) Forms::value( 'message' ),
						'error'    => __( 'Write a message between 20 and 2,000 characters.', 'techvenza-core' ),
					)
				)
			),
			Forms::value( 'consent' ) ? ' checked' : '',
			sprintf(
				/* translators: %s: privacy policy link */
				esc_html__( 'I agree that Techvenza may use these details to respond to my inquiry, as described in the %s.', 'techvenza-core' ),
				'<a class="link" href="' . esc_url( R::page_url( 'privacy-policy' ) ) . '">' . esc_html__( 'privacy policy', 'techvenza-core' ) . '</a>'
			),
			R::icon( 'alert' ),
			esc_html__( 'Please confirm so we can respond.', 'techvenza-core' ),
			Captcha::widget( 'contact' ),
			esc_html__( 'Send message', 'techvenza-core' )
		)
	);
}

$tv_aside = '';
if ( ! empty( $attributes['showAside'] ) ) {
	foreach (
		array(
			array( 'pen', __( 'Editorial inquiries', 'techvenza-core' ), __( 'Story feedback, tips and correction requests go directly to the standards editor.', 'techvenza-core' ) ),
			array( 'briefcase', __( 'Partnerships', 'techvenza-core' ), __( 'Events, research collaborations and syndication. We do not sell editorial coverage.', 'techvenza-core' ) ),
			array( 'users', __( 'Contributor support', 'techvenza-core' ), __( 'Help with applications, the author portal or a submission in review.', 'techvenza-core' ) ),
		) as [ $tv_icon, $tv_title, $tv_text ]
	) {
		$tv_aside .= sprintf( '<div class="panel"><div class="feature-item"><span class="ico">%s</span><h2 class="h3" style="font-size:1rem">%s</h2><p>%s</p></div></div>', R::icon( $tv_icon ), esc_html( $tv_title ), esc_html( $tv_text ) );
	}
	$tv_aside = '<aside class="grid" style="gap:var(--s4);align-content:start">' . $tv_aside . '<div class="form-note">' . R::icon( 'lock' ) . '<span>' . esc_html__( 'We use your details only to reply. They are not added to marketing lists.', 'techvenza-core' ) . '</span></div></aside>';
}

$tv_inner = sprintf(
	'<div class="%1$s"><div class="panel" style="padding:clamp(20px,3vw,40px)" data-form-box>%2$s</div>%3$s</div>',
	$tv_aside ? 'layout-sidebar wide-aside' : '',
	$tv_form,
	$tv_aside
);
echo R::section( $tv_inner, $attributes + array( 'spacing' => 'tight' ), 'tv-pt-0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
