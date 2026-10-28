<?php
/**
 * Answer-engine and generative-engine optimisation (AEO / GEO / AIO).
 *
 * - "In short" answer-first summary at the top of each article, FAQ and Sources sections at the end
 *   (feeding FAQPage / citation structured data).
 * - /llms.txt and /llms-full.txt: a curated, machine-readable map of the site for LLMs.
 * - Markdown versions of every article at /<article>/md/ (linked with rel="alternate").
 * - robots.txt rules for AI search and AI training crawlers (separately switchable).
 * - IndexNow pings on publish/update so Bing, Copilot, Yandex and others re-crawl immediately.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Seo;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

/**
 * AEO / GEO module.
 */
final class Aeo {

	/**
	 * Crawlers that fetch pages to answer a user's question live (citations, links back).
	 */
	public const SEARCH_BOTS = array( 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Perplexity-User', 'Claude-SearchBot', 'Claude-User', 'DuckAssistBot', 'MistralAI-User' );

	/**
	 * Crawlers that collect pages to train models.
	 */
	public const TRAINING_BOTS = array( 'GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Meta-ExternalAgent', 'Bytespider', 'Amazonbot', 'cohere-training-data-crawler' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'render_block_core/post-content', array( self::class, 'article_sections' ), 10, 2 );
		add_action( 'init', array( self::class, 'rewrites' ) );
		add_action( 'init', array( self::class, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'serve' ), 1 );
		add_filter( 'robots_txt', array( self::class, 'robots_txt' ), 20, 2 );
		add_action( 'wp_head', array( self::class, 'alternate_links' ), 3 );
		add_action( 'transition_post_status', array( self::class, 'queue_indexnow' ), 10, 3 );
		add_action( 'techvenza_indexnow', array( self::class, 'send_indexnow' ) );
		add_action( 'rss2_item', array( self::class, 'rss_image' ) );
		add_action( 'rss2_ns', array( self::class, 'rss_ns' ) );
	}

	/* ------------------------------------------------------------------ Article sections */

	/**
	 * Summary at the top, FAQ and sources at the end of single articles.
	 *
	 * @param string              $html  Rendered content.
	 * @param array<string,mixed> $block Block.
	 */
	public static function article_sections( string $html, array $block ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Filter signature.
		if ( ! is_singular( 'post' ) ) {
			return $html;
		}
		$id = (int) get_queried_object_id();
		if ( ! $id || (int) get_the_ID() !== $id ) {
			return $html;
		}
		$top    = self::summary_html( $id );
		$bottom = self::faq_html( $id ) . self::sources_html( $id );
		// Insert inside the post-content wrapper so the sections share the article typography.
		if ( preg_match( '/^\s*<div\b[^>]*>/', $html, $m ) && str_ends_with( rtrim( $html ), '</div>' ) ) {
			$inner = substr( rtrim( $html ), strlen( $m[0] ), -6 );
			return $m[0] . $top . $inner . $bottom . '</div>';
		}
		return $top . $html . $bottom;
	}

	/**
	 * "In short" box.
	 *
	 * @param int $id Post.
	 */
	public static function summary_html( int $id ): string {
		$summary = trim( (string) get_post_meta( $id, 'tv_summary', true ) );
		if ( '' === $summary ) {
			return '';
		}
		return sprintf(
			'<aside class="tv-summary" aria-labelledby="tv-summary-h"><p id="tv-summary-h" class="tv-summary-title">%1$s %2$s</p><p>%3$s</p></aside>',
			R::icon( 'sparkle' ),
			esc_html__( 'In short', 'techvenza-core' ),
			esc_html( $summary )
		);
	}

	/**
	 * FAQ items for a post.
	 *
	 * @param int $id Post.
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function faq( int $id ): array {
		return array_values( array_filter( (array) get_post_meta( $id, 'tv_faq', true ), static fn( $f ) => is_array( $f ) && ! empty( $f['q'] ) && ! empty( $f['a'] ) ) );
	}

	/**
	 * FAQ section (native details/summary; FAQPage JSON-LD via Seo).
	 *
	 * @param int $id Post.
	 */
	public static function faq_html( int $id ): string {
		$items = self::faq( $id );
		if ( ! $items ) {
			return '';
		}
		Seo::add_faq( $items );
		$out = '';
		foreach ( $items as $f ) {
			$out .= sprintf( '<details><summary>%s %s</summary><div class="ans">%s</div></details>', esc_html( (string) $f['q'] ), R::icon( 'plus' ), wp_kses_post( wpautop( (string) $f['a'] ) ) );
		}
		return '<section class="tv-faq" aria-labelledby="tv-faq-h"><h2 id="tv-faq-h">' . esc_html__( 'Frequently asked questions', 'techvenza-core' ) . '</h2><div class="faq">' . $out . '</div></section>';
	}

	/**
	 * Sources & further reading.
	 *
	 * @param int $id Post.
	 */
	public static function sources_html( int $id ): string {
		$sources = array_values( array_filter( (array) get_post_meta( $id, 'tv_sources', true ), static fn( $s ) => is_array( $s ) && ! empty( $s['url'] ) ) );
		if ( ! $sources ) {
			return '';
		}
		$li = '';
		foreach ( $sources as $s ) {
			$rel = Links::rel_for( (string) $s['url'], (string) ( $s['rel'] ?? '' ), $id );
			$li .= sprintf(
				'<li><a href="%1$s"%2$s%3$s>%4$s</a> <span class="muted">— %5$s</span></li>',
				esc_url( (string) $s['url'] ),
				$rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
				Links::new_tab( (string) $s['url'] ) ? ' target="_blank"' : '',
				esc_html( (string) ( $s['title'] ? $s['title'] : $s['url'] ) ),
				esc_html( (string) wp_parse_url( (string) $s['url'], PHP_URL_HOST ) )
			);
		}
		return '<section class="tv-sources" aria-labelledby="tv-sources-h"><h2 id="tv-sources-h">' . esc_html__( 'Sources and further reading', 'techvenza-core' ) . '</h2><ol>' . $li . '</ol></section>';
	}

	/* ------------------------------------------------------------------ Routes */

	/**
	 * Rewrite rules for llms.txt, llms-full.txt, IndexNow key and the /md/ endpoint.
	 */
	public static function rewrites(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?tv_llms=index', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?tv_llms=full', 'top' );
		add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?tv_indexnow_key=$matches[1]', 'top' );
		add_rewrite_endpoint( 'md', EP_PERMALINK | EP_PAGES );
		if ( get_option( 'techvenza_aeo_rewrites' ) !== '2' ) {
			update_option( 'techvenza_aeo_rewrites', '2' );
			update_option( 'techvenza_flush_rewrites', 1 );
		}
	}

	/**
	 * Flush rewrite rules once after activation or when new routes were added.
	 */
	public static function maybe_flush(): void {
		if ( get_option( 'techvenza_flush_rewrites' ) ) {
			delete_option( 'techvenza_flush_rewrites' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Query vars.
	 *
	 * @param array<int,string> $vars Vars.
	 * @return array<int,string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'tv_llms';
		$vars[] = 'tv_indexnow_key';
		return $vars;
	}

	/**
	 * Serve the machine-readable variants.
	 */
	public static function serve(): void {
		$llms = (string) get_query_var( 'tv_llms' );
		if ( $llms ) {
			if ( ! Options::enabled( 'llms_enabled' ) ) {
				return;
			}
			self::text( 'full' === $llms ? self::llms_full() : self::llms_index() );
		}
		$key = (string) get_query_var( 'tv_indexnow_key' );
		if ( $key ) {
			if ( hash_equals( self::indexnow_key(), $key ) ) {
				self::text( $key );
			}
			return;
		}
		if ( false !== get_query_var( 'md', false ) && is_singular() && Options::enabled( 'markdown_enabled' ) ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post && 'publish' === $post->post_status && ! post_password_required( $post ) ) {
				header( 'Content-Type: text/markdown; charset=utf-8' );
				header( 'X-Robots-Tag: noindex, follow' );
				header( 'Link: <' . esc_url_raw( (string) get_permalink( $post ) ) . '>; rel="canonical"' );
				echo self::post_markdown( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text response.
				exit;
			}
		}
	}

	/**
	 * Send a plain-text response.
	 *
	 * @param string $body Body.
	 */
	private static function text( string $body ): void {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text response.
		exit;
	}

	/**
	 * Markdown alternate link on articles and pages.
	 */
	public static function alternate_links(): void {
		if ( is_singular() && ! is_front_page() && Options::enabled( 'markdown_enabled' ) && ! Seo::noindex() ) {
			printf( '<link rel="alternate" type="text/markdown" href="%s">' . "\n", esc_url( self::md_url( (int) get_queried_object_id() ) ) );
		}
		if ( Options::enabled( 'llms_enabled' ) ) {
			printf( '<link rel="help" type="text/plain" href="%s" title="llms.txt">' . "\n", esc_url( home_url( '/llms.txt' ) ) );
		}
	}

	/**
	 * Markdown URL of a post.
	 *
	 * @param int $id Post.
	 */
	public static function md_url( int $id ): string {
		$url = (string) get_permalink( $id );
		return get_option( 'permalink_structure' ) ? trailingslashit( $url ) . 'md/' : add_query_arg( 'md', '1', $url );
	}

	/* ------------------------------------------------------------------ llms.txt */

	/**
	 * Published articles for llms files.
	 *
	 * @param int $limit Limit.
	 * @return array<int,\WP_Post>
	 */
	private static function articles( int $limit ): array {
		return get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'has_password'   => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
				),
			)
		);
	}

	/**
	 * One-line summary of a post.
	 *
	 * @param \WP_Post $p Post.
	 */
	public static function one_liner( \WP_Post $p ): string {
		$s = trim( (string) get_post_meta( $p->ID, 'tv_summary', true ) );
		$s = $s ? $s : trim( (string) get_post_meta( $p->ID, 'tv_seo_description', true ) );
		$s = $s ? $s : ( has_excerpt( $p ) ? $p->post_excerpt : wp_trim_words( wp_strip_all_tags( $p->post_content ), 30, '…' ) );
		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * /llms.txt — llmstxt.org format.
	 */
	public static function llms_index(): string {
		$name = get_bloginfo( 'name' );
		$desc = (string) Options::get( 'seo_description', get_bloginfo( 'description' ) );
		$md   = Options::enabled( 'markdown_enabled' );
		$out  = "# {$name}\n\n> {$desc}\n\n";
		$out .= (string) Options::get( 'llms_intro', '' );
		$out  = rtrim( $out ) . "\n\n";

		$out .= "## Key pages\n\n";
		foreach ( array( 'about', 'editorial-guidelines', 'authors', 'become-an-author', 'newsletter', 'contact' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				$line = self::one_liner( $page );
				$out .= sprintf( "- [%s](%s)%s\n", html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ), $md ? self::md_url( $page->ID ) : get_permalink( $page ), $line ? ': ' . $line : '' );
			}
		}

		$out .= "\n## Topics\n\n";
		foreach ( CategoryMeta::all() as $cat ) {
			$intro = trim( (string) ( $cat['intro'] ?? '' ) );
			$out  .= sprintf( "- [%s](%s)%s\n", html_entity_decode( (string) $cat['name'], ENT_QUOTES, 'UTF-8' ), $cat['url'], $intro ? ': ' . preg_replace( '/\s+/', ' ', $intro ) : '' );
		}

		$by_cat = array();
		foreach ( self::articles( 200 ) as $p ) {
			$cat = CategoryMeta::for_post( $p->ID );
			$by_cat[ html_entity_decode( (string) ( $cat['name'] ?? __( 'Articles', 'techvenza-core' ) ), ENT_QUOTES, 'UTF-8' ) ][] = $p;
		}
		foreach ( $by_cat as $label => $posts ) {
			$out .= "\n## {$label}\n\n";
			foreach ( $posts as $p ) {
				$out .= sprintf( "- [%s](%s): %s\n", html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' ), $md ? self::md_url( $p->ID ) : get_permalink( $p ), self::one_liner( $p ) );
			}
		}
		$out .= "\n## Optional\n\n";
		$out .= sprintf( "- [Full text of recent articles](%s)\n- [XML sitemap](%s)\n- [RSS feed](%s)\n", home_url( '/llms-full.txt' ), home_url( '/wp-sitemap.xml' ), get_feed_link() );
		return $out;
	}

	/**
	 * /llms-full.txt — full Markdown of the most recent articles.
	 */
	public static function llms_full(): string {
		$cached = get_transient( 'tv_llms_full' );
		if ( is_string( $cached ) ) {
			return $cached;
		}
		$out = '# ' . get_bloginfo( 'name' ) . "\n\n> " . Options::get( 'seo_description', get_bloginfo( 'description' ) ) . "\n";
		foreach ( self::articles( (int) apply_filters( 'techvenza/llms/full_limit', 50 ) ) as $p ) {
			$out .= "\n\n---\n\n" . self::post_markdown( $p );
		}
		set_transient( 'tv_llms_full', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Markdown document for a post, with front matter.
	 *
	 * @param \WP_Post $p Post.
	 */
	public static function post_markdown( \WP_Post $p ): string {
		$author = get_the_author_meta( 'display_name', (int) $p->post_author );
		$cat    = CategoryMeta::for_post( $p->ID );
		$lines  = array(
			'---',
			'title: ' . wp_json_encode( wp_strip_all_tags( get_the_title( $p ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'url: ' . get_permalink( $p ),
			'author: ' . wp_json_encode( $author, JSON_UNESCAPED_UNICODE ),
			'published: ' . get_the_date( 'Y-m-d', $p ),
			'updated: ' . get_the_modified_date( 'Y-m-d', $p ),
		);
		if ( $cat ) {
			$lines[] = 'category: ' . wp_json_encode( (string) $cat['name'], JSON_UNESCAPED_UNICODE );
		}
		$lines[] = '---';
		$md      = implode( "\n", $lines ) . "\n\n# " . wp_strip_all_tags( get_the_title( $p ) ) . "\n\n";
		$summary = trim( (string) get_post_meta( $p->ID, 'tv_summary', true ) );
		if ( $summary ) {
			$md .= '> **In short:** ' . $summary . "\n\n";
		}
		$html = apply_filters( 'the_content', $p->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		$md  .= Markdown::from_html( (string) $html );
		$faq  = self::faq( $p->ID );
		if ( $faq ) {
			$md .= "\n\n## Frequently asked questions\n";
			foreach ( $faq as $f ) {
				$md .= "\n### " . $f['q'] . "\n\n" . trim( Markdown::from_html( wpautop( $f['a'] ) ) ) . "\n";
			}
		}
		$sources = (array) get_post_meta( $p->ID, 'tv_sources', true );
		if ( $sources ) {
			$md .= "\n\n## Sources\n\n";
			foreach ( $sources as $s ) {
				if ( is_array( $s ) && ! empty( $s['url'] ) ) {
					$md .= '- [' . ( $s['title'] ? $s['title'] : $s['url'] ) . '](' . $s['url'] . ")\n";
				}
			}
		}
		return rtrim( $md ) . "\n";
	}

	/* ------------------------------------------------------------------ robots.txt */

	/**
	 * Rules for robots.txt: account pages out, sitemap in, AI crawler policy.
	 *
	 * @param string $output Output.
	 * @param bool   $public Site is public.
	 */
	public static function robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$rules = '';
		foreach ( array( '/dashboard/', '/author-portal/', '/login/', '/register/', '/forgot-password/', '/reset-password/', '/?s=', '/search/' ) as $path ) {
			$rules .= 'Disallow: ' . $path . "\n";
		}
		// Keep the rules inside the "User-agent: *" group, before any Sitemap line.
		$pos    = strpos( $output, "\nSitemap:" );
		$output = false === $pos ? rtrim( $output ) . "\n" . $rules : substr( $output, 0, $pos ) . "\n" . $rules . substr( $output, $pos );

		$groups = array(
			'AI search and answer engines (cite and link to articles)' => array( self::SEARCH_BOTS, Options::enabled( 'ai_search_bots' ) ),
			'AI model training crawlers' => array( self::TRAINING_BOTS, Options::enabled( 'ai_training_bots' ) ),
		);
		foreach ( $groups as $label => [ $bots, $allowed ] ) {
			$output .= "\n# {$label}\n";
			foreach ( $bots as $bot ) {
				$output .= "User-agent: {$bot}\n";
			}
			$output .= $allowed ? "Allow: /\nDisallow: /wp-admin/\nDisallow: /dashboard/\nDisallow: /author-portal/\n" : "Disallow: /\n";
		}
		if ( Options::enabled( 'llms_enabled' ) ) {
			$output .= "\n# LLM-readable site map: " . home_url( '/llms.txt' ) . "\n";
		}
		if ( ! str_contains( $output, 'Sitemap:' ) ) {
			$output .= "\nSitemap: " . home_url( '/wp-sitemap.xml' ) . "\n";
		}
		return $output;
	}

	/* ------------------------------------------------------------------ IndexNow */

	/**
	 * Site IndexNow key (32 hex chars, created on first use).
	 */
	public static function indexnow_key(): string {
		$key = (string) get_option( 'techvenza_indexnow_key', '' );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			$key = md5( wp_generate_password( 40, true, true ) );
			update_option( 'techvenza_indexnow_key', $key, true );
		}
		return $key;
	}

	/**
	 * Queue an IndexNow ping when public content is published, updated or unpublished.
	 *
	 * @param string   $new  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 */
	public static function queue_indexnow( string $new, string $old, \WP_Post $post ): void {
		if ( ! Options::enabled( 'indexnow_enabled' ) || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || wp_is_post_revision( $post ) ) {
			return;
		}
		if ( 'publish' !== $new && 'publish' !== $old ) {
			return;
		}
		if ( 'local' === wp_get_environment_type() ) {
			return;
		}
		$queue   = (array) get_option( 'techvenza_indexnow_queue', array() );
		$queue[] = (string) get_permalink( $post );
		update_option( 'techvenza_indexnow_queue', array_values( array_unique( $queue ) ), false );
		if ( ! wp_next_scheduled( 'techvenza_indexnow' ) ) {
			wp_schedule_single_event( time() + 60, 'techvenza_indexnow' );
		}
		delete_transient( 'tv_llms_full' );
	}

	/**
	 * Submit queued URLs (batched).
	 */
	public static function send_indexnow(): void {
		$urls = array_slice( (array) get_option( 'techvenza_indexnow_queue', array() ), 0, 1000 );
		delete_option( 'techvenza_indexnow_queue' );
		if ( ! $urls ) {
			return;
		}
		$res = wp_remote_post(
			'https://api.indexnow.org/indexnow',
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => (string) wp_json_encode(
					array(
						'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
						'key'         => self::indexnow_key(),
						'keyLocation' => home_url( '/' . self::indexnow_key() . '.txt' ),
						'urlList'     => array_values( $urls ),
					)
				),
			)
		);
		update_option( 'techvenza_indexnow_last', array( current_time( 'mysql' ), is_wp_error( $res ) ? $res->get_error_message() : (int) wp_remote_retrieve_response_code( $res ), count( $urls ) ), false );
	}

	/* ------------------------------------------------------------------ Feeds */

	/**
	 * Media namespace for RSS.
	 */
	public static function rss_ns(): void {
		echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
	}

	/**
	 * Cover image in RSS items (raster, so every reader can show it).
	 */
	public static function rss_image(): void {
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		$id  = (int) get_post_thumbnail_id( $post );
		$cat = CategoryMeta::for_post( $post->ID );
		if ( $id && ! \Techvenza\Core\Media\Images::is_svg( $id ) ) {
			$src = wp_get_attachment_image_src( $id, 'large' );
			$img = $src ? array( 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ) : null; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		} else {
			$img = \Techvenza\Core\Media\Images::social( (string) ( $cat['slug'] ?? 'default' ) );
		}
		if ( $img ) {
			printf( '<media:content url="%s" medium="image" width="%d" height="%d" />' . "\n", esc_url( $img['url'] ), (int) $img['width'], (int) $img['height'] );
		}
	}
}
