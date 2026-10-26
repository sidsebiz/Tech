<?php
/**
 * Generic section wrapper: optional heading, inner blocks inside the container.
 *
 * @package Techvenza\Core
 * @var array  $attributes
 * @var string $content
 */

use Techvenza\Core\Support\Render as R;

$tv_head = '';
if ( $attributes['eyebrow'] || $attributes['heading'] || $attributes['description'] ) {
	$tv_head = R::sec_head(
		array(
			'eyebrow' => $attributes['eyebrow'],
			'heading' => $attributes['heading'],
			'desc'    => $attributes['description'],
		)
	);
}
$tv_inner = $tv_head . '<div class="tv-section-body reveal">' . $content . '</div>';
if ( $attributes['narrow'] ) {
	$tv_inner = '<div class="narrow" style="margin-inline:0">' . $tv_inner . '</div>';
}
echo R::section( $tv_inner, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
