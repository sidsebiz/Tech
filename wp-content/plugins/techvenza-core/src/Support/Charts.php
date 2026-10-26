<?php
/**
 * Dependency-free SVG charts (line, bar, horizontal bars, donut) matching the prototype.
 *
 * Every chart has a text alternative: an aria-label plus a visually hidden data table.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Support;

/**
 * Chart renderer.
 */
final class Charts {

	/**
	 * A "nice" axis maximum (four gridlines).
	 *
	 * @param float $v Highest value.
	 */
	public static function nice_max( float $v ): float {
		$raw = max( 1.0, $v * 1.1 / 4 );
		$p   = 10 ** floor( log10( $raw ) );
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			if ( $m * $p >= $raw ) {
				return $m * $p * 4;
			}
		}
		return $raw * 4;
	}

	/**
	 * Compact number.
	 *
	 * @param float $v Value.
	 */
	private static function num( float $v ): string {
		if ( $v >= 1000000 ) {
			return round( $v / 1000000, 1 ) . 'M';
		}
		if ( $v >= 10000 ) {
			return round( $v / 1000 ) . 'k';
		}
		if ( $v >= 1000 ) {
			return round( $v / 1000, 1 ) . 'k';
		}
		return (string) ( $v >= 10 ? round( $v ) : round( $v, 1 ) );
	}

	/**
	 * Hidden data table for screen readers.
	 *
	 * @param string                                                  $caption Caption.
	 * @param array<int,string>                                       $labels  Row labels.
	 * @param array<int,array{name:string,data:array<int,float|int>}> $series  Series.
	 */
	private static function table( string $caption, array $labels, array $series ): string {
		$head = '<th scope="col">' . esc_html__( 'Period', 'techvenza-core' ) . '</th>' . implode( '', array_map( static fn( $s ) => '<th scope="col">' . esc_html( $s['name'] ) . '</th>', $series ) );
		$rows = '';
		foreach ( $labels as $i => $l ) {
			$rows .= '<tr><th scope="row">' . esc_html( $l ) . '</th>' . implode( '', array_map( static fn( $s ) => '<td>' . esc_html( number_format_i18n( (float) ( $s['data'][ $i ] ?? 0 ) ) ) . '</td>', $series ) ) . '</tr>';
		}
		return '<table class="sr-only"><caption>' . esc_html( $caption ) . '</caption><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Line chart.
	 *
	 * @param array<int,array{name:string,color:string,data:array<int,float|int>}> $series Series.
	 * @param array<int,string>                                                    $labels X labels.
	 * @param string                                                               $label  Accessible label.
	 * @param int                                                                  $h      Height.
	 */
	public static function line( array $series, array $labels, string $label, int $h = 220 ): string {
		$w   = 640;
		$pl  = 40;
		$pr  = 12;
		$pt  = 12;
		$pb  = 28;
		$all = array_merge( ...array_map( static fn( $s ) => array_map( 'floatval', $s['data'] ), $series ) );
		$max = self::nice_max( $all ? max( $all ) : 1 );
		$n   = max( 2, count( $labels ) );
		$x   = static fn( int $i ): float => $pl + $i * ( $w - $pl - $pr ) / ( $n - 1 );
		$y   = static fn( float $v ): float => $h - $pb - $v / $max * ( $h - $pt - $pb );
		$g   = '';
		for ( $i = 0; $i <= 4; $i++ ) {
			$v  = $max * $i / 4;
			$yy = $y( $v );
			$g .= sprintf( '<line class="gridline" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/><text x="%d" y="%.1f" text-anchor="end">%s</text>', $pl, $w - $pr, $yy, $yy, $pl - 8, $yy + 4, esc_html( self::num( $v ) ) );
		}
		$step = (int) ceil( count( $labels ) / 8 );
		foreach ( $labels as $i => $l ) {
			if ( 0 === $i % max( 1, $step ) || count( $labels ) - 1 === $i ) {
				$g .= sprintf( '<text x="%.1f" y="%d" text-anchor="middle">%s</text>', $x( $i ), $h - 8, esc_html( $l ) );
			}
		}
		$paths = '';
		foreach ( $series as $si => $s ) {
			$pts = implode( ' ', array_map( static fn( $v, $i ) => sprintf( '%.1f,%.1f', $x( (int) $i ), $y( (float) $v ) ), $s['data'], array_keys( $s['data'] ) ) );
			if ( 0 === $si ) {
				$paths .= sprintf( '<polygon points="%d,%d %s %.1f,%d" fill="%s" opacity=".10"/>', $pl, $h - $pb, $pts, $x( count( $s['data'] ) - 1 ), $h - $pb, esc_attr( $s['color'] ) );
			}
			$paths .= sprintf( '<polyline points="%s" fill="none" stroke="%s" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>', $pts, esc_attr( $s['color'] ) );
		}
		$legend = count( $series ) > 1 ? '<div class="legend">' . implode( '', array_map( static fn( $s ) => sprintf( '<span><i style="background:%s"></i>%s</span>', esc_attr( $s['color'] ), esc_html( $s['name'] ) ), $series ) ) . '</div>' : '';
		return sprintf( '<figure style="margin:0"><svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="%s">%s%s</svg>%s%s</figure>', $w, $h, esc_attr( $label ), $g, $paths, $legend, self::table( $label, $labels, $series ) );
	}

	/**
	 * Vertical bar chart.
	 *
	 * @param array<int,float|int> $data   Values.
	 * @param array<int,string>    $labels Labels.
	 * @param string               $label  Accessible label.
	 * @param array<int,string>    $colors Colours (cycled).
	 */
	public static function bars( array $data, array $labels, string $label, array $colors = array( '#2563EB' ) ): string {
		$w   = 640;
		$h   = 220;
		$pl  = 40;
		$pr  = 12;
		$pt  = 12;
		$pb  = 28;
		$max = self::nice_max( $data ? (float) max( $data ) : 1 );
		$bw  = ( $w - $pl - $pr ) / max( 1, count( $data ) );
		$g   = '';
		for ( $i = 0; $i <= 4; $i++ ) {
			$v  = $max * $i / 4;
			$yy = $h - $pb - $v / $max * ( $h - $pt - $pb );
			$g .= sprintf( '<line class="gridline" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/><text x="%d" y="%.1f" text-anchor="end">%s</text>', $pl, $w - $pr, $yy, $yy, $pl - 8, $yy + 4, esc_html( self::num( $v ) ) );
		}
		foreach ( array_values( $data ) as $i => $v ) {
			$bh = $v / $max * ( $h - $pt - $pb );
			$g .= sprintf(
				'<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="4" fill="%s"><title>%s: %s</title></rect><text x="%.1f" y="%d" text-anchor="middle">%s</text>',
				$pl + $i * $bw + $bw * .18,
				$h - $pb - $bh,
				$bw * .64,
				$bh,
				esc_attr( $colors[ $i % count( $colors ) ] ),
				esc_html( $labels[ $i ] ?? '' ),
				esc_html( number_format_i18n( (float) $v ) ),
				$pl + $i * $bw + $bw / 2,
				$h - 8,
				esc_html( $labels[ $i ] ?? '' )
			);
		}
		return sprintf(
			'<figure style="margin:0"><svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="%s">%s</svg>%s</figure>',
			$w,
			$h,
			esc_attr( $label ),
			$g,
			self::table(
				$label,
				$labels,
				array(
					array(
						'name' => $label,
						'data' => array_values( $data ),
					),
				)
			)
		);
	}

	/**
	 * Horizontal bars.
	 *
	 * @param array<int,array{0:string,1:float|int,2?:string,3?:string}> $rows Label, value, colour, display.
	 */
	public static function hbars( array $rows ): string {
		if ( ! $rows ) {
			return '';
		}
		$max = max( 1, max( array_map( static fn( $r ) => (float) $r[1], $rows ) ) );
		$out = '';
		foreach ( $rows as $r ) {
			$out .= sprintf(
				'<div class="hbar-row"><span>%s</span><span class="track" aria-hidden="true"><span style="width:%.1f%%;--c:%s"></span></span><b>%s</b></div>',
				esc_html( $r[0] ),
				(float) $r[1] / $max * 100,
				esc_attr( $r[2] ?? '#2563EB' ),
				esc_html( $r[3] ?? number_format_i18n( (float) $r[1] ) )
			);
		}
		return '<div class="hbar">' . $out . '</div>';
	}

	/**
	 * Donut chart.
	 *
	 * @param array<int,array{0:string,1:float|int,2:string}> $rows  Label, value, colour.
	 * @param string                                          $label Accessible label.
	 */
	public static function donut( array $rows, string $label ): string {
		$total = array_sum( array_map( static fn( $r ) => (float) $r[1], $rows ) );
		if ( $total <= 0 ) {
			return '';
		}
		$r    = 60;
		$c    = 2 * M_PI * $r;
		$acc  = 0.0;
		$segs = '';
		$leg  = '';
		foreach ( $rows as $row ) {
			$len   = (float) $row[1] / $total * $c;
			$pct   = (int) round( (float) $row[1] / $total * 100 );
			$segs .= sprintf( '<circle r="%d" cx="80" cy="80" fill="none" stroke="%s" stroke-width="22" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 80 80)"><title>%s %d%%</title></circle>', $r, esc_attr( $row[2] ), $len, $c - $len, -$acc, esc_html( $row[0] ), $pct );
			$leg  .= sprintf( '<span><i style="background:%s"></i>%s — %d%%</span>', esc_attr( $row[2] ), esc_html( $row[0] ), $pct );
			$acc  += $len;
		}
		return sprintf( '<div class="row" style="gap:24px;align-items:center"><svg viewBox="0 0 160 160" width="150" height="150" role="img" aria-label="%s">%s</svg><div class="legend" style="flex-direction:column;gap:8px;margin:0">%s</div></div>', esc_attr( $label ), $segs, $leg );
	}
}
