<?php
/**
 * Category term meta: colours, icon, intro, "what to watch", hero image, SEO, homepage flag, order.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

use Techvenza\Core\Support\Icons;
use Techvenza\Core\Support\Color;

/**
 * Registers category meta, admin form fields and a normalised accessor.
 */
final class CategoryMeta {

	/**
	 * Meta keys → schema type.
	 */
	private const KEYS = array(
		'tv_short'           => 'string',
		'tv_color'           => 'string',
		'tv_ink'             => 'string',
		'tv_soft'            => 'string',
		'tv_icon'            => 'string',
		'tv_intro'           => 'string',
		'tv_watch'           => 'string',
		'tv_hero_image'      => 'integer',
		'tv_seo_title'       => 'string',
		'tv_seo_description' => 'string',
		'tv_show_on_home'    => 'boolean',
		'tv_order'           => 'integer',
	);

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'category_add_form_fields', array( self::class, 'add_fields' ) );
		add_action( 'category_edit_form_fields', array( self::class, 'edit_fields' ) );
		add_action( 'created_category', array( self::class, 'save' ) );
		add_action( 'edited_category', array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'admin_assets' ) );
		add_filter( 'manage_edit-category_columns', array( self::class, 'columns' ) );
		add_filter( 'manage_category_custom_column', array( self::class, 'column' ), 10, 3 );
		add_action( 'edited_term', array( self::class, 'flush_cache' ) );
		add_action( 'created_term', array( self::class, 'flush_cache' ) );
		add_action( 'delete_term', array( self::class, 'flush_cache' ) );
	}

	/**
	 * Register term meta for REST.
	 */
	public static function register_meta(): void {
		foreach ( self::KEYS as $key => $type ) {
			register_term_meta(
				'category',
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => static fn( $v ) => self::sanitize( $key, $v ),
					'auth_callback'     => static fn() => current_user_can( 'manage_categories' ),
				)
			);
		}
	}

	/**
	 * Sanitise one value.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( string $key, $value ) {
		switch ( self::KEYS[ $key ] ?? 'string' ) {
			case 'integer':
				return (int) $value;
			case 'boolean':
				return (bool) $value;
		}
		if ( in_array( $key, array( 'tv_color', 'tv_ink', 'tv_soft' ), true ) ) {
			return (string) sanitize_hex_color( (string) $value );
		}
		if ( 'tv_icon' === $key ) {
			return in_array( $value, Icons::names(), true ) ? $value : 'layers';
		}
		return in_array( $key, array( 'tv_intro', 'tv_watch', 'tv_seo_description' ), true ) ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
	}

	/**
	 * Normalised category data (mirrors the prototype CAT objects).
	 *
	 * @param \WP_Term|int|string $term Term, ID or slug.
	 * @return array<string,mixed>|null
	 */
	public static function get( $term ): ?array {
		if ( is_string( $term ) && ! is_numeric( $term ) ) {
			$term = get_term_by( 'slug', $term, 'category' );
		} elseif ( is_numeric( $term ) ) {
			$term = get_term( (int) $term, 'category' );
		}
		if ( ! $term instanceof \WP_Term ) {
			return null;
		}
		$cache_key = 'cat_' . $term->term_id;
		$cached    = wp_cache_get( $cache_key, 'techvenza' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$m     = static fn( string $k ) => get_term_meta( $term->term_id, $k, true );
		$color = $m( 'tv_color' ) ? $m( 'tv_color' ) : '#2563EB';
		$ink   = $m( 'tv_ink' ) ? $m( 'tv_ink' ) : Color::darken( $color, 0.18 );
		$soft  = $m( 'tv_soft' ) ? $m( 'tv_soft' ) : Color::tint( $color, 0.92 );
		$kids  = get_terms(
			array(
				'taxonomy'   => 'category',
				'parent'     => $term->term_id,
				'hide_empty' => false,
				'fields'     => 'all',
			)
		);
		$data  = array(
			'id'          => $term->term_id,
			'slug'        => $term->slug,
			'name'        => $term->name,
			'short'       => $m( 'tv_short' ) ? $m( 'tv_short' ) : $term->name,
			'description' => $term->description,
			'color'       => $color,
			'ink'         => $ink,
			'soft'        => $soft,
			'icon'        => $m( 'tv_icon' ) ? $m( 'tv_icon' ) : 'layers',
			'intro'       => $m( 'tv_intro' ) ? $m( 'tv_intro' ) : $term->description,
			'watch'       => (string) $m( 'tv_watch' ),
			'hero'        => (int) $m( 'tv_hero_image' ),
			'seo_title'   => (string) $m( 'tv_seo_title' ),
			'seo_desc'    => (string) $m( 'tv_seo_description' ),
			'home'        => metadata_exists( 'term', $term->term_id, 'tv_show_on_home' ) ? (bool) $m( 'tv_show_on_home' ) : true,
			'order'       => (int) $m( 'tv_order' ),
			'count'       => (int) $term->count,
			'parent'      => (int) $term->parent,
			'url'         => get_category_link( $term ),
			'sub'         => is_array( $kids ) ? array_map(
				static fn( $k ) => array(
					'slug' => $k->slug,
					'name' => $k->name,
					'id'   => $k->term_id,
				),
				$kids
			) : array(),
		);
		wp_cache_set( $cache_key, $data, 'techvenza', HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Top-level categories ordered by tv_order then name.
	 *
	 * @param bool $home_only Only categories flagged for the homepage.
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( bool $home_only = false ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'parent'     => 0,
				'hide_empty' => false,
				'exclude'    => array( (int) get_option( 'default_category' ) ),
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$c = self::get( $t );
			if ( $c && ( ! $home_only || $c['home'] ) ) {
				$out[] = $c;
			}
		}
		usort( $out, static fn( $a, $b ) => array( $a['order'], $a['name'] ) <=> array( $b['order'], $b['name'] ) );
		return $out;
	}

	/**
	 * Inline CSS custom properties for a category.
	 *
	 * @param array<string,mixed>|null $cat Category data.
	 */
	public static function style( ?array $cat ): string {
		if ( ! $cat ) {
			return '';
		}
		return sprintf( '--c:%s;--ci:%s;--cs:%s', $cat['color'], $cat['ink'], $cat['soft'] );
	}

	/**
	 * Primary category for a post (first non-default).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	public static function for_post( int $post_id ): ?array {
		$cats = get_the_category( $post_id );
		if ( ! $cats ) {
			return null;
		}
		usort( $cats, static fn( $a, $b ) => $a->parent <=> $b->parent );
		foreach ( $cats as $c ) {
			if ( (int) get_option( 'default_category' ) !== $c->term_id || 1 === count( $cats ) ) {
				return self::get( $c->parent ? $c->parent : $c );
			}
		}
		return self::get( $cats[0] );
	}

	/**
	 * Clear accessor cache.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function flush_cache( int $term_id ): void {
		wp_cache_delete( 'cat_' . $term_id, 'techvenza' );
		$term = get_term( $term_id );
		if ( $term instanceof \WP_Term && $term->parent ) {
			wp_cache_delete( 'cat_' . $term->parent, 'techvenza' );
		}
	}

	/**
	 * Admin assets for the colour pickers and media selector.
	 *
	 * @param string $hook Admin hook.
	 */
	public static function admin_assets( string $hook ): void {
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || 'category' !== $taxonomy ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'techvenza-term-meta', TECHVENZA_CORE_URL . 'assets/js/admin-term.js', array( 'wp-color-picker', 'jquery' ), TECHVENZA_CORE_VERSION, true );
		wp_localize_script(
			'techvenza-term-meta',
			'techvenzaTerm',
			array(
				/* translators: %s: contrast ratio */
				'lowContrast' => __( 'Low contrast (%s:1). Text in this colour may be hard to read on its tint — WCAG AA needs 4.5:1.', 'techvenza-core' ),
				/* translators: %s: contrast ratio */
				'okContrast'  => __( 'Contrast %s:1 — passes WCAG AA.', 'techvenza-core' ),
				'choose'      => __( 'Choose image', 'techvenza-core' ),
			)
		);
	}

	/**
	 * Field rows (shared by add and edit forms).
	 *
	 * @param array<string,mixed> $v    Current values.
	 * @param bool                $edit Edit form (table rows) vs add form (divs).
	 */
	private static function fields( array $v, bool $edit ): void {
		wp_nonce_field( 'techvenza_term_meta', 'techvenza_term_nonce' );
		$rows = array(
			'tv_short'           => array( __( 'Short label', 'techvenza-core' ), 'text', __( 'Used on chips, e.g. “AI”.', 'techvenza-core' ) ),
			'tv_color'           => array( __( 'Accent colour', 'techvenza-core' ), 'color', __( 'Dots, borders, markers.', 'techvenza-core' ) ),
			'tv_ink'             => array( __( 'Ink colour (text on tint)', 'techvenza-core' ), 'color', __( 'Leave empty to derive from the accent.', 'techvenza-core' ) ),
			'tv_soft'            => array( __( 'Soft background', 'techvenza-core' ), 'color', __( 'Tint used for chips and the category hero.', 'techvenza-core' ) ),
			'tv_icon'            => array( __( 'Icon', 'techvenza-core' ), 'icon', '' ),
			'tv_intro'           => array( __( 'Intro text', 'techvenza-core' ), 'textarea', __( 'Shown in the category hero.', 'techvenza-core' ) ),
			'tv_watch'           => array( __( '“What to watch” text', 'techvenza-core' ), 'textarea', __( 'Used in generated article summaries.', 'techvenza-core' ) ),
			'tv_hero_image'      => array( __( 'Hero image', 'techvenza-core' ), 'media', '' ),
			'tv_seo_title'       => array( __( 'SEO title', 'techvenza-core' ), 'text', '' ),
			'tv_seo_description' => array( __( 'SEO description', 'techvenza-core' ), 'textarea', '' ),
			'tv_show_on_home'    => array( __( 'Show on homepage', 'techvenza-core' ), 'toggle', __( 'Include in “Explore by category” tiles.', 'techvenza-core' ) ),
			'tv_order'           => array( __( 'Sort order', 'techvenza-core' ), 'number', __( 'Lower numbers first.', 'techvenza-core' ) ),
		);
		foreach ( $rows as $key => [ $label, $type, $help ] ) {
			$val = $v[ $key ] ?? '';
			$id  = 'tv-term-' . $key;
			ob_start();
			switch ( $type ) {
				case 'color':
					printf( '<input type="text" class="tv-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="">', esc_attr( $id ), esc_attr( $key ), esc_attr( (string) $val ) );
					break;
				case 'textarea':
					printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $key ), esc_textarea( (string) $val ) );
					break;
				case 'icon':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '">';
					foreach ( Icons::names() as $icon ) {
						printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $icon ), selected( $val ? $val : 'layers', $icon, false ) );
					}
					echo '</select>';
					break;
				case 'media':
					$img = $val ? wp_get_attachment_image_url( (int) $val, 'thumbnail' ) : '';
					printf(
						'<div class="tv-media-field"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><img src="%4$s" alt="" style="max-width:160px;%5$s;border-radius:8px"><p><button type="button" class="button tv-media-pick">%6$s</button> <button type="button" class="button-link tv-media-clear">%7$s</button></p></div>',
						esc_attr( $id ),
						esc_attr( $key ),
						esc_attr( (string) $val ),
						esc_url( (string) $img ),
						$img ? 'display:block' : 'display:none',
						esc_html__( 'Choose image', 'techvenza-core' ),
						esc_html__( 'Remove', 'techvenza-core' )
					);
					break;
				case 'toggle':
					$checked = '' === $val ? true : (bool) $val;
					printf( '<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s> %4$s</label>', esc_attr( $key ), esc_attr( $id ), checked( $checked, true, false ), esc_html( $help ) );
					$help = '';
					break;
				case 'number':
					printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text">', esc_attr( $id ), esc_attr( $key ), esc_attr( (string) (int) $val ) );
					break;
				default:
					printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">', esc_attr( $id ), esc_attr( $key ), esc_attr( (string) $val ) );
			}
			if ( 'tv_soft' === $key ) {
				echo '<p class="description tv-contrast" aria-live="polite"></p>';
			}
			$control = (string) ob_get_clean();
			if ( $edit ) {
				printf( '<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>%3$s%4$s</td></tr>', esc_attr( $id ), esc_html( $label ), $control, $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $control is escaped above.
			} else {
				printf( '<div class="form-field"><label for="%1$s">%2$s</label>%3$s%4$s</div>', esc_attr( $id ), esc_html( $label ), $control, $help ? '<p>' . esc_html( $help ) . '</p>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Add form.
	 */
	public static function add_fields(): void {
		self::fields( array(), false );
	}

	/**
	 * Edit form.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function edit_fields( \WP_Term $term ): void {
		$values = array();
		foreach ( array_keys( self::KEYS ) as $key ) {
			$values[ $key ] = get_term_meta( $term->term_id, $key, true );
		}
		self::fields( $values, true );
	}

	/**
	 * Save handler.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( int $term_id ): void {
		if ( ! isset( $_POST['techvenza_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['techvenza_term_nonce'] ) ), 'techvenza_term_meta' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		foreach ( array_keys( self::KEYS ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_term_meta( $term_id, $key, self::sanitize( $key, wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by self::sanitize().
			}
		}
		self::flush_cache( $term_id );
	}

	/**
	 * List table column.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'name' === $k ) {
				$new['tv_swatch'] = '<span class="screen-reader-text">' . esc_html__( 'Colour', 'techvenza-core' ) . '</span>';
			}
			$new[ $k ] = $v;
		}
		return $new;
	}

	/**
	 * Colour swatch column.
	 *
	 * @param string $out     Output.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 */
	public static function column( string $out, string $column, int $term_id ): string {
		if ( 'tv_swatch' !== $column ) {
			return $out;
		}
		$c = self::get( $term_id );
		return $c ? sprintf( '<span style="display:inline-block;width:18px;height:18px;border-radius:5px;background:%s" title="%s"></span>', esc_attr( $c['color'] ), esc_attr( $c['color'] ) ) : $out;
	}
	/**
	 * Category being viewed (archive page, or the term passed to a fragment request).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function current(): ?array {
		if ( ! empty( $GLOBALS['techvenza_fragment_term'] ) ) {
			return self::get( (int) $GLOBALS['techvenza_fragment_term'] );
		}
		$term = get_queried_object();
		if ( $term instanceof \WP_Term && 'category' === $term->taxonomy ) {
			return self::get( $term );
		}
		return null;
	}
}
