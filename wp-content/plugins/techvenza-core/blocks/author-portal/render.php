<?php
/**
 * Author portal (contributors and editors; never cached).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Portal\Author;
use Techvenza\Core\Support\Render as R;

if ( ! is_user_logged_in() ) {
	echo R::section( R::empty_state( 'lock', __( 'Sign in to the author portal', 'techvenza-core' ), __( 'Drafts, submissions, feedback and analytics for contributors.', 'techvenza-core' ), sprintf( '<a class="btn btn-primary btn-sm" href="%s">%s</a>', esc_url( wp_login_url( (string) get_permalink() ) ), esc_html__( 'Log in', 'techvenza-core' ) ) ), array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
$tv_wrapper = get_block_wrapper_attributes( array( 'class' => 'tv-block tv-app' ) );
printf( '<div %s>%s</div>', $tv_wrapper, Author::render() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
