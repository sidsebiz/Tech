<?php
/**
 * Editor's picks mosaic (optional category filter chips, enhanced in place by live.js).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Blocks\Params;
use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'editors-picks' ) ) {
	return;
}
$tv_filter = ! empty( $attributes['filter'] );
$tv_active = $tv_filter ? Params::get( 'pick' ) : '';
$tv_all    = Query::flagged( 'tv_is_editors_pick', $tv_filter ? 40 : (int) $attributes['count'], ! empty( $attributes['excludeShown'] ) ? Query::shown() : array() );
$tv_posts  = $tv_active ? array_values( array_filter( $tv_all, static fn( $p ) => ( CategoryMeta::for_post( $p->ID )['slug'] ?? '' ) === $tv_active ) ) : $tv_all;
if ( ! $tv_all ) {
	return;
}
$tv_chips = '';
if ( $tv_filter ) {
	$tv_slugs = array();
	foreach ( $tv_all as $tv_p ) {
		$tv_c = CategoryMeta::for_post( $tv_p->ID );
		if ( $tv_c ) {
			$tv_slugs[ $tv_c['slug'] ] = $tv_c;
		}
	}
	$tv_chips = sprintf( '<div class="chip-row" role="group" aria-label="%s"><a class="filter-chip" href="%s" data-live-link aria-current="%s">%s</a>', esc_attr__( 'Filter picks by category', 'techvenza-core' ), esc_url( Params::url( array() ) ), $tv_active ? 'false' : 'true', esc_html__( 'All', 'techvenza-core' ) );
	foreach ( $tv_slugs as $tv_c ) {
		$tv_chips .= sprintf( '<a class="filter-chip" href="%s" data-live-link aria-current="%s"><span class="dot-c" style="--c:%s"></span>%s</a>', esc_url( Params::url( array( 'pick' => $tv_c['slug'] ) ) ), $tv_active === $tv_c['slug'] ? 'true' : 'false', esc_attr( $tv_c['color'] ), esc_html( $tv_c['short'] ) );
	}
	$tv_chips .= '</div>';
}
$tv_cards = '';
foreach ( array_slice( $tv_posts, 0, $tv_filter ? 12 : (int) $attributes['count'] ) as $tv_i => $tv_p ) {
	$tv_cards .= R::card( $tv_p, $tv_i < 2 ? 'md' : 'default' );
}
$tv_head = R::sec_head(
	array(
		'eyebrow'   => $attributes['eyebrow'],
		'heading'   => $attributes['heading'],
		'desc'      => $tv_filter ? '' : $attributes['description'],
		'linkLabel' => $tv_filter ? '' : $attributes['linkLabel'],
		'linkUrl'   => $attributes['linkUrl'],
		'accent'    => 'var(--amber)',
	)
);
if ( $tv_filter ) {
	$tv_head = str_replace( '</div></div>', '</div>' . $tv_chips . '</div>', $tv_head );
}
Query::mark_shown( $tv_posts );
$tv_body  = $tv_cards ? '<div class="picks">' . $tv_cards . '</div>' : R::empty_state( 'star', __( 'No picks in this category', 'techvenza-core' ), __( 'Choose another category.', 'techvenza-core' ) );
$tv_inner = $tv_head . '<div aria-live="polite">' . $tv_body . '</div>';
if ( $tv_filter ) {
	$tv_inner = R::live( 'editors-picks', $attributes, $tv_inner );
}
echo R::section( $tv_inner, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
