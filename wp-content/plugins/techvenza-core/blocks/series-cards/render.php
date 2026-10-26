<?php
/**
 * Featured series cards (auto from the Series taxonomy, or manual items).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Support\Render as R;

$tv_items = array();
if ( 'auto' === $attributes['mode'] && taxonomy_exists( 'tv_series' ) ) {
	$tv_terms = get_terms(
		array(
			'taxonomy'   => 'tv_series',
			'hide_empty' => true,
			'number'     => 6,
			'meta_key'   => 'tv_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( ! is_array( $tv_terms ) || ! $tv_terms ) {
		$tv_terms = get_terms(
			array(
				'taxonomy'   => 'tv_series',
				'hide_empty' => true,
				'number'     => 3,
			)
		);
	}
	foreach ( is_array( $tv_terms ) ? $tv_terms : array() as $tv_t ) {
		$tv_items[] = array(
			'title'    => $tv_t->name,
			/* translators: %d: number of parts */
			'parts'    => sprintf( _n( '%d-part series', '%d-part series', (int) $tv_t->count, 'techvenza-core' ), (int) $tv_t->count ),
			'text'     => $tv_t->description,
			'category' => (string) get_term_meta( $tv_t->term_id, 'tv_category', true ),
			'url'      => (string) get_term_link( $tv_t ),
		);
	}
}
if ( ! $tv_items ) {
	$tv_items = (array) $attributes['items'];
}
$tv_cards = '';
foreach ( $tv_items as $tv_s ) {
	if ( empty( $tv_s['title'] ) ) {
		continue;
	}
	$tv_cat    = CategoryMeta::get( (string) ( $tv_s['category'] ?? '' ) );
	$tv_cards .= sprintf(
		'<a class="series-card reveal" style="%s" href="%s"><span class="parts">%s</span><h3>%s</h3><p>%s</p><span class="sec-link" style="color:var(--ink);margin-top:auto">%s %s</span></a>',
		esc_attr( CategoryMeta::style( $tv_cat ) ),
		esc_url( R::url( (string) ( $tv_s['url'] ?? '' ) ) ),
		esc_html( $tv_s['parts'] ?? '' ),
		esc_html( $tv_s['title'] ),
		esc_html( $tv_s['text'] ?? '' ),
		esc_html__( 'Start the series', 'techvenza-core' ),
		R::icon( 'arrowRight' )
	);
}
if ( ! $tv_cards ) {
	return;
}
$tv_head = R::sec_head(
	array(
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'desc'    => $attributes['description'],
		'accent'  => 'var(--coral)',
	)
);
echo R::section( $tv_head . '<div class="series">' . $tv_cards . '</div>', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
