<?php
/**
 * Search overlay with live suggestions (populated by frontend.js from REST).
 *
 * @package Techvenza\Core
 * @var array $attributes
 */

use Techvenza\Core\Support\Render as R;

?>
<div class="search-overlay" id="searchOverlay" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Search the site', 'techvenza-core' ); ?>">
	<div class="search-panel">
		<div class="row between mb-5">
			<span class="eyebrow"><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Search %s', 'techvenza-core' ), get_bloginfo( 'name' ) ) ); ?></span>
			<button class="icon-btn" type="button" aria-label="<?php esc_attr_e( 'Close search', 'techvenza-core' ); ?>" data-close-search><?php echo R::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</div>
		<form class="search-big" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-search-form>
			<?php echo R::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<label class="sr-only" for="overlaySearch"><?php esc_html_e( 'Search articles, authors and topics', 'techvenza-core' ); ?></label>
			<input id="overlaySearch" name="s" type="search" placeholder="<?php esc_attr_e( 'Search articles, authors, topics…', 'techvenza-core' ); ?>" autocomplete="off" aria-controls="searchSuggest" aria-autocomplete="list">
			<button class="btn btn-primary btn-sm" type="submit"><?php esc_html_e( 'Search', 'techvenza-core' ); ?></button>
		</form>
		<div class="search-suggest" id="searchSuggest" role="listbox" aria-label="<?php esc_attr_e( 'Suggestions', 'techvenza-core' ); ?>"></div>
	</div>
</div>
