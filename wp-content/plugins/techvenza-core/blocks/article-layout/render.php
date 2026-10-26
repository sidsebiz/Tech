<?php
/**
 * Three-column article layout: sticky table of contents, body (inner blocks), trending rail.
 *
 * @package Techvenza\Core
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_post = get_post( $block->context['postId'] ?? null );
$tv_html = (string) $content;
$tv_tail = '';
if ( preg_match( '/<div[^>]*class="[^"]*\barticle-end\b/', $tv_html, $tv_m, PREG_OFFSET_CAPTURE ) ) {
	$tv_tail = substr( $tv_html, (int) $tv_m[0][1] );
	$tv_html = substr( $tv_html, 0, (int) $tv_m[0][1] );
}
$tv_heads = R::headings( $tv_html );
$tv_html .= $tv_tail;
$tv_toc   = '';
$tv_mtoc  = '';
if ( ! empty( $attributes['showToc'] ) && count( $tv_heads ) >= 2 ) {
	$tv_items = R::toc_items( $tv_heads );
	$tv_toc   = sprintf( '<nav class="toc" aria-label="%1$s" data-toc><h2>%2$s</h2><ol>%3$s</ol></nav>', esc_attr__( 'Table of contents', 'techvenza-core' ), esc_html__( 'On this page', 'techvenza-core' ), $tv_items );
	$tv_mtoc  = sprintf( '<div class="toc-mobile"><details><summary>%s</summary><ol>%s</ol></details></div>', esc_html__( 'On this page', 'techvenza-core' ), $tv_items );
}
$tv_rail = '';
if ( ! empty( $attributes['showRail'] ) ) {
	$tv_trend = Query::trending( 3, '7d', array(), $tv_post ? array( $tv_post->ID ) : array() );
	if ( $tv_trend ) {
		$tv_rail = sprintf( '<div class="aside-rail"><div class="panel"><h2 class="panel-title">%s</h2><div class="grid" style="gap:var(--s4)">%s</div></div></div>', esc_html__( 'Trending', 'techvenza-core' ), R::cards( $tv_trend, 'row' ) );
	}
}
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'container' ) );
printf(
	'<div %1$s style="max-width:calc(1240px + 2*var(--gutter))"><div class="article-layout"><aside class="toc-col">%2$s</aside><div class="tv-article-main">%3$s%4$s</div><aside class="aside-col" aria-label="%5$s">%6$s</aside></div></div>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_toc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_mtoc, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_attr__( 'More stories', 'techvenza-core' ),
	$tv_rail // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
