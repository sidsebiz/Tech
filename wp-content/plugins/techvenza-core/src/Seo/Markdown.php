<?php
/**
 * Small HTML → Markdown converter for article bodies (headings, paragraphs, lists, links,
 * emphasis, code, quotes, tables, images, figures). Unknown elements keep their text.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Seo;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument property names.

/**
 * HTML to Markdown.
 */
final class Markdown {

	/**
	 * Convert.
	 *
	 * @param string $html HTML.
	 */
	public static function from_html( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><div id="tv-md-root">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$root = $dom->getElementById( 'tv-md-root' );
		if ( ! $root ) {
			return trim( wp_strip_all_tags( $html ) );
		}
		$md = self::children( $root, 0 );
		$md = (string) preg_replace( '/^[ \t]+$/m', '', $md );
		$md = (string) preg_replace( "/\n{3,}/", "\n\n", $md );
		return trim( $md ) . "\n";
	}

	/**
	 * Convert children.
	 *
	 * @param \DOMNode $node  Node.
	 * @param int      $depth List depth.
	 */
	private static function children( \DOMNode $node, int $depth ): string {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= self::node( $child, $depth );
		}
		return $out;
	}

	/**
	 * Inline text of a node.
	 *
	 * @param \DOMNode $node Node.
	 */
	private static function inline( \DOMNode $node ): string {
		return trim( (string) preg_replace( '/[ \t]*\n[ \t]*/', ' ', self::children( $node, 0 ) ) );
	}

	/**
	 * Convert one node.
	 *
	 * @param \DOMNode $node  Node.
	 * @param int      $depth List depth.
	 */
	private static function node( \DOMNode $node, int $depth ): string {
		if ( $node instanceof \DOMText ) {
			$text = (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $node->nodeValue ?? '' );
			return str_replace( array( '*', '_', '`' ), array( '\*', '\_', '\`' ), $text );
		}
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}
		$tag = strtolower( $node->tagName );
		switch ( $tag ) {
			case 'script':
			case 'style':
			case 'noscript':
			case 'svg':
			case 'button':
			case 'form':
				return '';
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				return "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . self::inline( $node ) . "\n\n";
			case 'p':
				$text = self::inline( $node );
				return '' === $text ? '' : "\n\n" . $text . "\n\n";
			case 'br':
				return "  \n";
			case 'hr':
				return "\n\n---\n\n";
			case 'strong':
			case 'b':
				$t = self::inline( $node );
				return '' === $t ? '' : '**' . $t . '**';
			case 'em':
			case 'i':
				$t = self::inline( $node );
				return '' === $t ? '' : '*' . $t . '*';
			case 'code':
				return '`' . str_replace( '`', '\`', (string) $node->textContent ) . '`';
			case 'pre':
				return "\n\n```\n" . rtrim( (string) $node->textContent ) . "\n```\n\n";
			case 'a':
				$href = (string) $node->getAttribute( 'href' );
				$text = self::inline( $node );
				if ( '' === $href || str_starts_with( $href, '#' ) ) {
					return $text;
				}
				return '[' . ( '' !== $text ? $text : $href ) . '](' . $href . ')';
			case 'img':
				$src = (string) $node->getAttribute( 'src' );
				return $src ? '![' . (string) $node->getAttribute( 'alt' ) . '](' . $src . ')' : '';
			case 'figure':
				return "\n\n" . trim( self::children( $node, $depth ) ) . "\n\n";
			case 'figcaption':
				return "\n\n*" . self::inline( $node ) . "*\n\n";
			case 'blockquote':
				$inner = trim( self::children( $node, $depth ) );
				return "\n\n" . implode( "\n", array_map( static fn( $l ) => '> ' . $l, explode( "\n", $inner ) ) ) . "\n\n";
			case 'ul':
			case 'ol':
				$out = '';
				$i   = 1;
				foreach ( $node->childNodes as $li ) {
					if ( $li instanceof \DOMElement && 'li' === strtolower( $li->tagName ) ) {
						$marker = 'ol' === $tag ? ( $i++ ) . '. ' : '- ';
						$body   = trim( (string) preg_replace( "/\n{2,}/", "\n", self::children( $li, $depth + 1 ) ) );
						$out   .= str_repeat( '  ', $depth ) . $marker . str_replace( "\n", "\n" . str_repeat( '  ', $depth + 1 ), $body ) . "\n";
					}
				}
				return ( $depth ? "\n" : "\n\n" ) . $out . ( $depth ? '' : "\n" );
			case 'table':
				return "\n\n" . self::table( $node ) . "\n\n";
			case 'details':
				return "\n\n" . trim( self::children( $node, $depth ) ) . "\n\n";
			case 'summary':
				return "\n\n**" . self::inline( $node ) . "**\n\n";
			default:
				$block = in_array( $tag, array( 'div', 'section', 'aside', 'article', 'header', 'footer', 'nav' ), true );
				$inner = self::children( $node, $depth );
				return $block ? "\n\n" . $inner . "\n\n" : $inner;
		}
	}

	/**
	 * GitHub-flavoured Markdown table.
	 *
	 * @param \DOMElement $table Table.
	 */
	private static function table( \DOMElement $table ): string {
		$rows = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof \DOMElement && in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
					$cells[] = str_replace( '|', '\|', self::inline( $cell ) );
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}
		if ( ! $rows ) {
			return '';
		}
		$cols = max( array_map( 'count', $rows ) );
		$out  = '';
		foreach ( $rows as $i => $cells ) {
			$cells = array_pad( $cells, $cols, '' );
			$out  .= '| ' . implode( ' | ', $cells ) . " |\n";
			if ( 0 === $i ) {
				$out .= '|' . str_repeat( ' --- |', $cols ) . "\n";
			}
		}
		return rtrim( $out );
	}
}
