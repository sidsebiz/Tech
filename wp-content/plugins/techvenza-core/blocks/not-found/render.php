<?php
/**
 * 404: search, helpful links and popular stories.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_links = '';
foreach ( array(
	'explore'  => __( 'Explore', 'techvenza-core' ),
	'trending' => __( 'Trending', 'techvenza-core' ),
	'authors'  => __( 'Authors', 'techvenza-core' ),
	'contact'  => __( 'Contact', 'techvenza-core' ),
) as $tv_slug => $tv_label ) {
	$tv_links .= sprintf( '<a class="link" href="%s">%s</a>', esc_url( R::page_url( $tv_slug ) ), esc_html( $tv_label ) );
}
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block' ) );
printf(
	'<div %1$s><section class="err-hero"><div class="container narrow"><p class="err-code" aria-hidden="true">404</p><h1 class="page-title mt-5">%2$s</h1><p class="lead mt-4" style="margin-inline:auto">%3$s</p>
	<form class="nl-form mt-6" role="search" method="get" action="%4$s" style="max-width:520px;margin-inline:auto"><label class="sr-only" for="q404">%5$s</label><input class="input" id="q404" name="s" type="search" placeholder="%6$s"><button class="btn btn-primary" type="submit">%5$s</button></form>
	<div class="row mt-6" style="justify-content:center"><a class="btn btn-secondary" href="%4$s">%7$s</a>%8$s</div></div></section>
	<section class="section tight"><div class="container"><h2 class="h3 mb-5">%9$s</h2><div class="grid g-3">%10$s</div></div></section></div>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html( $attributes['title'] ),
	esc_html( $attributes['text'] ),
	esc_url( home_url( '/' ) ),
	esc_html__( 'Search', 'techvenza-core' ),
	esc_attr__( 'Search Techvenza', 'techvenza-core' ),
	esc_html__( 'Return home', 'techvenza-core' ),
	$tv_links, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html__( 'You might like', 'techvenza-core' ),
	R::cards( Query::trending( 3 ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
