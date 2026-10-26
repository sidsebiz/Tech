<?php
/**
 * Page hero: breadcrumbs, title and lead (auto mode reads the current page title/excerpt).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

$tv_a     = $attributes;
$tv_post  = get_post();
$tv_title = (string) $tv_a['title'];
$tv_lead  = (string) $tv_a['lead'];
if ( ( $tv_a['auto'] || '' === $tv_title ) && $tv_post ) {
	$tv_title = '' !== $tv_title ? $tv_title : get_the_title( $tv_post );
	$tv_lead  = '' !== $tv_lead ? $tv_lead : (string) $tv_post->post_excerpt;
}
$tv_crumbs = '';
if ( $tv_a['breadcrumbs'] ) {
	$tv_items = array( array( __( 'Home', 'techvenza-core' ), home_url( '/' ) ) );
	if ( $tv_post && 'page' === $tv_post->post_type ) {
		foreach ( array_reverse( get_post_ancestors( $tv_post ) ) as $tv_anc ) {
			$tv_items[] = array( get_the_title( $tv_anc ), (string) get_permalink( $tv_anc ) );
		}
		if ( 'apply' === $tv_post->post_name ) {
			$tv_items[] = array( __( 'Become an Author', 'techvenza-core' ), R::page_url( 'become-an-author' ) );
		}
	}
	$tv_items[] = array( $tv_post ? get_the_title( $tv_post ) : $tv_title, '' );
	$tv_crumbs  = R::crumbs( $tv_items );
}
$tv_classes = 'page-hero' . ( $tv_a['tint'] ? ' tinted' : '' ) . ( $tv_a['display'] ? ' hero' : '' );
$tv_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'tv-block ' . $tv_classes,
		'style' => $tv_a['tint'] ? '--cs:' . sanitize_hex_color( $tv_a['tint'] ) : '',
	)
);
printf(
	'<section %1$s><div class="container">%2$s%3$s<h1 class="%4$s mt-5"%5$s>%6$s</h1>%7$s</div></section>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_crumbs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$tv_a['eyebrow'] ? '<span class="eyebrow mt-6" style="display:flex">' . esc_html( $tv_a['eyebrow'] ) . '</span>' : '',
	$tv_a['display'] ? 'display' : 'page-title',
	$tv_a['display'] ? ' style="max-width:16ch"' : '',
	esc_html( $tv_title ),
	$tv_lead ? '<p class="lead">' . esc_html( $tv_lead ) . '</p>' : ''
);
