<?php
/**
 * Search results page: query form, result-type tabs (articles, authors, topics), highlighting, pagination.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Rest\SearchController;
use Techvenza\Core\Support\Render as R;

$tv_q    = trim( Params::get( 's' ) );
$tv_tab  = Params::get( 'rt', 'all' );
$tv_tab  = in_array( $tv_tab, array( 'all', 'articles', 'authors', 'topics' ), true ) ? $tv_tab : 'all';
$tv_page = Params::int( 'pg' );
$tv_per  = 10;
$tv_base = home_url( '/' );

$tv_form = sprintf(
	'<form class="toolbar mt-6" role="search" method="get" action="%1$s" style="border-top:0;margin-bottom:0"><div class="input-icon" style="max-width:600px">%2$s<label class="sr-only" for="sQ">%3$s</label><input class="input" id="sQ" name="s" type="search" value="%4$s" placeholder="%5$s"></div><button class="btn btn-primary" type="submit">%3$s</button>%6$s</form>',
	esc_url( $tv_base ),
	R::icon( 'search' ),
	esc_html__( 'Search', 'techvenza-core' ),
	esc_attr( $tv_q ),
	esc_attr__( 'Search articles, authors, topics…', 'techvenza-core' ),
	$tv_q ? sprintf( '<a class="btn btn-ghost" href="%s">%s %s</a>', esc_url( add_query_arg( 's', '', $tv_base ) ), R::icon( 'x' ), esc_html__( 'Clear search', 'techvenza-core' ) ) : ''
);
$tv_hero = sprintf(
	'<section class="page-hero"><div class="container">%s<h1 class="page-title mt-5">%s</h1>%s</div></section>',
	R::crumbs(
		array(
			array( __( 'Home', 'techvenza-core' ), $tv_base ),
			array( __( 'Search', 'techvenza-core' ), '' ),
		)
	),
	/* translators: %s: search query */
	$tv_q ? esc_html( sprintf( __( 'Results for “%s”', 'techvenza-core' ), $tv_q ) ) : esc_html__( 'Search Techvenza', 'techvenza-core' ),
	$tv_form
);

if ( '' === $tv_q ) {
	$tv_popular = array_filter( array_map( 'trim', explode( ',', (string) \Techvenza\Core\Settings\Options::get( 'popular_searches', 'AI agents, Zero trust, Platform engineering, Pricing, Digital twins, Data contracts, Leadership' ) ) ) );
	$tv_body    = sprintf(
		'<div class="grid g-2" style="gap:var(--s7)"><div><h2 class="h3 mb-5">%s</h2><div class="chip-row">%s</div></div><div><h2 class="h3 mb-5">%s</h2><div class="chip-row">%s</div></div></div>',
		esc_html__( 'Popular searches', 'techvenza-core' ),
		implode( '', array_map( static fn( $t ) => sprintf( '<a class="filter-chip" href="%s">%s</a>', esc_url( add_query_arg( 's', rawurlencode( $t ), $tv_base ) ), esc_html( $t ) ), $tv_popular ) ),
		esc_html__( 'Browse topics', 'techvenza-core' ),
		implode( '', array_map( static fn( $c ) => R::chip( $c ), array_slice( CategoryMeta::all(), 0, 10 ) ) )
	);
} else {
	$tv_query     = new WP_Query(
		array(
			's'              => $tv_q,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $tv_per,
			'paged'          => $tv_page,
		)
	);
	$tv_arts      = array_values( array_filter( $tv_query->posts, static fn( $p ) => $p instanceof WP_Post ) );
	$tv_n_art     = (int) $tv_query->found_posts;
	$tv_aus       = SearchController::authors( $tv_q );
	$tv_cats      = SearchController::categories( $tv_q );
	$tv_total     = $tv_n_art + count( $tv_aus ) + count( $tv_cats );
	$tv_tabs      = array(
		'all'      => array( __( 'All', 'techvenza-core' ), $tv_total ),
		'articles' => array( __( 'Articles', 'techvenza-core' ), $tv_n_art ),
		'authors'  => array( __( 'Authors', 'techvenza-core' ), count( $tv_aus ) ),
		'topics'   => array( __( 'Topics', 'techvenza-core' ), count( $tv_cats ) ),
	);
	$tv_tabs_html = '';
	foreach ( $tv_tabs as $tv_k => [ $tv_l, $tv_n ] ) {
		$tv_tabs_html .= sprintf(
			'<a class="tab" href="%s" data-live-link aria-current="%s">%s<span class="count">%d</span></a>',
			esc_url(
				add_query_arg(
					array_filter(
						array(
							's'  => rawurlencode( $tv_q ),
							'rt' => 'all' === $tv_k ? '' : $tv_k,
						)
					),
					$tv_base
				)
			),
			$tv_tab === $tv_k ? 'true' : 'false',
			esc_html( $tv_l ),
			(int) $tv_n
		);
	}
	$tv_body = '<nav class="tabs mb-6" aria-label="' . esc_attr__( 'Result types', 'techvenza-core' ) . '">' . $tv_tabs_html . '</nav>';
	/* translators: %s: number of results */
	$tv_body .= '<p class="results-meta" data-live-status><span>' . sprintf( _n( '%s result', '%s results', $tv_total, 'techvenza-core' ), '<b style="color:var(--ink)">' . (int) $tv_total . '</b>' ) . '</span></p>';
	if ( ! $tv_total ) {
		$tv_body .= R::empty_state(
			'search',
			/* translators: %s: query */
			sprintf( __( 'No results for “%s”', 'techvenza-core' ), $tv_q ),
			__( 'Check the spelling, try a broader term, or explore one of these:', 'techvenza-core' ),
			'<div class="chip-row" style="justify-content:center">' . implode( '', array_map( static fn( $c ) => R::chip( $c ), array_slice( CategoryMeta::all( true ), 0, 4 ) ) ) . '</div>'
		);
	} else {
		$tv_sec = '';
		if ( in_array( $tv_tab, array( 'all', 'topics' ), true ) && $tv_cats ) {
			$tv_sec .= '<section class="mb-6"><h2 class="h3 mb-4">' . esc_html__( 'Topics', 'techvenza-core' ) . '</h2><div class="chip-row">' . implode( '', array_map( static fn( $c ) => sprintf( '<a class="filter-chip" href="%s"><span class="dot-c" style="--c:%s"></span>%s</a>', esc_url( $c['url'] ), esc_attr( $c['color'] ), R::hl( $c['name'], $tv_q ) ), $tv_cats ) ) . '</div></section>';
		}
		if ( in_array( $tv_tab, array( 'all', 'authors' ), true ) && $tv_aus ) {
			$tv_sec .= '<section class="mb-6"><h2 class="h3 mb-4">' . esc_html__( 'Authors', 'techvenza-core' ) . '</h2><div class="grid g-3">' . implode( '', array_map( static fn( $a ) => R::author_card( $a, array( 'q' => $tv_q ) ), $tv_aus ) ) . '</div></section>';
		}
		if ( in_array( $tv_tab, array( 'all', 'articles' ), true ) && $tv_arts ) {
			$tv_rows = '';
			foreach ( $tv_arts as $tv_p ) {
				$tv_a = R::article( $tv_p );
				if ( ! $tv_a ) {
					continue;
				}
				$tv_rows .= sprintf(
					'<article class="card row-card reveal"><a class="card-link" href="%s">%s<div class="card-body">%s<h3 class="card-title" style="font-size:1.125rem">%s</h3><p class="card-excerpt">%s</p>%s</div></a></article>',
					esc_url( $tv_a['url'] ),
					R::media(
						$tv_a,
						array(
							'ratio' => 'r-sq',
							'sizes' => '140px',
							'size'  => 'techvenza-square',
						)
					),
					R::chip( $tv_a['cat'], false ),
					R::hl( $tv_a['title'], $tv_q ),
					R::hl( $tv_a['excerpt'], $tv_q ),
					R::byline( $tv_a, array( 'avatar' => false ) )
				);
			}
			$tv_sec .= '<section><h2 class="h3 mb-4">' . esc_html__( 'Articles', 'techvenza-core' ) . '</h2><div class="grid" style="gap:var(--s5)">' . $tv_rows . '</div>' . R::pager(
				$tv_n_art,
				$tv_page,
				$tv_per,
				'pg',
				array_filter(
					array(
						's'  => $tv_q,
						'rt' => 'all' === $tv_tab ? '' : $tv_tab,
					)
				)
			) . '</section>';
		}
		$tv_body .= $tv_sec ? $tv_sec : R::empty_state( 'filter', __( 'Nothing in this tab', 'techvenza-core' ), __( 'Switch to another result type.', 'techvenza-core' ) );
	}
}
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block' ) );
printf(
	'<div %s>%s<section class="section tight" style="padding-top:0"><div class="container">%s</div></section></div>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_hero, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	R::live( 'search-results', $attributes, '<div aria-live="polite" data-live-top>' . $tv_body . '</div>' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
