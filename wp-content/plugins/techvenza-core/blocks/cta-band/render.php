<?php
/**
 * CTA band with steps.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'cta-band' ) ) {
	return;
}
$tv_a       = $attributes;
$tv_buttons = '';
if ( $tv_a['primaryLabel'] ) {
	$tv_buttons .= sprintf( '<a class="btn btn-primary btn-lg" href="%s">%s</a>', esc_url( R::url( $tv_a['primaryUrl'] ) ), esc_html( $tv_a['primaryLabel'] ) );
}
if ( $tv_a['secondaryLabel'] ) {
	$tv_buttons .= sprintf( '<a class="btn btn-secondary btn-lg" href="%s">%s</a>', esc_url( R::url( $tv_a['secondaryUrl'] ) ), esc_html( $tv_a['secondaryLabel'] ) );
}
if ( 'center' === $tv_a['layout'] ) {
	$tv_inner = sprintf(
		'<div class="cta-band center reveal"><h2 class="h2">%s</h2><p class="mt-4" style="margin-inline:auto;max-width:48ch">%s</p><div class="hero-actions" style="justify-content:center">%s</div></div>',
		esc_html( $tv_a['heading'] ),
		esc_html( $tv_a['text'] ),
		$tv_buttons
	);
} else {
	$tv_steps = '';
	foreach ( (array) $tv_a['steps'] as $tv_s ) {
		$tv_steps .= '<li>' . esc_html( is_array( $tv_s ) ? ( $tv_s['text'] ?? '' ) : (string) $tv_s ) . '</li>';
	}
	$tv_inner = sprintf(
		'<div class="cta-band reveal"><div class="cta-grid"><div>%s<h2 class="h2 mt-4" style="max-width:18ch">%s</h2><p class="mt-4" style="max-width:48ch">%s</p><div class="hero-actions">%s</div></div>%s</div></div>',
		$tv_a['eyebrow'] ? '<span class="eyebrow" style="--accent:var(--coral)">' . esc_html( $tv_a['eyebrow'] ) . '</span>' : '',
		esc_html( $tv_a['heading'] ),
		esc_html( $tv_a['text'] ),
		$tv_buttons,
		$tv_steps ? '<ol class="steps-mini">' . $tv_steps . '</ol>' : ''
	);
}
echo R::section( $tv_inner, $tv_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
