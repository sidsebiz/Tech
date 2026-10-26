<?php
/**
 * Article header: breadcrumbs, labels, title, standfirst, byline/dates and reading tools.
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
$tv_au    = $tv_a['author'];
$tv_cat   = $tv_a['cat'];
$tv_trail = array( array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ) );
if ( $tv_cat ) {
	$tv_trail[] = array( $tv_cat['name'], $tv_cat['url'] );
}
$tv_trail[] = array( $tv_a['title'], '' );

$tv_labels = R::chip( $tv_cat );
if ( $tv_a['pick'] ) {
	$tv_labels .= '<span class="chip plain" style="--cs:#FFFBEB;--ci:#B45309">' . R::icon( 'star' ) . ' ' . esc_html__( "Editor's pick", 'techvenza-core' ) . '</span>';
}
if ( $tv_a['sponsored'] ) {
	$tv_sponsor = (string) get_post_meta( $tv_post->ID, 'tv_sponsor_name', true );
	/* translators: %s: sponsor name */
	$tv_labels .= '<span class="tv-sponsored">' . esc_html( $tv_sponsor ? sprintf( __( 'Sponsored by %s', 'techvenza-core' ), $tv_sponsor ) : __( 'Sponsored', 'techvenza-core' ) ) . '</span>';
}

$tv_dates = sprintf(
	'<span>%s <time datetime="%s">%s</time></span>',
	esc_html__( 'Published', 'techvenza-core' ),
	esc_attr( get_the_date( 'c', $tv_post ) ),
	esc_html( R::date( $tv_a['date'] ) )
);
if ( $tv_a['modified'] > $tv_a['date'] ) {
	$tv_dates .= sprintf( '<span class="dot"></span><span>%s <time datetime="%s">%s</time></span>', esc_html__( 'Updated', 'techvenza-core' ), esc_attr( get_the_modified_date( 'c', $tv_post ) ), esc_html( R::date( $tv_a['modified'] ) ) );
}
/* translators: %d: minutes */
$tv_dates .= '<span class="dot"></span><span>' . esc_html( sprintf( __( '%d min read', 'techvenza-core' ), $tv_a['minutes'] ) ) . '</span>';

$tv_tools = '';
if ( ! empty( $attributes['showTools'] ) ) {
	$tv_tools = sprintf(
		'<div class="article-tools" role="group" aria-label="%1$s">
			<button class="icon-btn" type="button" data-action="share" data-title="%2$s" data-url="%3$s" aria-label="%4$s">%5$s</button>
			<button class="icon-btn" type="button" data-action="copyLink" data-url="%3$s" aria-label="%6$s">%7$s</button>
			<button class="icon-btn" type="button" data-action="bookmark" data-id="%8$d" aria-pressed="false" aria-label="%9$s">%10$s</button>
			<span class="sep" aria-hidden="true"></span>
			<div class="font-ctrl" role="group" aria-label="%11$s"><button type="button" data-action="fontSize" data-dir="-1" aria-label="%12$s">A−</button><button type="button" data-action="fontSize" data-dir="1" aria-label="%13$s">A+</button></div>
			<button class="icon-btn" type="button" data-action="readingTheme" aria-pressed="false" aria-label="%14$s">%15$s</button>
		</div>',
		esc_attr__( 'Article tools', 'techvenza-core' ),
		esc_attr( $tv_a['title'] ),
		esc_url( $tv_a['url'] ),
		esc_attr__( 'Share article', 'techvenza-core' ),
		R::icon( 'share' ),
		esc_attr__( 'Copy link to article', 'techvenza-core' ),
		R::icon( 'link' ),
		(int) $tv_a['id'],
		esc_attr__( 'Save article', 'techvenza-core' ),
		R::icon( 'bookmark' ),
		esc_attr__( 'Text size', 'techvenza-core' ),
		esc_attr__( 'A− Decrease text size', 'techvenza-core' ),
		esc_attr__( 'A+ Increase text size', 'techvenza-core' ),
		esc_attr__( 'Dark reading theme', 'techvenza-core' ),
		R::icon( 'moon' )
	);
}

$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'article-head' ) );
printf(
	'<header %1$s><div class="container" style="max-width:calc(1000px + 2*var(--gutter))">%2$s<div class="row mt-6">%3$s</div><h1 id="artTitle">%4$s</h1>%5$s<div class="article-meta-bar"><div class="byline" style="font-size:var(--t-small)">%6$s<div>%7$s<div class="meta" style="margin-top:2px">%8$s</div></div></div>%9$s</div></div></header>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	R::crumbs( $tv_trail ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_labels, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html( $tv_a['title'] ),
	has_excerpt( $tv_post ) ? '<p class="lead">' . esc_html( $tv_a['excerpt'] ) . '</p>' : '',
	$tv_au ? R::avatar( $tv_au, 'md' ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_au ? '<a href="' . esc_url( $tv_au['url'] ) . '" rel="author"><strong>' . esc_html( $tv_au['name'] ) . '</strong></a>' : '',
	$tv_dates, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_tools // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
