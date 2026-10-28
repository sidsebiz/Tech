<?php
/**
 * Markdown → HTML for CSV imports (CommonMark subset: ATX headings, paragraphs, emphasis,
 * links, images, inline code, fenced code, block quotes, ordered/unordered lists, rules and
 * GitHub-style tables). Raw HTML is passed through and later filtered by wp_kses_post().
 *
 * @package Techvenza\Core
 */

declare( strict_types = 1 );

namespace Techvenza\Core\Import;

/**
 * Markdown converter.
 */
final class MarkdownToHtml {

	/**
	 * Convert.
	 *
	 * @param string $md Markdown.
	 */
	public static function convert( string $md ): string {
		$lines = preg_split( '/\R/', str_replace( "\t", '    ', $md ) );
		$lines = $lines ? $lines : array();
		$out   = array();
		$n     = count( $lines );
		for ( $i = 0; $i < $n; ) {
			$line = $lines[ $i ];
			if ( '' === trim( $line ) ) {
				++$i;
				continue;
			}
			// Fenced code.
			if ( preg_match( '/^\s*(```|~~~)\s*([\w-]*)\s*$/', $line, $m ) ) {
				$code = array();
				for ( ++$i; $i < $n && ! preg_match( '/^\s*' . preg_quote( $m[1], '/' ) . '\s*$/', $lines[ $i ] ); $i++ ) {
					$code[] = $lines[ $i ];
				}
				++$i;
				$out[] = '<pre><code' . ( $m[2] ? ' class="language-' . esc_attr( $m[2] ) . '"' : '' ) . '>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
				continue;
			}
			// Heading.
			if ( preg_match( '/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				$out[] = "<h{$level}>" . self::inline( $m[2] ) . "</h{$level}>";
				++$i;
				continue;
			}
			// Horizontal rule.
			if ( preg_match( '/^\s*([-*_])(\s*\1){2,}\s*$/', $line ) ) {
				$out[] = '<hr>';
				++$i;
				continue;
			}
			// Block quote.
			if ( preg_match( '/^\s*>/', $line ) ) {
				$quote = array();
				for ( ; $i < $n && preg_match( '/^\s*>\s?(.*)$/', $lines[ $i ], $m ); $i++ ) {
					$quote[] = $m[1];
				}
				$out[] = '<blockquote>' . self::convert( implode( "\n", $quote ) ) . '</blockquote>';
				continue;
			}
			// Table.
			if ( str_contains( $line, '|' ) && $i + 1 < $n && preg_match( '/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/', $lines[ $i + 1 ] ) ) {
				$head = self::cells( $line );
				$rows = array();
				for ( $i += 2; $i < $n && str_contains( $lines[ $i ], '|' ) && '' !== trim( $lines[ $i ] ); $i++ ) {
					$rows[] = self::cells( $lines[ $i ] );
				}
				$html  = '<table><thead><tr>' . implode( '', array_map( static fn( $c ) => '<th>' . self::inline( $c ) . '</th>', $head ) ) . '</tr></thead><tbody>';
				$html .= implode( '', array_map( static fn( $r ) => '<tr>' . implode( '', array_map( static fn( $c ) => '<td>' . self::inline( $c ) . '</td>', $r ) ) . '</tr>', $rows ) );
				$out[] = $html . '</tbody></table>';
				continue;
			}
			// Lists.
			if ( preg_match( '/^(\s*)([-*+]|\d+[.)])\s+/', $line, $m ) ) {
				$ordered = ctype_digit( $m[2][0] );
				$items   = array();
				for ( ; $i < $n; $i++ ) {
					if ( preg_match( '/^\s*([-*+]|\d+[.)])\s+(.*)$/', $lines[ $i ], $mm ) ) {
						$items[] = $mm[2];
					} elseif ( '' !== trim( $lines[ $i ] ) && preg_match( '/^\s{2,}\S/', $lines[ $i ] ) && $items ) {
						$items[ count( $items ) - 1 ] .= ' ' . trim( $lines[ $i ] );
					} else {
						break;
					}
				}
				$tag   = $ordered ? 'ol' : 'ul';
				$out[] = "<{$tag}>" . implode( '', array_map( static fn( $t ) => '<li>' . self::inline( $t ) . '</li>', $items ) ) . "</{$tag}>";
				continue;
			}
			// Raw HTML block.
			if ( preg_match( '/^\s*<(\/?[a-z][a-z0-9]*)[\s>]/i', $line ) ) {
				$block = array();
				for ( ; $i < $n && '' !== trim( $lines[ $i ] ); $i++ ) {
					$block[] = $lines[ $i ];
				}
				$out[] = implode( "\n", $block );
				continue;
			}
			// Paragraph (until blank line or another block starts).
			$para = array();
			for ( ; $i < $n && '' !== trim( $lines[ $i ] ) && ! preg_match( '/^(#{1,6}\s|\s*>|\s*([-*+]|\d+[.)])\s+|\s*```|\s*~~~)/', $lines[ $i ] ); $i++ ) {
				$para[] = trim( $lines[ $i ] );
			}
			if ( ! $para ) {
				$para[] = trim( $lines[ $i ] );
				++$i;
			}
			$text = implode( ' ', $para );
			// A paragraph holding only an image becomes a figure.
			if ( preg_match( '/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)$/', $text, $m ) ) {
				$out[] = '<figure><img src="' . self::src( $m[2] ) . '" alt="' . esc_attr( $m[1] ) . '">' . ( ! empty( $m[3] ) ? '<figcaption>' . esc_html( $m[3] ) . '</figcaption>' : '' ) . '</figure>';
				continue;
			}
			$out[] = '<p>' . self::inline( $text ) . '</p>';
		}
		return implode( "\n", $out );
	}

	/**
	 * Image source: absolute URLs are escaped as URLs, bare file names (from the images ZIP) kept.
	 *
	 * @param string $src Source.
	 */
	private static function src( string $src ): string {
		return preg_match( '#^(https?:)?//#i', $src ) ? esc_url( $src ) : esc_attr( sanitize_file_name( $src ) );
	}

	/**
	 * Table cells.
	 *
	 * @param string $line Row.
	 * @return array<int,string>
	 */
	private static function cells( string $line ): array {
		$line = trim( trim( $line ), '|' );
		return array_map( 'trim', preg_split( '/(?<!\\\\)\|/', $line ) ? (array) preg_split( '/(?<!\\\\)\|/', $line ) : array( $line ) );
	}

	/**
	 * Inline formatting.
	 *
	 * @param string $text Text.
	 */
	public static function inline( string $text ): string {
		$codes = array();
		$text  = (string) preg_replace_callback(
			'/`([^`]+)`/',
			static function ( $m ) use ( &$codes ) {
				$codes[] = '<code>' . esc_html( $m[1] ) . '</code>';
				return "\x1A" . ( count( $codes ) - 1 ) . "\x1A";
			},
			$text
		);
		$text  = (string) preg_replace_callback( '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', static fn( $m ) => '<img src="' . self::src( $m[2] ) . '" alt="' . esc_attr( $m[1] ) . '">', $text );
		$text  = (string) preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', static fn( $m ) => '<a href="' . esc_url( $m[2] ) . '">' . $m[1] . '</a>', $text );
		$text  = (string) preg_replace( '/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/', '<strong>$2</strong>', $text );
		$text  = (string) preg_replace( '/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/', '<em>$1</em>', $text );
		$text  = (string) preg_replace( '/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/', '<em>$1</em>', $text );
		$text  = (string) preg_replace( '/~~(.+?)~~/', '<del>$1</del>', $text );
		return (string) preg_replace_callback( "/\x1A(\d+)\x1A/", static fn( $m ) => $codes[ (int) $m[1] ], $text );
	}
}
