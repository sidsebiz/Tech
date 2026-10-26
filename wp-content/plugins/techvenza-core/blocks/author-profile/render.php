<?php
/**
 * Author profile (author archive): header, featured work, latest, topics and peers.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Support\Render as R;

$tv_user = get_queried_object();
$tv_au   = $tv_user instanceof WP_User ? UserMeta::author( $tv_user ) : null;
if ( ! $tv_au ) {
	return;
}
$tv_list     = Query::posts(
	array(
		'author'         => $tv_au['id'],
		'posts_per_page' => 60,
	)
);
$tv_featured = $tv_list;
usort( $tv_featured, static fn( $a, $b ) => Query::views( $b->ID ) <=> Query::views( $a->ID ) );
$tv_featured = array_slice( $tv_featured, 0, 2 );
$tv_feat_ids = wp_list_pluck( $tv_featured, 'ID' );
$tv_rest     = array_values( array_filter( $tv_list, static fn( $p ) => ! in_array( $p->ID, $tv_feat_ids, true ) ) );
$tv_first    = strtok( $tv_au['name'], ' ' );
$tv_n        = count( $tv_list );

$tv_links = '';
if ( $tv_au['website'] ) {
	$tv_links .= sprintf( '<a class="btn btn-secondary btn-sm" href="%s" target="_blank" rel="noopener nofollow me">%s %s <span class="sr-only">%s</span></a>', esc_url( $tv_au['website'] ), R::icon( 'globe' ), esc_html__( 'Website', 'techvenza-core' ), esc_html__( '(opens in new tab)', 'techvenza-core' ) );
}
if ( $tv_au['linkedin'] ) {
	$tv_links .= sprintf( '<a class="btn btn-secondary btn-sm" href="%s" target="_blank" rel="noopener nofollow me">%s LinkedIn <span class="sr-only">%s</span></a>', esc_url( $tv_au['linkedin'] ), R::icon( 'linkedin' ), esc_html__( '(opens in new tab)', 'techvenza-core' ) );
}
/* translators: %d: number of articles */
$tv_links .= '<span class="muted" style="font-size:var(--t-meta)">' . esc_html( sprintf( _n( '%d published article', '%d published articles', $tv_n, 'techvenza-core' ), $tv_n ) ) . '</span>';

$tv_head = sprintf(
	'<div class="container"><div style="padding-top:var(--s6)">%1$s</div><header class="profile-head">%2$s<div><span class="eyebrow">%3$s</span><h1 class="mt-4">%4$s</h1><p class="role">%5$s</p>%6$s<p class="bio">%7$s</p><div class="chip-row mt-5" aria-label="%8$s">%9$s</div><div class="links-row">%10$s</div></div></header></div>',
	R::crumbs(
		array(
			array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ),
			array( __( 'Authors', 'techvenza-core' ), R::page_url( 'authors' ) ),
			array( $tv_au['name'], '' ),
		)
	),
	R::avatar( $tv_au, 'xl' ),
	/* translators: 1: Staff/Contributor, 2: join date */
	esc_html( sprintf( __( '%1$s · Joined %2$s', 'techvenza-core' ), $tv_au['staff'] ? __( 'Staff', 'techvenza-core' ) : __( 'Contributor', 'techvenza-core' ), R::date( $tv_au['joined'] ) ) ),
	esc_html( $tv_au['name'] ),
	esc_html( $tv_au['title'] ),
	$tv_au['verified'] ? '<p class="verified mt-4">' . R::icon( 'checkCircle' ) . ' ' . esc_html( $tv_au['verified'] ) . '</p>' : '',
	esc_html( wp_strip_all_tags( $tv_au['bio'] ) ),
	esc_attr__( 'Expertise', 'techvenza-core' ),
	implode( '', array_map( static fn( $e ) => '<span class="tag">' . esc_html( $e ) . '</span>', $tv_au['expertise'] ) ),
	$tv_links
);

$tv_body = $tv_list
	? R::section(
		'<h2 class="h2 mb-6">' . esc_html__( 'Featured work', 'techvenza-core' ) . '</h2><div class="grid g-2">' . R::cards( $tv_featured, 'md' ) . '</div>',
		array(
			'spacing' => 'tight',
			'_plain'  => true,
		)
	)
	: R::section(
		R::empty_state( 'file', __( 'No published articles yet', 'techvenza-core' ), __( 'This author’s first story is in editorial review.', 'techvenza-core' ) ),
		array(
			'spacing' => 'tight',
			'_plain'  => true,
		)
	);
if ( $tv_rest ) {
	$tv_body .= R::section(
		/* translators: %s: author first name */
		'<h2 class="h2 mb-6">' . esc_html( sprintf( __( 'Latest from %s', 'techvenza-core' ), $tv_first ) ) . '</h2><div class="grid g-3">' . R::cards( $tv_rest ) . '</div>',
		array(
			'spacing' => 'tight',
			'surface' => 'alt',
			'_plain'  => true,
		)
	);
}
$tv_peers   = array_slice( array_values( array_filter( Query::authors(), static fn( $x ) => $x['id'] !== $tv_au['id'] && array_intersect( $x['cats'], $tv_au['cats'] ) ) ), 0, 3 );
$tv_body   .= R::section(
	sprintf(
		'<div class="grid g-2" style="gap:var(--s7)"><div><h2 class="h3 mb-5">%s</h2><div class="chip-row">%s</div></div><div><h2 class="h3 mb-5">%s</h2><div class="grid" style="gap:var(--s3)">%s</div></div></div>',
		esc_html__( 'More from this author’s topics', 'techvenza-core' ),
		implode( '', array_map( static fn( $s ) => R::chip( \Techvenza\Core\Content\CategoryMeta::get( $s ) ), $tv_au['cats'] ) ),
		esc_html__( 'Authors in similar fields', 'techvenza-core' ),
		implode( '', array_map( static fn( $a ) => sprintf( '<a class="panel row" style="gap:var(--s4);flex-wrap:nowrap" href="%s">%s<span><b style="color:var(--ink)">%s</b><br><span class="muted" style="font-size:var(--t-meta)">%s</span></span></a>', esc_url( $a['url'] ), R::avatar( $a, 'md' ), esc_html( $a['name'] ), esc_html( $a['title'] ) ), $tv_peers ) )
	),
	array(
		'spacing' => 'tight',
		'_plain'  => true,
	)
);
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block' ) );
printf( '<div %s>%s%s</div>', $tv_wrapper, $tv_head, $tv_body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
