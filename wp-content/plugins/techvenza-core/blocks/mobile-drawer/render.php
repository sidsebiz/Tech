<?php
/**
 * Mobile drawer block.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Frontend\Menus;

echo Menus::drawer( (string) ( $attributes['location'] ?? 'mobile' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Menus::drawer().
