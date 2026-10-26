<?php
/**
 * Curated learning paths (ordered lists of articles with total reading time).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Support\Render as R;

$tv_html = '';
foreach ( (array) $attributes['paths'] as $tv_p ) {
	$tv_items = '';
	$tv_mins  = 0;
	foreach ( (array) ( $tv_p['posts'] ?? array() ) as $tv_id ) {
		$tv_a = R::article( (int) $tv_id );
		if ( ! $tv_a ) {
			continue;
		}
		$tv_mins  += (int) $tv_a['minutes'];
		$tv_items .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( $tv_a['url'] ), esc_html( $tv_a['title'] ) );
	}
	if ( ! $tv_items || empty( $tv_p['title'] ) ) {
		continue;
	}
	$tv_html .= sprintf(
		'<div class="path reveal" style="%s"><span class="level" style="color:var(--ci)">%s</span><h3 class="h3">%s</h3><ol>%s</ol><span class="meta">%s</span></div>',
		esc_attr( CategoryMeta::style( CategoryMeta::get( (string) ( $tv_p['category'] ?? '' ) ) ) ),
		esc_html( $tv_p['level'] ?? '' ),
		esc_html( $tv_p['title'] ),
		$tv_items,
		/* translators: %d: minutes */
		esc_html( sprintf( __( '%d min total', 'techvenza-core' ), $tv_mins ) )
	);
}
if ( ! $tv_html ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'desc'    => $attributes['description'],
		'accent'  => 'var(--teal)',
	)
);
echo R::section( $tv_head . '<div class="grid g-3">' . $tv_html . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
