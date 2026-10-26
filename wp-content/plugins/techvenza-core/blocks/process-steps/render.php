<?php
/**
 * Numbered process steps.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

$tv_items = '';
foreach ( (array) $attributes['steps'] as $tv_s ) {
	$tv_items .= sprintf( '<li class="reveal"><h3>%s</h3><p>%s</p></li>', esc_html( $tv_s['title'] ?? '' ), esc_html( $tv_s['text'] ?? '' ) );
}
if ( ! $tv_items ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'accent'  => 'var(--coral)',
	)
);
echo R::section( $tv_head . '<ol class="process">' . $tv_items . '</ol>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
