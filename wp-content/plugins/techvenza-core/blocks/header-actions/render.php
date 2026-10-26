<?php
/**
 * Header actions: search, account (hydrated client-side for cache safety), CTA, menu toggle.
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

$tv_label = (string) ( $attributes['ctaLabel'] ?? '' );
$tv_label = '' !== $tv_label ? $tv_label : (string) Options::get( 'header_cta_label' );
$tv_url   = (string) ( $attributes['ctaUrl'] ?? '' );
$tv_url   = '' !== $tv_url ? $tv_url : (string) Options::get( 'header_cta_url' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'header-actions' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
		<button class="icon-btn" type="button" aria-label="<?php esc_attr_e( 'Search', 'techvenza-core' ); ?>" aria-controls="searchOverlay" data-open-search><?php echo R::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
	<?php endif; ?>
	<?php if ( ! empty( $attributes['showAccount'] ) ) : ?>
		<div data-account class="row" style="gap:8px">
			<a class="btn btn-ghost btn-sm hide-md" href="<?php echo esc_url( R::page_url( 'login' ) ); ?>" data-login-link><?php esc_html_e( 'Log in', 'techvenza-core' ); ?></a>
		</div>
	<?php endif; ?>
	<?php if ( $tv_label ) : ?>
		<a class="btn btn-primary btn-sm btn-become hide-md" href="<?php echo esc_url( R::url( $tv_url ) ); ?>"><?php echo esc_html( $tv_label ); ?></a>
	<?php endif; ?>
	<button class="icon-btn menu-toggle" type="button" aria-label="<?php esc_attr_e( 'Open menu', 'techvenza-core' ); ?>" aria-expanded="false" aria-controls="drawer" data-open-drawer><?php echo R::icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
</div>
