<?php
/**
 * Bulk Blog CSV: column schema, parsing (delimiter and encoding detection) and per-row
 * validation that produces clean data plus errors and warnings for the preview.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Import;

use Techvenza\Core\Content\PostMeta;

/**
 * CSV schema and validation.
 */
final class Csv {

	public const MAX_ROWS = 1000;

	/**
	 * Supported columns: key => [label, required, description, example, aliases].
	 *
	 * @return array<string,array{0:string,1:bool,2:string,3:string,4:array<int,string>}>
	 */
	public static function columns(): array {
		return array(
			'title'            => array( __( 'Title', 'techvenza-core' ), true, __( 'Article headline.', 'techvenza-core' ), 'How Platform Teams Cut Lead Time in Half', array( 'post_title', 'headline', 'name' ) ),
			'content'          => array( __( 'Content', 'techvenza-core' ), true, __( 'Body in HTML, Markdown or plain text (detected automatically). Images in the body can be imported too.', 'techvenza-core' ), "## Why it matters\n\nPlatform teams...", array( 'post_content', 'body', 'html', 'markdown' ) ),
			'slug'             => array( __( 'Slug', 'techvenza-core' ), false, __( 'URL slug. Generated from the title when empty. Used to find existing articles when updating.', 'techvenza-core' ), 'platform-teams-lead-time', array( 'post_name', 'url_slug', 'permalink' ) ),
			'excerpt'          => array( __( 'Excerpt', 'techvenza-core' ), false, __( 'Card and listing summary (40+ characters recommended).', 'techvenza-core' ), 'What three platform teams changed to halve lead time.', array( 'post_excerpt', 'summary_short', 'dek' ) ),
			'author'           => array( __( 'Author', 'techvenza-core' ), false, __( 'Email, username or display name of an existing user. Empty = you.', 'techvenza-core' ), 'priya-natarajan@example.com', array( 'post_author', 'author_email', 'byline' ) ),
			'category'         => array( __( 'Category', 'techvenza-core' ), false, __( 'Name or slug; several separated by |. Use "Parent > Child" for subcategories. The first one is the primary topic.', 'techvenza-core' ), 'Software Development > DevOps', array( 'categories', 'topic' ) ),
			'tags'             => array( __( 'Tags', 'techvenza-core' ), false, __( 'Separated by | or commas.', 'techvenza-core' ), 'Platform engineering|DevOps', array( 'tag', 'post_tags', 'keywords' ) ),
			'status'           => array( __( 'Status', 'techvenza-core' ), false, __( 'draft, pending (review queue), publish, future or private. Empty = the default chosen when uploading.', 'techvenza-core' ), 'publish', array( 'post_status' ) ),
			'publish_date'     => array( __( 'Publication date', 'techvenza-core' ), false, __( 'YYYY-MM-DD or YYYY-MM-DD HH:MM in the site time zone. A future date with status publish schedules the article.', 'techvenza-core' ), '2026-11-04 09:00', array( 'date', 'post_date', 'published', 'published_at' ) ),
			'featured_image'   => array( __( 'Featured image URL', 'techvenza-core' ), false, __( 'Public image URL (JPG, PNG, WebP, GIF, AVIF or SVG), or a file name inside the images ZIP. Re-used if already imported.', 'techvenza-core' ), 'https://example.com/images/platform.jpg', array( 'featured_image_url', 'image', 'image_url', 'thumbnail' ) ),
			'image_alt'        => array( __( 'Image alt text', 'techvenza-core' ), false, __( 'Describes the featured image for screen readers and image search.', 'techvenza-core' ), 'Engineers reviewing a deployment dashboard', array( 'featured_image_alt', 'alt', 'alt_text' ) ),
			'image_caption'    => array( __( 'Image caption / credit', 'techvenza-core' ), false, __( 'Shown under the featured image.', 'techvenza-core' ), 'Photo: Example Studio', array( 'caption', 'image_credit' ) ),
			'meta_title'       => array( __( 'Meta title', 'techvenza-core' ), false, __( 'SEO title (about 60 characters). Empty = article title.', 'techvenza-core' ), 'Platform Teams: How to Halve Lead Time', array( 'seo_title' ) ),
			'meta_description' => array( __( 'Meta description', 'techvenza-core' ), false, __( 'Search snippet (120–160 characters). Empty = excerpt.', 'techvenza-core' ), 'Three platform teams share the changes that halved lead time — and the metrics they used to prove it.', array( 'seo_description', 'description' ) ),
			'focus_keyword'    => array( __( 'Focus keyword', 'techvenza-core' ), false, __( 'Main search phrase.', 'techvenza-core' ), 'platform engineering lead time', array( 'keyword', 'focus_keyphrase' ) ),
			'summary'          => array( __( 'Answer-first summary', 'techvenza-core' ), false, __( '40–80 word direct answer shown as "In short" and used by AI assistants.', 'techvenza-core' ), 'Platform teams halve lead time by...', array( 'tldr', 'in_short', 'answer' ) ),
			'faq'              => array( __( 'FAQ', 'techvenza-core' ), false, __( 'Question|Answer pairs separated by ||, or a JSON array of {"q","a"}.', 'techvenza-core' ), 'What is a paved road?|A supported default path for building and shipping services.||Who owns it?|The platform team.', array( 'faqs', 'questions' ) ),
			'sources'          => array( __( 'Sources', 'techvenza-core' ), false, __( 'Title|URL pairs separated by ||, or plain URLs.', 'techvenza-core' ), 'DORA report|https://dora.dev/research/', array( 'citations', 'references' ) ),
			'key_points'       => array( __( 'Key points', 'techvenza-core' ), false, __( 'Takeaways separated by ||.', 'techvenza-core' ), 'Measure lead time weekly||Start with one paved road', array( 'takeaways' ) ),
			'article_type'     => array( __( 'Article type', 'techvenza-core' ), false, __( 'deep-dive, analysis, guide, opinion or news.', 'techvenza-core' ), 'analysis', array( 'type', 'format' ) ),
			'featured'         => array( __( 'Featured', 'techvenza-core' ), false, __( 'yes/no — eligible for the homepage hero.', 'techvenza-core' ), 'no', array( 'is_featured' ) ),
			'editors_pick'     => array( __( "Editor's pick", 'techvenza-core' ), false, __( 'yes/no.', 'techvenza-core' ), 'no', array( 'pick' ) ),
			'canonical_url'    => array( __( 'Canonical URL', 'techvenza-core' ), false, __( 'Only when the article was first published elsewhere.', 'techvenza-core' ), '', array( 'canonical' ) ),
			'noindex'          => array( __( 'Noindex', 'techvenza-core' ), false, __( 'yes to keep out of search results.', 'techvenza-core' ), 'no', array() ),
			'series'           => array( __( 'Series', 'techvenza-core' ), false, __( 'Series name (created if missing).', 'techvenza-core' ), '', array() ),
			'sponsored'        => array( __( 'Sponsored by', 'techvenza-core' ), false, __( 'Sponsor name; marks the article as sponsored (links become rel="sponsored").', 'techvenza-core' ), '', array( 'sponsor', 'sponsor_name' ) ),
		);
	}

	/**
	 * Header aliases → canonical keys.
	 *
	 * @return array<string,string>
	 */
	private static function aliases(): array {
		$map = array();
		foreach ( self::columns() as $key => $def ) {
			$map[ $key ] = $key;
			foreach ( $def[4] as $alias ) {
				$map[ $alias ] = $key;
			}
		}
		return $map;
	}

	/**
	 * Normalise a header cell.
	 *
	 * @param string $h Header.
	 */
	private static function norm( string $h ): string {
		$h = strtolower( trim( $h ) );
		$h = (string) preg_replace( '/[^a-z0-9]+/', '_', $h );
		return trim( $h, '_' );
	}

	/**
	 * Read a CSV file.
	 *
	 * @param string $path Path.
	 * @return array{rows:array<int,array<string,string>>,unknown:array<int,string>}|\WP_Error
	 */
	public static function read( string $path ) {
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( '' === trim( $raw ) ) {
			return new \WP_Error( 'tv_csv', __( 'The CSV file is empty.', 'techvenza-core' ) );
		}
		$raw = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$raw = (string) mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
		}
		$first = strtok( $raw, "\n" );
		$delim = ',';
		foreach ( array( ';', "\t" ) as $d ) {
			if ( substr_count( (string) $first, $d ) > substr_count( (string) $first, $delim ) ) {
				$delim = $d;
			}
		}
		$fh = fopen( 'php://temp', 'r+' );
		if ( ! $fh ) {
			return new \WP_Error( 'tv_csv', __( 'Could not read the file.', 'techvenza-core' ) );
		}
		fwrite( $fh, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $fh );
		$head = fgetcsv( $fh, 0, $delim, '"', '' );
		if ( ! $head ) {
			return new \WP_Error( 'tv_csv', __( 'The header row is missing.', 'techvenza-core' ) );
		}
		$aliases = self::aliases();
		$keys    = array();
		$unknown = array();
		foreach ( $head as $i => $h ) {
			$n          = self::norm( (string) $h );
			$keys[ $i ] = $aliases[ $n ] ?? '';
			if ( ! $keys[ $i ] && '' !== $n ) {
				$unknown[] = (string) $h;
			}
		}
		foreach ( self::columns() as $key => $def ) {
			if ( $def[1] && ! in_array( $key, $keys, true ) ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %s: column name */
				return new \WP_Error( 'tv_csv', sprintf( __( 'Required column "%s" is missing. Download the template to see the expected headers.', 'techvenza-core' ), $key ) );
			}
		}
		$rows = array();
		while ( ( $cells = fgetcsv( $fh, 0, $delim, '"', '' ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( count( array_filter( array_map( 'trim', array_map( 'strval', (array) $cells ) ) ) ) === 0 ) {
				continue;
			}
			$row = array();
			foreach ( $keys as $i => $key ) {
				if ( $key ) {
					$row[ $key ] = trim( (string) ( $cells[ $i ] ?? '' ) );
				}
			}
			$rows[] = $row;
			if ( count( $rows ) > self::MAX_ROWS ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				/* translators: %d: limit */
				return new \WP_Error( 'tv_csv', sprintf( __( 'A file can hold at most %d articles. Split it into several files.', 'techvenza-core' ), self::MAX_ROWS ) );
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! $rows ) {
			return new \WP_Error( 'tv_csv', __( 'The file has a header but no articles.', 'techvenza-core' ) );
		}
		return array(
			'rows'    => $rows,
			'unknown' => $unknown,
		);
	}

	/**
	 * Split a list cell.
	 *
	 * @param string $v     Cell.
	 * @param bool   $comma Also split on commas.
	 * @return array<int,string>
	 */
	public static function list( string $v, bool $comma = true ): array {
		$parts = preg_split( $comma ? '/\s*[|,;]\s*/' : '/\s*\|\s*/', $v );
		return array_values( array_filter( array_map( 'trim', $parts ? $parts : array() ), static fn( $s ) => '' !== $s ) );
	}

	/**
	 * Parse a yes/no cell.
	 *
	 * @param string $v Cell.
	 */
	public static function yes( string $v ): bool {
		return in_array( strtolower( trim( $v ) ), array( '1', 'yes', 'y', 'true', 'on', 'x' ), true );
	}

	/**
	 * Parse FAQ cell.
	 *
	 * @param string $v Cell.
	 * @return array<int,array{q:string,a:string}>|null Null when malformed.
	 */
	public static function faq( string $v ): ?array {
		$v = trim( $v );
		if ( '' === $v ) {
			return array();
		}
		if ( '[' === $v[0] ) {
			$data = json_decode( $v, true );
			if ( ! is_array( $data ) ) {
				return null;
			}
			$out = array();
			foreach ( $data as $row ) {
				$q = (string) ( $row['q'] ?? $row['question'] ?? '' );
				$a = (string) ( $row['a'] ?? $row['answer'] ?? '' );
				if ( '' !== $q && '' !== $a ) {
					$out[] = array(
						'q' => $q,
						'a' => $a,
					);
				}
			}
			return $out;
		}
		$out = array();
		foreach ( preg_split( '/\s*\|\|\s*/', $v ) ? (array) preg_split( '/\s*\|\|\s*/', $v ) : array() as $pair ) {
			$bits = explode( '|', (string) $pair, 2 );
			if ( 2 !== count( $bits ) || '' === trim( $bits[0] ) || '' === trim( $bits[1] ) ) {
				return null;
			}
			$out[] = array(
				'q' => trim( $bits[0] ),
				'a' => trim( $bits[1] ),
			);
		}
		return $out;
	}

	/**
	 * Parse sources cell.
	 *
	 * @param string $v Cell.
	 * @return array<int,array{title:string,url:string,rel:string}>
	 */
	public static function sources( string $v ): array {
		$out = array();
		foreach ( preg_split( '/\s*\|\|\s*|\s*\R\s*/', trim( $v ) ) ? (array) preg_split( '/\s*\|\|\s*|\s*\R\s*/', trim( $v ) ) : array() as $item ) {
			$bits  = array_map( 'trim', explode( '|', (string) $item ) );
			$url   = '';
			$title = '';
			foreach ( $bits as $b ) {
				if ( preg_match( '#^https?://#i', $b ) ) {
					$url = $b;
				} elseif ( '' !== $b ) {
					$title = $b;
				}
			}
			if ( $url ) {
				$out[] = array(
					'title' => $title,
					'url'   => esc_url_raw( $url ),
					'rel'   => '',
				);
			}
		}
		return $out;
	}

	/**
	 * Content format: html, markdown or text.
	 *
	 * @param string $v Content.
	 */
	public static function format( string $v ): string {
		if ( preg_match( '/<(p|h[1-6]|ul|ol|div|figure|table|blockquote|img|br|strong|em|a)\b[^>]*>/i', $v ) ) {
			return 'html';
		}
		if ( preg_match( '/(^|\n)\s*(#{1,6}\s|[-*+]\s|\d+\.\s|>\s|```)|\*\*[^*]+\*\*|\[[^\]]+\]\([^)]+\)/', $v ) ) {
			return 'markdown';
		}
		return 'text';
	}

	/**
	 * Parse a date in the site time zone.
	 *
	 * @param string $v Cell.
	 * @return string|null Y-m-d H:i:s (local) or null when invalid; '' when empty.
	 */
	public static function date( string $v ): ?string {
		$v = trim( $v );
		if ( '' === $v ) {
			return '';
		}
		try {
			$dt = new \DateTimeImmutable( $v, wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}
		if ( (int) $dt->format( 'Y' ) < 1990 || (int) $dt->format( 'Y' ) > 2100 ) {
			return null;
		}
		return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Find a user by email, login, nicename or display name.
	 *
	 * @param string $v Cell.
	 */
	public static function user( string $v ): ?\WP_User {
		$v = trim( $v );
		if ( '' === $v ) {
			return null;
		}
		foreach ( array( 'email', 'login', 'slug' ) as $field ) {
			$u = get_user_by( $field, $v );
			if ( $u ) {
				return $u;
			}
		}
		$found = get_users(
			array(
				'search'         => $v,
				'search_columns' => array( 'display_name' ),
				'number'         => 1,
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * Validate and normalise every row.
	 *
	 * @param array<int,array<string,string>> $rows    Rows.
	 * @param array<string,mixed>             $opts    status, duplicates (skip|update|new), create_terms, images_dir.
	 * @return array<int,array{line:int,data:array<string,mixed>,errors:array<int,string>,warnings:array<int,string>}>
	 */
	public static function validate( array $rows, array $opts ): array {
		$out   = array();
		$slugs = array();
		$types = array_keys( PostMeta::type_labels() );
		foreach ( $rows as $i => $r ) {
			$e = array();
			$w = array();
			$d = array();

			$d['title'] = sanitize_text_field( $r['title'] ?? '' );
			if ( '' === $d['title'] ) {
				$e[] = __( 'Title is empty.', 'techvenza-core' );
			} elseif ( mb_strlen( $d['title'] ) > 200 ) {
				$e[] = __( 'Title is longer than 200 characters.', 'techvenza-core' );
			}
			$d['content'] = (string) ( $r['content'] ?? '' );
			if ( '' === trim( $d['content'] ) ) {
				$e[] = __( 'Content is empty.', 'techvenza-core' );
			}
			$d['format'] = self::format( $d['content'] );

			$d['slug'] = sanitize_title( '' !== ( $r['slug'] ?? '' ) ? $r['slug'] : $d['title'] );
			if ( '' === $d['slug'] && '' !== $d['title'] ) {
				$e[] = __( 'Could not build a slug from the title.', 'techvenza-core' );
			}
			if ( isset( $slugs[ $d['slug'] ] ) ) {
				/* translators: %d: line number */
				$e[] = sprintf( __( 'Same slug as line %d in this file.', 'techvenza-core' ), $slugs[ $d['slug'] ] );
			}
			$slugs[ $d['slug'] ] = $i + 2;
			$existing            = $d['slug'] ? get_page_by_path( $d['slug'], OBJECT, 'post' ) : null;
			$d['existing']       = $existing ? $existing->ID : 0;
			if ( $existing ) {
				$mode = (string) ( $opts['duplicates'] ?? 'skip' );
				$w[]  = 'update' === $mode
					/* translators: %d: post id */
					? sprintf( __( 'Updates existing article #%d.', 'techvenza-core' ), $existing->ID )
					: ( 'new' === $mode ? __( 'Slug exists: a new article with a numbered slug will be created.', 'techvenza-core' ) : __( 'Slug exists: this row will be skipped.', 'techvenza-core' ) );
			}

			$status = strtolower( (string) ( $r['status'] ?? '' ) );
			$status = '' === $status ? (string) ( $opts['status'] ?? 'draft' ) : $status;
			$status = 'published' === $status ? 'publish' : ( 'scheduled' === $status ? 'future' : $status );
			if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'future', 'private' ), true ) ) {
				/* translators: %s: status */
				$e[]    = sprintf( __( 'Unknown status "%s".', 'techvenza-core' ), $status );
				$status = 'draft';
			}
			$date = self::date( (string) ( $r['publish_date'] ?? '' ) );
			if ( null === $date ) {
				$e[]  = __( 'Publication date is not a valid date (use YYYY-MM-DD HH:MM).', 'techvenza-core' );
				$date = '';
			}
			if ( $date && in_array( $status, array( 'publish', 'future' ), true ) ) {
				$status = strtotime( $date ) > current_time( 'timestamp' ) ? 'future' : 'publish'; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			} elseif ( 'future' === $status && ! $date ) {
				$e[] = __( 'Status "future" needs a publication date.', 'techvenza-core' );
			}
			$d['status'] = $status;
			$d['date']   = $date;

			$d['author'] = 0;
			if ( '' !== ( $r['author'] ?? '' ) ) {
				$u = self::user( (string) $r['author'] );
				if ( $u ) {
					$d['author'] = $u->ID;
				} else {
					/* translators: %s: author */
					$w[] = sprintf( __( 'Author "%s" not found — the article will be credited to you.', 'techvenza-core' ), $r['author'] );
				}
			}

			$d['categories'] = array();
			foreach ( self::list( (string) ( $r['category'] ?? '' ), false ) as $cat ) {
				$path  = array_map( 'trim', explode( '>', $cat ) );
				$found = self::find_term( $path );
				if ( ! $found && empty( $opts['create_terms'] ) ) {
					/* translators: %s: category */
					$w[] = sprintf( __( 'Category "%s" does not exist and will be ignored.', 'techvenza-core' ), $cat );
					continue;
				}
				if ( ! $found ) {
					/* translators: %s: category */
					$w[] = sprintf( __( 'Category "%s" will be created.', 'techvenza-core' ), $cat );
				}
				$d['categories'][] = $path;
			}
			if ( ! $d['categories'] ) {
				$w[] = __( 'No category: the article will use the default category.', 'techvenza-core' );
			}
			$d['tags'] = array_map( 'sanitize_text_field', self::list( (string) ( $r['tags'] ?? '' ) ) );

			$img            = (string) ( $r['featured_image'] ?? '' );
			$d['image']     = '';
			$d['image_alt'] = sanitize_text_field( $r['image_alt'] ?? '' );
			if ( '' !== $img ) {
				if ( preg_match( '#^https?://#i', $img ) ) {
					$d['image'] = esc_url_raw( $img );
					if ( ! $d['image'] ) {
						$e[] = __( 'Featured image URL is not valid.', 'techvenza-core' );
					}
				} elseif ( ! empty( $opts['images_dir'] ) && is_file( trailingslashit( (string) $opts['images_dir'] ) . basename( $img ) ) ) {
					$d['image'] = 'zip:' . basename( $img );
				} else {
					/* translators: %s: file */
					$e[] = sprintf( __( 'Featured image "%s" is neither a URL nor a file in the uploaded ZIP.', 'techvenza-core' ), $img );
				}
				if ( '' === $d['image_alt'] ) {
					$w[] = __( 'Featured image has no alt text (needed for accessibility and image search).', 'techvenza-core' );
				}
			}
			$d['image_caption'] = sanitize_text_field( $r['image_caption'] ?? '' );

			$d['excerpt']    = sanitize_textarea_field( $r['excerpt'] ?? '' );
			$d['meta_title'] = sanitize_text_field( $r['meta_title'] ?? '' );
			if ( mb_strlen( $d['meta_title'] ) > 70 ) {
				$w[] = __( 'Meta title is longer than 70 characters and may be cut in search results.', 'techvenza-core' );
			}
			$d['meta_description'] = sanitize_textarea_field( $r['meta_description'] ?? '' );
			if ( mb_strlen( $d['meta_description'] ) > 170 ) {
				$w[] = __( 'Meta description is longer than 170 characters and may be cut.', 'techvenza-core' );
			}
			$d['focus_keyword'] = sanitize_text_field( $r['focus_keyword'] ?? '' );
			$d['summary']       = sanitize_textarea_field( $r['summary'] ?? '' );
			$faq                = self::faq( (string) ( $r['faq'] ?? '' ) );
			if ( null === $faq ) {
				$e[] = __( 'FAQ is not in "Question|Answer||Question|Answer" or JSON format.', 'techvenza-core' );
				$faq = array();
			}
			$d['faq']        = $faq;
			$d['sources']    = self::sources( (string) ( $r['sources'] ?? '' ) );
			$d['key_points'] = array_values( array_filter( array_map( 'trim', explode( '||', (string) ( $r['key_points'] ?? '' ) ) ), static fn( $s ) => '' !== $s ) );
			$type            = strtolower( (string) ( $r['article_type'] ?? '' ) );
			if ( '' !== $type && ! in_array( $type, $types, true ) ) {
				/* translators: %s: type */
				$w[]  = sprintf( __( 'Unknown article type "%s" — using analysis.', 'techvenza-core' ), $type );
				$type = 'analysis';
			}
			$d['type']         = $type;
			$d['featured']     = self::yes( (string) ( $r['featured'] ?? '' ) );
			$d['editors_pick'] = self::yes( (string) ( $r['editors_pick'] ?? '' ) );
			$d['noindex']      = self::yes( (string) ( $r['noindex'] ?? '' ) );
			$d['canonical']    = esc_url_raw( (string) ( $r['canonical_url'] ?? '' ) );
			$d['series']       = sanitize_text_field( $r['series'] ?? '' );
			$d['sponsor']      = sanitize_text_field( $r['sponsored'] ?? '' );

			$out[] = array(
				'line'     => $i + 2,
				'data'     => $d,
				'errors'   => $e,
				'warnings' => $w,
			);
		}
		return $out;
	}

	/**
	 * Find a category from a "Parent > Child" path (names or slugs).
	 *
	 * @param array<int,string> $path Path.
	 */
	public static function find_term( array $path ): ?\WP_Term {
		$parent = 0;
		$term   = null;
		foreach ( $path as $name ) {
			$term = null;
			foreach ( array(
				'slug' => sanitize_title( $name ),
				'name' => $name,
			) as $field => $value ) {
				$found = get_terms(
					array(
						'taxonomy'   => 'category',
						'hide_empty' => false,
						$field       => $value,
						'parent'     => $parent,
						'number'     => 1,
					)
				);
				if ( is_array( $found ) && $found ) {
					$term = $found[0];
					break;
				}
			}
			if ( ! $term instanceof \WP_Term ) {
				return null;
			}
			$parent = $term->term_id;
		}
		return $term instanceof \WP_Term ? $term : null;
	}
}
