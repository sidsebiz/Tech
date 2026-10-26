<?php
/**
 * Newsletter sign-up block.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( is_front_page() && ! Options::section_enabled( 'newsletter' ) ) {
	return;
}
$tv_html = R::newsletter(
	array(
		'compact' => 'compact' === $attributes['layout'],
		'eyebrow' => $attributes['eyebrow'],
		'heading' => $attributes['heading'],
		'text'    => $attributes['text'],
		'source'  => 'newsletter-block',
	)
);
if ( empty( $attributes['wrap'] ) ) {
	echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'tv-block' ) ) . '>' . $tv_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
echo R::section( $tv_html, $attributes, 'tv-pt-0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
