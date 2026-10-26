<?php
/**
 * Explore: search, category/topic/format filters, sort and pagination (URL-driven, live-enhanced).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\PostMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_f      = array(
	'q'    => Params::get( 'q' ),
	'cat'  => sanitize_title( Params::get( 'cat' ) ),
	'tag'  => sanitize_title( Params::get( 'tag' ) ),
	'type' => sanitize_key( Params::get( 'type' ) ),
	'sort' => in_array( Params::get( 'sort' ), array( 'popular', 'picks' ), true ) ? Params::get( 'sort' ) : 'latest',
);
$tv_page   = Params::int( 'pg' );
$tv_per    = max( 3, (int) $attributes['perPage'] );
$tv_types  = PostMeta::type_labels();
$tv_active = '' !== $tv_f['q'] || '' !== $tv_f['cat'] || '' !== $tv_f['tag'] || '' !== $tv_f['type'];

$tv_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => $tv_per,
	'paged'               => $tv_page,
	'ignore_sticky_posts' => true,
);
if ( $tv_f['q'] ) {
	$tv_args['s'] = $tv_f['q'];
}
if ( $tv_f['cat'] ) {
	$tv_args['category_name'] = $tv_f['cat'];
}
if ( $tv_f['tag'] ) {
	$tv_args['tag'] = $tv_f['tag'];
}
$tv_meta = array();
if ( $tv_f['type'] && isset( $tv_types[ $tv_f['type'] ] ) ) {
	$tv_meta[] = array(
		'key'   => 'tv_article_type',
		'value' => $tv_f['type'],
	);
}
if ( 'popular' === $tv_f['sort'] ) {
	$tv_args['meta_key'] = 'tv_view_count'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$tv_args['orderby']  = array(
		'meta_value_num' => 'DESC',
		'date'           => 'DESC',
	);
} elseif ( 'picks' === $tv_f['sort'] ) {
	$tv_meta[] = array(
		'key'   => 'tv_is_editors_pick',
		'value' => '1',
	);
}
if ( $tv_meta ) {
	$tv_args['meta_query'] = $tv_meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
}

// Featured story on the unfiltered first page.
$tv_featured = null;
if ( ! $tv_active && 1 === $tv_page && ! empty( $attributes['showFeatured'] ) && 'latest' === $tv_f['sort'] ) {
	$tv_feat     = Query::flagged( 'tv_is_featured', 1 );
	$tv_featured = $tv_feat ? $tv_feat[0] : null;
	if ( $tv_featured ) {
		$tv_args['post__not_in'] = array( $tv_featured->ID );
	}
}
$tv_query = new WP_Query( $tv_args );
$tv_total = (int) $tv_query->found_posts + ( $tv_featured ? 1 : 0 );
$tv_keep  = array_filter( $tv_f, static fn( $v, $k ) => '' !== $v && ! ( 'sort' === $k && 'latest' === $v ), ARRAY_FILTER_USE_BOTH );

/* Toolbar */
$tv_sorts = '';
foreach ( array(
	'latest'  => __( 'Latest', 'techvenza-core' ),
	'popular' => __( 'Most read', 'techvenza-core' ),
	'picks'   => __( "Editor's picks", 'techvenza-core' ),
) as $tv_k => $tv_l ) {
	$tv_sorts .= sprintf( '<a href="%s" data-live-link aria-current="%s" class="seg-btn">%s</a>', esc_url( Params::url( array_merge( $tv_keep, array( 'sort' => 'latest' === $tv_k ? '' : $tv_k ) ) ) ), $tv_f['sort'] === $tv_k ? 'true' : 'false', esc_html( $tv_l ) );
}
$tv_type_opts = '<option value="">' . esc_html__( 'All formats', 'techvenza-core' ) . '</option>';
foreach ( $tv_types as $tv_k => $tv_l ) {
	$tv_type_opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $tv_k ), selected( $tv_f['type'], $tv_k, false ), esc_html( $tv_l ) );
}
$tv_toolbar = sprintf(
	'<form class="toolbar" role="search" method="get" action="%1$s" aria-label="%2$s">
		<div class="input-icon">%3$s<label class="sr-only" for="exQ">%4$s</label><input class="input" id="exQ" name="q" type="search" placeholder="%5$s" value="%6$s" data-live-input></div>
		<label class="sr-only" for="exType">%7$s</label><select class="select" id="exType" name="type" data-live-change>%8$s</select>
		%9$s<div class="seg" role="group" aria-label="%10$s">%11$s</div><button class="btn btn-secondary btn-sm tv-nojs" type="submit">%12$s</button>
	</form>',
	esc_url( Params::base_url() ),
	esc_attr__( 'Filter stories', 'techvenza-core' ),
	R::icon( 'search' ),
	esc_html__( 'Search stories', 'techvenza-core' ),
	esc_attr__( 'Search titles, topics, authors…', 'techvenza-core' ),
	esc_attr( $tv_f['q'] ),
	esc_html__( 'Format', 'techvenza-core' ),
	$tv_type_opts,
	( $tv_f['cat'] ? '<input type="hidden" name="cat" value="' . esc_attr( $tv_f['cat'] ) . '">' : '' ) . ( $tv_f['tag'] ? '<input type="hidden" name="tag" value="' . esc_attr( $tv_f['tag'] ) . '">' : '' ) . ( 'latest' !== $tv_f['sort'] ? '<input type="hidden" name="sort" value="' . esc_attr( $tv_f['sort'] ) . '">' : '' ),
	esc_attr__( 'Sort by', 'techvenza-core' ),
	$tv_sorts,
	esc_html__( 'Apply', 'techvenza-core' )
);

/* Category chips */
$tv_chips = sprintf( '<a class="filter-chip" href="%s" data-live-link aria-current="%s">%s <span class="count">%d</span></a>', esc_url( Params::url( array_merge( $tv_keep, array( 'cat' => '' ) ) ) ), $tv_f['cat'] ? 'false' : 'true', esc_html__( 'All categories', 'techvenza-core' ), (int) wp_count_posts( 'post' )->publish );
foreach ( CategoryMeta::all() as $tv_c ) {
	if ( ! $tv_c['count'] ) {
		continue;
	}
	$tv_chips .= sprintf( '<a class="filter-chip" href="%s" data-live-link aria-current="%s"><span class="dot-c" style="--c:%s"></span>%s <span class="count">%d</span></a>', esc_url( Params::url( array_merge( $tv_keep, array( 'cat' => $tv_c['slug'] ) ) ) ), $tv_f['cat'] === $tv_c['slug'] ? 'true' : 'false', esc_attr( $tv_c['color'] ), esc_html( $tv_c['short'] ), $tv_c['count'] );
}
/* Topic tags */
$tv_tags     = get_tags(
	array(
		'orderby' => 'count',
		'order'   => 'DESC',
		'number'  => 14,
	)
);
$tv_tag_html = '';
foreach ( is_array( $tv_tags ) ? $tv_tags : array() as $tv_t ) {
	$tv_on        = $tv_f['tag'] === $tv_t->slug;
	$tv_tag_html .= sprintf( '<a class="tag%s" href="%s" data-live-link aria-current="%s">#%s</a>', $tv_on ? ' is-active' : '', esc_url( Params::url( array_merge( $tv_keep, array( 'tag' => $tv_on ? '' : $tv_t->slug ) ) ) ), $tv_on ? 'true' : 'false', esc_html( $tv_t->name ) );
}

/* Results */
$tv_posts = array_values( array_filter( $tv_query->posts, static fn( $p ) => $p instanceof WP_Post ) );
if ( ! $tv_posts && ! $tv_featured ) {
	$tv_results = R::empty_state(
		'search',
		__( 'No stories match these filters', 'techvenza-core' ),
		$tv_f['q'] ? sprintf( /* translators: %s: search query */ __( 'We couldn’t find stories for “%s” with the current filters. Try a broader term or clear a filter.', 'techvenza-core' ), $tv_f['q'] ) : __( 'Try removing a filter to see more stories.', 'techvenza-core' ),
		sprintf( '<a class="btn btn-primary btn-sm" href="%s" data-live-link>%s</a>', esc_url( Params::url( array() ) ), esc_html__( 'Clear all filters', 'techvenza-core' ) )
	);
} else {
	$tv_summary = sprintf(
		/* translators: %s: number of stories */
		_n( '%s story', '%s stories', $tv_total, 'techvenza-core' ),
		'<b style="color:var(--ink)">' . number_format_i18n( $tv_total ) . '</b>'
	);
	if ( $tv_f['q'] ) {
		/* translators: %s: query */
		$tv_summary .= ' ' . sprintf( esc_html__( 'for “%s”', 'techvenza-core' ), esc_html( $tv_f['q'] ) );
	}
	if ( $tv_f['cat'] && ( $tv_cat = CategoryMeta::get( $tv_f['cat'] ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		/* translators: %s: category */
		$tv_summary .= ' ' . sprintf( esc_html__( 'in %s', 'techvenza-core' ), esc_html( $tv_cat['name'] ) );
	}
	if ( $tv_f['tag'] ) {
		/* translators: %s: tag */
		$tv_summary .= ' ' . sprintf( esc_html__( 'tagged #%s', 'techvenza-core' ), esc_html( $tv_f['tag'] ) );
	}
	if ( $tv_f['type'] && isset( $tv_types[ $tv_f['type'] ] ) ) {
		$tv_summary .= ' · ' . esc_html( $tv_types[ $tv_f['type'] ] );
	}
	$tv_results  = sprintf(
		'<div class="results-meta" data-live-status aria-live="polite"><span>%s</span>%s</div>',
		$tv_summary,
		$tv_active ? sprintf( '<a class="btn btn-ghost btn-sm" href="%s" data-live-link>%s %s</a>', esc_url( Params::url( 'latest' !== $tv_f['sort'] ? array( 'sort' => $tv_f['sort'] ) : array() ) ), R::icon( 'x' ), esc_html__( 'Clear filters', 'techvenza-core' ) ) : ''
	);
	$tv_results .= $tv_featured ? '<div class="mb-6">' . R::card( $tv_featured, 'md', array( 'ratio' => 'r-wide' ) ) . '</div>' : '';
	$tv_results .= '<div class="grid g-2">' . R::cards( $tv_posts, 'default', array( 'q' => $tv_f['q'] ) ) . '</div>';
	$tv_results .= R::pager( (int) $tv_query->found_posts, $tv_page, $tv_per, 'pg', $tv_keep );
}

$tv_sidebar = '';
if ( ! empty( $attributes['showSidebar'] ) ) {
	$tv_sidebar = sprintf(
		'<aside class="sidebar-sticky" aria-label="%s"><div class="panel"><h2 class="panel-title">%s <a class="sec-link" href="%s" style="font-size:var(--t-meta)">%s %s</a></h2><div class="grid" style="gap:var(--s4)">%s</div></div>%s</aside>',
		esc_attr__( 'More to discover', 'techvenza-core' ),
		esc_html__( 'Trending now', 'techvenza-core' ),
		esc_url( R::page_url( 'trending' ) ),
		esc_html__( 'All', 'techvenza-core' ),
		R::icon( 'arrowRight' ),
		R::cards( Query::trending( 4 ), 'row' ),
		R::newsletter(
			array(
				'compact' => true,
				'source'  => 'explore',
			)
		)
	);
}

$tv_inner = $tv_toolbar
	. '<div class="chip-scroll mb-4" role="group" aria-label="' . esc_attr__( 'Category', 'techvenza-core' ) . '">' . $tv_chips . '</div>'
	. ( $tv_tag_html ? '<div class="chip-row mb-6" role="group" aria-label="' . esc_attr__( 'Topic', 'techvenza-core' ) . '">' . $tv_tag_html . '</div>' : '' )
	. '<div class="' . ( $tv_sidebar ? 'layout-sidebar' : '' ) . '"><div data-live-top style="scroll-margin-top:90px">' . $tv_results . '</div>' . $tv_sidebar . '</div>';

$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block section tight tv-pt-0' ) );
printf( '<section %s><div class="container">%s</div></section>', $tv_wrapper, R::live( 'explore', $attributes, $tv_inner ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
