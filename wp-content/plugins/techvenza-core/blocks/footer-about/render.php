<?php
/**
 * Footer about column.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Frontend\Brand;
use Techvenza\Core\Settings\Options;

$tv_text = (string) ( $attributes['text'] ?? '' );
$tv_text = '' !== $tv_text ? $tv_text : (string) Options::get( 'footer_about' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'footer-about' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo Brand::logo( 'dark', 'class="logo on-dark"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p><?php echo esc_html( $tv_text ); ?></p>
	<?php echo Brand::social(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
