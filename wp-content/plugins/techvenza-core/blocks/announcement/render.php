<?php
/**
 * Announcement strip.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

if ( ! Options::section_enabled( 'announcement' ) ) {
	return;
}
if ( ! empty( $attributes['useSettings'] ) ) {
	if ( ! Options::enabled( 'announce_enabled' ) ) {
		return;
	}
	$tv_now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with site-local datetime-local values.
	$tv_start = (string) Options::get( 'announce_start', '' );
	$tv_end   = (string) Options::get( 'announce_end', '' );
	if ( ( $tv_start && strtotime( $tv_start ) > $tv_now ) || ( $tv_end && strtotime( $tv_end ) < $tv_now ) ) {
		return;
	}
	$tv_pill  = (string) Options::get( 'announce_pill' );
	$tv_text  = (string) Options::get( 'announce_text' );
	$tv_label = (string) Options::get( 'announce_link_label' );
	$tv_url   = (string) Options::get( 'announce_link_url' );
} else {
	$tv_pill  = (string) $attributes['pill'];
	$tv_text  = (string) $attributes['text'];
	$tv_label = (string) $attributes['linkLabel'];
	$tv_url   = (string) $attributes['linkUrl'];
}
if ( '' === trim( $tv_text ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'strip tv-block' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><div class="container">
	<?php
	if ( $tv_pill ) :
		?>
		<span class="pill"><?php echo esc_html( $tv_pill ); ?></span><?php endif; ?>
	<span><?php echo esc_html( $tv_text ); ?></span>
	<?php
	if ( $tv_label && $tv_url ) :
		?>
		<a href="<?php echo esc_url( R::url( $tv_url ) ); ?>"><?php echo esc_html( $tv_label ); ?> <?php echo R::icon( 'arrowRight' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endif; ?>
</div></div>
