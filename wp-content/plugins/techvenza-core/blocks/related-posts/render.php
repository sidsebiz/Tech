<?php
/**
 * Related stories (same category or shared tags, newest first).
 *
 * @package Techvenza\Core
 * @var array    $attributes
 * @var WP_Block $block
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_post = get_post( $block->context['postId'] ?? null );
if ( ! $tv_post ) {
	return;
}
$tv_posts = Query::related( $tv_post->ID, max( 1, (int) $attributes['count'] ) );
if ( ! $tv_posts ) {
	return;
}
$tv_id   = wp_unique_id( 'rel-' );
$tv_html = R::section(
	sprintf( '<h2 class="h2 mb-6" id="%s">%s</h2><div class="grid g-3">%s</div>', esc_attr( $tv_id ), esc_html( $attributes['heading'] ), R::cards( $tv_posts ) ),
	array_merge( array( 'spacing' => 'tight' ), $attributes )
);
echo $tv_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by R::section().
