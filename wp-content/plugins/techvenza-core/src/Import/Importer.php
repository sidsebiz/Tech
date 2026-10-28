<?php
/**
 * Bulk Blog importer: turns validated CSV rows into articles (block content, taxonomy,
 * featured and inline images, SEO/AEO meta) and manages import jobs on disk.
 *
 * Jobs live in uploads/techvenza-imports/<id>/ (blocked from the web) with the original CSV,
 * extracted ZIP images, validated rows and per-row results, so long imports run in small
 * batches and can be resumed.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Import;

use Techvenza\Core\Cli\BlockConverter;

/**
 * Importer.
 */
final class Importer {

	public const BATCH = 5;

	/* ------------------------------------------------------------------ Jobs */

	/**
	 * Base directory for jobs.
	 */
	public static function base(): string {
		$up   = wp_upload_dir( null, false );
		$base = trailingslashit( $up['basedir'] ) . 'techvenza-imports/';
		if ( ! is_dir( $base ) ) {
			wp_mkdir_p( $base );
			file_put_contents( $base . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $base . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $base;
	}

	/**
	 * Directory of a job.
	 *
	 * @param string $id Job id.
	 */
	public static function dir( string $id ): string {
		return self::base() . preg_replace( '/[^a-z0-9]/', '', strtolower( $id ) ) . '/';
	}

	/**
	 * Load a job.
	 *
	 * @param string $id Job id.
	 * @return array<string,mixed>|null
	 */
	public static function job( string $id ): ?array {
		$file = self::dir( $id ) . 'job.json';
		if ( ! preg_match( '/^[a-z0-9]{12,40}$/', $id ) || ! is_file( $file ) ) {
			return null;
		}
		$job = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Save a job.
	 *
	 * @param array<string,mixed> $job Job.
	 */
	public static function save( array $job ): void {
		file_put_contents( self::dir( (string) $job['id'] ) . 'job.json', (string) wp_json_encode( $job ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Recent jobs, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent(): array {
		$jobs = array();
		foreach ( (array) glob( self::base() . '*/job.json' ) as $file ) {
			$job = json_decode( (string) file_get_contents( (string) $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $job ) ) {
				$jobs[] = $job;
			}
		}
		usort( $jobs, static fn( $a, $b ) => strcmp( (string) $b['created'], (string) $a['created'] ) );
		return array_slice( $jobs, 0, 10 );
	}

	/**
	 * Create a job from an uploaded CSV (and optional ZIP of images).
	 *
	 * @param string              $csv_path Uploaded CSV temp path.
	 * @param string              $zip_path Uploaded ZIP temp path ('' when none).
	 * @param string              $name     Original file name.
	 * @param array<string,mixed> $opts     Options.
	 * @return array<string,mixed>|\WP_Error Job.
	 */
	public static function create( string $csv_path, string $zip_path, string $name, array $opts ) {
		$id  = strtolower( wp_generate_password( 20, false ) );
		$dir = self::dir( $id );
		wp_mkdir_p( $dir . 'images' );
		if ( ! copy( $csv_path, $dir . 'source.csv' ) ) {
			return new \WP_Error( 'tv_csv', __( 'Could not store the uploaded file.', 'techvenza-core' ) );
		}
		if ( $zip_path ) {
			$res = self::extract_images( $zip_path, $dir . 'images/' );
			if ( is_wp_error( $res ) ) {
				self::delete( $id );
				return $res;
			}
		}
		$parsed = Csv::read( $dir . 'source.csv' );
		if ( is_wp_error( $parsed ) ) {
			self::delete( $id );
			return $parsed;
		}
		$opts['images_dir'] = $dir . 'images/';
		$rows               = Csv::validate( $parsed['rows'], $opts );
		file_put_contents( $dir . 'rows.json', (string) wp_json_encode( $rows ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$job = array(
			'id'      => $id,
			'name'    => sanitize_file_name( $name ),
			'created' => current_time( 'mysql' ),
			'user'    => get_current_user_id(),
			'opts'    => array_diff_key( $opts, array( 'images_dir' => 1 ) ),
			'status'  => 'validated',
			'total'   => count( $rows ),
			'valid'   => count( array_filter( $rows, static fn( $r ) => ! $r['errors'] ) ),
			'cursor'  => 0,
			'unknown' => $parsed['unknown'],
			'counts'  => array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'failed'  => 0,
			),
		);
		self::save( $job );
		return $job;
	}

	/**
	 * Unzip images (allow-listed extensions, no paths).
	 *
	 * @param string $zip Zip path.
	 * @param string $to  Destination.
	 * @return true|\WP_Error
	 */
	private static function extract_images( string $zip, string $to ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'tv_zip', __( 'This server cannot open ZIP files. Use image URLs in the CSV instead.', 'techvenza-core' ) );
		}
		$z = new \ZipArchive();
		if ( true !== $z->open( $zip ) ) {
			return new \WP_Error( 'tv_zip', __( 'The images ZIP could not be opened.', 'techvenza-core' ) );
		}
		$total = 0;
		for ( $i = 0; $i < $z->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$stat = $z->statIndex( $i );
			$name = basename( (string) ( $stat['name'] ?? '' ) );
			if ( ! $stat || str_ends_with( (string) $stat['name'], '/' ) || str_starts_with( $name, '.' ) || ! preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)$/i', $name ) ) {
				continue;
			}
			$total += (int) $stat['size'];
			if ( $total > 200 * MB_IN_BYTES ) {
				$z->close();
				return new \WP_Error( 'tv_zip', __( 'The images ZIP is larger than 200 MB once extracted.', 'techvenza-core' ) );
			}
			$data = $z->getFromIndex( $i );
			if ( false !== $data ) {
				file_put_contents( $to . sanitize_file_name( $name ), $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		$z->close();
		return true;
	}

	/**
	 * Delete a job and its files.
	 *
	 * @param string $id Job id.
	 */
	public static function delete( string $id ): void {
		$dir = self::dir( $id );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : wp_delete_file( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Remove jobs older than a week (daily cron).
	 */
	public static function purge(): void {
		foreach ( (array) glob( self::base() . '*/job.json' ) as $file ) {
			if ( filemtime( (string) $file ) < time() - WEEK_IN_SECONDS ) {
				self::delete( basename( dirname( (string) $file ) ) );
			}
		}
	}

	/**
	 * Validated rows of a job.
	 *
	 * @param string $id Job id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function rows( string $id ): array {
		$rows = json_decode( (string) file_get_contents( self::dir( $id ) . 'rows.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Results of a job.
	 *
	 * @param string $id Job id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function results( string $id ): array {
		$file = self::dir( $id ) . 'results.json';
		$res  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $res ) ? $res : array();
	}

	/**
	 * Process the next batch.
	 *
	 * @param string $id      Job id.
	 * @param int    $seconds Time budget.
	 * @return array<string,mixed>|\WP_Error Job.
	 */
	public static function run( string $id, int $seconds = 20 ) {
		$job = self::job( $id );
		if ( ! $job ) {
			return new \WP_Error( 'tv_job', __( 'Import not found.', 'techvenza-core' ), array( 'status' => 404 ) );
		}
		if ( 'done' === $job['status'] ) {
			return $job;
		}
		$lock = 'tv_import_lock_' . $id;
		if ( get_transient( $lock ) ) {
			return $job;
		}
		set_transient( $lock, 1, 60 );
		$rows          = self::rows( $id );
		$results       = self::results( $id );
		$start         = microtime( true );
		$job['status'] = 'running';
		$total         = count( $rows );
		while ( $job['cursor'] < $total && microtime( true ) - $start < $seconds ) {
			$row = $rows[ $job['cursor'] ];
			if ( $row['errors'] ) {
				$res = array(
					'result'  => 'failed',
					'post'    => 0,
					'message' => implode( ' ', (array) $row['errors'] ),
				);
			} else {
				try {
					$res = self::import_row( (array) $row['data'], (array) $job['opts'], self::dir( $id ) . 'images/', (int) $job['user'] );
				} catch ( \Throwable $t ) {
					$res = array(
						'result'  => 'failed',
						'post'    => 0,
						'message' => $t->getMessage(),
					);
				}
			}
			$res['line']  = $row['line'];
			$res['title'] = $row['data']['title'] ?? '';
			$results[]    = $res;
			++$job['counts'][ $res['result'] ];
			++$job['cursor'];
		}
		if ( $job['cursor'] >= $total ) {
			$job['status']   = 'done';
			$job['finished'] = current_time( 'mysql' );
			delete_transient( 'tv_llms_full' );
		}
		file_put_contents( self::dir( $id ) . 'results.json', (string) wp_json_encode( $results ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		self::save( $job );
		delete_transient( $lock );
		return $job;
	}

	/* ------------------------------------------------------------------ Rows */

	/**
	 * Import one validated row.
	 *
	 * @param array<string,mixed> $d       Row data.
	 * @param array<string,mixed> $opts    Options (duplicates, create_terms, inline_images).
	 * @param string              $images  Directory of ZIP images.
	 * @param int                 $user_id Importing user.
	 * @return array{result:string,post:int,message:string}
	 */
	public static function import_row( array $d, array $opts, string $images, int $user_id ): array {
		$existing = (int) ( $d['existing'] ?? 0 );
		$existing = $existing && get_post( $existing ) ? $existing : (int) ( get_page_by_path( (string) $d['slug'], OBJECT, 'post' )->ID ?? 0 );
		$mode     = (string) ( $opts['duplicates'] ?? 'skip' );
		if ( $existing && 'skip' === $mode ) {
			return array(
				'result'  => 'skipped',
				'post'    => $existing,
				'message' => __( 'An article with this slug already exists.', 'techvenza-core' ),
			);
		}
		$update = $existing && 'update' === $mode;

		// Body: Markdown/text → HTML → blocks, with optional inline image import.
		$html = (string) $d['content'];
		if ( 'markdown' === $d['format'] ) {
			$html = MarkdownToHtml::convert( $html );
		} elseif ( 'text' === $d['format'] ) {
			$html = wpautop( esc_html( $html ) );
		}
		$html  = wp_kses_post( $html );
		$notes = array();
		if ( ! empty( $opts['inline_images'] ) ) {
			$html = self::inline_images( $html, $images, $notes );
		}
		$content = BlockConverter::convert( $html );

		$cats = array();
		foreach ( (array) $d['categories'] as $path ) {
			$term = Csv::find_term( (array) $path );
			if ( ! $term && ! empty( $opts['create_terms'] ) ) {
				$parent = 0;
				foreach ( (array) $path as $name ) {
					$found = Csv::find_term( array_merge( $parent ? self::term_path( $parent ) : array(), array( $name ) ) );
					if ( $found ) {
						$parent = $found->term_id;
						continue;
					}
					$new = wp_insert_term( (string) $name, 'category', array( 'parent' => $parent ) );
					if ( is_wp_error( $new ) ) {
						break;
					}
					$parent = (int) $new['term_id'];
				}
				$term = $parent ? get_term( $parent, 'category' ) : null;
			}
			if ( $term instanceof \WP_Term ) {
				$cats[] = $term->term_id;
			}
		}

		$post = array(
			'post_type'    => 'post',
			'post_title'   => (string) $d['title'],
			'post_name'    => $update ? get_post_field( 'post_name', $existing ) : (string) $d['slug'],
			'post_content' => $content,
			'post_excerpt' => (string) $d['excerpt'],
			'post_status'  => (string) $d['status'],
			'post_author'  => (int) ( $d['author'] ? $d['author'] : $user_id ),
		);
		if ( $update ) {
			$post['ID'] = $existing;
		}
		if ( $d['date'] ) {
			$post['post_date']     = (string) $d['date'];
			$post['post_date_gmt'] = get_gmt_from_date( (string) $d['date'] );
			$post['edit_date']     = true;
		}
		if ( $cats ) {
			$post['post_category'] = $cats;
		}
		$id = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $id ) ) {
			return array(
				'result'  => 'failed',
				'post'    => 0,
				'message' => $id->get_error_message(),
			);
		}
		wp_set_post_tags( $id, (array) $d['tags'], false );
		if ( '' !== (string) $d['series'] ) {
			wp_set_object_terms( $id, (string) $d['series'], 'tv_series', false );
		}

		$meta = array(
			'tv_seo_title'       => $d['meta_title'],
			'tv_seo_description' => $d['meta_description'],
			'tv_focus_keyword'   => $d['focus_keyword'],
			'tv_summary'         => $d['summary'],
			'tv_faq'             => $d['faq'],
			'tv_sources'         => $d['sources'],
			'tv_key_points'      => $d['key_points'],
			'tv_canonical_url'   => $d['canonical'],
			'tv_noindex'         => $d['noindex'],
			'tv_is_featured'     => $d['featured'],
			'tv_is_editors_pick' => $d['editors_pick'],
			'tv_is_sponsored'    => '' !== (string) $d['sponsor'],
			'tv_sponsor_name'    => $d['sponsor'],
			'tv_imported'        => current_time( 'mysql' ),
		);
		if ( $d['type'] ) {
			$meta['tv_article_type'] = $d['type'];
		}
		foreach ( $meta as $k => $v ) {
			if ( ( '' === $v || array() === $v || false === $v ) && ! $update ) {
				continue;
			}
			update_post_meta( $id, $k, is_string( $v ) ? wp_slash( $v ) : $v );
		}

		if ( $d['image'] ) {
			$att = self::image( (string) $d['image'], $images, $id, (string) $d['image_alt'], (string) $d['image_caption'] );
			if ( is_wp_error( $att ) ) {
				/* translators: %s: error */
				$notes[] = sprintf( __( 'Featured image not imported: %s', 'techvenza-core' ), $att->get_error_message() );
			} else {
				set_post_thumbnail( $id, $att );
			}
		}

		return array(
			'result'  => $update ? 'updated' : 'created',
			'post'    => (int) $id,
			'message' => implode( ' ', $notes ),
		);
	}

	/**
	 * Category path names for a term.
	 *
	 * @param int $term_id Term.
	 * @return array<int,string>
	 */
	private static function term_path( int $term_id ): array {
		$names = array();
		foreach ( array_reverse( get_ancestors( $term_id, 'category' ) ) as $a ) {
			$names[] = (string) get_term_field( 'name', $a, 'category' );
		}
		$names[] = (string) get_term_field( 'name', $term_id, 'category' );
		return $names;
	}

	/**
	 * Import an image (URL or ZIP file), re-using earlier imports of the same source.
	 *
	 * @param string $src     URL or zip:filename.
	 * @param string $images  ZIP image directory.
	 * @param int    $post_id Parent post.
	 * @param string $alt     Alt text.
	 * @param string $caption Caption.
	 * @return int|\WP_Error Attachment id.
	 */
	public static function image( string $src, string $images, int $post_id, string $alt = '', string $caption = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$key   = str_starts_with( $src, 'zip:' ) ? $src . ':' . md5_file( $images . substr( $src, 4 ) ) : $src;
		$found = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'numberposts' => 1,
				'meta_key'    => 'tv_source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $found ) {
			$id = (int) $found[0];
		} else {
			if ( str_starts_with( $src, 'zip:' ) ) {
				$file = $images . basename( substr( $src, 4 ) );
				$tmp  = wp_tempnam( basename( $file ) );
				if ( ! is_file( $file ) || ! $tmp || ! copy( $file, $tmp ) ) {
					return new \WP_Error( 'tv_img', __( 'Image file missing from the ZIP.', 'techvenza-core' ) );
				}
				$name = basename( $file );
			} else {
				$tmp = download_url( $src, 30 );
				if ( is_wp_error( $tmp ) ) {
					return $tmp;
				}
				$path = (string) wp_parse_url( $src, PHP_URL_PATH );
				$name = sanitize_file_name( basename( $path ) );
				if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)$/i', $name ) ) {
					$type = wp_get_image_mime( $tmp );
					$ext  = array(
						'image/jpeg' => 'jpg',
						'image/png'  => 'png',
						'image/gif'  => 'gif',
						'image/webp' => 'webp',
						'image/avif' => 'avif',
					)[ (string) $type ] ?? '';
					if ( ! $ext ) {
						wp_delete_file( $tmp );
						return new \WP_Error( 'tv_img', __( 'The URL did not return a supported image.', 'techvenza-core' ) );
					}
					$name = ( $name ? $name : 'image' ) . '.' . $ext;
				}
			}
			$id = media_handle_sideload(
				array(
					'name'     => $name,
					'tmp_name' => $tmp,
				),
				$post_id,
				$alt ? $alt : null
			);
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				return $id;
			}
			update_post_meta( $id, 'tv_source', $key );
		}
		if ( $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
		if ( $caption ) {
			wp_update_post(
				array(
					'ID'           => $id,
					'post_excerpt' => $caption,
				)
			);
		}
		return (int) $id;
	}

	/**
	 * Import images referenced in the body and point the HTML at the Media Library copies.
	 *
	 * @param string            $html   HTML.
	 * @param string            $images ZIP dir.
	 * @param array<int,string> $notes  Notes (by reference).
	 */
	private static function inline_images( string $html, string $images, array &$notes ): string {
		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$p = new \WP_HTML_Tag_Processor( $html );
		while ( $p->next_tag( array( 'tag_name' => 'img' ) ) ) {
			$src = (string) $p->get_attribute( 'src' );
			$ref = preg_match( '#^https?://#i', $src ) ? $src : ( is_file( $images . basename( $src ) ) ? 'zip:' . basename( $src ) : '' );
			if ( '' === $ref || str_starts_with( $src, (string) wp_upload_dir( null, false )['baseurl'] ) ) {
				continue;
			}
			$att = self::image( $ref, $images, 0, (string) $p->get_attribute( 'alt' ) );
			if ( is_wp_error( $att ) ) {
				/* translators: 1: image, 2: error */
				$notes[] = sprintf( __( 'Inline image %1$s kept as an external link: %2$s', 'techvenza-core' ), basename( $src ), $att->get_error_message() );
				continue;
			}
			$p->set_attribute( 'src', (string) wp_get_attachment_image_url( $att, 'large' ) );
			$p->add_class( 'wp-image-' . $att );
		}
		return $p->get_updated_html();
	}

	/**
	 * Template CSV contents.
	 */
	public static function template(): string {
		$cols = Csv::columns();
		$out  = fopen( 'php://temp', 'r+' );
		if ( ! $out ) {
			return '';
		}
		fputcsv( $out, array_keys( $cols ), ',', '"', '' );
		fputcsv( $out, array_map( static fn( $c ) => $c[3], $cols ), ',', '"', '' );
		$second             = array_map( static fn() => '', $cols );
		$second['title']    = 'Zero Trust for Small Teams: A 90-Day Plan';
		$second['content']  = '<p>Zero trust is a strategy, not a product.</p><h2>Start with identity</h2><p>Enforce phishing-resistant MFA first.</p>';
		$second['status']   = 'draft';
		$second['category'] = 'Cybersecurity';
		$second['tags']     = 'Zero Trust|Identity';
		fputcsv( $out, array_values( $second ), ',', '"', '' );
		rewind( $out );
		$csv = (string) stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return "\xEF\xBB\xBF" . $csv;
	}
}
