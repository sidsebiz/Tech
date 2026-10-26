<?php
/**
 * Shared component renderers (cards, chips, bylines, section heads, pagination, forms…).
 *
 * PHP ports of the prototype's component functions so every block outputs the same markup
 * the theme CSS was written for.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Support;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\PostMeta;
use Techvenza\Core\Content\ReadingTime;
use Techvenza\Core\Content\UserMeta;

/**
 * Stateless HTML helpers. All output is escaped here.
 */
final class Render {

	/**
	 * Per-request cache of article data.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private static array $articles = array();

	/**
	 * Icon shortcut.
	 *
	 * @param string $name       Icon.
	 * @param string $class_name Class.
	 */
	public static function icon( string $name, string $class_name = '' ): string {
		return Icons::svg( $name, $class_name );
	}

	/**
	 * Normalised article data for a post.
	 *
	 * @param \WP_Post|int $post Post.
	 * @return array<string,mixed>|null
	 */
	public static function article( $post ): ?array {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		if ( isset( self::$articles[ $post->ID ] ) ) {
			return self::$articles[ $post->ID ];
		}
		$type    = (string) get_post_meta( $post->ID, 'tv_article_type', true );
		$thumb   = (int) get_post_thumbnail_id( $post );
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30 );
		$data    = array(
			'id'        => $post->ID,
			'url'       => get_permalink( $post ),
			'title'     => get_the_title( $post ),
			'excerpt'   => html_entity_decode( (string) $excerpt, ENT_QUOTES, 'UTF-8' ),
			'cat'       => CategoryMeta::for_post( $post->ID ),
			'author'    => UserMeta::author( (int) $post->post_author ),
			'date'      => get_the_date( 'Y-m-d', $post ),
			'modified'  => get_the_modified_date( 'Y-m-d', $post ),
			'minutes'   => ReadingTime::get( $post->ID ),
			'type'      => $type ? $type : 'analysis',
			'level'     => (string) get_post_meta( $post->ID, 'tv_guide_level', true ),
			'image'     => $thumb,
			'alt'       => $thumb ? (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) : '',
			'points'    => array_values( array_filter( (array) get_post_meta( $post->ID, 'tv_key_points', true ) ) ),
			'pick'      => (bool) get_post_meta( $post->ID, 'tv_is_editors_pick', true ),
			'sponsored' => (bool) get_post_meta( $post->ID, 'tv_is_sponsored', true ),
		);

		self::$articles[ $post->ID ] = $data;
		return $data;
	}

	/**
	 * Format a Y-m-d date like the prototype (9 Oct 2026 / 9 October 2026).
	 *
	 * @param string $ymd  Date.
	 * @param bool   $long Long month.
	 */
	public static function date( string $ymd, bool $long = false ): string {
		$ts = strtotime( $ymd . ' 12:00:00' );
		if ( ! $ts ) {
			return '—';
		}
		return wp_date( $long ? 'j F Y' : 'j M Y', $ts );
	}

	/**
	 * Category chip.
	 *
	 * @param array<string,mixed>|null $cat  Category.
	 * @param bool                     $link Render as link.
	 */
	public static function chip( ?array $cat, bool $link = true ): string {
		if ( ! $cat ) {
			return '';
		}
		$style = esc_attr( CategoryMeta::style( $cat ) );
		return $link
			? sprintf( '<a class="chip" style="%s" href="%s">%s</a>', $style, esc_url( $cat['url'] ), esc_html( $cat['short'] ) )
			: sprintf( '<span class="chip" style="%s">%s</span>', $style, esc_html( $cat['short'] ) );
	}

	/**
	 * Responsive media block with branded gradient placeholder.
	 *
	 * @param array<string,mixed> $a Article.
	 * @param array<string,mixed> $o Options: ratio, eager, sizes, decorative, size.
	 */
	public static function media( array $a, array $o = array() ): string {
		$cat   = $a['cat'] ?? null;
		$style = sprintf( '--c:%s;--c2:%s', $cat['color'] ?? '#2563EB', $o['c2'] ?? '#7C3AED' );
		$img   = '';
		if ( ! empty( $a['image'] ) ) {
			$attrs = array(
				'alt'      => ! empty( $o['decorative'] ) ? '' : (string) $a['alt'],
				'sizes'    => $o['sizes'] ?? '(max-width:720px) 100vw, 50vw',
				'decoding' => 'async',
				'class'    => '',
			);
			if ( ! empty( $o['eager'] ) ) {
				$attrs['loading']       = 'eager';
				$attrs['fetchpriority'] = 'high';
			} else {
				$attrs['loading'] = 'lazy';
			}
			$img = (string) wp_get_attachment_image( (int) $a['image'], $o['size'] ?? 'large', false, $attrs );
		}
		return sprintf(
			'<div class="media %s%s" style="%s"><span class="media-mark">%s</span>%s</div>',
			esc_attr( $o['ratio'] ?? '' ),
			$img ? '' : ' is-empty',
			esc_attr( $style ),
			esc_html( $cat['short'] ?? '' ),
			$img
		);
	}

	/**
	 * Avatar (photo or initials on a gradient).
	 *
	 * @param array<string,mixed>|null $p    Person (author array or initials/a1/a2).
	 * @param string                   $size sm|md|lg|xl|''.
	 */
	public static function avatar( ?array $p, string $size = '' ): string {
		if ( ! $p ) {
			return '';
		}
		if ( ! empty( $p['photo'] ) ) {
			$px = array(
				'sm' => 28,
				'md' => 48,
				'lg' => 72,
				'xl' => 128,
			)[ $size ] ?? 40;
			return sprintf(
				'<img class="avatar %1$s" src="%2$s" alt="" width="%3$d" height="%3$d" loading="lazy" style="object-fit:cover">',
				esc_attr( $size ),
				esc_url( $p['photo'] ),
				$px
			);
		}
		return sprintf(
			'<span class="avatar %s" style="--a1:%s;--a2:%s" aria-hidden="true">%s</span>',
			esc_attr( $size ),
			esc_attr( $p['a1'] ?? '#2563EB' ),
			esc_attr( $p['a2'] ?? '#7C3AED' ),
			esc_html( $p['initials'] ?? '?' )
		);
	}

	/**
	 * Byline with optional avatar and date.
	 *
	 * @param array<string,mixed> $a Article.
	 * @param array<string,mixed> $o avatar(bool), date(bool).
	 */
	public static function byline( array $a, array $o = array() ): string {
		$au = $a['author'] ?? null;
		if ( ! $au ) {
			return '';
		}
		$date = ( $o['date'] ?? true )
			? sprintf( ' <span aria-hidden="true">·</span> <time datetime="%s">%s</time>', esc_attr( $a['date'] ), esc_html( self::date( $a['date'] ) ) )
			: '';
		$name = ( $o['link'] ?? true )
			? sprintf( '<a href="%s"><strong>%s</strong></a>', esc_url( $au['url'] ), esc_html( $au['name'] ) )
			: '<strong>' . esc_html( $au['name'] ) . '</strong>';
		return sprintf(
			'<div class="byline">%s<span>%s%s</span></div>',
			( $o['avatar'] ?? true ) ? self::avatar( $au, 'sm' ) : '',
			$name,
			$date
		);
	}

	/**
	 * Bookmark toggle (state applied client-side so cached HTML stays anonymous).
	 *
	 * @param array<string,mixed> $a Article.
	 */
	public static function bookmark( array $a ): string {
		return sprintf(
			'<button class="bookmark-btn" type="button" data-action="bookmark" data-id="%1$d" aria-pressed="false" aria-label="%2$s">%3$s</button>',
			(int) $a['id'],
			/* translators: %s: article title */
			esc_attr( sprintf( __( 'Save: %s', 'techvenza-core' ), $a['title'] ) ),
			self::icon( 'bookmark' )
		);
	}

	/**
	 * Meta line: reading time + format.
	 *
	 * @param array<string,mixed> $a Article.
	 */
	public static function meta_line( array $a ): string {
		$types = PostMeta::type_labels();
		return sprintf(
			'<div class="meta"><span>%s</span>%s</div>',
			/* translators: %d: minutes */
			esc_html( sprintf( _n( '%d min read', '%d min read', (int) $a['minutes'], 'techvenza-core' ), (int) $a['minutes'] ) ),
			isset( $types[ $a['type'] ] ) ? '<span class="dot"></span><span>' . esc_html( $types[ $a['type'] ] ) . '</span>' : ''
		);
	}

	/**
	 * Highlight query terms in already-escaped text.
	 *
	 * @param string $text Raw text.
	 * @param string $q    Query.
	 */
	public static function hl( string $text, string $q ): string {
		$safe  = esc_html( $text );
		$terms = array_filter( preg_split( '/\s+/', trim( $q ) ) ? preg_split( '/\s+/', trim( $q ) ) : array(), static fn( $t ) => mb_strlen( $t ) > 1 );
		if ( ! $terms ) {
			return $safe;
		}
		$pattern = '/(' . implode( '|', array_map( static fn( $t ) => preg_quote( esc_html( $t ), '/' ), $terms ) ) . ')/iu';
		return (string) preg_replace( $pattern, '<mark>$1</mark>', $safe );
	}

	/**
	 * Article card in one of the prototype variants.
	 *
	 * @param \WP_Post|int|array<string,mixed> $post    Post or article data.
	 * @param string                           $variant default|lg|md|boxed|row|text.
	 * @param array<string,mixed>              $o       ratio, eager, q, noExcerpt, excerpt.
	 */
	public static function card( $post, string $variant = 'default', array $o = array() ): string {
		$a = is_array( $post ) ? $post : self::article( $post );
		if ( ! $a ) {
			return '';
		}
		$q     = (string) ( $o['q'] ?? '' );
		$title = $q ? self::hl( $a['title'], $q ) : esc_html( $a['title'] );
		$url   = esc_url( $a['url'] );

		if ( 'row' === $variant ) {
			return sprintf(
				'<article class="card row-card reveal"><a class="card-link" href="%s">%s<div class="card-body">%s<h3 class="card-title"><span class="u-line">%s</span></h3>%s</div></a></article>',
				$url,
				self::media(
					$a,
					array(
						'ratio' => 'r-sq',
						'sizes' => '140px',
						'size'  => 'techvenza-square',
					)
				),
				self::chip( $a['cat'], false ),
				$title,
				self::byline(
					$a,
					array(
						'avatar' => false,
						'link'   => false,
					)
				)
			);
		}
		if ( 'text' === $variant ) {
			return sprintf(
				'<article class="card reveal"><div class="card-body">%s<h3 class="card-title"><a href="%s"><span class="u-line">%s</span></a></h3>%s%s</div></article>',
				self::chip( $a['cat'] ),
				$url,
				$title,
				! empty( $o['excerpt'] ) ? '<p class="card-excerpt">' . esc_html( $a['excerpt'] ) . '</p>' : '',
				self::byline( $a )
			);
		}
		$cls = in_array( $variant, array( 'lg', 'md', 'boxed' ), true ) ? $variant : '';
		return sprintf(
			'<article class="card %1$s reveal">%2$s<a class="card-link" href="%3$s">%4$s<div class="card-body"><div class="card-top">%5$s%6$s</div><h3 class="card-title"><span class="u-line">%7$s</span></h3>%8$s</div></a>%9$s</article>',
			esc_attr( $cls ),
			self::bookmark( $a ),
			$url,
			self::media(
				$a,
				array(
					'ratio' => $o['ratio'] ?? '',
					'eager' => ! empty( $o['eager'] ),
					'sizes' => 'lg' === $variant ? '(max-width:1024px) 100vw, 760px' : '(max-width:720px) 100vw, 400px',
					'size'  => 'lg' === $variant ? 'large' : 'techvenza-card',
				)
			),
			self::chip( $a['cat'], false ),
			self::meta_line( $a ),
			$title,
			! empty( $o['noExcerpt'] ) ? '' : '<p class="card-excerpt">' . ( $q ? self::hl( $a['excerpt'], $q ) : esc_html( $a['excerpt'] ) ) . '</p>',
			self::byline( $a, array( 'link' => false ) )
		);
	}

	/**
	 * Several cards.
	 *
	 * @param array<int,\WP_Post> $posts   Posts.
	 * @param string              $variant Variant.
	 * @param array<string,mixed> $o       Options.
	 */
	public static function cards( array $posts, string $variant = 'default', array $o = array() ): string {
		return implode( '', array_map( static fn( $p ) => self::card( $p, $variant, $o ), $posts ) );
	}

	/**
	 * Author card.
	 *
	 * @param array<string,mixed> $au Author.
	 * @param array<string,mixed> $o  q.
	 */
	public static function author_card( array $au, array $o = array() ): string {
		$n     = (int) ( $au['count'] ?? \Techvenza\Core\Content\Query::author_count( (int) $au['id'] ) );
		$chips = implode( '', array_map( static fn( $slug ) => self::chip( CategoryMeta::get( $slug ), false ), array_slice( $au['cats'], 0, 3 ) ) );
		return sprintf(
			'<a class="author-card reveal" href="%1$s">%2$s<div><h3>%3$s</h3><div class="role">%4$s</div></div><p>%5$s</p><div class="chip-row">%6$s</div><div class="foot"><span>%7$s</span><span class="sec-link" style="font-size:var(--t-meta)">%8$s %9$s</span></div></a>',
			esc_url( $au['url'] ),
			self::avatar( $au, 'md' ),
			! empty( $o['q'] ) ? self::hl( $au['name'], (string) $o['q'] ) : esc_html( $au['name'] ),
			esc_html( $au['title'] ),
			esc_html( wp_strip_all_tags( $au['bio'] ) ),
			$chips,
			/* translators: %d: number of articles */
			esc_html( sprintf( _n( '%d published article', '%d published articles', $n, 'techvenza-core' ), $n ) ),
			esc_html__( 'View profile', 'techvenza-core' ),
			self::icon( 'arrowRight' )
		);
	}

	/**
	 * Section heading block.
	 *
	 * @param array<string,mixed> $o eyebrow, heading, desc, linkLabel, linkUrl, accent, id.
	 */
	public static function sec_head( array $o ): string {
		$link = '';
		if ( ! empty( $o['linkLabel'] ) && ! empty( $o['linkUrl'] ) ) {
			$link = sprintf( '<a class="sec-link" href="%s">%s %s</a>', esc_url( self::url( (string) $o['linkUrl'] ) ), esc_html( $o['linkLabel'] ), self::icon( 'arrowRight' ) );
		}
		if ( empty( $o['eyebrow'] ) && empty( $o['heading'] ) && ! $link ) {
			return '';
		}
		return sprintf(
			'<div class="sec-head reveal"><div>%s%s%s</div>%s</div>',
			! empty( $o['eyebrow'] ) ? sprintf( '<span class="eyebrow"%s>%s</span>', ! empty( $o['accent'] ) ? ' style="--accent:' . esc_attr( $o['accent'] ) . '"' : '', esc_html( $o['eyebrow'] ) ) : '',
			! empty( $o['heading'] ) ? sprintf( '<h2 class="h2 mt-4"%s>%s</h2>', ! empty( $o['id'] ) ? ' id="' . esc_attr( $o['id'] ) . '"' : '', esc_html( $o['heading'] ) ) : '',
			! empty( $o['desc'] ) ? '<p>' . esc_html( $o['desc'] ) . '</p>' : '',
			$link
		);
	}

	/**
	 * Breadcrumbs (BreadcrumbList JSON-LD is emitted by the SEO module from the same trail).
	 *
	 * @param array<int,array{0:string,1:string}> $items Label, URL ('' for current).
	 * @param string                              $style Inline style.
	 */
	public static function crumbs( array $items, string $style = '' ): string {
		$li   = array();
		$last = count( $items ) - 1;
		foreach ( $items as $i => [ $label, $url ] ) {
			$li[] = $i === $last || ! $url
				? '<li><span aria-current="page">' . esc_html( $label ) . '</span></li>'
				: '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
		\Techvenza\Core\Seo\Breadcrumbs::set( $items );
		return sprintf( '<nav class="breadcrumbs" aria-label="%s"%s><ol>%s</ol></nav>', esc_attr__( 'Breadcrumb', 'techvenza-core' ), $style ? ' style="' . esc_attr( $style ) . '"' : '', implode( '', $li ) );
	}

	/**
	 * Empty state.
	 *
	 * @param string $icon   Icon.
	 * @param string $title  Title.
	 * @param string $text   Text.
	 * @param string $action Pre-escaped action HTML.
	 */
	public static function empty_state( string $icon, string $title, string $text, string $action = '' ): string {
		return sprintf( '<div class="empty"><span class="ico">%s</span><h3>%s</h3><p>%s</p>%s</div>', self::icon( $icon ), esc_html( $title ), esc_html( $text ), $action );
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Status key.
	 */
	public static function badge( string $status ): string {
		$labels = array(
			'draft'             => __( 'Draft', 'techvenza-core' ),
			'auto-draft'        => __( 'Draft', 'techvenza-core' ),
			'submitted'         => __( 'New submission', 'techvenza-core' ),
			'pending'           => __( 'Pending', 'techvenza-core' ),
			'in_review'         => __( 'In review', 'techvenza-core' ),
			'changes_requested' => __( 'Changes requested', 'techvenza-core' ),
			'resubmitted'       => __( 'Resubmitted', 'techvenza-core' ),
			'approved'          => __( 'Approved', 'techvenza-core' ),
			'future'            => __( 'Scheduled', 'techvenza-core' ),
			'scheduled'         => __( 'Scheduled', 'techvenza-core' ),
			'publish'           => __( 'Published', 'techvenza-core' ),
			'published'         => __( 'Published', 'techvenza-core' ),
			'rejected'          => __( 'Rejected', 'techvenza-core' ),
			'archived'          => __( 'Archived', 'techvenza-core' ),
			'info_requested'    => __( 'Info requested', 'techvenza-core' ),
			'active'            => __( 'Active', 'techvenza-core' ),
			'suspended'         => __( 'Suspended', 'techvenza-core' ),
			'unsubscribed'      => __( 'Unsubscribed', 'techvenza-core' ),
			'confirmed'         => __( 'Active', 'techvenza-core' ),
			'sent'              => __( 'Sent', 'techvenza-core' ),
		);
		$css    = array(
			'future'    => 'scheduled',
			'publish'   => 'published',
			'confirmed' => 'active',
		)[ $status ] ?? $status;
		return sprintf( '<span class="badge %s">%s</span>', esc_attr( $css ), esc_html( $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) ) ) );
	}

	/**
	 * Crawlable pagination (links) that JS enhances into in-place updates.
	 *
	 * @param int                      $total Total items.
	 * @param int                      $page  Current page.
	 * @param int                      $per   Per page.
	 * @param string                   $param  Query param name.
	 * @param array<string,string|int> $params Other active filters to keep in links.
	 */
	public static function pager( int $total, int $page, int $per, string $param = 'pg', array $params = array() ): string {
		$pages = (int) max( 1, ceil( $total / max( 1, $per ) ) );
		if ( $pages <= 1 ) {
			return '';
		}
		$link = static function ( int $p ) use ( $param, $params ): string {
			return esc_url( \Techvenza\Core\Blocks\Params::url( array_merge( $params, array( $param => $p > 1 ? $p : '' ) ) ) );
		};
		$h    = sprintf( '<nav class="pagination" aria-label="%s">', esc_attr__( 'Pagination', 'techvenza-core' ) );
		$h   .= $page > 1
			? sprintf( '<a class="pg" href="%s" data-page="%d" aria-label="%s">%s</a>', $link( $page - 1 ), $page - 1, esc_attr__( 'Previous page', 'techvenza-core' ), self::icon( 'chevLeft' ) )
			: sprintf( '<span class="pg" aria-disabled="true">%s</span>', self::icon( 'chevLeft' ) );
		for ( $i = 1; $i <= $pages; $i++ ) {
			$h .= $i === $page
				? sprintf( '<span class="pg" aria-current="page">%d</span>', $i )
				/* translators: %d: page number */
				: sprintf( '<a class="pg" href="%s" data-page="%d" aria-label="%s">%d</a>', $link( $i ), $i, esc_attr( sprintf( __( 'Page %d', 'techvenza-core' ), $i ) ), $i );
		}
		$h .= $page < $pages
			? sprintf( '<a class="pg" href="%s" data-page="%d" aria-label="%s">%s</a>', $link( $page + 1 ), $page + 1, esc_attr__( 'Next page', 'techvenza-core' ), self::icon( 'chevRight' ) )
			: sprintf( '<span class="pg" aria-disabled="true">%s</span>', self::icon( 'chevRight' ) );
		return $h . '</nav>';
	}

	/**
	 * Turn site-relative paths ("/apply/") into absolute URLs; leave absolute URLs alone.
	 *
	 * @param string $url URL or path.
	 */
	public static function url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return home_url( $url );
		}
		return $url;
	}

	/**
	 * Form field (labels, hints, error slot, counters).
	 *
	 * @param array<string,mixed> $o id, name, label, type, required, hint, opt, value, placeholder, rows, options, autocomplete, error, max, strength, full.
	 */
	public static function field( array $o ): string {
		$id    = (string) $o['id'];
		$name  = (string) ( $o['name'] ?? $id );
		$type  = (string) ( $o['type'] ?? 'text' );
		$req   = ! empty( $o['required'] ) ? ' required aria-required="true"' : '';
		$desc  = trim( ( ! empty( $o['hint'] ) ? $id . '-h ' : '' ) . $id . '-e' );
		$value = (string) ( $o['value'] ?? '' );
		$ph    = ! empty( $o['placeholder'] ) ? ' placeholder="' . esc_attr( $o['placeholder'] ) . '"' : '';
		$ac    = ! empty( $o['autocomplete'] ) ? ' autocomplete="' . esc_attr( $o['autocomplete'] ) . '"' : '';
		$max   = ! empty( $o['max'] ) ? ' data-max="' . (int) $o['max'] . '"' : '';

		switch ( $type ) {
			case 'textarea':
				$ctrl = sprintf( '<textarea class="textarea" id="%1$s" name="%2$s" rows="%3$d" aria-describedby="%4$s"%5$s%6$s%7$s>%8$s</textarea>', esc_attr( $id ), esc_attr( $name ), (int) ( $o['rows'] ?? 5 ), esc_attr( $desc ), $req, $ph, $max, esc_textarea( $value ) );
				break;
			case 'select':
				$opts = '';
				foreach ( (array) ( $o['options'] ?? array() ) as $v => $l ) {
					$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( (string) $v ), selected( $value, (string) $v, false ), esc_html( (string) $l ) );
				}
				$ctrl = sprintf( '<select class="select" id="%1$s" name="%2$s" aria-describedby="%3$s"%4$s>%5$s</select>', esc_attr( $id ), esc_attr( $name ), esc_attr( $desc ), $req, $opts );
				break;
			case 'password':
				$ctrl = sprintf(
					'<div class="input-group"><input class="input" id="%1$s" name="%2$s" type="password" aria-describedby="%3$s"%4$s%5$s%6$s><button class="icon-btn" type="button" data-action="togglePw" data-target="%1$s" aria-label="%7$s" aria-pressed="false">%8$s</button></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $desc ),
					$req,
					$ac ? $ac : ' autocomplete="current-password"',
					! empty( $o['strength'] ) ? ' data-strength' : '',
					esc_attr__( 'Show password', 'techvenza-core' ),
					self::icon( 'eye' )
				);
				break;
			default:
				$ctrl = sprintf( '<input class="input" id="%1$s" name="%2$s" type="%3$s" value="%4$s" aria-describedby="%5$s"%6$s%7$s%8$s%9$s>', esc_attr( $id ), esc_attr( $name ), esc_attr( $type ), esc_attr( $value ), esc_attr( $desc ), $req, $ph, $ac, $max );
		}

		$extra = '';
		if ( ! empty( $o['strength'] ) ) {
			$extra .= sprintf( '<div class="strength" data-score="0" aria-hidden="true"><span></span><span></span><span></span><span></span></div><span class="hint" id="%s-sh" aria-live="polite">%s</span>', esc_attr( $id ), esc_html__( 'Use 10+ characters with a mix of letters, numbers and symbols.', 'techvenza-core' ) );
		}
		if ( ! empty( $o['hint'] ) ) {
			$extra .= sprintf( '<span class="hint" id="%s-h">%s</span>', esc_attr( $id ), esc_html( $o['hint'] ) );
		}
		if ( ! empty( $o['max'] ) ) {
			$extra .= sprintf( '<span class="char-count" data-count-for="%s">%d / %d</span>', esc_attr( $id ), mb_strlen( $value ), (int) $o['max'] );
		}
		return sprintf(
			'<div class="field%1$s" data-field="%2$s"><label class="label" for="%3$s">%4$s%5$s%6$s</label>%7$s%8$s<span class="error" id="%3$s-e" role="alert">%9$s <span>%10$s</span></span></div>',
			! empty( $o['full'] ) ? ' full' : '',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_html( $o['label'] ),
			! empty( $o['opt'] ) ? ' <span class="opt">' . esc_html__( '(optional)', 'techvenza-core' ) . '</span>' : '',
			! empty( $o['required'] ) ? ' <span aria-hidden="true" style="color:var(--rose)">*</span>' : '',
			$ctrl,
			$extra,
			self::icon( 'alert' ),
			esc_html( $o['error'] ?? __( 'This field is required.', 'techvenza-core' ) )
		);
	}

	/**
	 * Checkbox grid.
	 *
	 * @param string               $name     Field name (without []).
	 * @param array<string,string> $items    Value → label.
	 * @param array<int,string>    $selected Selected values.
	 * @param array<string,mixed>  $o        label, box, type.
	 */
	public static function checks( string $name, array $items, array $selected = array(), array $o = array() ): string {
		$type = $o['type'] ?? 'checkbox';
		$out  = '';
		foreach ( $items as $v => $l ) {
			$out .= sprintf(
				'<label class="check%1$s"><input type="%2$s" name="%3$s" value="%4$s"%5$s> <span>%6$s</span></label>',
				! empty( $o['box'] ) ? ' check-box' : '',
				esc_attr( $type ),
				esc_attr( 'checkbox' === $type ? $name . '[]' : $name ),
				esc_attr( (string) $v ),
				checked( in_array( (string) $v, $selected, true ), true, false ),
				esc_html( $l )
			);
		}
		return sprintf( '<div class="check-grid" role="group"%s>%s</div>', ! empty( $o['label'] ) ? ' aria-label="' . esc_attr( $o['label'] ) . '"' : '', $out );
	}

	/**
	 * Wrap a block's inner HTML in the standard section/container structure.
	 *
	 * @param string              $inner      Inner HTML.
	 * @param array<string,mixed> $attributes Block attributes (surface, spacing, anchor).
	 * @param string              $extra      Extra classes.
	 * @param bool                $container  Add .container.
	 * @param string              $tag        Wrapper tag.
	 */
	public static function section( string $inner, array $attributes, string $extra = '', bool $container = true, string $tag = 'section' ): string {
		$classes = array( 'tv-block', 'section' );
		$surface = $attributes['surface'] ?? 'default';
		$spacing = $attributes['spacing'] ?? 'default';
		if ( 'alt' === $surface ) {
			$classes[] = 'alt';
		}
		if ( 'tight' === $spacing ) {
			$classes[] = 'tight';
		}
		if ( 'none-bottom' === $spacing ) {
			$classes[] = 'tv-pb-0';
		}
		if ( 'none-top' === $spacing ) {
			$classes[] = 'tv-pt-0';
		}
		if ( $extra ) {
			$classes[] = $extra;
		}
		$wrapper = empty( $attributes['_plain'] ) && function_exists( 'get_block_wrapper_attributes' ) && \WP_Block_Supports::$block_to_render
			? get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) )
			: 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		return sprintf( '<%1$s %2$s>%3$s</%1$s>', tag_escape( $tag ), $wrapper, $container ? '<div class="container">' . $inner . '</div>' : $inner );
	}

	/**
	 * Wrapper for blocks that re-render in place through the fragment endpoint (live.js).
	 *
	 * @param string              $block      Block slug without namespace.
	 * @param array<string,mixed> $attributes Attributes to round-trip.
	 * @param string              $inner      Inner HTML.
	 * @param array<string,mixed> $data       Extra data attributes (term).
	 * @param string              $tag        Tag.
	 * @param string              $classes    Classes.
	 */
	public static function live( string $block, array $attributes, string $inner, array $data = array(), string $tag = 'div', string $classes = '' ): string {
		$extra = '';
		foreach ( $data as $k => $v ) {
			$extra .= sprintf( ' data-%s="%s"', esc_attr( $k ), esc_attr( (string) $v ) );
		}
		unset( $attributes['className'], $attributes['anchor'], $attributes['lock'], $attributes['metadata'] );
		return sprintf(
			'<%1$s class="tv-live %2$s" data-live="%3$s" data-attrs="%4$s"%5$s>%6$s</%1$s>',
			tag_escape( $tag ),
			esc_attr( $classes ),
			esc_attr( $block ),
			esc_attr( (string) wp_json_encode( $attributes ) ),
			$extra,
			$inner
		);
	}

	/**
	 * Compact or full newsletter sign-up (posts to REST; works without JS via admin-post).
	 *
	 * @param array<string,mixed> $o compact, eyebrow, heading, text, source.
	 */
	public static function newsletter( array $o = array() ): string {
		$id      = wp_unique_id( 'nl-' );
		$compact = ! empty( $o['compact'] );
		return sprintf(
			'<section class="newsletter %1$s reveal" aria-labelledby="%2$s-h"><div><span class="eyebrow" style="--accent:var(--blue)">%3$s</span><h2 class="%4$s mt-4" id="%2$s-h">%5$s</h2><p class="mt-4" style="max-width:46ch">%6$s</p></div>
			<form method="post" action="%7$s" data-form="newsletterQuick" novalidate><input type="hidden" name="action" value="techvenza_subscribe"><input type="hidden" name="source" value="%8$s">%9$s
			<div class="field" data-field="email"><label class="sr-only" for="%2$s">%10$s</label><div class="nl-form"><input class="input" id="%2$s" name="email" type="email" required autocomplete="email" placeholder="you@company.com" aria-describedby="%2$s-e"><button class="btn btn-primary" type="submit">%11$s</button></div>
			<span class="error" id="%2$s-e" role="alert">%12$s %13$s</span></div>
			<label class="tv-hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
			<p class="nl-fine">%14$s <a class="link" href="%15$s">%16$s</a> · <a class="link" href="%17$s">%18$s</a></p></form></section>',
			$compact ? 'compact' : '',
			esc_attr( $id ),
			esc_html( $o['eyebrow'] ?? __( 'The Weekly Brief', 'techvenza-core' ) ),
			$compact ? 'h3' : 'h2',
			esc_html( $o['heading'] ?? __( 'One thoughtful email a week.', 'techvenza-core' ) ),
			esc_html( $o['text'] ?? __( 'The most useful stories on AI, software, business and industry — selected by our editors, with no sponsored filler.', 'techvenza-core' ) ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $o['source'] ?? 'block' ),
			wp_nonce_field( 'techvenza_subscribe', '_tvnonce', false, false ),
			esc_html__( 'Email address', 'techvenza-core' ),
			esc_html__( 'Subscribe', 'techvenza-core' ),
			self::icon( 'alert' ),
			esc_html__( 'Enter a valid email address.', 'techvenza-core' ),
			esc_html__( 'We will email you to confirm before sending anything. Every issue has a one-click unsubscribe.', 'techvenza-core' ),
			esc_url( self::page_url( 'privacy-policy' ) ),
			esc_html__( 'Privacy', 'techvenza-core' ),
			esc_url( self::page_url( 'newsletter' ) ),
			esc_html__( 'Choose topics', 'techvenza-core' )
		);
	}

	/**
	 * URL of a page by slug (falls back to /slug/).
	 *
	 * @param string $slug Page slug.
	 */
	public static function page_url( string $slug ): string {
		static $cache = array();
		if ( ! isset( $cache[ $slug ] ) ) {
			$page           = get_page_by_path( $slug );
			$cache[ $slug ] = $page ? (string) get_permalink( $page ) : home_url( '/' . $slug . '/' );
		}
		return $cache[ $slug ];
	}
	/**
	 * Ensure H2/H3 headings in rendered HTML have ids and return a table of contents.
	 *
	 * @param string            $html   Rendered HTML (ids are added in place).
	 * @param array<int,string> $levels Heading tags to include.
	 * @return array<int,array{id:string,text:string,level:int}>
	 */
	public static function headings( string &$html, array $levels = array( 'h2', 'h3' ) ): array {
		$items = array();
		$used  = array();
		$tags  = implode( '|', array_map( 'preg_quote', $levels ) );
		$html  = (string) preg_replace_callback(
			'#<(' . $tags . ')(\s[^>]*)?>(.*?)</\1>#is',
			static function ( array $m ) use ( &$items, &$used ): string {
				$attrs = $m[2] ?? '';
				$text  = trim( html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $text ) {
					return $m[0];
				}
				if ( preg_match( '/\sid=("|\')([^"\']+)\1/i', $attrs, $idm ) ) {
					$id = $idm[2];
				} else {
					$id   = sanitize_title( $text );
					$id   = '' !== $id ? $id : 'section';
					$base = $id;
					$n    = 2;
					while ( isset( $used[ $id ] ) ) {
						$id = $base . '-' . $n;
						++$n;
					}
					$attrs .= ' id="' . esc_attr( $id ) . '"';
				}
				$used[ $id ] = true;
				$items[]     = array(
					'id'    => $id,
					'text'  => $text,
					'level' => (int) substr( strtolower( $m[1] ), 1 ),
				);
				return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
			},
			$html
		);
		return $items;
	}

	/**
	 * Table-of-contents list items.
	 *
	 * @param array<int,array{id:string,text:string,level:int}> $items Headings.
	 */
	public static function toc_items( array $items ): string {
		return implode(
			'',
			array_map(
				static fn( $h ) => sprintf( '<li><a href="#%s"%s>%s</a></li>', esc_attr( $h['id'] ), $h['level'] > 2 ? ' class="sub"' : '', esc_html( $h['text'] ) ),
				$items
			)
		);
	}
}
