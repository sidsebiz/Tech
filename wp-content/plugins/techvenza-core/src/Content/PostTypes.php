<?php
/**
 * Private custom post types: author applications, newsletter campaigns, contact messages, redirects.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

/**
 * Registers non-public CPTs used by the editorial CMS.
 */
final class PostTypes {

	public const APPLICATION = 'tv_application';
	public const CAMPAIGN    = 'tv_campaign';
	public const MESSAGE     = 'tv_message';
	public const REDIRECT    = 'tv_redirect';

	public const APP_STATUSES = array( 'pending', 'info_requested', 'approved', 'rejected' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_types' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ), 1 );
	}

	/**
	 * Whether the Redirection plugin handles redirects.
	 */
	public static function redirection_active(): bool {
		return defined( 'REDIRECTION_VERSION' ) || class_exists( 'Redirection' );
	}

	/**
	 * Register CPTs and their meta.
	 */
	public static function register_types(): void {
		$private = array(
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => array( 'title' ),
		);

		register_post_type(
			self::APPLICATION,
			array_merge(
				$private,
				array(
					'labels'       => array(
						'name'          => __( 'Author applications', 'techvenza-core' ),
						'singular_name' => __( 'Author application', 'techvenza-core' ),
					),
					'capabilities' => self::caps( 'tv_manage_applications' ),
					'map_meta_cap' => false,
				)
			)
		);

		register_post_type(
			self::CAMPAIGN,
			array_merge(
				$private,
				array(
					'labels'       => array(
						'name'          => __( 'Newsletter campaigns', 'techvenza-core' ),
						'singular_name' => __( 'Campaign', 'techvenza-core' ),
						'add_new_item'  => __( 'New campaign', 'techvenza-core' ),
						'edit_item'     => __( 'Edit campaign', 'techvenza-core' ),
					),
					'capabilities' => self::caps( 'tv_manage_newsletter' ),
					'map_meta_cap' => false,
					'supports'     => array( 'title' ),
				)
			)
		);

		register_post_type(
			self::MESSAGE,
			array_merge(
				$private,
				array(
					'labels'       => array(
						'name'          => __( 'Contact messages', 'techvenza-core' ),
						'singular_name' => __( 'Contact message', 'techvenza-core' ),
					),
					'capabilities' => self::caps( 'tv_manage_messages' ),
					'map_meta_cap' => false,
					'supports'     => array( 'title', 'editor' ),
				)
			)
		);

		if ( ! self::redirection_active() ) {
			register_post_type(
				self::REDIRECT,
				array_merge(
					$private,
					array(
						'labels'       => array(
							'name'          => __( 'Redirects', 'techvenza-core' ),
							'singular_name' => __( 'Redirect', 'techvenza-core' ),
						),
						'capabilities' => self::caps( 'tv_manage_seo' ),
						'map_meta_cap' => false,
					)
				)
			);
		}

		$meta = array(
			self::APPLICATION => array( 'tv_email', 'tv_title', 'tv_org', 'tv_expertise', 'tv_categories', 'tv_bio', 'tv_link', 'tv_samples', 'tv_reason', 'tv_status', 'tv_reference', 'tv_user_id', 'tv_decision_note', 'tv_consent_at', 'tv_history' ),
			self::CAMPAIGN    => array( 'tv_subject', 'tv_preheader', 'tv_intro', 'tv_posts', 'tv_audience', 'tv_status', 'tv_send_at', 'tv_sent_at', 'tv_stats', 'tv_esp_id' ),
			self::MESSAGE     => array( 'tv_name', 'tv_email', 'tv_type', 'tv_routed_to', 'tv_consent_at', 'tv_ip_hash' ),
			self::REDIRECT    => array( 'tv_from', 'tv_to', 'tv_code', 'tv_hits' ),
		);
		foreach ( $meta as $type => $keys ) {
			foreach ( $keys as $key ) {
				register_post_meta(
					$type,
					$key,
					array(
						'single'        => true,
						'show_in_rest'  => false,
						'auth_callback' => '__return_false',
					)
				);
			}
		}
	}

	/**
	 * Capability map that funnels every primitive cap to one Techvenza capability.
	 *
	 * @param string $cap Capability.
	 * @return array<string,string>
	 */
	private static function caps( string $cap ): array {
		$keys = array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' );
		return array_fill_keys( $keys, $cap );
	}

	/**
	 * Built-in redirects (only when the Redirection plugin is not active).
	 */
	public static function maybe_redirect(): void {
		if ( self::redirection_active() || ! is_404() ) {
			return;
		}
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		if ( '' === $path ) {
			return;
		}
		$path  = trailingslashit( $path );
		$found = get_posts(
			array(
				'post_type'        => self::REDIRECT,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'meta_key'         => 'tv_from', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		if ( ! $found ) {
			return;
		}
		$id   = $found[0]->ID;
		$to   = (string) get_post_meta( $id, 'tv_to', true );
		$code = (int) get_post_meta( $id, 'tv_code', true );
		update_post_meta( $id, 'tv_hits', (int) get_post_meta( $id, 'tv_hits', true ) + 1 );
		if ( $to ) {
			wp_safe_redirect( str_starts_with( $to, '/' ) ? home_url( $to ) : $to, in_array( $code, array( 301, 302, 307, 308 ), true ) ? $code : 301 );
			exit;
		}
	}
}
