<?php
/**
 * SEO: titles, meta descriptions, canonical URLs, robots rules, Open Graph / X cards,
 * JSON-LD (Organization, WebSite + SearchAction, NewsArticle, Person, BreadcrumbList),
 * Search Console verification and core sitemap tuning.
 *
 * Everything steps aside when Yoast SEO, Rank Math, SEOPress or AIOSEO is active.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Seo;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\UserMeta;
use Techvenza\Core\Settings\Options;

/**
 * SEO module.
 */
final class Seo {

	/**
	 * Blocks that mark a page as an app/account screen (never indexed).
	 */
	private const PRIVATE_BLOCKS = array( 'techvenza/login-form', 'techvenza/register-form', 'techvenza/forgot-form', 'techvenza/reset-form', 'techvenza/reader-dashboard', 'techvenza/author-portal' );

	/**
	 * Another SEO plugin owns the head.
	 */
	public static function other_plugin(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'boot' ), 20 );
	}

	/**
	 * Late boot (other plugins have defined their constants).
	 */
	public static function boot(): void {
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'sitemap_posts' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'sitemap_taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'sitemap_providers' ), 10, 2 );
		if ( self::other_plugin() ) {
			return;
		}
		add_filter( 'document_title_separator', array( self::class, 'separator' ) );
		add_filter( 'document_title_parts', array( self::class, 'title_parts' ) );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( self::class, 'head' ), 2 );
		add_action( 'wp_footer', array( self::class, 'breadcrumbs_ld' ), 50 );
	}

	/* ------------------------------------------------------------------ Titles */

	/**
	 * Title separator.
	 */
	public static function separator(): string {
		$sep = trim( (string) Options::get( 'seo_title_sep', '—' ) );
		return '' !== $sep ? $sep : '—';
	}

	/**
	 * Title parts (custom SEO titles replace the page title part).
	 *
	 * @param array<string,string> $parts Parts.
	 * @return array<string,string>
	 */
	public static function title_parts( array $parts ): array {
		$custom = '';
		if ( is_singular() ) {
			$custom = (string) get_post_meta( (int) get_queried_object_id(), 'tv_seo_title', true );
		} elseif ( is_category() ) {
			$cat    = CategoryMeta::current();
			$custom = (string) ( $cat['seo_title'] ?? '' );
		}
		if ( '' !== trim( $custom ) ) {
			$parts['title'] = $custom;
		}
		unset( $parts['tagline'] );
		return $parts;
	}

	/* ------------------------------------------------------------------ Descriptions */

	/**
	 * Meta description for the current request.
	 */
	public static function description(): string {
		$desc = '';
		if ( is_singular() ) {
			$id   = (int) get_queried_object_id();
			$desc = (string) get_post_meta( $id, 'tv_seo_description', true );
			if ( '' === trim( $desc ) ) {
				$post = get_post( $id );
				$desc = $post ? ( has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' ) ) : '';
			}
		} elseif ( is_category() ) {
			$cat  = CategoryMeta::current();
			$desc = (string) ( ( $cat['seo_desc'] ?? '' ) ? $cat['seo_desc'] : ( $cat['intro'] ?? term_description() ) );
		} elseif ( is_tag() || is_tax() ) {
			$desc = (string) term_description();
		} elseif ( is_author() ) {
			$au   = UserMeta::author( (int) get_queried_object_id() );
			$desc = (string) ( $au['bio'] ?? '' );
		}
		if ( '' === trim( $desc ) || is_front_page() ) {
			$desc = '' !== trim( $desc ) && ! is_front_page() ? $desc : (string) Options::get( 'seo_description', get_bloginfo( 'description' ) );
		}
		$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( $desc, ENT_QUOTES, 'UTF-8' ) ) ) ?? '' );
		if ( mb_strlen( $desc ) <= 160 ) {
			return $desc;
		}
		$cut = mb_substr( $desc, 0, 157 );
		$sp  = mb_strrpos( $cut, ' ' );
		return rtrim( $sp > 100 ? mb_substr( $cut, 0, (int) $sp ) : $cut, ' ,.;:—-' ) . '…';
	}

	/* ------------------------------------------------------------------ Canonical & robots */

	/**
	 * Canonical URL ('' when the page should not declare one).
	 */
	public static function canonical(): string {
		if ( is_404() || is_search() ) {
			return '';
		}
		if ( is_singular() ) {
			$id     = (int) get_queried_object_id();
			$custom = (string) get_post_meta( $id, 'tv_canonical_url', true );
			return $custom ? esc_url_raw( $custom ) : (string) wp_get_canonical_url( $id );
		}
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		$url   = '';
		if ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$url  = $term instanceof \WP_Term ? (string) get_term_link( $term ) : '';
		} elseif ( is_author() ) {
			$url = get_author_posts_url( (int) get_queried_object_id() );
		} elseif ( is_home() ) {
			$url = (string) get_permalink( (int) get_option( 'page_for_posts' ) );
		}
		if ( $url && $paged > 1 ) {
			$url = trailingslashit( $url ) . user_trailingslashit( 'page/' . $paged, 'paged' );
		}
		return $url;
	}

	/**
	 * Whether the current request should be kept out of search results.
	 */
	public static function noindex(): bool {
		if ( is_search() || is_404() || is_attachment() ) {
			return true;
		}
		if ( is_singular() ) {
			$id = (int) get_queried_object_id();
			if ( get_post_meta( $id, 'tv_noindex', true ) ) {
				return true;
			}
			$post = get_post( $id );
			foreach ( self::PRIVATE_BLOCKS as $block ) {
				if ( $post && has_block( $block, $post ) ) {
					return true;
				}
			}
			return false;
		}
		if ( is_tag() && Options::enabled( 'seo_noindex_tags' ) ) {
			return true;
		}
		if ( is_author() && Options::enabled( 'seo_noindex_authors' ) ) {
			return true;
		}
		return is_date();
	}

	/**
	 * Robots directives.
	 *
	 * @param array<string,bool|string> $robots Directives.
	 * @return array<string,bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( self::noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		} elseif ( '0' !== (string) get_option( 'blog_public' ) ) {
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = '-1';
		}
		return $robots;
	}

	/* ------------------------------------------------------------------ Head output */

	/**
	 * Social image for the current request.
	 *
	 * @return array{url:string,width:int,height:int,alt:string}|null
	 */
	public static function image(): ?array {
		$id = 0;
		if ( is_singular() && has_post_thumbnail( (int) get_queried_object_id() ) ) {
			$id = (int) get_post_thumbnail_id( (int) get_queried_object_id() );
		} elseif ( is_category() ) {
			$cat = CategoryMeta::current();
			$id  = (int) ( $cat['hero'] ?? 0 );
		}
		$id = $id ? $id : (int) Options::get( 'seo_default_image', 0 );
		if ( ! $id ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $id, 'large' );
		if ( ! $src ) {
			return null;
		}
		return array(
			'url'    => (string) $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Meta tags + JSON-LD.
	 */
	public static function head(): void {
		$title = wp_get_document_title();
		$desc  = self::description();
		$canon = self::canonical();
		$img   = self::image();
		$tags  = array();
		if ( $desc ) {
			$tags[] = array( 'name', 'description', $desc );
		}
		$verify = (string) Options::get( 'gsc_verification', '' );
		if ( $verify ) {
			$tags[] = array( 'name', 'google-site-verification', $verify );
		}
		$is_article = is_singular( 'post' );
		$tags[]     = array( 'property', 'og:site_name', get_bloginfo( 'name' ) );
		$tags[]     = array( 'property', 'og:locale', str_replace( '-', '_', get_bloginfo( 'language' ) ) );
		$tags[]     = array( 'property', 'og:type', $is_article ? 'article' : ( is_author() ? 'profile' : 'website' ) );
		$tags[]     = array( 'property', 'og:title', $title );
		if ( $desc ) {
			$tags[] = array( 'property', 'og:description', $desc );
		}
		if ( $canon ) {
			$tags[] = array( 'property', 'og:url', $canon );
		}
		if ( $img ) {
			$tags[] = array( 'property', 'og:image', $img['url'] );
			$tags[] = array( 'property', 'og:image:width', (string) $img['width'] );
			$tags[] = array( 'property', 'og:image:height', (string) $img['height'] );
			if ( $img['alt'] ) {
				$tags[] = array( 'property', 'og:image:alt', $img['alt'] );
			}
		}
		if ( $is_article ) {
			$post   = get_post();
			$tags[] = array( 'property', 'article:published_time', (string) get_the_date( 'c', $post ) );
			$tags[] = array( 'property', 'article:modified_time', (string) get_the_modified_date( 'c', $post ) );
			$cat    = $post ? CategoryMeta::for_post( $post->ID ) : null;
			if ( $cat ) {
				$tags[] = array( 'property', 'article:section', (string) $cat['name'] );
			}
		}
		$tags[] = array( 'name', 'twitter:card', $img ? 'summary_large_image' : 'summary' );
		$handle = (string) Options::get( 'seo_twitter', '' );
		if ( $handle ) {
			$tags[] = array( 'name', 'twitter:site', '@' . ltrim( $handle, '@' ) );
		}

		echo "\n<!-- Techvenza SEO -->\n";
		if ( $canon ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canon ) );
		}
		foreach ( $tags as [ $attr, $key, $value ] ) {
			printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $attr ), esc_attr( $key ), esc_attr( $value ) );
		}
		$graph = self::graph();
		if ( $graph ) {
			self::print_ld( $graph );
		}
	}

	/**
	 * JSON-LD graph for the current request (breadcrumbs are added in the footer).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function graph(): array {
		$home = home_url( '/' );
		$org  = array(
			'@type' => 'Organization',
			'@id'   => $home . '#organization',
			'name'  => (string) Options::get( 'seo_org_name', get_bloginfo( 'name' ) ),
			'url'   => $home,
		);
		$logo = (int) Options::get( 'logo_light', 0 );
		$logo = $logo ? wp_get_attachment_image_url( $logo, 'full' ) : get_site_icon_url( 512 );
		$same = array_values( array_filter( array_map( static fn( $k ) => (string) Options::get( 'social_' . $k, '' ), array( 'linkedin', 'x', 'youtube', 'facebook', 'instagram' ) ) ) );
		if ( $logo ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		$graph = array( $org );
		if ( is_front_page() ) {
			$graph[] = array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => get_bloginfo( 'name' ),
				'description'     => (string) Options::get( 'seo_description', get_bloginfo( 'description' ) ),
				'publisher'       => array( '@id' => $home . '#organization' ),
				'inLanguage'      => get_bloginfo( 'language' ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}' ),
					),
					'query-input' => 'required name=search_term_string',
				),
			);
		}
		if ( is_singular( 'post' ) ) {
			$post = get_post();
			if ( $post ) {
				$au      = UserMeta::author( (int) $post->post_author );
				$img     = self::image();
				$cat     = CategoryMeta::for_post( $post->ID );
				$article = array(
					'@type'               => 'NewsArticle',
					'@id'                 => get_permalink( $post ) . '#article',
					'headline'            => wp_strip_all_tags( get_the_title( $post ) ),
					'description'         => self::description(),
					'url'                 => get_permalink( $post ),
					'mainEntityOfPage'    => self::canonical(),
					'datePublished'       => get_the_date( 'c', $post ),
					'dateModified'        => get_the_modified_date( 'c', $post ),
					'inLanguage'          => get_bloginfo( 'language' ),
					'publisher'           => array( '@id' => $home . '#organization' ),
					'author'              => $au ? self::person( $au ) : null,
					'articleSection'      => $cat ? (string) $cat['name'] : null,
					'keywords'            => implode( ', ', wp_list_pluck( (array) get_the_tags( $post->ID ), 'name' ) ),
					'wordCount'           => str_word_count( wp_strip_all_tags( $post->post_content ) ),
					'isAccessibleForFree' => true,
					'image'               => $img ? array( '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] ) : null, // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				);
				$graph[] = array_filter( $article, static fn( $v ) => null !== $v && '' !== $v );
			}
		}
		if ( is_author() ) {
			$au = UserMeta::author( (int) get_queried_object_id() );
			if ( $au ) {
				$graph[] = array(
					'@type'      => 'ProfilePage',
					'url'        => (string) $au['url'],
					'mainEntity' => self::person( $au ),
				);
			}
		}
		/**
		 * Filter the JSON-LD graph.
		 *
		 * @param array $graph Graph nodes.
		 */
		return (array) apply_filters( 'techvenza/seo/graph', $graph );
	}

	/**
	 * Person node.
	 *
	 * @param array<string,mixed> $au Author data.
	 * @return array<string,mixed>
	 */
	private static function person( array $au ): array {
		$same = array_values( array_filter( array( (string) $au['linkedin'], (string) $au['x'], (string) $au['website'] ) ) );
		return array_filter(
			array(
				'@type'      => 'Person',
				'@id'        => $au['url'] . '#person',
				'name'       => (string) $au['name'],
				'url'        => (string) $au['url'],
				'jobTitle'   => (string) $au['title'],
				'image'      => (string) $au['photo'],
				'knowsAbout' => (array) $au['expertise'], // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
				'sameAs'     => $same,
			)
		);
	}

	/**
	 * BreadcrumbList from the trail the page rendered.
	 */
	public static function breadcrumbs_ld(): void {
		$trail = Breadcrumbs::get();
		if ( count( $trail ) < 2 || self::noindex() ) {
			return;
		}
		$items = array();
		foreach ( array_values( $trail ) as $i => [ $label, $url ] ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags( $label ),
			);
			$url  = $url ? $url : self::canonical();
			if ( $url ) {
				$item['item'] = $url;
			}
			$items[] = $item;
		}
		self::print_ld(
			array(
				array(
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $items,
				),
			)
		);
	}

	/**
	 * Print a JSON-LD graph.
	 *
	 * @param array<int,array<string,mixed>> $graph Nodes.
	 */
	private static function print_ld( array $graph ): void {
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => array_values( $graph ),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
			)
		);
	}

	/* ------------------------------------------------------------------ Sitemaps */

	/**
	 * Leave noindexed posts and app pages out of the sitemap.
	 *
	 * @param array<string,mixed> $args      Query args.
	 * @param string              $post_type Post type.
	 * @return array<string,mixed>
	 */
	public static function sitemap_posts( array $args, string $post_type ): array {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => 'tv_noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'tv_noindex',
				'value'   => array( '', '0' ),
				'compare' => 'IN', // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
			),
		);
		if ( 'page' === $post_type ) {
			$exclude = array();
			foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
				foreach ( self::PRIVATE_BLOCKS as $block ) {
					if ( has_block( $block, $page ) ) {
						$exclude[] = $page->ID;
						break;
					}
				}
			}
			if ( $exclude ) {
				$args['post__not_in'] = $exclude; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			}
		}
		return $args;
	}

	/**
	 * Drop tag archives from the sitemap when they are noindexed.
	 *
	 * @param array<string,\WP_Taxonomy> $taxonomies Taxonomies.
	 * @return array<string,\WP_Taxonomy>
	 */
	public static function sitemap_taxonomies( array $taxonomies ): array {
		if ( Options::enabled( 'seo_noindex_tags' ) ) {
			unset( $taxonomies['post_tag'] );
		}
		unset( $taxonomies['post_format'] );
		return $taxonomies;
	}

	/**
	 * Drop the users sitemap when author archives are noindexed.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                      $name     Name.
	 * @return \WP_Sitemaps_Provider|false
	 */
	public static function sitemap_providers( $provider, string $name ) {
		return 'users' === $name && Options::enabled( 'seo_noindex_authors' ) ? false : $provider;
	}
}
