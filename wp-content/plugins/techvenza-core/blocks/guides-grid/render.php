<?php
/**
 * How-to guides grid with a level filter (links, enhanced in place).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_levels = array(
	''             => __( 'All levels', 'techvenza-core' ),
	'Beginner'     => __( 'Beginner', 'techvenza-core' ),
	'Intermediate' => __( 'Intermediate', 'techvenza-core' ),
	'Advanced'     => __( 'Advanced', 'techvenza-core' ),
);
$tv_level  = Params::get( 'level' );
$tv_level  = isset( $tv_levels[ $tv_level ] ) ? $tv_level : '';
$tv_all    = Query::by_type( array( 'guide' ), max( 3, (int) $attributes['count'] ) * 3, 'date' );
$tv_have   = array_unique( array_map( static fn( $p ) => (string) get_post_meta( $p->ID, 'tv_guide_level', true ), $tv_all ) );
$tv_list   = array_slice( array_values( array_filter( $tv_all, static fn( $p ) => ! $tv_level || get_post_meta( $p->ID, 'tv_guide_level', true ) === $tv_level ) ), 0, (int) $attributes['count'] );

$tv_seg = '';
foreach ( $tv_levels as $tv_v => $tv_l ) {
	if ( $tv_v && ! in_array( $tv_v, $tv_have, true ) ) {
		continue;
	}
	$tv_seg .= sprintf( '<a class="seg-btn" href="%s" data-live-link aria-current="%s">%s</a>', esc_url( Params::url( array( 'level' => $tv_v ) ) ), $tv_v === $tv_level ? 'true' : 'false', esc_html( $tv_l ) );
}
$tv_cards = '';
foreach ( $tv_list as $tv_p ) {
	$tv_a = R::article( $tv_p );
	if ( ! $tv_a ) {
		continue;
	}
	$tv_cards .= sprintf(
		'<article class="card boxed guide-card reveal" style="%s">%s<a class="card-link" href="%s">%s<div class="card-body"><div class="card-top"><span class="level">%s</span><span class="meta">%s</span></div><h3 class="card-title"><span class="u-line">%s</span></h3><p class="card-excerpt">%s</p></div></a></article>',
		esc_attr( \Techvenza\Core\Content\CategoryMeta::style( $tv_a['cat'] ) ),
		R::bookmark( $tv_a ),
		esc_url( $tv_a['url'] ),
		R::media(
			$tv_a,
			array(
				'ratio' => 'r-32',
				'size'  => 'techvenza-card',
			)
		),
		esc_html( $tv_a['level'] ? $tv_a['level'] : __( 'All levels', 'techvenza-core' ) ),
		/* translators: %d: minutes */
		esc_html( sprintf( __( '%d min', 'techvenza-core' ), $tv_a['minutes'] ) ),
		esc_html( $tv_a['title'] ),
		esc_html( $tv_a['excerpt'] )
	);
}
$tv_body  = $tv_cards ? '<div class="grid g-3">' . $tv_cards . '</div>' : R::empty_state( 'compass', __( 'No guides at this level yet', 'techvenza-core' ), __( 'Try another level.', 'techvenza-core' ) );
$tv_inner = sprintf(
	'<div class="row mb-6" style="justify-content:space-between;flex-wrap:wrap;gap:var(--s4)"><h2 class="h2">%s</h2><div class="seg" role="group" aria-label="%s">%s</div></div><div data-live-status aria-live="polite">%s</div>',
	esc_html( $attributes['heading'] ),
	esc_attr__( 'Level', 'techvenza-core' ),
	$tv_seg,
	$tv_body
);
echo R::section( R::live( 'guides-grid', $attributes, $tv_inner ), $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
