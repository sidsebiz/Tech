<?php
/**
 * Partners: logos and links from Editorial → Partners.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

echo \Techvenza\Core\Seo\Links::block( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Links::block().
