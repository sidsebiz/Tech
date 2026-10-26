<?php
/**
 * Trending ranked list.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( ! Options::section_enabled( 'trending-list' ) ) {
	return;
}
$tv_posts = Query::trending( (int) $attributes['count'], (string) $attributes['period'] );
if ( ! $tv_posts ) {
	return;
}
$tv_cols  = max( 1, min( 2, (int) $attributes['columns'] ) );
$tv_per   = (int) ceil( count( $tv_posts ) / $tv_cols );
$tv_lists = '';
foreach ( array_chunk( $tv_posts, $tv_per ) as $tv_i => $tv_chunk ) {
	$tv_items = '';
	foreach ( $tv_chunk as $tv_p ) {
		$tv_items .= '<li class="rank-item">' . R::card( $tv_p, 'text' ) . '</li>';
	}
	$tv_lists .= sprintf( '<ol class="rank-list" start="%1$d" style="counter-reset:rank %2$d">%3$s</ol>', $tv_i * $tv_per + 1, $tv_i * $tv_per, $tv_items );
}
$tv_head = R::sec_head(
	array(
		'eyebrow'   => $attributes['eyebrow'],
		'heading'   => $attributes['heading'],
		'desc'      => $attributes['description'],
		'linkLabel' => $attributes['linkLabel'],
		'linkUrl'   => $attributes['linkUrl'],
		'accent'    => 'var(--rose)',
	)
);
echo R::section( $tv_head . '<div class="grid g-' . $tv_cols . '" style="gap:0 var(--s8)">' . $tv_lists . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
