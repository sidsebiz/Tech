<?php
/**
 * Analytics: daily views (first-party, privacy-friendly counter), top articles with period-over-period
 * change, categories and authors, plus a CSV export.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Admin;

use Techvenza\Core\Content\CategoryMeta;
use Techvenza\Core\Content\Query;
use Techvenza\Core\Database\Schema;
use Techvenza\Core\Support\Charts;
use Techvenza\Core\Support\Render as R;

/**
 * Analytics screen.
 */
final class Analytics {

	/**
	 * Allowed windows.
	 *
	 * @return array<int,string>
	 */
	private static function periods(): array {
		return array(
			7  => __( '7 days', 'techvenza-core' ),
			30 => __( '30 days', 'techvenza-core' ),
			90 => __( '90 days', 'techvenza-core' ),
		);
	}

	/**
	 * Selected window.
	 */
	private static function days(): int {
		$d = isset( $_REQUEST['days'] ) ? absint( $_REQUEST['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( self::periods()[ $d ] ) ? $d : 30;
	}

	/**
	 * Daily site-wide views for the window and the one before it.
	 *
	 * @param int $days Window.
	 * @return array{0:array<string,int>,1:array<string,int>} [current by date, previous by offset date]
	 */
	public static function daily( int $days ): array {
		global $wpdb;
		$table = Schema::table( 'post_views' );
		$today = current_time( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', (int) strtotime( $today . ' -' . ( $days - 1 ) . ' days' ) );
		$prev  = gmdate( 'Y-m-d', (int) strtotime( $start . ' -' . $days . ' days' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT view_date AS d, SUM(views) AS v FROM {$table} WHERE view_date >= %s GROUP BY view_date", $prev ) );
		$by   = array();
		foreach ( (array) $rows as $r ) {
			$by[ (string) $r->d ] = (int) $r->v;
		}
		$cur = array();
		$old = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$d         = gmdate( 'Y-m-d', (int) strtotime( $start . ' +' . $i . ' days' ) );
			$p         = gmdate( 'Y-m-d', (int) strtotime( $prev . ' +' . $i . ' days' ) );
			$cur[ $d ] = $by[ $d ] ?? 0;
			$old[ $p ] = $by[ $p ] ?? 0;
		}
		return array( $cur, $old );
	}

	/**
	 * Per-post rows for the window.
	 *
	 * @param int $days Window.
	 * @return array<int,array{id:int,title:string,author:string,author_id:int,cat:array<string,mixed>|null,cur:int,prev:int}>
	 */
	public static function articles( int $days ): array {
		$ids  = Query::ids( array( 'posts_per_page' => 500 ) );
		$rows = array();
		foreach ( Query::period_views( $ids, $days ) as $id => [ $cur, $prev ] ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$rows[] = array(
				'id'        => $id,
				'title'     => get_the_title( $post ),
				'author'    => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
				'author_id' => (int) $post->post_author,
				'cat'       => CategoryMeta::for_post( $id ),
				'cur'       => $cur,
				'prev'      => $prev,
			);
		}
		usort( $rows, static fn( $a, $b ) => $b['cur'] <=> $a['cur'] );
		return $rows;
	}

	/**
	 * Percentage change markup.
	 *
	 * @param int $cur  Current.
	 * @param int $prev Previous.
	 */
	private static function delta( int $cur, int $prev ): string {
		if ( ! $prev ) {
			return $cur ? '<span class="delta up">' . esc_html__( 'new', 'techvenza-core' ) . '</span>' : '<span class="muted">—</span>';
		}
		$pct = (int) round( ( $cur - $prev ) / $prev * 100 );
		return sprintf( '<span class="delta %s">%s%d%%</span>', $pct >= 0 ? 'up' : 'down', $pct >= 0 ? '+' : '−', abs( $pct ) );
	}

	/**
	 * Render.
	 */
	public static function render(): string {
		$days          = self::days();
		[ $cur, $old ] = self::daily( $days );
		$total         = array_sum( $cur );
		$total_prev    = array_sum( $old );
		$articles      = self::articles( $days );
		$since         = (string) array_key_first( $cur );

		$published = count(
			Query::ids(
				array(
					'posts_per_page' => 500,
					'date_query'     => array(
						array(
							'after'     => $since . ' 00:00:00',
							'inclusive' => true,
						),
					),
				)
			)
		);
		$readers   = count(
			get_users(
				array(
					'fields'     => 'ID',
					'number'     => 5000,
					'date_query' => array(
						array(
							'after'     => $since . ' 00:00:00',
							'inclusive' => true,
						),
					),
				)
			)
		);
		$read      = array_filter( $articles, static fn( $a ) => $a['cur'] > 0 );
		$avg       = $read ? (int) round( $total / count( $read ) ) : 0;

		// Categories & authors.
		$cats    = array();
		$authors = array();
		foreach ( $articles as $a ) {
			if ( $a['cat'] ) {
				$k              = (string) $a['cat']['slug'];
				$cats[ $k ]   ??= array( (string) $a['cat']['short'], 0, (string) $a['cat']['color'] );
				$cats[ $k ][1] += $a['cur'];
			}
			$authors[ $a['author_id'] ] ??= array(
				'name'  => $a['author'],
				'posts' => 0,
				'cur'   => 0,
				'prev'  => 0,
			);
			++$authors[ $a['author_id'] ]['posts'];
			$authors[ $a['author_id'] ]['cur']  += $a['cur'];
			$authors[ $a['author_id'] ]['prev'] += $a['prev'];
		}
		$cats = array_values( array_filter( $cats, static fn( $c ) => $c[1] > 0 ) );
		usort( $cats, static fn( $a, $b ) => $b[1] <=> $a[1] );
		uasort( $authors, static fn( $a, $b ) => $b['cur'] <=> $a['cur'] );

		$labels = array_map( static fn( $d ) => wp_date( 'M j', (int) strtotime( $d ) ), array_keys( $cur ) );
		if ( $days > 14 ) {
			$step   = $days > 30 ? 14 : 5;
			$labels = array_map( static fn( $l, $i ) => 0 === $i % $step ? $l : '', $labels, array_keys( $labels ) );
		}
		$chart = Charts::line(
			array(
				array(
					'name'  => __( 'This period', 'techvenza-core' ),
					'color' => '#2563EB',
					'data'  => array_values( $cur ),
				),
				array(
					'name'  => __( 'Previous period', 'techvenza-core' ),
					'color' => '#94A3B8',
					'data'  => array_values( $old ),
				),
			),
			$labels,
			/* translators: %d: days */
			sprintf( __( 'Daily article views, last %d days', 'techvenza-core' ), $days )
		);

		$top = '';
		foreach ( array_slice( $articles, 0, 15 ) as $i => $a ) {
			$top .= sprintf(
				'<tr><td>%d</td><td><a href="%s">%s</a><div class="meta">%s</div></td><td>%s</td><td class="num">%s</td><td class="num">%s</td></tr>',
				$i + 1,
				esc_url( (string) get_permalink( $a['id'] ) ),
				esc_html( $a['title'] ),
				esc_html( $a['author'] ),
				$a['cat'] ? R::chip( $a['cat'] ) : '',
				esc_html( number_format_i18n( $a['cur'] ) ),
				self::delta( $a['cur'], $a['prev'] )
			);
		}
		$auth = '';
		foreach ( array_slice( $authors, 0, 10, true ) as $id => $a ) {
			$auth .= sprintf(
				'<tr><td><a href="%s">%s</a></td><td class="num">%d</td><td class="num">%s</td><td class="num">%s</td></tr>',
				esc_url( get_author_posts_url( (int) $id ) ),
				esc_html( $a['name'] ),
				$a['posts'],
				esc_html( number_format_i18n( $a['cur'] ) ),
				self::delta( $a['cur'], $a['prev'] )
			);
		}

		$tabs = '';
		foreach ( self::periods() as $d => $label ) {
			$tabs .= sprintf( '<a class="button%s" href="%s"%s>%s</a>', $d === $days ? ' button-primary' : '', esc_url( Admin::url( 'techvenza-analytics', array( 'days' => $d ) ) ), $d === $days ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		$export = sprintf(
			'<a class="button" href="%s">%s %s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=techvenza_analytics_csv&days=' . $days ), 'techvenza_analytics_csv' ) ),
			R::icon( 'download' ),
			esc_html__( 'Export CSV', 'techvenza-core' )
		);
		$stat   = static fn( string $label, string $value, string $color, string $note ): string => sprintf( '<div class="stat" style="--c:%s"><span class="k"><i></i>%s</span><span class="v">%s</span><span class="d">%s</span></div>', esc_attr( $color ), esc_html( $label ), esc_html( $value ), $note );
		$th     = static fn( string ...$cols ): string => '<thead><tr>' . implode( '', array_map( static fn( $c ) => '<th scope="col">' . esc_html( $c ) . '</th>', $cols ) ) . '</tr></thead>';

		return Admin::head(
			array(
				array( __( 'Editorial', 'techvenza-core' ), Admin::url( Admin::SLUG ) ),
				array( __( 'Analytics', 'techvenza-core' ), '' ),
			),
			__( 'Analytics', 'techvenza-core' ),
			esc_html__( 'First-party view counts (no cookies, bots excluded). Figures compare with the previous period of the same length.', 'techvenza-core' ),
			'<div class="row" style="gap:4px" role="group" aria-label="' . esc_attr__( 'Period', 'techvenza-core' ) . '">' . $tabs . '</div>' . $export
		)
		. '<div class="stats">'
		. $stat( __( 'Article views', 'techvenza-core' ), number_format_i18n( $total ), 'var(--blue)', self::delta( $total, $total_prev ) . ' ' . esc_html__( 'vs previous', 'techvenza-core' ) )
		. $stat( __( 'Articles read', 'techvenza-core' ), number_format_i18n( count( $read ) ), 'var(--violet)', esc_html__( 'With at least one view', 'techvenza-core' ) )
		. $stat( __( 'Avg. views / article', 'techvenza-core' ), number_format_i18n( $avg ), 'var(--emerald)', esc_html__( 'Across articles read', 'techvenza-core' ) )
		. $stat( __( 'Published', 'techvenza-core' ), number_format_i18n( $published ), 'var(--amber)', esc_html__( 'New articles this period', 'techvenza-core' ) )
		. $stat( __( 'New accounts', 'techvenza-core' ), number_format_i18n( $readers ), '#94A3B8', esc_html__( 'Registered this period', 'techvenza-core' ) )
		. '</div>'
		. sprintf( '<section class="panel"><h2 class="panel-title">%s</h2>%s</section>', esc_html__( 'Views over time', 'techvenza-core' ), $chart )
		. sprintf(
			'<div class="dash-grid"><section class="panel"><h2 class="panel-title">%1$s</h2>%2$s</section><section class="panel"><h2 class="panel-title">%3$s</h2>%4$s</section></div>',
			esc_html__( 'Top articles', 'techvenza-core' ),
			$top ? '<div class="table-wrap"><table class="widefat striped">' . $th( '#', __( 'Article', 'techvenza-core' ), __( 'Category', 'techvenza-core' ), __( 'Views', 'techvenza-core' ), __( 'Change', 'techvenza-core' ) ) . '<tbody>' . $top . '</tbody></table></div>' : R::empty_state( 'chart', __( 'No views recorded yet', 'techvenza-core' ), __( 'Views are counted when readers open an article.', 'techvenza-core' ) ),
			esc_html__( 'Categories by views', 'techvenza-core' ),
			$cats ? Charts::hbars( array_slice( $cats, 0, 8 ) ) : '<p class="muted">' . esc_html__( 'No data for this period.', 'techvenza-core' ) . '</p>'
		)
		. sprintf(
			'<section class="panel"><h2 class="panel-title">%s</h2>%s</section>',
			esc_html__( 'Authors', 'techvenza-core' ),
			$auth ? '<div class="table-wrap"><table class="widefat striped">' . $th( __( 'Author', 'techvenza-core' ), __( 'Articles', 'techvenza-core' ), __( 'Views', 'techvenza-core' ), __( 'Change', 'techvenza-core' ) ) . '<tbody>' . $auth . '</tbody></table></div>' : '<p class="muted">' . esc_html__( 'No data for this period.', 'techvenza-core' ) . '</p>'
		);
	}

	/**
	 * CSV export (admin-post).
	 */
	public static function csv(): void {
		check_admin_referer( 'techvenza_analytics_csv' );
		if ( ! current_user_can( 'tv_view_analytics' ) ) {
			wp_die( esc_html__( 'You do not have permission to export analytics.', 'techvenza-core' ), 403 );
		}
		$days = self::days();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="techvenza-analytics-' . $days . 'd-' . current_time( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			exit;
		}
		fputcsv( $out, array( 'post_id', 'title', 'author', 'category', 'url', 'views', 'previous_period_views' ) );
		foreach ( self::articles( $days ) as $a ) {
			fputcsv( $out, array( $a['id'], self::cell( $a['title'] ), self::cell( $a['author'] ), self::cell( (string) ( $a['cat']['name'] ?? '' ) ), get_permalink( $a['id'] ), $a['cur'], $a['prev'] ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralise spreadsheet formula injection.
	 *
	 * @param string $v Value.
	 */
	public static function cell( string $v ): string {
		$v = html_entity_decode( $v, ENT_QUOTES, 'UTF-8' );
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	}
}
