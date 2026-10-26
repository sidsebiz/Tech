<?php
/**
 * Reader dashboard (signed-in readers; never cached).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Portal\Reader;
use Techvenza\Core\Support\Render as R;

if ( ! is_user_logged_in() ) {
	echo R::section( R::empty_state( 'lock', __( 'Sign in to see your dashboard', 'techvenza-core' ), __( 'Your saved stories, reading history and topics live here.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( wp_login_url( (string) get_permalink() ) ), esc_html__( 'Log in', 'techvenza-core' ) ) ), array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block tv-app' ) );
printf( '<div %s>%s</div>', $tv_wrapper, Reader::render() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
