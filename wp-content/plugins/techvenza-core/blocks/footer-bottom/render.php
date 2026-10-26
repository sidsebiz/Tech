<?php
/**
 * Footer bottom bar.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Frontend\Menus;
use Techvenza\Core\Settings\Options;

$tv_text = (string) ( $attributes['text'] ?? '' );
$tv_text = '' !== $tv_text ? $tv_text : (string) Options::get( 'footer_copyright' );
$tv_text = strtr(
	$tv_text,
	array(
		'{year}'    => wp_date( 'Y' ),
		'{site}'    => get_bloginfo( 'name' ),
		'{company}' => (string) Options::get( 'company_name', get_bloginfo( 'name' ) ),
	)
);
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'footer-bottom' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<span><?php echo esc_html( $tv_text ); ?></span>
	<div class="row" style="gap:20px">
		<?php
		echo Menus::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'location' => 'legal',
				'variant'  => 'bare',
			)
		);
		?>
		<button type="button" data-cookie-prefs style="color:inherit"><?php esc_html_e( 'Cookie preferences', 'techvenza-core' ); ?></button>
	</div>
</div>
