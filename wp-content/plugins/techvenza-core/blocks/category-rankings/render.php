<?php
/**
 * Most-read stories per category.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_cols = '';
foreach ( (array) $attributes['categories'] as $tv_slug ) {
	$tv_c = CategoryMeta::get( (string) $tv_slug );
	if ( ! $tv_c ) {
		continue;
	}
	$tv_posts = Query::trending( (int) $attributes['count'], '30d', array( $tv_c['slug'] ), array(), false );
	if ( ! $tv_posts ) {
		continue;
	}
	$tv_items = '';
	foreach ( $tv_posts as $tv_p ) {
		$tv_items .= sprintf( '<li class="rank-item" style="grid-template-columns:36px 1fr"><h4 class="card-title" style="font-size:.95rem"><a href="%s"><span class="u-line">%s</span></a></h4></li>', esc_url( (string) get_permalink( $tv_p ) ), esc_html( get_the_title( $tv_p ) ) );
	}
	$tv_cols .= sprintf(
		'<div class="reveal"><h3 class="row mb-4" style="font-size:1rem"><span class="dot-c" style="--c:%s"></span><a href="%s">%s</a></h3><ol class="rank-list">%s</ol></div>',
		esc_attr( $tv_c['color'] ),
		esc_url( $tv_c['url'] ),
		esc_html( $tv_c['name'] ),
		$tv_items
	);
}
if ( ! $tv_cols ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'accent'  => 'var(--violet)',
	)
);
echo R::section( $tv_head . '<div class="grid g-4">' . $tv_cols . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
