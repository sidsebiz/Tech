<?php
/**
 * Category featured story + trending-in-category panel.
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
if ( ! $tv_c['count'] ) {
	$tv_html = R::section(
		R::empty_state(
			'file',
			__( 'Stories are on the way', 'techvenza-core' ),
			/* translators: %s: category */
			sprintf( __( 'Our editors are commissioning %s coverage. Follow the topic to see new stories first.', 'techvenza-core' ), $tv_c['name'] ),
			sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( R::page_url( 'explore' ) ), esc_html__( 'Explore other topics', 'techvenza-core' ) )
		),
		$attributes
	);
	echo $tv_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by R::section().
	return;
}
$tv_feat = Query::flagged( 'tv_is_featured', 1, array(), array( $tv_c['slug'] ) );
if ( ! $tv_feat ) {
	$tv_feat = Query::flagged( 'tv_is_editors_pick', 1, array(), array( $tv_c['slug'] ) );
}
if ( ! $tv_feat ) {
	$tv_feat = Query::latest( 1, array(), array( $tv_c['slug'] ) );
}
$tv_lead  = $tv_feat[0] ?? null;
$tv_trend = Query::trending( max( 1, (int) $attributes['count'] ), '30d', array( $tv_c['slug'] ), array(), false );
$tv_items = implode( '', array_map( static fn( $p ) => '<li class="rank-item">' . R::card( $p, 'text' ) . '</li>', $tv_trend ) );
$tv_inner = sprintf(
	'<div class="feature-grid"><div>%s</div><div class="panel"><h2 class="panel-title">%s</h2><ol class="rank-list">%s</ol></div></div>',
	$tv_lead ? R::card( $tv_lead, 'lg', array( 'eager' => true ) ) : '',
	/* translators: %s: category short name */
	esc_html( sprintf( __( 'Trending in %s', 'techvenza-core' ), $tv_c['short'] ) ),
	$tv_items
);
echo R::section( $tv_inner, array_merge( array( 'spacing' => 'tight' ), $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
