<?php
/**
 * Menu block (classic menu location).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Frontend\Menus;

echo Menus::render( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Menus::render().
