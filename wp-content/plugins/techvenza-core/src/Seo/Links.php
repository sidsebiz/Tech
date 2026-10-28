<?php
/**
 * Outbound links and partner backlinks.
 *
 * - Partners (Editorial → Partners): company name, website, logo, link type
 *   (follow / nofollow / sponsored / ugc), optional keywords and an end date.
 * - Keyword auto-linking: the first mention of a partner keyword in an article links to the
 *   partner (limited per article, never inside headings, existing links or code).
 * - Outbound link policy: rel and target for external links (sponsored articles always use
 *   rel="sponsored"), applied when content is rendered so it can be changed site-wide later.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Seo;

use Techvenza\Core\Settings\Options;
use Techvenza\Core\Support\Render as R;

/**
 * Links module.
 */
final class Links {

	/**
	 * Per-request partner list.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private static ?array $cache = null;

	public const CPT  = 'tv_partner';
	public const RELS = array( 'follow', 'nofollow', 'sponsored', 'ugc' );

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'post_type' ) );
		add_action( 'add_meta_boxes_' . self::CPT, array( self::class, 'meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( self::class, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( self::class, 'cell' ), 10, 2 );
		add_filter( 'the_content', array( self::class, 'filter_content' ), 30 );
	}

	/**
	 * Partner post type.
	 */
	public static function post_type(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Partners', 'techvenza-core' ),
					'singular_name' => __( 'Partner', 'techvenza-core' ),
					'add_new_item'  => __( 'Add partner', 'techvenza-core' ),
					'edit_item'     => __( 'Edit partner', 'techvenza-core' ),
					'search_items'  => __( 'Search partners', 'techvenza-core' ),
					'menu_name'     => __( 'Partners', 'techvenza-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'techvenza',
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'excerpt', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_post'          => 'tv_manage_seo',
					'read_post'          => 'tv_manage_seo',
					'delete_post'        => 'tv_manage_seo',
					'edit_posts'         => 'tv_manage_seo',
					'edit_others_posts'  => 'tv_manage_seo',
					'publish_posts'      => 'tv_manage_seo',
					'read_private_posts' => 'tv_manage_seo',
					'delete_posts'       => 'tv_manage_seo',
					'create_posts'       => 'tv_manage_seo',
				),
				'map_meta_cap'    => false,
			)
		);
	}

	/**
	 * Active partners.
	 *
	 * @return array<int,array{id:int,name:string,url:string,rel:string,keywords:array<int,string>,logo:int,description:string}>
	 */
	public static function partners(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$cache = array();
		$today = current_time( 'Y-m-d' );
		foreach ( get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		) as $p ) {
			$url   = (string) get_post_meta( $p->ID, 'tv_url', true );
			$until = (string) get_post_meta( $p->ID, 'tv_until', true );
			if ( ! $url || ( $until && $until < $today ) ) {
				continue;
			}
			$rel     = (string) get_post_meta( $p->ID, 'tv_rel', true );
			$cache[] = array(
				'id'          => $p->ID,
				'name'        => get_the_title( $p ),
				'url'         => $url,
				'rel'         => in_array( $rel, self::RELS, true ) ? $rel : 'sponsored',
				'keywords'    => array_values( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $p->ID, 'tv_keywords', true ) ) ) ) ),
				'logo'        => (int) get_post_thumbnail_id( $p ),
				'description' => (string) $p->post_excerpt,
			);
		}
		self::$cache = $cache;
		return $cache;
	}

	/**
	 * Forget the per-request partner list (after a partner changes).
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/* ------------------------------------------------------------------ Admin UI */

	/**
	 * Meta box.
	 */
	public static function meta_box(): void {
		add_meta_box( 'tv-partner', __( 'Link details', 'techvenza-core' ), array( self::class, 'meta_box_html' ), self::CPT, 'normal', 'high' );
	}

	/**
	 * Meta box HTML.
	 *
	 * @param \WP_Post $post Partner.
	 */
	public static function meta_box_html( \WP_Post $post ): void {
		wp_nonce_field( 'tv_partner', 'tv_partner_nonce' );
		$rel    = (string) get_post_meta( $post->ID, 'tv_rel', true );
		$rel    = $rel ? $rel : 'sponsored';
		$labels = array(
			'follow'    => __( 'Follow — editorial link that passes ranking signals', 'techvenza-core' ),
			'nofollow'  => __( 'Nofollow — link without endorsement', 'techvenza-core' ),
			'sponsored' => __( 'Sponsored — paid or partnership link (required by Google for paid links)', 'techvenza-core' ),
			'ugc'       => __( 'UGC — user-generated content', 'techvenza-core' ),
		);
		$opts   = '';
		foreach ( $labels as $k => $l ) {
			$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $rel, $k, false ), esc_html( $l ) );
		}
		printf(
			'<table class="form-table" role="presentation">
			<tr><th><label for="tvPUrl">%1$s</label></th><td><input class="large-text" type="url" id="tvPUrl" name="tv_url" value="%2$s" placeholder="https://" required></td></tr>
			<tr><th><label for="tvPRel">%3$s</label></th><td><select id="tvPRel" name="tv_rel">%4$s</select></td></tr>
			<tr><th><label for="tvPKw">%5$s</label></th><td><input class="large-text" type="text" id="tvPKw" name="tv_keywords" value="%6$s"><p class="description">%7$s</p></td></tr>
			<tr><th><label for="tvPUntil">%8$s</label></th><td><input type="date" id="tvPUntil" name="tv_until" value="%9$s"><p class="description">%10$s</p></td></tr>
			</table><p class="description">%11$s</p>',
			esc_html__( 'Website URL', 'techvenza-core' ),
			esc_attr( (string) get_post_meta( $post->ID, 'tv_url', true ) ),
			esc_html__( 'Link type', 'techvenza-core' ),
			$opts, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html__( 'Auto-link keywords', 'techvenza-core' ),
			esc_attr( (string) get_post_meta( $post->ID, 'tv_keywords', true ) ),
			esc_html__( 'Comma-separated. The first mention in an article links here (limit per article in Settings → Links). Leave empty to link only from the Partners block.', 'techvenza-core' ),
			esc_html__( 'Active until', 'techvenza-core' ),
			esc_attr( (string) get_post_meta( $post->ID, 'tv_until', true ) ),
			esc_html__( 'Optional. Links stop automatically after this date (useful for paid placements).', 'techvenza-core' ),
			esc_html__( 'Use the excerpt for a one-line description and the featured image for the logo. Drag order with "Order" in Page Attributes.', 'techvenza-core' )
		);
	}

	/**
	 * Save meta box.
	 *
	 * @param int      $id   Post.
	 * @param \WP_Post $post Post.
	 */
	public static function save( int $id, \WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature.
		if ( ! isset( $_POST['tv_partner_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tv_partner_nonce'] ) ), 'tv_partner' ) || ! current_user_can( 'tv_manage_seo' ) ) {
			return;
		}
		$rel = isset( $_POST['tv_rel'] ) ? sanitize_key( wp_unslash( $_POST['tv_rel'] ) ) : 'sponsored';
		update_post_meta( $id, 'tv_url', isset( $_POST['tv_url'] ) ? esc_url_raw( wp_unslash( $_POST['tv_url'] ) ) : '' );
		update_post_meta( $id, 'tv_rel', in_array( $rel, self::RELS, true ) ? $rel : 'sponsored' );
		update_post_meta( $id, 'tv_keywords', isset( $_POST['tv_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['tv_keywords'] ) ) : '' );
		$until = isset( $_POST['tv_until'] ) ? sanitize_text_field( wp_unslash( $_POST['tv_until'] ) ) : '';
		update_post_meta( $id, 'tv_until', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '' );
		self::flush();
	}

	/**
	 * List columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( array $cols ): array {
		$date = $cols['date'] ?? '';
		unset( $cols['date'] );
		$cols['tv_url']      = __( 'Website', 'techvenza-core' );
		$cols['tv_rel']      = __( 'Link type', 'techvenza-core' );
		$cols['tv_keywords'] = __( 'Keywords', 'techvenza-core' );
		$cols['date']        = $date;
		return $cols;
	}

	/**
	 * Column cells.
	 *
	 * @param string $col Column.
	 * @param int    $id  Post.
	 */
	public static function cell( string $col, int $id ): void {
		if ( 'tv_url' === $col ) {
			$url = (string) get_post_meta( $id, 'tv_url', true );
			echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( (string) wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>' : '—';
		} elseif ( 'tv_rel' === $col ) {
			echo esc_html( (string) get_post_meta( $id, 'tv_rel', true ) );
		} elseif ( 'tv_keywords' === $col ) {
			echo esc_html( (string) get_post_meta( $id, 'tv_keywords', true ) );
		}
	}

	/* ------------------------------------------------------------------ Link policy */

	/**
	 * Whether a URL points to another site.
	 *
	 * @param string $url URL.
	 */
	public static function is_external( string $url ): bool {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return '' !== $host && strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * Open external links in a new tab?
	 *
	 * @param string $url URL.
	 */
	public static function new_tab( string $url ): bool {
		return self::is_external( $url ) && Options::enabled( 'external_new_tab' );
	}

	/**
	 * The rel attribute for an outbound link.
	 *
	 * @param string $url     URL.
	 * @param string $chosen  Explicit choice (follow|nofollow|sponsored|ugc|'').
	 * @param int    $post_id Article (sponsored articles force rel=sponsored).
	 */
	public static function rel_for( string $url, string $chosen = '', int $post_id = 0 ): string {
		if ( ! self::is_external( $url ) ) {
			return '';
		}
		$rels = array();
		if ( $post_id && get_post_meta( $post_id, 'tv_is_sponsored', true ) ) {
			$rels[] = 'sponsored';
		} elseif ( $chosen && 'follow' !== $chosen ) {
			$rels[] = $chosen;
		} elseif ( ! $chosen ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			foreach ( self::partners() as $p ) {
				if ( strtolower( (string) wp_parse_url( $p['url'], PHP_URL_HOST ) ) === $host ) {
					if ( 'follow' !== $p['rel'] ) {
						$rels[] = $p['rel'];
					}
					$chosen = 'partner';
					break;
				}
			}
			$default = (string) Options::get( 'external_rel', '' );
			if ( 'partner' !== $chosen && in_array( $default, array( 'nofollow', 'ugc', 'sponsored' ), true ) ) {
				$rels[] = $default;
			}
		}
		if ( self::new_tab( $url ) ) {
			$rels[] = 'noopener';
		}
		return implode( ' ', array_unique( $rels ) );
	}

	/**
	 * Apply auto-links and the outbound policy to article content.
	 *
	 * @param string $content Content.
	 */
	public static function filter_content( string $content ): string {
		$post = get_post();
		if ( ! $post || 'post' !== $post->post_type || '' === $content ) {
			return $content;
		}
		if ( Options::enabled( 'autolink_enabled' ) && ! get_post_meta( $post->ID, 'tv_disable_autolinks', true ) && ! is_feed() ) {
			$content = self::autolink( $content, (int) Options::get( 'autolink_max', 3 ) );
		}
		return self::apply_policy( $content, $post->ID );
	}

	/**
	 * Add rel/target to external links that do not set rel themselves.
	 *
	 * @param string $html    HTML.
	 * @param int    $post_id Post.
	 */
	public static function apply_policy( string $html, int $post_id ): string {
		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$p = new \WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$href = (string) $p->get_attribute( 'href' );
			if ( ! self::is_external( $href ) ) {
				continue;
			}
			$existing = trim( (string) $p->get_attribute( 'rel' ) );
			$rel      = self::rel_for( $href, $existing ? 'follow' : '', $post_id );
			$merged   = trim( implode( ' ', array_unique( array_filter( array_merge( explode( ' ', $existing ), explode( ' ', $rel ) ) ) ) ) );
			if ( $merged ) {
				$p->set_attribute( 'rel', $merged );
			}
			if ( self::new_tab( $href ) && null === $p->get_attribute( 'target' ) ) {
				$p->set_attribute( 'target', '_blank' );
			}
		}
		return $p->get_updated_html();
	}

	/**
	 * Link the first mention of each partner keyword.
	 *
	 * @param string $html HTML.
	 * @param int    $max  Max links.
	 */
	public static function autolink( string $html, int $max ): string {
		$targets = array();
		foreach ( self::partners() as $p ) {
			foreach ( $p['keywords'] as $kw ) {
				if ( mb_strlen( $kw ) >= 3 ) {
					$targets[] = array( $kw, $p );
				}
			}
		}
		if ( ! $targets || $max < 1 ) {
			return $html;
		}
		usort( $targets, static fn( $a, $b ) => mb_strlen( $b[0] ) <=> mb_strlen( $a[0] ) );
		$parts   = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$parts   = $parts ? $parts : array( $html );
		$skip    = 0;
		$done    = array();
		$count   = 0;
		$blocked = '/^<(\/?)(a|h[1-6]|code|pre|script|style|figcaption|button|summary|kbd)\b/i';
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( '<' === $part[0] ) {
				if ( preg_match( $blocked, $part, $m ) && ! str_ends_with( $part, '/>' ) ) {
					$skip += '/' === $m[1] ? -1 : 1;
					$skip  = max( 0, $skip );
				}
				continue;
			}
			if ( $skip || $count >= $max ) {
				continue;
			}
			foreach ( $targets as [ $kw, $partner ] ) {
				if ( $count >= $max || isset( $done[ $partner['id'] ] ) ) {
					continue;
				}
				$re = '/(?<![\p{L}\p{N}])(' . preg_quote( $kw, '/' ) . ')(?![\p{L}\p{N}])/iu';
				if ( ! preg_match( $re, $part ) ) {
					continue;
				}
				$rel                    = 'follow' === $partner['rel'] ? '' : $partner['rel'];
				$rel                    = trim( $rel . ( Options::enabled( 'external_new_tab' ) ? ' noopener' : '' ) );
				$link                   = sprintf( '<a class="tv-partner-link" href="%s"%s%s>$1</a>', esc_url( $partner['url'] ), $rel ? ' rel="' . esc_attr( $rel ) . '"' : '', Options::enabled( 'external_new_tab' ) ? ' target="_blank"' : '' );
				$part                   = (string) preg_replace( $re, $link, $part, 1 );
				$parts[ $i ]            = $part;
				$done[ $partner['id'] ] = true;
				++$count;
				// A link was inserted inside this text node; stop scanning it for other keywords.
				break;
			}
		}
		return implode( '', $parts );
	}

	/**
	 * Partners block markup.
	 *
	 * @param array<string,mixed> $attributes Attributes.
	 */
	public static function block( array $attributes ): string {
		$partners = self::partners();
		if ( ! $partners ) {
			return '';
		}
		$cards = '';
		foreach ( array_slice( $partners, 0, max( 1, (int) ( $attributes['count'] ?? 12 ) ) ) as $p ) {
			$rel    = trim( ( 'follow' === $p['rel'] ? '' : $p['rel'] ) . ' noopener' );
			$logo   = $p['logo'] ? wp_get_attachment_image(
				$p['logo'],
				'medium',
				false,
				array(
					'alt'     => '',
					'loading' => 'lazy',
					'class'   => 'tv-partner-logo',
				)
			) : '<span class="tv-partner-initial" aria-hidden="true">' . esc_html( mb_substr( $p['name'], 0, 1 ) ) . '</span>';
			$cards .= sprintf(
				'<li class="tv-partner"><a href="%1$s" rel="%2$s" target="_blank">%3$s<span class="tv-partner-name">%4$s</span>%5$s</a></li>',
				esc_url( $p['url'] ),
				esc_attr( $rel ),
				$logo,
				esc_html( $p['name'] ),
				! empty( $attributes['showDescription'] ) && $p['description'] ? '<span class="tv-partner-desc">' . esc_html( $p['description'] ) . '</span>' : ''
			);
		}
		$head = R::sec_head(
			array(
				'eyebrow' => (string) ( $attributes['eyebrow'] ?? '' ),
				'heading' => (string) ( $attributes['heading'] ?? '' ),
				'desc'    => (string) ( $attributes['description'] ?? '' ),
			)
		);
		return R::section( $head . '<ul class="tv-partners">' . $cards . '</ul>', $attributes );
	}
}
