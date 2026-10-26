<?php
/**
 * Editor's note (pull quote with attribution).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

if ( '' === trim( (string) $attributes['text'] ) ) {
	return;
}
$tv_inner = sprintf(
	'<p class="editor-note reveal">%s%s</p>',
	esc_html( $attributes['text'] ),
	$attributes['attribution'] ? ' <span class="caption" style="display:block;font-family:var(--font-sans);margin-top:8px">' . esc_html( $attributes['attribution'] ) . '</span>' : ''
);
echo R::section( $tv_inner, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
