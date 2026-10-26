<?php
/**
 * Category extras: topic tags, related categories and featured authors in the category.
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
$tv_ids      = Query::ids(
	array(
		'cat'            => $tv_c['id'],
		'posts_per_page' => 60,
	)
);
$tv_tags     = $tv_ids ? wp_get_object_terms(
	array_map( 'intval', $tv_ids ),
	'post_tag',
	array(
		'orderby' => 'count',
		'order'   => 'DESC',
	)
) : array();
$tv_tags     = is_array( $tv_tags ) ? array_slice( $tv_tags, 0, 16 ) : array();
$tv_tag_html = $tv_tags
	? implode( '', array_map( static fn( $t ) => sprintf( '<a class="tag" href="%s">#%s</a>', esc_url( add_query_arg( 'tag', $t->slug, R::page_url( 'explore' ) ) ), esc_html( $t->name ) ), $tv_tags ) )
	: '<p class="muted">' . esc_html__( 'Tags will appear as stories are published.', 'techvenza-core' ) . '</p>';

$tv_related = array_filter( CategoryMeta::all(), static fn( $x ) => $x['slug'] !== $tv_c['slug'] );
usort( $tv_related, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
$tv_rel_html = implode(
	'',
	array_map(
		/* translators: %d: number of stories */
		static fn( $r ) => sprintf( '<a class="cat-tile" style="%s;min-height:96px" href="%s"><h3>%s</h3><span class="n">%s</span></a>', esc_attr( CategoryMeta::style( $r ) ), esc_url( $r['url'] ), esc_html( $r['name'] ), esc_html( sprintf( _n( '%d story', '%d stories', $r['count'], 'techvenza-core' ), $r['count'] ) ) ),
		array_slice( $tv_related, 0, 4 )
	)
);
$tv_authors = Query::authors( array( 'cat' => $tv_c['slug'] ) );
$tv_au_html = $tv_authors
	? implode( '', array_map( static fn( $a ) => sprintf( '<a class="panel row" style="gap:var(--s4);flex-wrap:nowrap" href="%s">%s<span><b style="color:var(--ink)">%s</b><br><span class="muted" style="font-size:var(--t-meta)">%s</span></span></a>', esc_url( $a['url'] ), R::avatar( $a, 'md' ), esc_html( $a['name'] ), esc_html( $a['title'] ) ), array_slice( $tv_authors, 0, 4 ) ) )
	: '<p class="muted">' . esc_html__( 'We are recruiting experts in this field.', 'techvenza-core' ) . ' <a class="link" href="' . esc_url( R::page_url( 'become-an-author' ) ) . '">' . esc_html__( 'Apply to write', 'techvenza-core' ) . '</a>.</p>';

$tv_inner = sprintf(
	'<div class="grid g-2" style="gap:var(--s7)"><div><h2 class="h3 mb-5">%s</h2><div class="chip-row">%s</div><h2 class="h3 mt-7 mb-5">%s</h2><div class="grid g-2" style="gap:var(--s3)">%s</div></div><div><h2 class="h3 mb-5">%s</h2><div class="grid" style="gap:var(--s3)">%s</div></div></div>',
	esc_html__( 'Topic tags', 'techvenza-core' ),
	$tv_tag_html,
	esc_html__( 'Related categories', 'techvenza-core' ),
	$tv_rel_html,
	/* translators: %s: category short name */
	esc_html( sprintf( __( 'Featured authors in %s', 'techvenza-core' ), $tv_c['short'] ) ),
	$tv_au_html
);
echo R::section( $tv_inner, array_merge( array( 'spacing' => 'tight' ), $attributes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
