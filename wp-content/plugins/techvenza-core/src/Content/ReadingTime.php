<?php
/**
 * Reading time: calculated on save unless overridden by the editor.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Content;

/**
 * Word count based reading-time calculator.
 */
final class ReadingTime {

	public const WPM = 220;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'save_post_post', array( self::class, 'update' ), 20, 2 );
		add_action( 'rest_after_insert_post', array( self::class, 'update_rest' ), 20 );
	}

	/**
	 * Count words in post content (blocks rendered, tags stripped).
	 *
	 * @param string $content Post content.
	 */
	public static function words( string $content ): int {
		$text  = wp_strip_all_tags( strip_shortcodes( $content ) );
		$text  = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/**
	 * Minutes for content.
	 *
	 * @param string $content Post content.
	 */
	public static function minutes( string $content ): int {
		return max( 1, (int) round( self::words( $content ) / self::WPM ) );
	}

	/**
	 * Save handler.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function update( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( get_post_meta( $post_id, 'tv_reading_time_manual', true ) && (int) get_post_meta( $post_id, 'tv_reading_time', true ) > 0 ) {
			return;
		}
		update_post_meta( $post_id, 'tv_reading_time', self::minutes( $post->post_content ) );
	}

	/**
	 * After REST insert (meta is saved after save_post in the block editor flow).
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function update_rest( \WP_Post $post ): void {
		self::update( $post->ID, $post );
	}

	/**
	 * Reading time for display.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function get( int $post_id ): int {
		$value = (int) get_post_meta( $post_id, 'tv_reading_time', true );
		if ( $value > 0 ) {
			return $value;
		}
		$post = get_post( $post_id );
		return $post ? self::minutes( $post->post_content ) : 1;
	}
}
