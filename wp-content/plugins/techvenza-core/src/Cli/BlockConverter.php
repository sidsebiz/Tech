<?php
/**
 * Converts simple article HTML (from the prototype) into serialized core blocks.
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Cli;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument property names.

/**
 * HTML → block markup for paragraphs, headings, lists, quotes, tables, code and callouts.
 */
final class BlockConverter {

	/**
	 * Convert.
	 *
	 * @param string $html HTML.
	 */
	public static function convert( string $html ): string {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="tv-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$root = $doc->getElementById( 'tv-root' );
		if ( ! $root ) {
			return '';
		}
		$out = array();
		foreach ( iterator_to_array( $root->childNodes ) as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				$text = trim( (string) $node->textContent );
				if ( '' !== $text ) {
					$out[] = self::block( 'paragraph', array(), '<p>' . esc_html( $text ) . '</p>' );
				}
				continue;
			}
			$out[] = self::element( $doc, $node );
		}
		return implode( "\n\n", array_filter( $out ) );
	}

	/**
	 * Inner HTML of a node.
	 *
	 * @param \DOMDocument $doc  Document.
	 * @param \DOMNode     $node Node.
	 */
	private static function inner( \DOMDocument $doc, \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $doc->saveHTML( $child );
		}
		return trim( $html );
	}

	/**
	 * Serialize a block.
	 *
	 * @param string              $name  Block name without core/.
	 * @param array<string,mixed> $attrs Attributes.
	 * @param string              $html  Inner HTML.
	 */
	private static function block( string $name, array $attrs, string $html ): string {
		$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
		return "<!-- wp:{$name}{$json} -->\n{$html}\n<!-- /wp:{$name} -->";
	}

	/**
	 * Convert one top-level element.
	 *
	 * @param \DOMDocument $doc Document.
	 * @param \DOMElement  $el  Element.
	 */
	private static function element( \DOMDocument $doc, \DOMElement $el ): string {
		$tag   = strtolower( $el->tagName );
		$inner = self::inner( $doc, $el );
		switch ( $tag ) {
			case 'p':
				return self::block( 'paragraph', array(), '<p>' . $inner . '</p>' );
			case 'h1':
			case 'h5':
			case 'h6':
				$tag = 'h1' === $tag ? 'h2' : $tag;
				// Fall through: an article has one H1 (its title), so content H1s become H2s.
			case 'h2':
			case 'h3':
			case 'h4':
				$level = (int) substr( $tag, 1 );
				$id    = $el->getAttribute( 'id' );
				$id    = $id ? $id : sanitize_title( $el->textContent );
				return self::block( 'heading', 2 === $level ? array() : array( 'level' => $level ), sprintf( '<%1$s class="wp-block-heading" id="%2$s">%3$s</%1$s>', $tag, esc_attr( $id ), $inner ) );
			case 'ul':
			case 'ol':
				$items = array();
				foreach ( $el->childNodes as $li ) {
					if ( $li instanceof \DOMElement && 'li' === strtolower( $li->tagName ) ) {
						$items[] = self::block( 'list-item', array(), '<li>' . self::inner( $doc, $li ) . '</li>' );
					}
				}
				return self::block( 'list', 'ol' === $tag ? array( 'ordered' => true ) : array(), sprintf( '<%1$s class="wp-block-list">%2$s</%1$s>', $tag, implode( '', $items ) ) );
			case 'blockquote':
				$cite = '';
				$text = '';
				foreach ( $el->childNodes as $child ) {
					if ( $child instanceof \DOMElement && 'cite' === strtolower( $child->tagName ) ) {
						$cite = self::inner( $doc, $child );
					} else {
						$text .= $doc->saveHTML( $child );
					}
				}
				$text = trim( $text );
				$para = preg_match( '/^<p[\s>]/i', $text ) ? self::convert( $text ) : self::block( 'paragraph', array(), '<p>' . $text . '</p>' );
				return self::block( 'quote', array(), '<blockquote class="wp-block-quote">' . $para . ( $cite ? '<cite>' . $cite . '</cite>' : '' ) . '</blockquote>' );
			case 'table':
				return self::block( 'table', array(), '<figure class="wp-block-table"><table>' . $inner . '</table></figure>' );
			case 'pre':
				$code = $el->getElementsByTagName( 'code' )->item( 0 );
				$text = $code ? self::inner( $doc, $code ) : $inner;
				return self::block( 'code', array(), '<pre class="wp-block-code"><code>' . $text . '</code></pre>' );
			case 'hr':
				return self::block( 'separator', array(), '<hr class="wp-block-separator has-alpha-channel-opacity"/>' );
			case 'img':
			case 'figure':
				$img = 'img' === $tag ? $el : $el->getElementsByTagName( 'img' )->item( 0 );
				if ( ! $img instanceof \DOMElement ) {
					return self::block( 'html', array(), $doc->saveHTML( $el ) );
				}
				$cap   = $el->getElementsByTagName( 'figcaption' )->item( 0 );
				$id    = preg_match( '/wp-image-(\d+)/', $img->getAttribute( 'class' ), $m ) ? (int) $m[1] : 0;
				$attrs = $id ? array(
					'id'              => $id,
					'sizeSlug'        => 'large',
					'linkDestination' => 'none',
				) : array();
				return self::block(
					'image',
					$attrs,
					sprintf(
						'<figure class="wp-block-image%1$s"><img src="%2$s" alt="%3$s"%4$s/>%5$s</figure>',
						$id ? ' size-large' : '',
						esc_url( $img->getAttribute( 'src' ) ),
						esc_attr( $img->getAttribute( 'alt' ) ),
						$id ? ' class="wp-image-' . $id . '"' : '',
						$cap ? '<figcaption class="wp-element-caption">' . self::inner( $doc, $cap ) . '</figcaption>' : ''
					)
				);
			case 'div':
				$class = $el->getAttribute( 'class' );
				return self::block( 'paragraph', array( 'className' => $class ? $class : 'callout' ), '<p class="' . esc_attr( $class ? $class : 'callout' ) . '">' . $inner . '</p>' );
			default:
				return self::block( 'html', array(), $doc->saveHTML( $el ) );
		}
	}
}
