<?php
/**
 * "Series" taxonomy for multi-part features (Featured series cards).
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

/**
 * Registers tv_series with order + accent category meta.
 */
final class Series {

	public const TAX = 'tv_series';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_taxonomy' ) );
		add_action( 'tv_series_add_form_fields', array( self::class, 'add_fields' ) );
		add_action( 'tv_series_edit_form_fields', array( self::class, 'edit_fields' ) );
		add_action( 'created_tv_series', array( self::class, 'save' ) );
		add_action( 'edited_tv_series', array( self::class, 'save' ) );
	}

	/**
	 * Register taxonomy and meta.
	 */
	public static function register_taxonomy(): void {
		register_taxonomy(
			self::TAX,
			array( 'post' ),
			array(
				'labels'            => array(
					'name'          => __( 'Series', 'techvenza-core' ),
					'singular_name' => __( 'Series', 'techvenza-core' ),
					'add_new_item'  => __( 'Add new series', 'techvenza-core' ),
					'edit_item'     => __( 'Edit series', 'techvenza-core' ),
					'search_items'  => __( 'Search series', 'techvenza-core' ),
					'menu_name'     => __( 'Series', 'techvenza-core' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'series' ),
			)
		);
		register_term_meta(
			self::TAX,
			'tv_order',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static fn() => current_user_can( 'manage_categories' ),
			)
		);
		register_term_meta(
			self::TAX,
			'tv_category',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_title',
				'auth_callback'     => static fn() => current_user_can( 'manage_categories' ),
			)
		);
	}

	/**
	 * Add form fields.
	 */
	public static function add_fields(): void {
		wp_nonce_field( 'techvenza_series', 'techvenza_series_nonce' );
		echo '<div class="form-field"><label for="tv_order">' . esc_html__( 'Display order', 'techvenza-core' ) . '</label><input type="number" name="tv_order" id="tv_order" value="0"></div>';
		echo '<div class="form-field"><label for="tv_category">' . esc_html__( 'Accent category', 'techvenza-core' ) . '</label>';
		wp_dropdown_categories(
			array(
				'name'              => 'tv_category',
				'id'                => 'tv_category',
				'value_field'       => 'slug',
				'hide_empty'        => false,
				'show_option_none'  => __( '— None —', 'techvenza-core' ),
				'option_none_value' => '',
			)
		);
		echo '</div>';
	}

	/**
	 * Edit form fields.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function edit_fields( \WP_Term $term ): void {
		wp_nonce_field( 'techvenza_series', 'techvenza_series_nonce' );
		printf(
			'<tr class="form-field"><th><label for="tv_order">%s</label></th><td><input type="number" name="tv_order" id="tv_order" value="%d"></td></tr>',
			esc_html__( 'Display order', 'techvenza-core' ),
			(int) get_term_meta( $term->term_id, 'tv_order', true )
		);
		echo '<tr class="form-field"><th><label for="tv_category">' . esc_html__( 'Accent category', 'techvenza-core' ) . '</label></th><td>';
		wp_dropdown_categories(
			array(
				'name'              => 'tv_category',
				'id'                => 'tv_category',
				'value_field'       => 'slug',
				'hide_empty'        => false,
				'selected'          => (string) get_term_meta( $term->term_id, 'tv_category', true ),
				'show_option_none'  => __( '— None —', 'techvenza-core' ),
				'option_none_value' => '',
			)
		);
		echo '</td></tr>';
	}

	/**
	 * Save.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( int $term_id ): void {
		if ( ! isset( $_POST['techvenza_series_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['techvenza_series_nonce'] ) ), 'techvenza_series' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		update_term_meta( $term_id, 'tv_order', absint( $_POST['tv_order'] ?? 0 ) );
		update_term_meta( $term_id, 'tv_category', sanitize_title( wp_unslash( $_POST['tv_category'] ?? '' ) ) );
	}
}
