<?php
/**
 * Registers every dynamic block in /blocks and the shared editor UI.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Blocks;

use Techvenza\Core\Support\Icons;

/**
 * Block registration and editor bootstrapping.
 */
final class Registry {

	public const EDITOR_HANDLE = 'techvenza-blocks-editor';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_scripts' ), 5 );
		add_action( 'init', array( self::class, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( self::class, 'category' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_data' ) );
		add_filter( 'rest_pre_dispatch', array( self::class, 'detect_editor_render' ), 10, 3 );
	}

	/**
	 * Block directories.
	 *
	 * @return array<int,string>
	 */
	public static function dirs(): array {
		$dirs = glob( TECHVENZA_CORE_DIR . 'blocks/*', GLOB_ONLYDIR );
		return $dirs ? $dirs : array();
	}

	/**
	 * Script handles referenced from block.json files.
	 */
	public static function register_scripts(): void {
		$url = static function ( string $path ): array {
			$min = preg_replace( '/\.js$/', '.min.js', $path );
			if ( ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) && $min && file_exists( TECHVENZA_CORE_DIR . $min ) ) {
				$path = $min;
			}
			return array( TECHVENZA_CORE_URL . $path, (string) ( file_exists( TECHVENZA_CORE_DIR . $path ) ? filemtime( TECHVENZA_CORE_DIR . $path ) : TECHVENZA_CORE_VERSION ) );
		};

		[ $src, $ver ] = $url( 'assets/js/blocks-editor.js' );
		wp_register_script(
			self::EDITOR_HANDLE,
			$src,
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data', 'wp-core-data', 'wp-server-side-render', 'wp-api-fetch' ),
			$ver,
			true
		);
		wp_set_script_translations( self::EDITOR_HANDLE, 'techvenza-core', TECHVENZA_CORE_DIR . 'languages' );

		$views = array(
			'techvenza-frontend' => 'assets/js/frontend.js',
			'techvenza-live'     => 'assets/js/live.js',
			'techvenza-article'  => 'assets/js/article.js',
			'techvenza-forms'    => 'assets/js/forms.js',
			'techvenza-portal'   => 'assets/js/portal.js',
		);
		foreach ( $views as $handle => $path ) {
			[ $src, $ver ] = $url( $path );
			wp_register_script(
				$handle,
				$src,
				'techvenza-frontend' === $handle ? array() : array( 'techvenza-frontend' ),
				$ver,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Register each block from its block.json.
	 */
	public static function register_blocks(): void {
		foreach ( self::dirs() as $dir ) {
			if ( file_exists( $dir . '/block.json' ) ) {
				register_block_type( $dir );
			}
		}
	}

	/**
	 * Inserter category.
	 *
	 * @param array<int,array<string,mixed>> $cats Categories.
	 * @return array<int,array<string,mixed>>
	 */
	public static function category( array $cats ): array {
		array_unshift(
			$cats,
			array(
				'slug'  => 'techvenza',
				'title' => __( 'Techvenza', 'techvenza-core' ),
				'icon'  => null,
			)
		);
		return $cats;
	}

	/**
	 * Pass inspector control definitions (custom `tvControls` key in block.json) to the editor.
	 */
	public static function editor_data(): void {
		$defs = array();
		foreach ( self::dirs() as $dir ) {
			$file = $dir . '/block.json';
			if ( ! file_exists( $file ) ) {
				continue;
			}
			$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			if ( ! is_array( $json ) || empty( $json['name'] ) ) {
				continue;
			}
			$defs[ $json['name'] ] = array(
				'controls' => $json['tvControls'] ?? array(),
				'inner'    => $json['tvInner'] ?? null,
				'preview'  => $json['tvPreview'] ?? 'server',
				'hint'     => $json['tvHint'] ?? '',
			);
		}
		wp_add_inline_script( self::EDITOR_HANDLE, 'window.techvenzaBlocks = ' . wp_json_encode( $defs ) . ';', 'before' );
		wp_add_inline_script(
			self::EDITOR_HANDLE,
			'window.techvenzaIcons = ' . wp_json_encode( Icons::names() ) . ';',
			'before'
		);
	}

	/**
	 * Server-side previews in the editor run through the block-renderer REST route,
	 * where the page-level icon sprite is absent: switch icons to inline SVG.
	 *
	 * @param mixed                                 $result  Pre-dispatch result.
	 * @param \WP_REST_Server                       $server  Server.
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return mixed
	 */
	public static function detect_editor_render( $result, $server, $request ) {
		if ( $request instanceof \WP_REST_Request && str_starts_with( $request->get_route(), '/wp/v2/block-renderer/techvenza/' ) ) {
			Icons::use_inline( true );
		}
		return $result;
	}
}
