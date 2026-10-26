<?php
/**
 * Featured stories: lead + two supporting.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( ! Options::section_enabled( 'featured-grid' ) ) {
	return;
}
$tv_lead = null;
$tv_side = array();
if ( 'manual' === $attributes['mode'] ) {
	$tv_lead = $attributes['lead'] ? get_post( (int) $attributes['lead'] ) : null;
	$tv_side = array_filter( array_map( 'get_post', array_map( 'intval', (array) $attributes['side'] ) ) );
}
if ( ! $tv_lead ) {
	$tv_setting = (int) Options::get( 'home_lead_post', 0 );
	$tv_lead    = $tv_setting ? get_post( $tv_setting ) : null;
	if ( ! $tv_lead || 'publish' !== $tv_lead->post_status ) {
		$tv_found = Query::flagged( 'tv_is_featured', 1 );
		$tv_lead  = $tv_found ? $tv_found[0] : ( Query::latest( 1 )[0] ?? null );
	}
}
if ( ! $tv_lead ) {
	return;
}
if ( count( $tv_side ) < 2 ) {
	$tv_exclude = array_merge( array( $tv_lead->ID ), wp_list_pluck( $tv_side, 'ID' ) );
	$tv_more    = Query::flagged( 'tv_is_featured', 2 - count( $tv_side ), $tv_exclude );
	if ( count( $tv_side ) + count( $tv_more ) < 2 ) {
		$tv_more = array_merge( $tv_more, Query::latest( 2 - count( $tv_side ) - count( $tv_more ), array_merge( $tv_exclude, wp_list_pluck( $tv_more, 'ID' ) ) ) );
	}
	$tv_side = array_merge( $tv_side, $tv_more );
}
Query::mark_shown( array_merge( array( $tv_lead ), $tv_side ) );
$tv_inner = '<h2 class="sr-only">' . esc_html__( 'Featured stories', 'techvenza-core' ) . '</h2><div class="feature-grid"><div class="feature-main">'
	. R::card( $tv_lead, 'lg', array( 'eager' => true ) ) . '</div><div class="feature-side">' . R::cards( $tv_side, 'md' ) . '</div></div>';
echo R::section( $tv_inner, array_merge( $attributes, array( 'spacing' => 'tight' ) ), 'tv-pt-0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
