<?php
/**
 * Techvenza theme bootstrap.
 *
 * Presentation only: design tokens (theme.json), templates, parts, patterns and CSS.
 * All data, dynamic blocks and behaviour are provided by the Techvenza Core plugin.
 *
 * @package Techvenza
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TECHVENZA_THEME_VERSION', '1.0.0' );

/**
 * Asset URL helper: prefers minified files when SCRIPT_DEBUG is off and a build exists.
 *
 * @param string $path Path relative to the theme root, e.g. assets/css/main.css.
 * @return array{0:string,1:string} URL and version string.
 */
function techvenza_asset( string $path ): array {
	$dir = get_theme_file_path();
	$min = preg_replace( '/\.(css|js)$/', '.min.$1', $path );
	if ( ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) && $min && file_exists( $dir . '/' . $min ) ) {
		$path = $min;
	}
	$file = $dir . '/' . $path;
	$ver  = file_exists( $file ) ? (string) filemtime( $file ) : TECHVENZA_THEME_VERSION;
	return array( get_theme_file_uri( $path ), $ver );
}

/**
 * Theme supports, menus and editor styles.
 */
function techvenza_setup(): void {
	load_theme_textdomain( 'techvenza', get_template_directory() . '/languages' );

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'menus' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_editor_style( array( 'assets/css/main.css', 'assets/css/article.css' ) );

	/*
	 * Classic menu locations are used by the Techvenza "Menu" block so every header,
	 * drawer and footer menu is editable in Appearance → Menus (see docs/DECISIONS.md D5).
	 */
	register_nav_menus(
		array(
			'primary'  => __( 'Header — primary navigation', 'techvenza' ),
			'mobile'   => __( 'Mobile drawer (falls back to primary)', 'techvenza' ),
			'footer-1' => __( 'Footer column 1', 'techvenza' ),
			'footer-2' => __( 'Footer column 2', 'techvenza' ),
			'footer-3' => __( 'Footer column 3', 'techvenza' ),
			'footer-4' => __( 'Footer column 4', 'techvenza' ),
			'legal'    => __( 'Footer bottom bar — legal links', 'techvenza' ),
		)
	);

	add_image_size( 'techvenza-card', 900, 563, true );
	add_image_size( 'techvenza-hero', 1600, 800, true );
	add_image_size( 'techvenza-square', 400, 400, true );
}
add_action( 'after_setup_theme', 'techvenza_setup' );

/**
 * Front-end styles. Article and app sheets load only with the blocks that need them.
 */
function techvenza_enqueue(): void {
	[ $url, $ver ] = techvenza_asset( 'assets/css/main.css' );
	wp_enqueue_style( 'techvenza-main', $url, array(), $ver );

	// Preload the primary text face to avoid a late swap on the LCP headline.
	add_action(
		'wp_head',
		static function () {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( get_theme_file_uri( 'assets/fonts/inter-latin-wght.woff2' ) )
			);
		},
		1
	);
}
add_action( 'wp_enqueue_scripts', 'techvenza_enqueue' );

/**
 * Per-block conditional styles (only printed when the block renders).
 */
function techvenza_block_styles(): void {
	[ $article_url, $article_ver ] = techvenza_asset( 'assets/css/article.css' );
	[ $app_url, $app_ver ]         = techvenza_asset( 'assets/css/app.css' );

	$article_blocks = array( 'techvenza/article-header', 'techvenza/article-layout', 'techvenza/toc', 'techvenza/article-tools', 'techvenza/author-bio', 'core/post-content' );
	foreach ( $article_blocks as $block ) {
		wp_enqueue_block_style(
			$block,
			array(
				'handle' => 'techvenza-article',
				'src'    => $article_url,
				'ver'    => $article_ver,
				'path'   => get_theme_file_path( 'assets/css/article.css' ),
			)
		);
	}
	$app_blocks = array( 'techvenza/reader-dashboard', 'techvenza/author-portal', 'techvenza/app-shell' );
	foreach ( $app_blocks as $block ) {
		wp_enqueue_block_style(
			$block,
			array(
				'handle' => 'techvenza-app',
				'src'    => $app_url,
				'ver'    => $app_ver,
				'path'   => get_theme_file_path( 'assets/css/app.css' ),
			)
		);
	}
}
add_action( 'init', 'techvenza_block_styles' );

/**
 * Block style variations that map core blocks onto the prototype's components.
 */
function techvenza_register_block_styles(): void {
	$buttons = array(
		'secondary'     => __( 'Secondary', 'techvenza' ),
		'ghost'         => __( 'Ghost', 'techvenza' ),
		'blue'          => __( 'Blue', 'techvenza' ),
		'light'         => __( 'Light', 'techvenza' ),
		'outline-light' => __( 'Outline (on dark)', 'techvenza' ),
	);
	foreach ( $buttons as $name => $label ) {
		register_block_style(
			'core/button',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}
	$groups = array(
		'panel'     => __( 'Panel', 'techvenza' ),
		'cta-band'  => __( 'CTA band', 'techvenza' ),
		'spot-dark' => __( 'Spotlight (dark)', 'techvenza' ),
		'spot-tint' => __( 'Spotlight (tint)', 'techvenza' ),
	);
	foreach ( $groups as $name => $label ) {
		register_block_style(
			'core/group',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}
}
add_action( 'init', 'techvenza_register_block_styles' );

/**
 * Mark the document as JS-capable before first paint so reveal animations
 * only hide content when JavaScript is actually running (progressive enhancement).
 */
function techvenza_js_flag(): void {
	echo "<script>document.documentElement.classList.add('tv-js');</script>\n";
}
add_action( 'wp_head', 'techvenza_js_flag', 0 );

/**
 * Pattern categories.
 */
function techvenza_pattern_categories(): void {
	$cats = array(
		'techvenza-home'     => __( 'Techvenza — Homepage sections', 'techvenza' ),
		'techvenza-sections' => __( 'Techvenza — Page sections', 'techvenza' ),
		'techvenza-pages'    => __( 'Techvenza — Full pages', 'techvenza' ),
	);
	foreach ( $cats as $slug => $label ) {
		register_block_pattern_category( $slug, array( 'label' => $label ) );
	}
}
add_action( 'init', 'techvenza_pattern_categories' );

/**
 * Friendly admin notice when the companion plugin is missing.
 */
function techvenza_require_core_notice(): void {
	if ( defined( 'TECHVENZA_CORE_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'The Techvenza theme needs the Techvenza Core plugin for its blocks, workflow and settings. Please activate it.', 'techvenza' ) . '</p></div>';
}
add_action( 'admin_notices', 'techvenza_require_core_notice' );
