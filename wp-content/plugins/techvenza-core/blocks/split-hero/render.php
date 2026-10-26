<?php
/**
 * Split hero (Become an Author): copy and CTAs beside a numbered steps panel.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

$tv_a     = $attributes;
$tv_steps = '';
foreach ( (array) $tv_a['steps'] as $tv_s ) {
	$tv_steps .= '<li>' . esc_html( is_array( $tv_s ) ? ( $tv_s['text'] ?? '' ) : (string) $tv_s ) . '</li>';
}
$tv_actions = '';
if ( $tv_a['primaryLabel'] ) {
	$tv_actions .= sprintf( '<a class="btn btn-primary btn-lg" href="%s">%s %s</a>', esc_url( R::url( $tv_a['primaryUrl'] ) ), esc_html( $tv_a['primaryLabel'] ), R::icon( 'arrowRight' ) );
}
if ( $tv_a['secondaryLabel'] ) {
	$tv_actions .= sprintf( '<a class="btn btn-secondary btn-lg" href="%s">%s</a>', esc_url( R::url( $tv_a['secondaryUrl'] ) ), esc_html( $tv_a['secondaryLabel'] ) );
}
$tv_post    = get_post();
$tv_crumbs  = R::crumbs(
	array(
		array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ),
		array( $tv_post ? get_the_title( $tv_post ) : $tv_a['title'], '' ),
	)
);
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block hero' ) );
printf(
	'<section %1$s style="padding-bottom:var(--s7)"><div class="container"><div class="hero-inner" style="align-items:center"><div>%2$s%3$s<h1 class="display mt-5">%4$s</h1>%5$s<div class="hero-actions">%6$s</div>%7$s</div>%8$s</div></div></section>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_crumbs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_a['eyebrow'] ? '<span class="eyebrow" style="margin-top:var(--s6);--accent:var(--coral)">' . esc_html( $tv_a['eyebrow'] ) . '</span>' : '',
	esc_html( $tv_a['title'] ),
	$tv_a['lead'] ? '<p class="lead">' . esc_html( $tv_a['lead'] ) . '</p>' : '',
	$tv_actions, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_a['caption'] ? '<p class="caption mt-5">' . esc_html( $tv_a['caption'] ) . '</p>' : '',
	$tv_steps ? '<ol class="steps-mini cta-band" style="padding:var(--s6)">' . $tv_steps . '</ol>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
