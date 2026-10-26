<?php
/**
 * FAQ accordion (native <details>, FAQPage JSON-LD emitted by the SEO module).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

$tv_items = array_filter( (array) $attributes['items'], static fn( $i ) => ! empty( $i['q'] ) );
if ( ! $tv_items ) {
	return;
}
$tv_html = '';
foreach ( $tv_items as $tv_i ) {
	$tv_html .= sprintf( '<details><summary>%s %s</summary><div class="ans">%s</div></details>', esc_html( $tv_i['q'] ), R::icon( 'plus' ), wp_kses_post( wpautop( (string) ( $tv_i['a'] ?? '' ) ) ) );
}
if ( class_exists( '\Techvenza\Core\Seo\Seo' ) && method_exists( '\Techvenza\Core\Seo\Seo', 'add_faq' ) ) {
	\Techvenza\Core\Seo\Seo::add_faq( $tv_items );
}
$tv_inner = sprintf(
	'<div class="%s">%s<div class="faq">%s</div></div>',
	$attributes['narrow'] ? 'narrow' : '',
	$attributes['heading'] ? '<h2 class="h2 mb-6">' . esc_html( $attributes['heading'] ) . '</h2>' : '',
	$tv_html
);
echo R::section( $tv_inner, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
