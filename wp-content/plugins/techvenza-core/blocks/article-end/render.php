<?php
/**
 * Article end: tags, corrections, disclosures, share row, author bio, more from author, newsletter.
 *
 * @package Techvenza\Core
 * @var array    $attributes
 * @var WP_Block $block
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_post = get_post( $block->context['postId'] ?? null );
$tv_a    = $tv_post ? R::article( $tv_post ) : null;
if ( ! $tv_a ) {
	return;
}
$tv_out = '';

$tv_tags = wp_get_post_tags( $tv_post->ID );
if ( $tv_tags ) {
	$tv_out .= '<div class="chip-row">' . implode( '', array_map( static fn( $t ) => sprintf( '<a class="tag" href="%s" rel="tag">#%s</a>', esc_url( (string) get_tag_link( $t ) ), esc_html( $t->name ) ), $tv_tags ) ) . '</div>';
}

$tv_disclose = array();
if ( get_post_meta( $tv_post->ID, 'tv_ai_disclosure', true ) ) {
	$tv_txt        = (string) get_post_meta( $tv_post->ID, 'tv_ai_disclosure_text', true );
	$tv_disclose[] = $tv_txt ? $tv_txt : __( 'The author used AI tools during research or editing. All facts were verified by the author and our editors.', 'techvenza-core' );
}
$tv_conflict = (string) get_post_meta( $tv_post->ID, 'tv_conflict_disclosure', true );
if ( $tv_conflict ) {
	$tv_disclose[] = $tv_conflict;
}
if ( $tv_a['sponsored'] ) {
	$tv_sponsor = (string) get_post_meta( $tv_post->ID, 'tv_sponsor_name', true );
	/* translators: %s: sponsor */
	$tv_disclose[] = $tv_sponsor ? sprintf( __( 'This article was sponsored by %s. Our editors retained full editorial control.', 'techvenza-core' ), $tv_sponsor ) : __( 'This is sponsored content. Our editors retained full editorial control.', 'techvenza-core' );
}
if ( $tv_disclose ) {
	$tv_out .= '<aside class="tv-disclosure" aria-label="' . esc_attr__( 'Disclosure', 'techvenza-core' ) . '"><h2>' . esc_html__( 'Disclosure', 'techvenza-core' ) . '</h2>' . implode( '', array_map( static fn( $d ) => '<p>' . esc_html( $d ) . '</p>', $tv_disclose ) ) . '</aside>';
}
$tv_corr = array_filter( (array) get_post_meta( $tv_post->ID, 'tv_corrections', true ), static fn( $c ) => is_array( $c ) && ! empty( $c['text'] ) );
if ( $tv_corr ) {
	$tv_li = '';
	foreach ( $tv_corr as $tv_c ) {
		$tv_li .= sprintf( '<li>%s%s</li>', ! empty( $tv_c['date'] ) ? '<time datetime="' . esc_attr( $tv_c['date'] ) . '"><strong>' . esc_html( R::date( (string) $tv_c['date'] ) ) . ':</strong></time> ' : '', esc_html( $tv_c['text'] ) );
	}
	$tv_out .= '<aside class="tv-corrections" id="corrections" aria-label="' . esc_attr__( 'Corrections', 'techvenza-core' ) . '"><h2>' . esc_html__( 'Corrections', 'techvenza-core' ) . '</h2><ul>' . $tv_li . '</ul></aside>';
}

$tv_out .= sprintf(
	'<div class="share-row"><span class="label" style="margin-right:8px">%1$s</span><button class="btn btn-secondary btn-sm" type="button" data-action="copyLink" data-url="%2$s">%3$s <span data-copy-label>%4$s</span></button><button class="btn btn-secondary btn-sm" type="button" data-action="share" data-title="%5$s" data-url="%2$s">%6$s %7$s</button><button class="btn btn-secondary btn-sm" type="button" data-action="bookmark" data-id="%8$d" aria-pressed="false">%9$s <span data-bm-label>%10$s</span></button></div>',
	esc_html__( 'Share this story', 'techvenza-core' ),
	esc_url( $tv_a['url'] ),
	R::icon( 'link' ),
	esc_html__( 'Copy link', 'techvenza-core' ),
	esc_attr( $tv_a['title'] ),
	R::icon( 'share' ),
	esc_html__( 'Share', 'techvenza-core' ),
	(int) $tv_a['id'],
	R::icon( 'bookmark' ),
	esc_html__( 'Save', 'techvenza-core' )
);

$tv_au = $tv_a['author'];
if ( $tv_au ) {
	$tv_first = strtok( $tv_au['name'], ' ' );
	$tv_out  .= sprintf(
		'<section class="bio-card" aria-label="%1$s">%2$s<div><span class="eyebrow">%1$s</span><h3 class="mt-4"><a href="%3$s">%4$s</a></h3><div class="muted" style="font-size:var(--t-meta)">%5$s</div><p>%6$s</p><a class="sec-link mt-4" style="font-size:var(--t-meta)" href="%3$s">%7$s %8$s</a></div></section>',
		esc_attr__( 'About the author', 'techvenza-core' ),
		R::avatar( $tv_au, 'lg' ),
		esc_url( $tv_au['url'] ),
		esc_html( $tv_au['name'] ),
		esc_html( $tv_au['title'] ),
		esc_html( wp_strip_all_tags( $tv_au['bio'] ) ),
		/* translators: %s: author first name */
		esc_html( sprintf( __( 'All stories by %s', 'techvenza-core' ), $tv_first ) ),
		R::icon( 'arrowRight' )
	);
	if ( ! empty( $attributes['showMore'] ) ) {
		$tv_more = Query::posts(
			array(
				'author'         => (int) $tv_au['id'],
				'posts_per_page' => 3,
				'post__not_in'   => array( $tv_post->ID ),
			)
		);
		if ( $tv_more ) {
			/* translators: %s: author name */
			$tv_out .= sprintf( '<section aria-labelledby="moreAuth"><h2 class="h3 mb-5" id="moreAuth">%s</h2><div class="grid" style="gap:var(--s5)">%s</div></section>', esc_html( sprintf( __( 'More from %s', 'techvenza-core' ), $tv_au['name'] ) ), R::cards( $tv_more, 'row' ) );
		}
	}
}
if ( ! empty( $attributes['showNewsletter'] ) ) {
	$tv_out .= R::newsletter(
		array(
			'compact' => true,
			'source'  => 'article',
		)
	);
}
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'article-end' ) );
printf( '<div %s>%s</div>', $tv_wrapper, $tv_out ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
