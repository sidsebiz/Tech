<?php
/**
 * Popular this week (by views) with optional newsletter sidebar.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'popular' ) ) {
	return;
}
$tv_posts = Query::trending( (int) $attributes['count'], (string) $attributes['period'], array(), array(), false );
if ( ! $tv_posts ) {
	return;
}
$tv_items = '';
foreach ( $tv_posts as $tv_p ) {
	$tv_a      = R::article( $tv_p );
	$tv_views  = Query::views( $tv_p->ID, (string) $attributes['period'] );
	$tv_items .= sprintf(
		'<li class="rank-item"><div class="card-body">%s<h3 class="card-title"><a href="%s"><span class="u-line">%s</span></a></h3><div class="meta"><span>%s</span>%s</div></div></li>',
		R::chip( $tv_a['cat'] ),
		esc_url( $tv_a['url'] ),
		esc_html( $tv_a['title'] ),
		esc_html( $tv_a['author']['name'] ?? '' ),
		/* translators: %s: formatted number of reads */
		$tv_views ? '<span class="dot"></span><span>' . esc_html( sprintf( __( '%s reads', 'techvenza-core' ), number_format_i18n( $tv_views ) ) ) . '</span>' : ''
	);
}
$tv_head  = R::sec_head(
	array(
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'desc'    => $attributes['description'],
		'accent'  => 'var(--coral)',
	)
);
$tv_main  = '<div>' . $tv_head . '<ol class="rank-list">' . $tv_items . '</ol></div>';
$tv_inner = ! empty( $attributes['showNewsletter'] ) && Options::section_enabled( 'newsletter' )
	? '<div class="layout-sidebar">' . $tv_main . '<aside class="sidebar-sticky" aria-label="' . esc_attr__( 'Newsletter', 'techvenza-core' ) . '">' . R::newsletter(
		array(
			'compact' => true,
			'source'  => 'popular',
		)
	) . '</aside></div>'
	: $tv_main;
echo R::section( $tv_inner, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
