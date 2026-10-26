<?php
/**
 * Feature items (icon + title + text) — community, benefits, principles, formats.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'feature-items' ) ) {
	return;
}
$tv_items = '';
$tv_panel = 'panel' === $attributes['style'];
foreach ( (array) $attributes['items'] as $tv_it ) {
	$tv_style = sprintf( '--cs:%s;--ci:%s', sanitize_hex_color( (string) ( $tv_it['soft'] ?? '' ) ) ? $tv_it['soft'] : 'var(--soft-blue)', sanitize_hex_color( (string) ( $tv_it['ink'] ?? '' ) ) ? $tv_it['ink'] : 'var(--blue)' );
	if ( $tv_panel ) {
		$tv_items .= sprintf(
			'<div class="panel reveal" style="%s">%s<h3 class="h3 mt-4">%s</h3><p class="mt-4" style="font-size:var(--t-small)">%s</p></div>',
			esc_attr( $tv_style ),
			! empty( $tv_it['label'] ) ? '<span class="level" style="color:var(--ci)">' . esc_html( $tv_it['label'] ) . '</span>' : '',
			esc_html( $tv_it['title'] ?? '' ),
			esc_html( $tv_it['text'] ?? '' )
		);
	} else {
		$tv_items .= sprintf(
			'<div class="feature-item reveal" style="%s"><span class="ico">%s</span><h3>%s</h3><p>%s</p></div>',
			esc_attr( $tv_style ),
			R::icon( (string) ( $tv_it['icon'] ?? 'checkCircle' ) ),
			esc_html( $tv_it['title'] ?? '' ),
			esc_html( $tv_it['text'] ?? '' )
		);
	}
}
$tv_cols   = max( 2, min( 4, (int) $attributes['columns'] ) );
$tv_center = 'center' === $attributes['align'];
$tv_head   = '';
if ( $attributes['heading'] || $attributes['eyebrow'] ) {
	$tv_head = $tv_center
		? sprintf(
			'<div class="center reveal" style="max-width:640px;margin:0 auto var(--s7)">%s<h2 class="h2 mt-4">%s</h2>%s</div>',
			$attributes['eyebrow'] ? '<span class="eyebrow" style="--accent:var(--blue)">' . esc_html( $attributes['eyebrow'] ) . '</span>' : '',
			esc_html( $attributes['heading'] ),
			$attributes['lead'] ? '<p class="lead mt-4" style="margin-inline:auto">' . esc_html( $attributes['lead'] ) . '</p>' : ''
		)
		: R::sec_head(
			array(
				'eyebrow' => $attributes['eyebrow'],
				'heading' => $attributes['heading'],
				'desc'    => $attributes['lead'],
			)
		);
}
$tv_class = 3 === $tv_cols ? 'community' : 'grid g-' . $tv_cols;
echo R::section( $tv_head . '<div class="' . esc_attr( $tv_class ) . '">' . $tv_items . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
