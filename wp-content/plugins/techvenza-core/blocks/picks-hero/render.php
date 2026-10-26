<?php
/**
 * Editor's Picks dark hero with the lead pick.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Support\Render as R;

$tv_a    = $attributes;
$tv_lead = $tv_a['post'] ? get_post( (int) $tv_a['post'] ) : null;
if ( ! $tv_lead || 'publish' !== $tv_lead->post_status ) {
	$tv_p    = Query::flagged( 'tv_is_editors_pick', 1 );
	$tv_lead = $tv_p ? $tv_p[0] : null;
}
$tv_post = get_post();
$tv_wrap = get_block_wrapper_attributes( array( 'class' => 'tv-block ep-hero' ) );
printf(
	'<section %1$s><div class="container"><nav class="breadcrumbs" aria-label="%2$s" style="--muted:#94A3B8"><ol><li><a href="%3$s" style="color:#CBD5E1">%4$s</a></li><li><span aria-current="page" style="color:#fff">%5$s</span></li></ol></nav>
	<div class="ep-lead mt-6"><div>%6$s<h1 class="display mt-5" style="font-size:clamp(2.5rem,1.5rem + 3.5vw,4.5rem)">%7$s</h1>%8$s</div><div>%9$s</div></div></div></section>',
	$tv_wrap, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_attr__( 'Breadcrumb', 'techvenza-core' ),
	esc_url( home_url( '/' ) ),
	esc_html__( 'Home', 'techvenza-core' ),
	esc_html( $tv_post ? get_the_title( $tv_post ) : $tv_a['eyebrow'] ),
	$tv_a['eyebrow'] ? '<span class="eyebrow">' . esc_html( $tv_a['eyebrow'] ) . '</span>' : '',
	esc_html( $tv_a['title'] ),
	$tv_a['lead'] ? '<p class="lead mt-5" style="color:#CBD5E1">' . esc_html( $tv_a['lead'] ) . '</p>' : '',
	$tv_lead ? R::card( $tv_lead, 'md', array( 'eager' => true ) ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
if ( $tv_lead ) {
	Query::mark_shown( array( $tv_lead ) );
}
