<?php
/**
 * Logo block.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Frontend\Brand;

echo Brand::logo( (string) ( $attributes['variant'] ?? 'auto' ), get_block_wrapper_attributes( array( 'class' => 'logo' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Brand::logo().
