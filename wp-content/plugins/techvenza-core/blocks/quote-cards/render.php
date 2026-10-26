<?php
/**
 * Deep dives / perspectives as quote cards.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'quote-cards' ) ) {
	return;
}
$tv_posts = Query::by_type( array_map( 'sanitize_key', (array) $attributes['types'] ), (int) $attributes['count'] );
if ( ! $tv_posts ) {
	return;
}
$tv_cards = '';
foreach ( $tv_posts as $tv_p ) {
	$tv_a      = R::article( $tv_p );
	$tv_quote  = $tv_a['points'][0] ?? $tv_a['excerpt'];
	$tv_cards .= sprintf(
		'<div class="quote-card reveal">%s<blockquote>%s</blockquote><div><h3 class="card-title"><a href="%s"><span class="u-line">%s</span></a></h3><div class="mt-4">%s</div></div></div>',
		R::chip( $tv_a['cat'] ),
		esc_html( $tv_quote ),
		esc_url( $tv_a['url'] ),
		esc_html( $tv_a['title'] ),
		R::byline( $tv_a )
	);
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
echo R::section( $tv_head . '<div class="grid g-3">' . $tv_cards . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
