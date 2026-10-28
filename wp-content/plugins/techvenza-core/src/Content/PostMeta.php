<?php
/**
 * Article (post) meta: registered for REST with sanitisation and auth callbacks.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

/**
 * Registers tv_* post meta and the block editor sidebar.
 */
final class PostMeta {

	public const TYPES  = array( 'deep-dive', 'analysis', 'guide', 'opinion', 'news' );
	public const LEVELS = array( 'Beginner', 'Intermediate', 'Advanced' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_assets' ) );
	}

	/**
	 * Labels for article types.
	 *
	 * @return array<string,string>
	 */
	public static function type_labels(): array {
		return array(
			'deep-dive' => __( 'Deep dive', 'techvenza-core' ),
			'analysis'  => __( 'Analysis', 'techvenza-core' ),
			'guide'     => __( 'Guide', 'techvenza-core' ),
			'opinion'   => __( 'Perspective', 'techvenza-core' ),
			'news'      => __( 'News', 'techvenza-core' ),
		);
	}

	/**
	 * Meta definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions(): array {
		$str  = array(
			'type'    => 'string',
			'default' => '',
		);
		$bool = array(
			'type'    => 'boolean',
			'default' => false,
		);
		$int  = array(
			'type'    => 'integer',
			'default' => 0,
		);

		return array(
			'tv_reading_time'        => $int + array( 'description' => 'Minutes. Auto-calculated on save unless tv_reading_time_manual is true.' ),
			'tv_reading_time_manual' => $bool,
			'tv_is_featured'         => $bool,
			'tv_is_trending_manual'  => $bool,
			'tv_is_editors_pick'     => $bool,
			'tv_article_type'        => array(
				'type'    => 'string',
				'default' => 'analysis',
				'enum'    => self::TYPES,
			),
			'tv_guide_level'         => array(
				'type'    => 'string',
				'default' => '',
				'enum'    => array_merge( array( '' ), self::LEVELS ),
			),
			'tv_key_points'          => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'string' ),
			),
			'tv_assigned_editor'     => $int,
			'tv_scheduled_note'      => $str,
			'tv_ai_disclosure'       => $bool,
			'tv_ai_disclosure_text'  => $str,
			'tv_conflict_disclosure' => $str,
			'tv_is_sponsored'        => $bool,
			'tv_sponsor_name'        => $str,
			'tv_corrections'         => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array(
					'type'       => 'object',
					'properties' => array(
						'date' => array( 'type' => 'string' ),
						'text' => array( 'type' => 'string' ),
					),
				),
			),
			'tv_summary'             => $str + array( 'description' => 'Answer-first summary (40–80 words) shown at the top and used by answer engines.' ),
			'tv_focus_keyword'       => $str,
			'tv_faq'                 => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array(
					'type'       => 'object',
					'properties' => array(
						'q' => array( 'type' => 'string' ),
						'a' => array( 'type' => 'string' ),
					),
				),
			),
			'tv_sources'             => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array(
					'type'       => 'object',
					'properties' => array(
						'title' => array( 'type' => 'string' ),
						'url'   => array( 'type' => 'string' ),
						'rel'   => array( 'type' => 'string' ),
					),
				),
			),
			'tv_disable_autolinks'   => $bool,
			'tv_seo_title'           => $str,
			'tv_seo_description'     => $str,
			'tv_canonical_url'       => $str,
			'tv_noindex'             => $bool,
			'tv_view_count'          => $int + array( 'readonly' => true ),
		);
	}

	/**
	 * Register meta for posts.
	 */
	public static function register_meta(): void {
		foreach ( self::definitions() as $key => $def ) {
			$readonly = ! empty( $def['readonly'] );
			unset( $def['readonly'] );
			$schema = $def;
			unset( $schema['default'] );
			$args = array(
				'type'              => $def['type'],
				'single'            => true,
				'default'           => $def['default'],
				'description'       => $def['description'] ?? '',
				'sanitize_callback' => static fn( $value ) => self::sanitize( $key, $value ),
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) use ( $key, $readonly ) {
					if ( $readonly ) {
						return false;
					}
					// Editorial flags are only editable by reviewers.
					if ( in_array( $key, array( 'tv_is_featured', 'tv_is_trending_manual', 'tv_is_editors_pick', 'tv_assigned_editor', 'tv_scheduled_note' ), true ) ) {
						return current_user_can( 'tv_review_posts' ) || current_user_can( 'edit_others_posts' );
					}
					return current_user_can( 'edit_post', (int) $post_id );
				},
				'show_in_rest'      => array( 'schema' => $schema ),
			);
			if ( 'tv_view_count' === $key ) {
				$args['show_in_rest'] = array(
					'schema' => array(
						'type'     => 'integer',
						'readonly' => true,
					),
				);
			}
			register_post_meta( 'post', $key, $args );
		}

		// Internal (not REST-writable) meta.
		register_post_meta(
			'post',
			'tv_timeline',
			array(
				'type'          => 'array',
				'single'        => true,
				'default'       => array(),
				'auth_callback' => '__return_false',
				'show_in_rest'  => array(
					'schema' => array(
						'type'     => 'array',
						'readonly' => true,
						'items'    => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitise a meta value by key.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( string $key, $value ) {
		$defs = self::definitions();
		$type = $defs[ $key ]['type'] ?? 'string';
		switch ( $type ) {
			case 'boolean':
				return (bool) $value;
			case 'integer':
				return max( 0, (int) $value );
			case 'array':
				if ( ! is_array( $value ) ) {
					return array();
				}
				if ( 'tv_faq' === $key ) {
					$out = array();
					foreach ( $value as $row ) {
						if ( is_array( $row ) && '' !== trim( (string) ( $row['q'] ?? '' ) ) ) {
							$out[] = array(
								'q' => sanitize_text_field( (string) $row['q'] ),
								'a' => wp_kses_post( (string) ( $row['a'] ?? '' ) ),
							);
						}
					}
					return $out;
				}
				if ( 'tv_sources' === $key ) {
					$out = array();
					foreach ( $value as $row ) {
						$url = is_array( $row ) ? esc_url_raw( (string) ( $row['url'] ?? '' ) ) : '';
						if ( $url ) {
							$rel   = sanitize_key( (string) ( $row['rel'] ?? '' ) );
							$out[] = array(
								'title' => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
								'url'   => $url,
								'rel'   => in_array( $rel, array( 'nofollow', 'sponsored', 'ugc' ), true ) ? $rel : '',
							);
						}
					}
					return $out;
				}
				if ( 'tv_corrections' === $key ) {
					$out = array();
					foreach ( $value as $row ) {
						if ( is_array( $row ) && ! empty( $row['text'] ) ) {
							$out[] = array(
								'date' => sanitize_text_field( (string) ( $row['date'] ?? '' ) ),
								'text' => sanitize_textarea_field( (string) $row['text'] ),
							);
						}
					}
					return $out;
				}
				return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) ) );
			default:
				if ( 'tv_canonical_url' === $key ) {
					return esc_url_raw( (string) $value );
				}
				if ( isset( $defs[ $key ]['enum'] ) && ! in_array( $value, $defs[ $key ]['enum'], true ) ) {
					return $defs[ $key ]['default'];
				}
				return in_array( $key, array( 'tv_ai_disclosure_text', 'tv_conflict_disclosure', 'tv_seo_description', 'tv_scheduled_note', 'tv_summary' ), true )
					? sanitize_textarea_field( (string) $value )
					: sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Convenience getter.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key without the tv_ prefix.
	 * @return mixed
	 */
	public static function get( int $post_id, string $key ) {
		return get_post_meta( $post_id, 'tv_' . $key, true );
	}

	/**
	 * Editor sidebar (no build step: plain JS against wp.* globals).
	 */
	public static function editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}
		$path = 'assets/js/editor-sidebar.js';
		wp_enqueue_script(
			'techvenza-editor-sidebar',
			TECHVENZA_CORE_URL . $path,
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch', 'wp-notices', 'wp-url' ),
			(string) filemtime( TECHVENZA_CORE_DIR . $path ),
			true
		);
		$editors = array();
		foreach ( get_users(
			array(
				'capability' => 'tv_review_posts',
				'fields'     => array( 'ID', 'display_name' ),
			)
		) as $u ) {
			$editors[] = array(
				'value' => (int) $u->ID,
				'label' => $u->display_name,
			);
		}
		wp_localize_script(
			'techvenza-editor-sidebar',
			'techvenzaEditor',
			array(
				'types'      => self::type_labels(),
				'levels'     => self::LEVELS,
				'editors'    => $editors,
				'canReview'  => current_user_can( 'tv_review_posts' ),
				'minWords'   => (int) \Techvenza\Core\Settings\Options::get( 'min_words', 150 ),
				'statuses'   => class_exists( \Techvenza\Core\Workflow\Statuses::class ) ? \Techvenza\Core\Workflow\Statuses::labels() : array(),
				'portalUrl'  => home_url( '/author-portal/' ),
				'siteDomain' => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);
		wp_set_script_translations( 'techvenza-editor-sidebar', 'techvenza-core', TECHVENZA_CORE_DIR . 'languages' );
	}
}
