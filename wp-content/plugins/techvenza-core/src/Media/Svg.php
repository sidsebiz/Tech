<?php
/**
 * Allow-list SVG sanitizer: keeps drawing elements and presentation attributes, removes scripts,
 * event handlers, external references, foreign content, entities and processing instructions.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Media;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument property names.

/**
 * SVG sanitizer.
 */
final class Svg {

	private const ELEMENTS = array( 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'defs', 'lineargradient', 'radialgradient', 'stop', 'title', 'desc', 'text', 'tspan', 'clippath', 'mask', 'use', 'symbol', 'pattern', 'image', 'filter', 'fegaussianblur', 'feoffset', 'feblend', 'fecolormatrix', 'feflood', 'fecomposite', 'femerge', 'femergenode', 'fedropshadow', 'marker', 'style' );

	private const ATTRS = array( 'id', 'class', 'style', 'xmlns', 'xmlns:xlink', 'version', 'viewbox', 'width', 'height', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit', 'opacity', 'transform', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'spreadmethod', 'clip-path', 'mask', 'href', 'xlink:href', 'preserveaspectratio', 'font-family', 'font-size', 'font-weight', 'text-anchor', 'dominant-baseline', 'letter-spacing', 'dx', 'dy', 'role', 'aria-label', 'aria-hidden', 'focusable', 'patternunits', 'patterncontentunits', 'patterntransform', 'maskunits', 'maskcontentunits', 'clippathunits', 'filter', 'stddeviation', 'in', 'in2', 'result', 'mode', 'values', 'type', 'flood-color', 'flood-opacity', 'operator', 'k1', 'k2', 'k3', 'k4', 'markerwidth', 'markerheight', 'refx', 'refy', 'orient', 'marker-start', 'marker-mid', 'marker-end', 'vector-effect', 'shape-rendering', 'color', 'display', 'visibility', 'filterunits' );

	/**
	 * Sanitize markup ('' when it is not a usable SVG).
	 *
	 * @param string $svg Raw SVG.
	 */
	public static function sanitize( string $svg ): string {
		if ( '' === trim( $svg ) || preg_match( '/<!(DOCTYPE|ENTITY)/i', $svg ) ) {
			return '';
		}
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$ok       = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOBLANKS );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $ok || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return '';
		}
		self::clean( $dom->documentElement );
		$out = $dom->saveXML( $dom->documentElement );
		return is_string( $out ) ? $out . "\n" : '';
	}

	/**
	 * Recursively clean a node.
	 *
	 * @param \DOMElement $el Element.
	 */
	private static function clean( \DOMElement $el ): void {
		for ( $i = $el->childNodes->length - 1; $i >= 0; $i-- ) {
			$child = $el->childNodes->item( $i );
			if ( $child instanceof \DOMElement ) {
				if ( ! in_array( strtolower( $child->localName ), self::ELEMENTS, true ) ) {
					$el->removeChild( $child );
					continue;
				}
				self::clean( $child );
			} elseif ( $child instanceof \DOMProcessingInstruction || $child instanceof \DOMComment ) {
				$el->removeChild( $child );
			}
		}
		for ( $i = $el->attributes->length - 1; $i >= 0; $i-- ) {
			$attr = $el->attributes->item( $i );
			if ( ! $attr instanceof \DOMAttr ) {
				continue;
			}
			$name  = strtolower( $attr->nodeName );
			$value = (string) $attr->nodeValue;
			$bad   = ! in_array( $name, self::ATTRS, true ) || str_starts_with( $name, 'on' );
			if ( ! $bad && in_array( $name, array( 'href', 'xlink:href' ), true ) ) {
				// Internal references, or embedded raster images only.
				$bad = ! ( str_starts_with( $value, '#' ) || preg_match( '#^data:image/(png|jpe?g|gif|webp);base64,#i', $value ) );
			}
			if ( ! $bad && preg_match( '/(javascript:|expression\s*\(|url\s*\(\s*["\']?\s*(?!#)|@import|behavior\s*:)/i', $value ) ) {
				$bad = true;
			}
			if ( $bad ) {
				$el->removeAttributeNode( $attr );
			}
		}
		if ( 'style' === strtolower( $el->localName ) && preg_match( '/(@import|javascript:|expression\s*\(|url\s*\(\s*["\']?\s*(?!#))/i', (string) $el->textContent ) ) {
			$el->textContent = '';
		}
	}

	/**
	 * Width and height from the SVG (viewBox first), default 1600×900.
	 *
	 * @param string $svg SVG.
	 * @return array{0:int,1:int}
	 */
	public static function dimensions( string $svg ): array {
		if ( preg_match( '/viewBox=["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $m ) ) {
			return array( (int) round( (float) $m[1] ), (int) round( (float) $m[2] ) );
		}
		if ( preg_match( '/<svg[^>]*\swidth=["\']([\d.]+)(px)?["\'][^>]*\sheight=["\']([\d.]+)(px)?["\']/i', $svg, $m ) ) {
			return array( (int) round( (float) $m[1] ), (int) round( (float) $m[3] ) );
		}
		return array( 1600, 900 );
	}
}
