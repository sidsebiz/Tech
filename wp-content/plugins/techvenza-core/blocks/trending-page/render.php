<?php
/**
 * Trending page: period switcher, top story, most-read panel and ranked list with change vs previous period.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_periods = array(
	'24h' => array( __( '24 hours', 'techvenza-core' ), 1 ),
	'7d'  => array( __( '7 days', 'techvenza-core' ), 7 ),
	'30d' => array( __( '30 days', 'techvenza-core' ), 30 ),
);
$tv_period  = Params::get( 'period', '7d' );
$tv_period  = isset( $tv_periods[ $tv_period ] ) ? $tv_period : '7d';
$tv_count   = max( 5, (int) $attributes['count'] );
$tv_ranked  = Query::trending( $tv_count, $tv_period, array(), array(), false );
if ( ! $tv_ranked ) {
	echo R::section( R::empty_state( 'trend', __( 'Nothing trending yet', 'techvenza-core' ), __( 'Rankings appear once readers start opening stories.', 'techvenza-core' ) ), $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
$tv_stats = Query::period_views( wp_list_pluck( $tv_ranked, 'ID' ), $tv_periods[ $tv_period ][1] );
$tv_max   = max( 1, $tv_stats[ $tv_ranked[0]->ID ][0] ?? 1 );

$tv_seg = '';
foreach ( $tv_periods as $tv_k => [ $tv_l ] ) {
	$tv_seg .= sprintf( '<a class="seg-btn" href="%s" data-live-link aria-pressed="%s">%s</a>', esc_url( Params::url( array( 'period' => '7d' === $tv_k ? '' : $tv_k ) ) ), $tv_k === $tv_period ? 'true' : 'false', esc_html( $tv_l ) );
}
$tv_post = get_post();
$tv_hero = sprintf(
	'<section class="page-hero"><div class="container">%s<div class="trend-hero mt-5"><div><span class="eyebrow" style="--accent:var(--rose)">%s</span><h1 class="page-title mt-4">%s</h1><p class="lead">%s</p></div><div class="row" style="justify-content:flex-end"><div class="seg" role="group" aria-label="%s">%s</div></div></div></div></section>',
	R::crumbs(
		array(
			array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ),
			array( $tv_post ? get_the_title( $tv_post ) : __( 'Trending', 'techvenza-core' ), '' ),
		)
	),
	esc_html__( 'Trending', 'techvenza-core' ),
	esc_html__( 'What readers are opening right now.', 'techvenza-core' ),
	esc_html__( 'Rankings reflect reading activity on Techvenza, updated hourly.', 'techvenza-core' ),
	esc_attr__( 'Time period', 'techvenza-core' ),
	$tv_seg
);

$tv_side = '';
foreach ( array_slice( $tv_ranked, 1, 4 ) as $tv_p ) {
	$tv_side .= '<li class="rank-item">' . R::card( $tv_p, 'text' ) . '</li>';
}
$tv_top = sprintf(
	'<div class="feature-grid"><div>%s</div><div class="panel"><h2 class="panel-title">%s</h2><ol class="rank-list">%s</ol></div></div>',
	R::card( $tv_ranked[0], 'lg', array( 'eager' => true ) ),
	/* translators: %s: period label */
	esc_html( sprintf( __( 'Most read · %s', 'techvenza-core' ), $tv_periods[ $tv_period ][0] ) ),
	$tv_side
);

$tv_rows = '';
foreach ( $tv_ranked as $tv_i => $tv_p ) {
	$tv_a = R::article( $tv_p );
	if ( ! $tv_a ) {
		continue;
	}
	[ $tv_cur, $tv_prev ] = $tv_stats[ $tv_p->ID ] ?? array( 0, 0 );
	$tv_delta             = $tv_prev > 0 ? (int) round( ( $tv_cur - $tv_prev ) / $tv_prev * 100 ) : null;
	$tv_rows             .= sprintf(
		'<div class="trend-row"><span class="num">%02d</span><div class="card-body">%s<h3 class="card-title"><a href="%s"><span class="u-line">%s</span></a></h3><div class="meta"><span>%s</span><span class="dot"></span><span>%s</span></div></div><div class="trend-bar" aria-hidden="true"><span style="width:%d%%"></span></div><div>%s<div class="meta" style="justify-content:flex-end">%s</div></div></div>',
		$tv_i + 1,
		R::chip( $tv_a['cat'] ),
		esc_url( $tv_a['url'] ),
		esc_html( $tv_a['title'] ),
		esc_html( $tv_a['author']['name'] ?? '' ),
		/* translators: %d: minutes */
		esc_html( sprintf( __( '%d min', 'techvenza-core' ), $tv_a['minutes'] ) ),
		(int) round( $tv_cur / $tv_max * 100 ),
		null === $tv_delta ? '' : sprintf(
			'<div class="delta%s"><span aria-hidden="true">%s</span> <span class="sr-only">%s</span>%d%%</div>',
			$tv_delta < 0 ? ' down' : '',
			$tv_delta < 0 ? '▼' : '▲',
			$tv_delta < 0 ? esc_html__( 'Down', 'techvenza-core' ) : esc_html__( 'Up', 'techvenza-core' ),
			abs( $tv_delta )
		),
		/* translators: %s: number of reads */
		esc_html( sprintf( _n( '%s read', '%s reads', $tv_cur, 'techvenza-core' ), number_format_i18n( $tv_cur ) ) )
	);
}

$tv_tags = array();
foreach ( $tv_ranked as $tv_p ) {
	foreach ( wp_get_post_tags( $tv_p->ID ) as $tv_t ) {
		$tv_tags[ $tv_t->slug ] = $tv_t;
	}
}
$tv_tag_html = '';
foreach ( array_slice( $tv_tags, 0, 12 ) as $tv_t ) {
	$tv_tag_html .= sprintf( '<a class="tag" href="%s">#%s</a>', esc_url( add_query_arg( 'tag', $tv_t->slug, R::page_url( 'explore' ) ) ), esc_html( $tv_t->name ) );
}

$tv_body = sprintf(
	'<section class="section tight" style="padding-top:var(--s5)"><div class="container"><div data-live-status aria-live="polite">%s</div><div class="layout-sidebar mt-7"><div><h2 class="h3 mb-4">%s</h2>%s</div><aside class="sidebar-sticky">%s%s</aside></div></div></section>',
	$tv_top,
	esc_html__( 'Ranked list', 'techvenza-core' ),
	$tv_rows,
	$tv_tag_html ? '<div class="panel"><h2 class="panel-title">' . esc_html__( 'Related topics', 'techvenza-core' ) . '</h2><div class="chip-row">' . $tv_tag_html . '</div></div>' : '',
	R::newsletter(
		array(
			'compact' => true,
			'source'  => 'trending',
		)
	)
);
Query::mark_shown( $tv_ranked );
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block' ) );
printf( '<div %s>%s</div>', $tv_wrapper, R::live( 'trending-page', $attributes, $tv_hero . $tv_body ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
