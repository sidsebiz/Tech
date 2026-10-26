<?php
/**
 * Article cover figure (featured image with caption/credit, or branded placeholder).
 *
 * @package Techvenza\Core
 * @var array    $attributes
 * @var WP_Block $block
 */

use Techvenza\Core\Support\Render as R;

$tv_post = get_post( $block->context['postId'] ?? null );
$tv_a    = $tv_post ? R::article( $tv_post ) : null;
if ( ! $tv_a ) {
	return;
}
$tv_caption = $tv_a['image'] ? wp_get_attachment_caption( $tv_a['image'] ) : '';
$tv_caption = $tv_caption ? $tv_caption : ( $tv_a['alt'] ? $tv_a['alt'] : '' );
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'container' ) );
printf(
	'<div %s style="max-width:calc(1240px + 2*var(--gutter))"><figure class="article-figure">%s%s</figure></div>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	R::media( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$tv_a,
		array(
			'eager' => true,
			'sizes' => '(max-width:1280px) 100vw, 1240px',
			'size'  => 'full',
		)
	),
	$tv_caption ? '<figcaption class="caption">' . wp_kses_post( $tv_caption ) . '</figcaption>' : ''
);
