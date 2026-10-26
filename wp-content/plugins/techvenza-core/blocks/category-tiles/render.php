<?php
/**
 * Category tiles.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'category-tiles' ) ) {
	return;
}
switch ( $attributes['mode'] ) {
	case 'manual':
		$tv_cats = array_filter( array_map( array( CategoryMeta::class, 'get' ), (array) $attributes['categories'] ) );
		break;
	case 'all':
		$tv_cats = CategoryMeta::all();
		break;
	default:
		$tv_cats = CategoryMeta::all( true );
}
$tv_guides = 'guide' === $attributes['countType'];
$tv_tiles  = '';
foreach ( array_slice( $tv_cats, 0, (int) $attributes['count'] ) as $tv_c ) {
	$tv_n = $tv_c['count'];
	$tv_u = $tv_c['url'];
	if ( $tv_guides ) {
		$tv_n = count(
			Query::ids(
				array(
					'posts_per_page' => 50,
					'cat'            => $tv_c['id'],
					'meta_key'       => 'tv_article_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => 'guide', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
		if ( ! $tv_n ) {
			continue;
		}
		$tv_u = add_query_arg(
			array(
				'cat'  => $tv_c['slug'],
				'type' => 'guide',
			),
			R::page_url( 'explore' )
		);
		/* translators: %d: number of guides */
		$tv_label = sprintf( _n( '%d guide', '%d guides', $tv_n, 'techvenza-core' ), $tv_n );
	} else {
		/* translators: %d: number of stories */
		$tv_label = sprintf( _n( '%d story', '%d stories', $tv_n, 'techvenza-core' ), $tv_n );
	}
	$tv_tiles .= sprintf(
		'<a class="cat-tile reveal" href="%s" style="%s%s">%s<div><h3>%s</h3>%s</div></a>',
		esc_url( $tv_u ),
		esc_attr( CategoryMeta::style( $tv_c ) ),
		! empty( $attributes['compact'] ) ? ';min-height:110px' : '',
		R::icon( $tv_c['icon'], 'ico' ),
		esc_html( $tv_c['name'] ),
		empty( $attributes['compact'] ) ? '<span class="n">' . esc_html( $tv_label ) . '</span>' : ''
	);
}
if ( ! $tv_tiles ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow'   => $attributes['eyebrow'],
		'heading'   => $attributes['heading'],
		'desc'      => $attributes['description'],
		'linkLabel' => $attributes['linkLabel'],
		'linkUrl'   => $attributes['linkUrl'],
		'accent'    => 'var(--violet)',
	)
);
echo R::section( $tv_head . '<div class="cat-tiles">' . $tv_tiles . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
