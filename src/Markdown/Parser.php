<?php
/**
 * Safe Markdown parser.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Markdown;

defined( 'ABSPATH' ) || exit;

/**
 * Converts a small, safe subset of Markdown to HTML.
 *
 * Supported: paragraphs and line breaks, **bold**, *italic*, ~~strike~~,
 * `inline code`, fenced code blocks, > blockquotes, - bulleted and 1.
 * numbered lists, [links](https://…) and bare http(s) URLs.
 *
 * Security model: the input is treated as plain text. Everything is escaped
 * first, and only the markup this class generates is added afterwards, so
 * raw HTML in a comment can never reach the page. Links accept only http,
 * https and mailto URLs. As a final safety net the output is passed through
 * wp_kses() with an allow-list of exactly the tags generated here.
 *
 * Parsing is line based and linear in the input size, with a nesting limit
 * for blockquotes, so crafted input cannot cause runaway recursion.
 *
 * @since 1.0.0
 */
class Parser {

	/**
	 * Maximum blockquote nesting depth.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_QUOTE_DEPTH = 3;

	/**
	 * Inline fragments (code, links) set aside while formatting runs.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private $placeholders = array();

	/**
	 * Convert Markdown to safe HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $markdown Markdown text. HTML entities (as stored by core's
	 *                         kses filters) are decoded first, so "&lt;" shows as "<".
	 * @return string HTML.
	 */
	public function parse( string $markdown ): string {
		$text = html_entity_decode( $markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// NUL bytes are reserved for placeholders; carriage returns are noise.
		$text = str_replace( array( "\0", "\r\n", "\r" ), array( '', "\n", "\n" ), $text );

		$html = $this->blocks( explode( "\n", $text ), 0 );

		return wp_kses( $html, $this->allowed_html() );
	}

	/**
	 * Get the tags and attributes the parser may output.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, bool>>
	 */
	public function allowed_html(): array {
		$tags = array( 'p', 'br', 'strong', 'em', 'del', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li' );
		$html = array_fill_keys( $tags, array() );

		$html['a'] = array(
			'href' => true,
			'rel'  => true,
		);

		return $html;
	}

	/**
	 * Render a sequence of lines as block elements.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $lines Source lines.
	 * @param int      $depth Blockquote nesting depth.
	 * @return string
	 */
	private function blocks( array $lines, int $depth ): string {
		$html  = '';
		$count = count( $lines );
		$i     = 0;

		while ( $i < $count ) {
			$line = $lines[ $i ];

			if ( '' === trim( $line ) ) {
				++$i;
				continue;
			}

			// Fenced code block: everything up to the closing fence is literal.
			if ( preg_match( '/^\s{0,3}(```|~~~)/', $line, $fence ) ) {
				$code = array();
				++$i;

				while ( $i < $count && ! preg_match( '/^\s{0,3}' . preg_quote( $fence[1], '/' ) . '\s*$/', $lines[ $i ] ) ) {
					$code[] = $lines[ $i ];
					++$i;
				}

				++$i;
				// Skip the closing fence (or run off the end).
				$html .= '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
				continue;
			}

			// Blockquote: consecutive lines starting with ">".
			if ( preg_match( '/^\s{0,3}>/', $line ) ) {
				$quoted = array();

				while ( $i < $count && preg_match( '/^\s{0,3}>\s?(.*)$/', $lines[ $i ], $found ) ) {
					$quoted[] = $found[1];
					++$i;
				}

				$html .= $depth < self::MAX_QUOTE_DEPTH
					? '<blockquote>' . $this->blocks( $quoted, $depth + 1 ) . '</blockquote>'
					: '<p>' . $this->inline( implode( "\n", $quoted ) ) . '</p>';
				continue;
			}

			// Lists: "- ", "* ", "+ " bullets or "1. " numbers. Flat, no nesting.
			if ( preg_match( '/^\s{0,3}([-*+]|\d{1,9}[.)])\s+/', $line, $marker ) ) {
				$ordered = ctype_digit( $marker[1][0] );
				$pattern = $ordered ? '/^\s{0,3}\d{1,9}[.)]\s+(.*)$/' : '/^\s{0,3}[-*+]\s+(.*)$/';
				$items   = array();

				while ( $i < $count && '' !== trim( $lines[ $i ] ) ) {
					if ( preg_match( $pattern, $lines[ $i ], $found ) ) {
						$items[] = $found[1];
					} elseif ( $items ) {
						// A continuation line belongs to the previous item.
						$items[ count( $items ) - 1 ] .= "\n" . trim( $lines[ $i ] );
					}

					++$i;
				}

				$tag   = $ordered ? 'ol' : 'ul';
				$html .= '<' . $tag . '>';

				foreach ( $items as $item ) {
					$html .= '<li>' . $this->inline( $item ) . '</li>';
				}

				$html .= '</' . $tag . '>';
				continue;
			}//end if

			// Paragraph: consecutive lines until a blank line or another block starts.
			$paragraph = array();

			while ( $i < $count && '' !== trim( $lines[ $i ] ) && ! $this->starts_block( $lines[ $i ] ) ) {
				$paragraph[] = $lines[ $i ];
				++$i;
			}

			// A line that starts a block but was not handled above still advances.
			if ( ! $paragraph ) {
				$paragraph[] = $lines[ $i ];
				++$i;
			}

			$html .= '<p>' . $this->inline( implode( "\n", $paragraph ) ) . '</p>';
		}//end while

		return $html;
	}

	/**
	 * Whether a line starts a non-paragraph block.
	 *
	 * @since 1.0.0
	 *
	 * @param string $line Source line.
	 * @return bool
	 */
	private function starts_block( string $line ): bool {
		return (bool) preg_match( '/^\s{0,3}(```|~~~|>|[-*+]\s|\d{1,9}[.)]\s)/', $line );
	}

	/**
	 * Render inline Markdown inside a block.
	 *
	 * Code spans and links are swapped for placeholders first, so emphasis
	 * markers inside them are left alone. The remaining text is escaped,
	 * emphasis is applied to the escaped text, and the placeholders are
	 * restored last.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw inline text.
	 * @return string
	 */
	private function inline( string $text ): string {
		$this->placeholders = array();

		// Code spans: `code` or ``code with ` inside``.
		$text = $this->replace_callback(
			'/(`+)(.+?)\1/s',
			function ( $found ) {
				return $this->hold( '<code>' . esc_html( trim( $found[2] ) ) . '</code>' );
			},
			$text
		);

		// Links written as [label](url).
		$text = $this->replace_callback(
			'/\[([^\[\]\n]{1,500})\]\(\s*([^\s()<>]{1,2000})\s*\)/',
			function ( $found ) {
				$url = $this->safe_url( $found[2] );

				if ( '' === $url ) {
					return $found[0];
				}

				return $this->hold( '<a href="' . esc_url( $url ) . '" rel="nofollow ugc noopener">' . $this->emphasis( esc_html( $found[1] ) ) . '</a>' );
			},
			$text
		);

		// Bare URLs.
		$text = $this->replace_callback(
			'#\bhttps?://[^\s<>"\'\]\)]{1,2000}#i',
			function ( $found ) {
				// Trailing punctuation belongs to the sentence, not the URL.
				$url  = rtrim( $found[0], '.,;:!?' );
				$tail = substr( $found[0], strlen( $url ) );

				return $this->hold( '<a href="' . esc_url( $url ) . '" rel="nofollow ugc noopener">' . esc_html( $url ) . '</a>' ) . $tail;
			},
			$text
		);

		$html = str_replace( "\n", '<br>', $this->emphasis( esc_html( $text ) ) );

		return $this->replace_callback(
			"/\0(\d+)\0/",
			function ( $found ) {
				return $this->placeholders[ (int) $found[1] ] ?? '';
			},
			$html
		);
	}

	/**
	 * Apply bold, italic and strikethrough to already-escaped text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Escaped text.
	 * @return string
	 */
	private function emphasis( string $html ): string {
		$rules = array(
			'/\*\*(?=\S)(.+?)(?<=\S)\*\*/s'                => '<strong>$1</strong>',
			'/(?<![\w_])__(?=\S)(.+?)(?<=\S)__(?![\w_])/s' => '<strong>$1</strong>',
			'/\*(?=\S)(.+?)(?<=\S)\*/s'                    => '<em>$1</em>',
			'/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/s'   => '<em>$1</em>',
			'/~~(?=\S)(.+?)(?<=\S)~~/s'                    => '<del>$1</del>',
		);

		foreach ( $rules as $pattern => $replacement ) {
			$result = preg_replace( $pattern, $replacement, $html );

			// On a regex failure (e.g. backtrack limit on crafted input) keep the text as is.
			if ( is_string( $result ) ) {
				$html = $result;
			}
		}

		return $html;
	}

	/**
	 * Run preg_replace_callback(), keeping the subject unchanged on failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $pattern  Regular expression.
	 * @param callable $callback Replacement callback.
	 * @param string   $subject  Input text.
	 * @return string
	 */
	private function replace_callback( string $pattern, callable $callback, string $subject ): string {
		$result = preg_replace_callback( $pattern, $callback, $subject );

		return is_string( $result ) ? $result : $subject;
	}

	/**
	 * Store an HTML fragment and return its placeholder.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Safe HTML.
	 * @return string
	 */
	private function hold( string $html ): string {
		$this->placeholders[] = $html;

		return "\0" . ( count( $this->placeholders ) - 1 ) . "\0";
	}

	/**
	 * Return the URL if it uses an allowed scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Candidate URL.
	 * @return string The URL, or empty when not allowed.
	 */
	private function safe_url( string $url ): string {
		return '' !== esc_url_raw( $url, array( 'http', 'https', 'mailto' ) ) && preg_match( '#^(https?://|mailto:)#i', $url ) ? $url : '';
	}
}
