<?php
/**
 * Featured authors row.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'featured-authors' ) ) {
	return;
}
$tv_authors = Query::authors( array( 'featured' => true ) );
if ( ! $tv_authors ) {
	$tv_authors = Query::authors( array( 'by_count' => true ) );
}
$tv_authors = array_slice( $tv_authors, 0, (int) $attributes['count'] );
if ( ! $tv_authors ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow'   => $attributes['eyebrow'],
		'heading'   => $attributes['heading'],
		'desc'      => $attributes['description'],
		'linkLabel' => $attributes['linkLabel'],
		'linkUrl'   => $attributes['linkUrl'],
		'accent'    => 'var(--teal)',
	)
);
echo R::section( $tv_head . '<div class="author-row">' . implode( '', array_map( array( R::class, 'author_card' ), $tv_authors ) ) . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
