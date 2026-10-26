<?php
/**
 * Category spotlight in four compositions (dark, split, grid, tint).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'category-spotlight' ) ) {
	return;
}
$tv_cat = CategoryMeta::get( (string) $attributes['category'] );
if ( ! $tv_cat ) {
	return;
}
$tv_slugs = array_merge( array( $tv_cat['slug'] ), (array) $attributes['extraCategories'] );
$tv_count = max( 2, (int) $attributes['count'] );
$tv_posts = Query::latest( $tv_count, Query::shown(), $tv_slugs );
if ( count( $tv_posts ) < 2 ) {
	$tv_posts = Query::latest( $tv_count, array(), $tv_slugs );
}
if ( ! $tv_posts ) {
	return;
}
Query::mark_shown( $tv_posts );
$tv_variant = (string) $attributes['variant'];
$tv_eyebrow = $attributes['eyebrow'] ? $attributes['eyebrow'] : sprintf( /* translators: %s: category */ __( 'Spotlight · %s', 'techvenza-core' ), $tv_cat['name'] );
$tv_heading = $attributes['heading'] ? $attributes['heading'] : $tv_cat['name'];
$tv_desc    = $attributes['description'] ? $attributes['description'] : $tv_cat['intro'];
/* translators: %s: category short name */
$tv_link = $attributes['linkLabel'] ? $attributes['linkLabel'] : sprintf( __( 'All %s coverage', 'techvenza-core' ), $tv_cat['short'] );
$tv_hid  = wp_unique_id( 'spot-' );

switch ( $tv_variant ) {
	case 'split':
		$tv_lead  = R::article( $tv_posts[0] );
		$tv_inner = sprintf(
			'<div class="split"><div class="reveal"><span class="eyebrow" style="--accent:%1$s">%2$s</span><h2 class="h2 mt-4" id="%3$s">%4$s</h2><p class="lead mt-4">%5$s</p>%6$s</div><div class="grid" style="gap:var(--s6)">%7$s<a class="sec-link" href="%8$s">%9$s %10$s</a></div></div>',
			esc_attr( $tv_cat['color'] ),
			esc_html( $tv_eyebrow ),
			esc_attr( $tv_hid ),
			esc_html( $tv_heading ),
			esc_html( $tv_desc ),
			$attributes['quote'] ? sprintf( '<div class="quote-card mt-6"><blockquote>%s</blockquote>%s</div>', esc_html( $attributes['quote'] ), R::byline( (array) $tv_lead, array( 'date' => false ) ) ) : '',
			R::cards( array_slice( $tv_posts, 0, 3 ), 'row' ),
			esc_url( $tv_cat['url'] ),
			esc_html( $tv_link ),
			R::icon( 'arrowRight' )
		);
		echo R::section( $tv_inner, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		break;

	case 'grid':
		$tv_head = R::sec_head(
			array(
				'eyebrow'   => $tv_eyebrow,
				'heading'   => $tv_heading,
				'desc'      => $tv_desc,
				'linkLabel' => $tv_link,
				'linkUrl'   => $tv_cat['url'],
				'accent'    => $tv_cat['color'],
				'id'        => $tv_hid,
			)
		);
		echo R::section( $tv_head . '<div class="grid g-3">' . R::cards( array_slice( $tv_posts, 0, 3 ), 'boxed' ) . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		break;

	case 'tint':
		$tv_inner = sprintf(
			'<div class="spot tint" style="%1$s"><div class="sec-head"><div><span class="eyebrow" style="--accent:%2$s">%3$s</span><h2 class="h2 mt-4" id="%4$s">%5$s</h2><p>%6$s</p></div><a class="sec-link" href="%7$s">%8$s %9$s</a></div><div class="mfg-band">%10$s</div></div>',
			esc_attr( CategoryMeta::style( $tv_cat ) ),
			esc_attr( $tv_cat['color'] ),
			esc_html( $tv_eyebrow ),
			esc_attr( $tv_hid ),
			esc_html( $tv_heading ),
			esc_html( $tv_desc ),
			esc_url( $tv_cat['url'] ),
			esc_html( $tv_link ),
			R::icon( 'arrowRight' ),
			R::cards( array_slice( $tv_posts, 0, 3 ) )
		);
		echo R::section( $tv_inner, array_merge( $attributes, array( 'spacing' => 'tight' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		break;

	default:
		$tv_inner = sprintf(
			'<div class="spot dark"><div class="spot-grid"><div><span class="eyebrow">%1$s</span><h2 class="h2 mt-4" id="%2$s">%3$s</h2><p class="mt-4" style="max-width:48ch;color:#A5B4C8">%4$s</p><div class="mt-6">%5$s</div></div><div class="spot-list">%6$s<a class="btn btn-light" href="%7$s" style="justify-self:start">%8$s %9$s</a></div></div></div>',
			esc_html( $tv_eyebrow ),
			esc_attr( $tv_hid ),
			esc_html( $tv_heading ),
			esc_html( $tv_desc ),
			R::card( $tv_posts[0] ),
			R::cards( array_slice( $tv_posts, 1, 3 ), 'row' ),
			esc_url( $tv_cat['url'] ),
			esc_html( $tv_link ),
			R::icon( 'arrowRight' )
		);
		echo R::section( $tv_inner, array_merge( $attributes, array( 'spacing' => 'tight' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
