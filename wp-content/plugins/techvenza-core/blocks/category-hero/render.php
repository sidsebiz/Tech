<?php
/**
 * Category hero: icon, name, intro, follow button, counts and subcategory chips.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_c = CategoryMeta::current();
if ( ! $tv_c ) {
	return;
}
$tv_authors = count( Query::authors( array( 'cat' => $tv_c['slug'] ) ) );
$tv_trail   = array( array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ), array( __( 'Categories', 'techvenza-core' ), R::page_url( 'explore' ) ) );
if ( $tv_c['parent'] ) {
	$tv_parent = CategoryMeta::get( (int) $tv_c['parent'] );
	if ( $tv_parent ) {
		$tv_trail[] = array( $tv_parent['name'], $tv_parent['url'] );
	}
}
$tv_trail[] = array( $tv_c['name'], '' );

$tv_subs   = '';
$tv_active = sanitize_title( \Techvenza\Core\Blocks\Params::get( 'sub' ) );
if ( $tv_c['sub'] ) {
	$tv_subs = sprintf( '<a class="filter-chip" href="%s" aria-pressed="%s">%s</a>', esc_url( $tv_c['url'] . '#latest' ), $tv_active ? 'false' : 'true', esc_html__( 'All', 'techvenza-core' ) );
	foreach ( $tv_c['sub'] as $tv_s ) {
		$tv_subs .= sprintf( '<a class="filter-chip" href="%s" aria-pressed="%s">%s</a>', esc_url( add_query_arg( 'sub', $tv_s['slug'], $tv_c['url'] ) . '#latest' ), $tv_active === $tv_s['slug'] ? 'true' : 'false', esc_html( $tv_s['name'] ) );
	}
	$tv_subs = '<div class="chip-scroll mt-6" role="group" aria-label="' . esc_attr__( 'Subcategories', 'techvenza-core' ) . '">' . $tv_subs . '</div>';
}
$tv_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'tv-block cat-hero',
		'style' => CategoryMeta::style( $tv_c ),
	)
);
printf(
	'<section %1$s><div class="container">%2$s<div class="row mt-6" style="gap:var(--s4);align-items:flex-start"><span class="feature-item" style="--cs:#fff;--ci:%3$s"><span class="ico" style="width:56px;height:56px">%4$s</span></span><div style="flex:1;min-width:260px"><span class="eyebrow">%5$s</span><h1 class="page-title mt-4">%6$s</h1><p class="lead">%7$s</p><div class="row mt-5"><button class="btn btn-primary" type="button" data-action="followTopic" data-slug="%8$s" aria-pressed="false" data-label-on="%9$s" data-label-off="%10$s">%11$s <span>%10$s</span></button><span class="muted" style="font-size:var(--t-meta)">%12$s · %13$s</span></div></div></div>%14$s</div></section>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	R::crumbs( $tv_trail ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_attr( $tv_c['ink'] ),
	R::icon( $tv_c['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html__( 'Category', 'techvenza-core' ),
	esc_html( $tv_c['name'] ),
	esc_html( $tv_c['intro'] ),
	esc_attr( $tv_c['slug'] ),
	esc_attr__( 'Following', 'techvenza-core' ),
	esc_attr__( 'Follow topic', 'techvenza-core' ),
	R::icon( 'plus' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	/* translators: %d: number of stories */
	esc_html( sprintf( _n( '%d story', '%d stories', $tv_c['count'], 'techvenza-core' ), $tv_c['count'] ) ),
	/* translators: %d: number of authors */
	esc_html( sprintf( _n( '%d author', '%d authors', $tv_authors, 'techvenza-core' ), $tv_authors ) ),
	$tv_subs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
