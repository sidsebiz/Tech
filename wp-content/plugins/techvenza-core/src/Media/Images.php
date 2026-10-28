<?php
/**
 * Images: the bundled illustration library (original SVG artwork per category and page),
 * a manageable fallback chain for covers, safe SVG uploads and raster social cards.
 *
 * Cover resolution for an article: its featured image → its category's image (Posts →
 * Categories → "Category image") → Settings → Brand → "Default cover" → the category's
 * illustration from the Media Library → the bundled SVG file. Every step except the last is
 * a Media Library item, so editors can replace any image without touching code.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Media;

use Techvenza\Core\Settings\Options;

/**
 * Image handling.
 */
final class Images {

	public const OPTION = 'techvenza_illustrations';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'upload_mimes', array( self::class, 'mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( self::class, 'check_filetype' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'sanitize_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( self::class, 'sanitize_upload' ) );
		add_filter( 'wp_generate_attachment_metadata', array( self::class, 'svg_metadata' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( self::class, 'svg_src' ), 10, 4 );
		add_action( 'admin_init', array( self::class, 'maybe_install' ) );
	}

	/* ------------------------------------------------------------------ Library */

	/**
	 * Absolute directory of the bundled artwork.
	 */
	public static function dir(): string {
		return TECHVENZA_CORE_DIR . 'assets/illustrations/';
	}

	/**
	 * Bundled illustrations: name => file (without extension).
	 *
	 * @return array<int,string>
	 */
	public static function names(): array {
		$files = glob( self::dir() . '*.svg' );
		return array_map( static fn( $f ) => basename( (string) $f, '.svg' ), $files ? $files : array() );
	}

	/**
	 * Illustration name for a category (and variant 1–3).
	 *
	 * @param string $slug    Category slug.
	 * @param int    $variant Variant seed.
	 */
	public static function name_for( string $slug, int $variant = 1 ): string {
		$name = $slug . '-' . ( ( abs( $variant ) % 3 ) + 1 );
		return file_exists( self::dir() . $name . '.svg' ) ? $name : 'default';
	}

	/**
	 * URL of a bundled SVG.
	 *
	 * @param string $name Name.
	 */
	public static function bundled_url( string $name ): string {
		return TECHVENZA_CORE_URL . 'assets/illustrations/' . rawurlencode( $name ) . '.svg';
	}

	/**
	 * 1200×630 JPEG social card for a category or page illustration.
	 *
	 * @param string $slug Category slug or page illustration name.
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	public static function social( string $slug ): array {
		$file = file_exists( self::dir() . 'social/' . $slug . '.jpg' ) ? $slug : 'default';
		return array(
			'url'    => TECHVENZA_CORE_URL . 'assets/illustrations/social/' . $file . '.jpg',
			'width'  => 1200,
			'height' => 630,
			'alt'    => '',
		);
	}

	/**
	 * Media Library attachment for an illustration (0 when not imported yet).
	 *
	 * @param string $name Name.
	 */
	public static function attachment( string $name ): int {
		$map = (array) get_option( self::OPTION, array() );
		$id  = (int) ( $map[ $name ] ?? 0 );
		return $id && 'attachment' === get_post_type( $id ) ? $id : 0;
	}

	/**
	 * Human label for an illustration name.
	 *
	 * @param string $name Name.
	 */
	public static function label( string $name ): string {
		$base = preg_replace( '/-\d$/', '', str_replace( 'page-', '', $name ) );
		$term = get_category_by_slug( (string) $base );
		return $term ? $term->name : ucwords( str_replace( '-', ' ', (string) $base ) );
	}

	/**
	 * Import every bundled illustration into the Media Library (skips existing ones).
	 *
	 * @param bool $force Re-import even when already present.
	 * @return int Number imported.
	 */
	public static function install_library( bool $force = false ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$map   = (array) get_option( self::OPTION, array() );
		$count = 0;
		foreach ( self::names() as $name ) {
			if ( ! $force && self::attachment( $name ) ) {
				continue;
			}
			$tmp = wp_tempnam( $name . '.svg' );
			if ( ! $tmp || ! copy( self::dir() . $name . '.svg', $tmp ) ) {
				continue;
			}
			$label = self::label( $name );
			$alt   = str_starts_with( $name, 'page-' ) || 'default' === $name
				/* translators: %s: topic */
				? sprintf( __( 'Illustration: %s', 'techvenza-core' ), $label )
				/* translators: %s: category */
				: sprintf( __( 'Illustration representing %s', 'techvenza-core' ), $label );
			self::$trusted = true;
			$id            = media_handle_sideload(
				array(
					'name'     => 'techvenza-' . $name . '.svg',
					'tmp_name' => $tmp,
				),
				0,
				$alt
			);
			self::$trusted = false;
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				continue;
			}
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			update_post_meta( $id, 'tv_illustration', $name );
			wp_update_post(
				array(
					'ID'           => $id,
					'post_excerpt' => __( 'Original Techvenza illustration.', 'techvenza-core' ),
				)
			);
			$map[ $name ] = (int) $id;
			++$count;
		}
		update_option( self::OPTION, $map, false );
		return $count;
	}

	/**
	 * Import the library once, the first time an administrator opens wp-admin after an update.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::OPTION ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		update_option( self::OPTION, array(), false ); // Mark as attempted even if uploads fail.
		self::install_library();
	}

	/**
	 * Cover image for an article without a featured image.
	 *
	 * @param array<string,mixed>|null $cat     Category data (CategoryMeta).
	 * @param int                      $post_id Post (picks the variant).
	 * @return int Attachment ID or 0 (then use bundled_url()).
	 */
	public static function fallback_id( ?array $cat, int $post_id = 0 ): int {
		if ( $cat && ! empty( $cat['hero'] ) ) {
			return (int) $cat['hero'];
		}
		$default = (int) Options::get( 'default_cover', 0 );
		if ( $default ) {
			return $default;
		}
		return self::attachment( self::name_for( (string) ( $cat['slug'] ?? 'default' ), $post_id ) );
	}

	/**
	 * Whether an attachment is an SVG.
	 *
	 * @param int $id Attachment.
	 */
	public static function is_svg( int $id ): bool {
		return 'image/svg+xml' === get_post_mime_type( $id );
	}

	/* ------------------------------------------------------------------ SVG uploads */

	/**
	 * Set while importing the bundled (already safe) library.
	 *
	 * @var bool
	 */
	private static bool $trusted = false;

	/**
	 * Editors and above may upload SVG (always sanitized).
	 */
	public static function can_upload_svg(): bool {
		return self::$trusted || current_user_can( 'edit_others_posts' ) || ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * Allow SVG for trusted roles.
	 *
	 * @param array<string,string> $mimes Mimes.
	 * @return array<string,string>
	 */
	public static function mimes( array $mimes ): array {
		if ( self::can_upload_svg() ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * WordPress cannot sniff SVG types; trust the extension once the content was sanitized.
	 *
	 * @param array<string,mixed>       $data     Result.
	 * @param string                    $file     Path.
	 * @param string                    $filename Name.
	 * @param array<string,string>|null $mimes Mimes.
	 * @return array<string,mixed>
	 */
	public static function check_filetype( $data, $file, $filename, $mimes ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Filter signature.
		if ( self::can_upload_svg() && 'svg' === strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) ) {
			$data['ext']             = 'svg';
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $data['proper_filename'] ?? false;
		}
		return (array) $data;
	}

	/**
	 * Sanitize SVG uploads in place (or reject them).
	 *
	 * @param array<string,mixed> $file Upload.
	 * @return array<string,mixed>
	 */
	public static function sanitize_upload( array $file ): array {
		if ( 'svg' !== strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) {
			return $file;
		}
		if ( ! self::can_upload_svg() ) {
			$file['error'] = __( 'Only editors and administrators can upload SVG images.', 'techvenza-core' );
			return $file;
		}
		$raw   = (string) file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$clean = Svg::sanitize( $raw );
		if ( '' === $clean ) {
			$file['error'] = __( 'This SVG could not be read or contains unsafe content.', 'techvenza-core' );
			return $file;
		}
		file_put_contents( (string) $file['tmp_name'], $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $file;
	}

	/**
	 * Width/height metadata for SVG attachments so they render with dimensions.
	 *
	 * @param array<string,mixed> $meta Metadata.
	 * @param int                 $id   Attachment.
	 * @return array<string,mixed>
	 */
	public static function svg_metadata( $meta, int $id ): array {
		if ( ! self::is_svg( $id ) ) {
			return (array) $meta;
		}
		$file           = (string) get_attached_file( $id );
		[ $w, $h ]      = Svg::dimensions( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$meta           = (array) $meta;
		$meta['width']  = $w;
		$meta['height'] = $h;
		$meta['file']   = _wp_relative_upload_path( $file );
		return $meta;
	}

	/**
	 * SVGs scale freely: return the original file for every size, with proportional dimensions.
	 *
	 * @param array<int,mixed>|false $image Image data.
	 * @param int                    $id    Attachment.
	 * @param string|int[]           $size  Size.
	 * @param bool                   $icon  Icon.
	 * @return array<int,mixed>|false
	 */
	public static function svg_src( $image, int $id, $size, bool $icon ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Filter signature.
		if ( ! $image || ! self::is_svg( $id ) ) {
			return $image;
		}
		$meta = wp_get_attachment_metadata( $id );
		$w    = (int) ( $meta['width'] ?? 1600 );
		$h    = (int) ( $meta['height'] ?? 900 );
		if ( is_array( $size ) && ! empty( $size[0] ) ) {
			$h = (int) round( $h * $size[0] / max( 1, $w ) );
			$w = (int) $size[0];
		}
		return array( (string) wp_get_attachment_url( $id ), $w, $h, false );
	}
}
