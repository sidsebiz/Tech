<?php
/**
 * Standalone table of contents: built client-side from the nearest .prose headings.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'toc' ) );
printf(
	'<nav %1$s aria-label="%2$s" data-toc data-toc-auto hidden><h2>%3$s</h2><ol></ol></nav>',
	$tv_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_attr__( 'Table of contents', 'techvenza-core' ),
	esc_html( $attributes['title'] )
);
