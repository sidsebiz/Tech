<?php
/**
 * Editorial → Bulk Blog: upload a CSV (and optionally a ZIP of images), preview and validate
 * every row, then import in background-friendly batches with a live progress bar.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Import\Csv;
use Techvenza\Core\Import\Importer;
use Techvenza\Core\Rest\Routes;
use Techvenza\Core\Support\Render as R;

/**
 * Bulk Blog screen.
 */
final class BulkBlog {

	public const SLUG = 'techvenza-bulk';
	public const CAP  = 'tv_publish_posts';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_techvenza_bulk_upload', array( self::class, 'upload' ) );
		add_action( 'admin_post_techvenza_bulk_run', array( self::class, 'run_nojs' ) );
		add_action( 'admin_post_techvenza_bulk_template', array( self::class, 'template' ) );
		add_action( 'admin_post_techvenza_bulk_report', array( self::class, 'report' ) );
		add_action( 'admin_post_techvenza_bulk_delete', array( self::class, 'delete' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'techvenza_daily', array( Importer::class, 'purge' ) );
	}

	/**
	 * REST: run the next batch.
	 */
	public static function routes(): void {
		register_rest_route(
			Routes::NS,
			'/bulk/(?P<job>[a-z0-9]{12,40})/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_run' ),
				'permission_callback' => static fn(): bool => current_user_can( self::CAP ),
			)
		);
	}

	/**
	 * REST handler.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_run( \WP_REST_Request $request ) {
		$job = Importer::run( (string) $request['job'], 15 );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		return rest_ensure_response(
			array(
				'status'   => $job['status'],
				'total'    => (int) $job['total'],
				'done'     => (int) $job['cursor'],
				'counts'   => $job['counts'],
				'complete' => 'done' === $job['status'],
			)
		);
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$id   = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( $_GET['job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job  = $id ? Importer::job( $id ) : null;
		$head = Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Bulk Blog', 'techvenza-core' ), $job ? Admin::url( self::SLUG ) : '' ),
			),
			__( 'Bulk Blog', 'techvenza-core' ),
			esc_html__( 'Upload many articles at once from a CSV file — with featured images, authors, categories, tags, SEO fields and publication dates. Every row is checked before anything is created.', 'techvenza-core' ),
			sprintf( '<a class="button" href="%s">%s %s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=techvenza_bulk_template' ), 'techvenza_bulk_template' ) ), R::icon( 'download' ), esc_html__( 'Download CSV template', 'techvenza-core' ) )
		);
		if ( $job ) {
			return $head . ( 'validated' === $job['status'] ? self::preview( $job ) : self::progress( $job ) );
		}
		return $head . self::upload_form() . self::columns_help() . self::history();
	}

	/**
	 * Upload form.
	 */
	private static function upload_form(): string {
		$max = size_format( wp_max_upload_size() );
		return sprintf(
			'<section class="panel"><h2 class="panel-title">%1$s</h2><form method="post" action="%2$s" enctype="multipart/form-data">%3$s<input type="hidden" name="action" value="techvenza_bulk_upload">
			<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="tvBulkCsv">%4$s</label></th><td><input type="file" id="tvBulkCsv" name="csv" accept=".csv,text/csv" required><p class="description">%5$s</p></td></tr>
			<tr><th scope="row"><label for="tvBulkZip">%6$s</label></th><td><input type="file" id="tvBulkZip" name="images" accept=".zip,application/zip"><p class="description">%7$s</p></td></tr>
			<tr><th scope="row"><label for="tvBulkStatus">%8$s</label></th><td><select id="tvBulkStatus" name="status"><option value="draft">%9$s</option><option value="pending">%10$s</option><option value="publish">%11$s</option></select><p class="description">%12$s</p></td></tr>
			<tr><th scope="row"><label for="tvBulkDup">%13$s</label></th><td><select id="tvBulkDup" name="duplicates"><option value="skip">%14$s</option><option value="update">%15$s</option><option value="new">%16$s</option></select></td></tr>
			<tr><th scope="row">%17$s</th><td><fieldset><legend class="screen-reader-text">%17$s</legend><label><input type="checkbox" name="create_terms" value="1" checked> %18$s</label><br><label><input type="checkbox" name="inline_images" value="1" checked> %19$s</label></fieldset></td></tr>
			</table><p><button class="button button-primary" type="submit">%20$s</button></p></form></section>',
			esc_html__( '1. Upload', 'techvenza-core' ),
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'techvenza_bulk_upload', '_wpnonce', true, false ),
			esc_html__( 'CSV file', 'techvenza-core' ),
			/* translators: 1: max size, 2: max rows */
			esc_html( sprintf( __( 'UTF-8, comma, semicolon or tab separated, first row = column names. Up to %2$d articles and %1$s per file.', 'techvenza-core' ), $max, Csv::MAX_ROWS ) ),
			esc_html__( 'Images ZIP (optional)', 'techvenza-core' ),
			esc_html__( 'Put image files in a ZIP and write just the file name (e.g. cover-1.jpg) in the featured_image column or in <img src> tags.', 'techvenza-core' ),
			esc_html__( 'Default status', 'techvenza-core' ),
			esc_html__( 'Draft', 'techvenza-core' ),
			esc_html__( 'Pending review (review queue)', 'techvenza-core' ),
			esc_html__( 'Published (or scheduled when the date is in the future)', 'techvenza-core' ),
			esc_html__( 'Used for rows whose status column is empty.', 'techvenza-core' ),
			esc_html__( 'When the slug already exists', 'techvenza-core' ),
			esc_html__( 'Skip the row', 'techvenza-core' ),
			esc_html__( 'Update the existing article', 'techvenza-core' ),
			esc_html__( 'Create a new article with a numbered slug', 'techvenza-core' ),
			esc_html__( 'Options', 'techvenza-core' ),
			esc_html__( 'Create missing categories and series', 'techvenza-core' ),
			esc_html__( 'Copy images used inside the content into the Media Library', 'techvenza-core' ),
			esc_html__( 'Upload and check', 'techvenza-core' )
		);
	}

	/**
	 * Column reference.
	 */
	private static function columns_help(): string {
		$rows = '';
		foreach ( Csv::columns() as $key => [ $label, $req, $desc, $example ] ) {
			$rows .= sprintf(
				'<tr><td><code>%s</code>%s</td><td>%s</td><td>%s</td><td class="meta">%s</td></tr>',
				esc_html( $key ),
				$req ? ' <strong>*</strong>' : '',
				esc_html( $label ),
				esc_html( $desc ),
				esc_html( wp_html_excerpt( str_replace( "\n", ' ', $example ), 60, '…' ) )
			);
		}
		return sprintf(
			'<section class="panel"><h2 class="panel-title">%s</h2><p class="meta">%s</p><div class="table-wrap"><table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>%s</tbody></table></div></section>',
			esc_html__( 'Columns', 'techvenza-core' ),
			esc_html__( '* required. Column names are not case-sensitive and common alternatives (post_title, featured_image_url, seo_title…) are recognised. Unknown columns are ignored.', 'techvenza-core' ),
			esc_html__( 'Column', 'techvenza-core' ),
			esc_html__( 'Field', 'techvenza-core' ),
			esc_html__( 'What to put in it', 'techvenza-core' ),
			esc_html__( 'Example', 'techvenza-core' ),
			$rows
		);
	}

	/**
	 * Recent imports.
	 */
	private static function history(): string {
		$jobs = Importer::recent();
		if ( ! $jobs ) {
			return '';
		}
		$rows = '';
		foreach ( $jobs as $j ) {
			$c     = (array) $j['counts'];
			$rows .= sprintf(
				'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%d / %d</td><td class="meta">%s</td></tr>',
				esc_url( Admin::url( self::SLUG, array( 'job' => $j['id'] ) ) ),
				esc_html( (string) $j['name'] ),
				esc_html( (string) $j['created'] ),
				R::badge( 'done' === $j['status'] ? 'published' : ( 'validated' === $j['status'] ? 'draft' : 'in_review' ) ),
				(int) $j['cursor'],
				(int) $j['total'],
				/* translators: 1: created, 2: updated, 3: skipped, 4: failed */
				esc_html( sprintf( __( '%1$d created · %2$d updated · %3$d skipped · %4$d failed', 'techvenza-core' ), $c['created'] ?? 0, $c['updated'] ?? 0, $c['skipped'] ?? 0, $c['failed'] ?? 0 ) )
			);
		}
		return sprintf(
			'<section class="panel"><h2 class="panel-title">%s</h2><div class="table-wrap"><table class="widefat striped"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>%s</tbody></table></div><p class="meta">%s</p></section>',
			esc_html__( 'Recent imports', 'techvenza-core' ),
			esc_html__( 'File', 'techvenza-core' ),
			esc_html__( 'Uploaded', 'techvenza-core' ),
			esc_html__( 'Status', 'techvenza-core' ),
			esc_html__( 'Rows', 'techvenza-core' ),
			esc_html__( 'Result', 'techvenza-core' ),
			$rows,
			esc_html__( 'Import files are deleted automatically after 7 days. Imported articles stay.', 'techvenza-core' )
		);
	}

	/**
	 * Preview of a validated job.
	 *
	 * @param array<string,mixed> $job Job.
	 */
	private static function preview( array $job ): string {
		$rows  = Importer::rows( (string) $job['id'] );
		$body  = '';
		$warns = 0;
		foreach ( array_slice( $rows, 0, 300 ) as $r ) {
			$d      = $r['data'];
			$issues = '';
			foreach ( (array) $r['errors'] as $e ) {
				$issues .= '<li style="color:#b91c1c">✕ ' . esc_html( (string) $e ) . '</li>';
			}
			foreach ( (array) $r['warnings'] as $w ) {
				$issues .= '<li style="color:#b45309">! ' . esc_html( (string) $w ) . '</li>';
				++$warns;
			}
			$author = $d['author'] ? get_the_author_meta( 'display_name', (int) $d['author'] ) : wp_get_current_user()->display_name;
			$body  .= sprintf(
				'<tr><td>%d</td><td><strong>%s</strong><div class="meta">/%s/ · %s</div></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><ul style="margin:0">%s</ul></td></tr>',
				(int) $r['line'],
				esc_html( (string) $d['title'] ),
				esc_html( (string) $d['slug'] ),
				esc_html( strtoupper( (string) $d['format'] ) ),
				R::badge( 'future' === $d['status'] ? 'scheduled' : ( 'pending' === $d['status'] ? 'submitted' : (string) $d['status'] ) ) . ( $d['date'] ? '<div class="meta">' . esc_html( substr( (string) $d['date'], 0, 16 ) ) . '</div>' : '' ),
				esc_html( (string) $author ),
				esc_html( implode( ', ', array_map( static fn( $p ) => implode( ' › ', (array) $p ), (array) $d['categories'] ) ) ),
				$d['image'] ? '✓' : '—',
				esc_html( trim( ( $d['meta_title'] ? 'Title ' : '' ) . ( $d['meta_description'] ? 'Desc ' : '' ) . ( $d['summary'] ? 'Summary ' : '' ) . ( $d['faq'] ? count( (array) $d['faq'] ) . ' FAQ' : '' ) ) ),
				$issues ? $issues : '<li style="color:#047857">✓ ' . esc_html__( 'Ready', 'techvenza-core' ) . '</li>'
			);
		}
		$invalid = (int) $job['total'] - (int) $job['valid'];
		$summary = sprintf(
			/* translators: 1: total, 2: valid, 3: invalid, 4: warnings */
			__( '%1$d rows: %2$d ready, %3$d with errors (they will be skipped), %4$d warnings.', 'techvenza-core' ),
			(int) $job['total'],
			(int) $job['valid'],
			$invalid,
			$warns
		);
		$unknown = $job['unknown'] ? '<p class="meta">' . esc_html( sprintf( /* translators: %s: columns */ __( 'Ignored columns: %s', 'techvenza-core' ), implode( ', ', (array) $job['unknown'] ) ) ) . '</p>' : '';
		$action  = (int) $job['valid'] ? sprintf(
			'<form method="post" action="%1$s" data-bulk-form="%2$s" style="display:inline">%3$s<input type="hidden" name="action" value="techvenza_bulk_run"><input type="hidden" name="job" value="%2$s"><button class="button button-primary" type="submit">%4$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( (string) $job['id'] ),
			wp_nonce_field( 'techvenza_bulk_run', '_wpnonce', true, false ),
			/* translators: %d: rows */
			esc_html( sprintf( _n( 'Import %d article', 'Import %d articles', (int) $job['valid'], 'techvenza-core' ), (int) $job['valid'] ) )
		) : '';
		return sprintf(
			'<section class="panel"><h2 class="panel-title">%1$s</h2><p>%2$s</p>%3$s<div class="row" style="gap:8px">%4$s%5$s</div><div data-bulk-progress hidden style="margin-top:16px"><div style="height:10px;border-radius:5px;background:#e5e7eb;overflow:hidden"><div data-bulk-bar style="height:100%%;width:0;background:#2563eb;transition:width .3s"></div></div><p class="meta" data-bulk-status role="status" aria-live="polite"></p></div></section>
			<div class="table-wrap"><table class="widefat striped"><thead><tr><th scope="col">%6$s</th><th scope="col">%7$s</th><th scope="col">%8$s</th><th scope="col">%9$s</th><th scope="col">%10$s</th><th scope="col">%11$s</th><th scope="col">%12$s</th><th scope="col">%13$s</th></tr></thead><tbody>%14$s</tbody></table></div>',
			esc_html__( '2. Check and import', 'techvenza-core' ),
			esc_html( $summary ),
			$unknown,
			$action,
			self::delete_button( (string) $job['id'], __( 'Cancel', 'techvenza-core' ) ),
			esc_html__( 'Line', 'techvenza-core' ),
			esc_html__( 'Article', 'techvenza-core' ),
			esc_html__( 'Status', 'techvenza-core' ),
			esc_html__( 'Author', 'techvenza-core' ),
			esc_html__( 'Category', 'techvenza-core' ),
			esc_html__( 'Image', 'techvenza-core' ),
			esc_html__( 'SEO / AEO', 'techvenza-core' ),
			esc_html__( 'Checks', 'techvenza-core' ),
			$body
		);
	}

	/**
	 * Progress / results of a running or finished job.
	 *
	 * @param array<string,mixed> $job Job.
	 */
	private static function progress( array $job ): string {
		$c       = (array) $job['counts'];
		$pct     = $job['total'] ? (int) round( $job['cursor'] / $job['total'] * 100 ) : 0;
		$done    = 'done' === $job['status'];
		$results = '';
		foreach ( Importer::results( (string) $job['id'] ) as $r ) {
			$post     = (int) $r['post'];
			$links    = $post ? sprintf( '<a href="%s">%s</a> · <a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post ) ), esc_html__( 'Edit', 'techvenza-core' ), esc_url( (string) get_permalink( $post ) ), esc_html__( 'View', 'techvenza-core' ) ) : '';
			$results .= sprintf(
				'<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td class="meta">%s</td></tr>',
				(int) $r['line'],
				esc_html( (string) $r['title'] ),
				R::badge(
					array(
						'created' => 'published',
						'updated' => 'approved',
						'skipped' => 'draft',
						'failed'  => 'rejected',
					)[ $r['result'] ] ?? 'draft'
				) . ' <span class="meta">' . esc_html( (string) $r['result'] ) . '</span>',
				$links,
				esc_html( (string) $r['message'] )
			);
		}
		$continue = $done ? '' : sprintf(
			'<form method="post" action="%1$s" data-bulk-form="%2$s" data-bulk-autostart style="display:inline">%3$s<input type="hidden" name="action" value="techvenza_bulk_run"><input type="hidden" name="job" value="%2$s"><button class="button button-primary" type="submit">%4$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( (string) $job['id'] ),
			wp_nonce_field( 'techvenza_bulk_run', '_wpnonce', true, false ),
			esc_html__( 'Continue import', 'techvenza-core' )
		);
		return sprintf(
			'<section class="panel"><h2 class="panel-title">%1$s</h2><div data-bulk-progress style="margin-bottom:12px"><div style="height:10px;border-radius:5px;background:#e5e7eb;overflow:hidden"><div data-bulk-bar style="height:100%%;width:%2$d%%;background:%3$s"></div></div><p class="meta" data-bulk-status role="status" aria-live="polite">%4$s</p></div><div class="row" style="gap:8px">%5$s<a class="button" href="%6$s">%7$s</a><a class="button" href="%8$s">%9$s</a>%10$s</div></section>
			<div class="table-wrap"><table class="widefat striped"><thead><tr><th scope="col">%11$s</th><th scope="col">%12$s</th><th scope="col">%13$s</th><th scope="col">%14$s</th><th scope="col">%15$s</th></tr></thead><tbody>%16$s</tbody></table></div>',
			$done ? esc_html__( 'Import finished', 'techvenza-core' ) : esc_html__( 'Importing…', 'techvenza-core' ),
			$pct,
			$done ? '#047857' : '#2563eb',
			/* translators: 1: done, 2: total, 3: created, 4: updated, 5: skipped, 6: failed */
			esc_html( sprintf( __( '%1$d of %2$d rows — %3$d created, %4$d updated, %5$d skipped, %6$d failed.', 'techvenza-core' ), (int) $job['cursor'], (int) $job['total'], $c['created'] ?? 0, $c['updated'] ?? 0, $c['skipped'] ?? 0, $c['failed'] ?? 0 ) ),
			$continue,
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=techvenza_bulk_report&job=' . $job['id'] ), 'techvenza_bulk_report' ) ),
			esc_html__( 'Download report (CSV)', 'techvenza-core' ),
			esc_url( admin_url( 'edit.php?orderby=date&order=desc' ) ),
			esc_html__( 'Open posts list', 'techvenza-core' ),
			$done ? self::delete_button( (string) $job['id'], __( 'Delete import files', 'techvenza-core' ) ) : '',
			esc_html__( 'Line', 'techvenza-core' ),
			esc_html__( 'Article', 'techvenza-core' ),
			esc_html__( 'Result', 'techvenza-core' ),
			esc_html__( 'Links', 'techvenza-core' ),
			esc_html__( 'Notes', 'techvenza-core' ),
			$results ? $results : '<tr><td colspan="5" class="meta">' . esc_html__( 'Results appear here as rows are imported.', 'techvenza-core' ) . '</td></tr>'
		);
	}

	/**
	 * Delete-job button.
	 *
	 * @param string $id    Job.
	 * @param string $label Label.
	 */
	private static function delete_button( string $id, string $label ): string {
		return sprintf(
			'<form method="post" action="%1$s" style="display:inline">%2$s<input type="hidden" name="action" value="techvenza_bulk_delete"><input type="hidden" name="job" value="%3$s"><button class="button" type="submit">%4$s</button></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'techvenza_bulk_delete', '_wpnonce', true, false ),
			esc_attr( $id ),
			esc_html( $label )
		);
	}

	/* ------------------------------------------------------------------ Handlers */

	/**
	 * Permission check (each handler verifies its own nonce first).
	 */
	private static function guard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to import articles.', 'techvenza-core' ), 403 );
		}
	}

	/**
	 * Upload (admin-post).
	 */
	public static function upload(): void {
		check_admin_referer( 'techvenza_bulk_upload' );
		self::guard();
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File arrays are validated below.
		$csv = isset( $_FILES['csv'] ) && is_array( $_FILES['csv'] ) ? $_FILES['csv'] : array();
		$zip = isset( $_FILES['images'] ) && is_array( $_FILES['images'] ) ? $_FILES['images'] : array();
		// phpcs:enable
		if ( empty( $csv['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $csv['error'] ?? 1 ) || ! is_uploaded_file( (string) $csv['tmp_name'] ) ) {
			Admin::back( 'error', __( 'Choose a CSV file to upload.', 'techvenza-core' ) );
		}
		if ( ! preg_match( '/\.(csv|txt)$/i', (string) ( $csv['name'] ?? '' ) ) ) {
			Admin::back( 'error', __( 'The file must be a .csv file.', 'techvenza-core' ) );
		}
		$zip_path = '';
		if ( ! empty( $zip['tmp_name'] ) && UPLOAD_ERR_OK === (int) ( $zip['error'] ?? 1 ) && is_uploaded_file( (string) $zip['tmp_name'] ) ) {
			if ( ! preg_match( '/\.zip$/i', (string) ( $zip['name'] ?? '' ) ) ) {
				Admin::back( 'error', __( 'Images must be uploaded as a .zip file.', 'techvenza-core' ) );
			}
			$zip_path = (string) $zip['tmp_name'];
		}
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
		$dup    = isset( $_POST['duplicates'] ) ? sanitize_key( wp_unslash( $_POST['duplicates'] ) ) : 'skip';
		$job    = Importer::create(
			(string) $csv['tmp_name'],
			$zip_path,
			(string) ( $csv['name'] ?? 'import.csv' ),
			array(
				'status'        => in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ? $status : 'draft',
				'duplicates'    => in_array( $dup, array( 'skip', 'update', 'new' ), true ) ? $dup : 'skip',
				'create_terms'  => ! empty( $_POST['create_terms'] ),
				'inline_images' => ! empty( $_POST['inline_images'] ),
			)
		);
		if ( is_wp_error( $job ) ) {
			Admin::back( 'error', $job->get_error_message() );
		}
		wp_safe_redirect( Admin::url( self::SLUG, array( 'job' => $job['id'] ) ) );
		exit;
	}

	/**
	 * Run batches without JavaScript (admin-post): ~25 s, then back to the job page.
	 */
	public static function run_nojs(): void {
		check_admin_referer( 'techvenza_bulk_run' );
		self::guard();
		$id = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 60 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}
		Importer::run( $id, 25 );
		wp_safe_redirect( Admin::url( self::SLUG, array( 'job' => $id ) ) );
		exit;
	}

	/**
	 * Template download.
	 */
	public static function template(): void {
		check_admin_referer( 'techvenza_bulk_template' );
		self::guard();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="techvenza-bulk-blog-template.csv"' );
		echo Importer::template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		exit;
	}

	/**
	 * Results report download.
	 */
	public static function report(): void {
		check_admin_referer( 'techvenza_bulk_report' );
		self::guard();
		$id = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( $_GET['job'] ) ) : '';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="techvenza-import-report-' . $id . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			exit;
		}
		fputcsv( $out, array( 'line', 'title', 'result', 'post_id', 'url', 'edit_url', 'notes' ), ',', '"', '' );
		foreach ( Importer::results( $id ) as $r ) {
			$post = (int) $r['post'];
			fputcsv( $out, array( $r['line'], Analytics::cell( (string) $r['title'] ), $r['result'], $post, $post ? get_permalink( $post ) : '', $post ? admin_url( 'post.php?action=edit&post=' . $post ) : '', Analytics::cell( (string) $r['message'] ) ), ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Delete a job's files.
	 */
	public static function delete(): void {
		check_admin_referer( 'techvenza_bulk_delete' );
		self::guard();
		$id = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		Importer::delete( $id );
		Admin::back( 'done', __( 'Import files deleted.', 'techvenza-core' ), Admin::url( self::SLUG ) );
	}
}
