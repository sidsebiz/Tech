<?php
/**
 * Author directory: featured contributors, name search, expertise and category filters, load more.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_f    = array(
	'aq'   => Params::get( 'aq' ),
	'exp'  => Params::get( 'exp' ),
	'acat' => sanitize_title( Params::get( 'acat' ) ),
);
$tv_page = Params::int( 'apg' );
$tv_per  = max( 3, (int) $attributes['perPage'] );
$tv_all  = Query::authors();
$tv_list = Query::authors(
	array(
		'q'         => $tv_f['aq'],
		'expertise' => $tv_f['exp'],
		'cat'       => $tv_f['acat'],
	)
);
$tv_keep = array_filter( $tv_f );

$tv_exps = array();
$tv_cats = array();
foreach ( $tv_all as $tv_au ) {
	foreach ( $tv_au['expertise'] as $tv_e ) {
		$tv_exps[ $tv_e ] = $tv_e;
	}
	foreach ( $tv_au['cats'] as $tv_c ) {
		$tv_cats[ $tv_c ] = $tv_c;
	}
}
ksort( $tv_exps );
$tv_exp_opts = '<option value="">' . esc_html__( 'All expertise', 'techvenza-core' ) . '</option>';
foreach ( $tv_exps as $tv_e ) {
	$tv_exp_opts .= sprintf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $tv_e ), selected( $tv_f['exp'], $tv_e, false ) );
}
$tv_chips = sprintf( '<a class="filter-chip" href="%s" data-live-link aria-pressed="%s">%s</a>', esc_url( Params::url( array_merge( $tv_keep, array( 'acat' => '' ) ) ) ), $tv_f['acat'] ? 'false' : 'true', esc_html__( 'All categories', 'techvenza-core' ) );
foreach ( $tv_cats as $tv_slug ) {
	$tv_c = CategoryMeta::get( $tv_slug );
	if ( $tv_c ) {
		$tv_chips .= sprintf( '<a class="filter-chip" href="%s" data-live-link aria-pressed="%s"><span class="dot-c" style="--c:%s"></span>%s</a>', esc_url( Params::url( array_merge( $tv_keep, array( 'acat' => $tv_slug ) ) ) ), $tv_f['acat'] === $tv_slug ? 'true' : 'false', esc_attr( $tv_c['color'] ), esc_html( $tv_c['short'] ) );
	}
}

$tv_featured = '';
if ( ! empty( $attributes['showFeatured'] ) ) {
	$tv_feat = array_filter( $tv_all, static fn( $a ) => $a['featured'] );
	if ( $tv_feat ) {
		$tv_featured = '<h2 class="h3 mb-5">' . esc_html__( 'Featured contributors', 'techvenza-core' ) . '</h2><div class="author-row mb-7">' . implode( '', array_map( array( R::class, 'author_card' ), $tv_feat ) ) . '</div>';
	}
}

$tv_toolbar = sprintf(
	'<form class="toolbar" method="get" action="%1$s" role="search" aria-label="%2$s"><div class="input-icon">%3$s<label class="sr-only" for="adQ">%4$s</label><input class="input" id="adQ" name="aq" type="search" value="%5$s" placeholder="%6$s" data-live-input></div><label class="sr-only" for="adExp">%7$s</label><select class="select" id="adExp" name="exp" data-live-change>%8$s</select>%9$s<button class="btn btn-secondary btn-sm tv-nojs" type="submit">%10$s</button></form>',
	esc_url( Params::base_url() ),
	esc_attr__( 'Filter authors', 'techvenza-core' ),
	R::icon( 'search' ),
	esc_html__( 'Search authors by name', 'techvenza-core' ),
	esc_attr( $tv_f['aq'] ),
	esc_attr__( 'Search by name…', 'techvenza-core' ),
	esc_html__( 'Expertise', 'techvenza-core' ),
	$tv_exp_opts,
	$tv_f['acat'] ? '<input type="hidden" name="acat" value="' . esc_attr( $tv_f['acat'] ) . '">' : '',
	esc_html__( 'Apply', 'techvenza-core' )
);

if ( $tv_list ) {
	$tv_shown   = array_slice( $tv_list, 0, $tv_page * $tv_per );
	$tv_n       = count( $tv_list );
	$tv_results = sprintf(
		'<p class="results-meta" data-live-status><span>%s</span></p><div class="grid g-3">%s</div>',
		/* translators: %s: count */
		sprintf( _n( '%s author', '%s authors', $tv_n, 'techvenza-core' ), '<b style="color:var(--ink)">' . (int) $tv_n . '</b>' ),
		implode( '', array_map( static fn( $a ) => R::author_card( $a, array( 'q' => $tv_f['aq'] ) ), $tv_shown ) )
	);
	if ( $tv_n > count( $tv_shown ) ) {
		$tv_results .= sprintf( '<div class="center mt-7"><a class="btn btn-secondary" href="%s" data-live-link data-live-more>%s</a></div>', esc_url( Params::url( array_merge( $tv_keep, array( 'apg' => $tv_page + 1 ) ) ) ), esc_html__( 'Load more authors', 'techvenza-core' ) );
	}
} else {
	$tv_results = R::empty_state( 'users', __( 'No authors found', 'techvenza-core' ), __( 'Try a different name, expertise or category.', 'techvenza-core' ), sprintf( '<a class="btn btn-secondary btn-sm" href="%s" data-live-link>%s</a>', esc_url( Params::url( array() ) ), esc_html__( 'Reset filters', 'techvenza-core' ) ) );
}

$tv_inner   = $tv_featured . $tv_toolbar
	. '<div class="chip-scroll mb-6" role="group" aria-label="' . esc_attr__( 'Category', 'techvenza-core' ) . '">' . $tv_chips . '</div>'
	. '<div aria-live="polite">' . $tv_results . '</div>';
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block section tight tv-pt-0' ) );
printf( '<section %s><div class="container">%s</div></section>', $tv_wrapper, R::live( 'author-directory', $attributes, $tv_inner ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
