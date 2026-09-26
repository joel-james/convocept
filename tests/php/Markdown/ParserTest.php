<?php
/**
 * Tests for the safe Markdown parser, including an XSS suite.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Markdown;

use Convocept\Markdown\Parser;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Markdown\Parser
 */
class ParserTest extends TestCase {

	/**
	 * Parse Markdown.
	 *
	 * @param string $markdown Markdown.
	 * @return string
	 */
	private function parse( string $markdown ): string {
		return ( new Parser() )->parse( $markdown );
	}

	/**
	 * @dataProvider formatting_cases
	 *
	 * @param string $markdown Input.
	 * @param string $expected Expected HTML.
	 */
	public function test_formatting( string $markdown, string $expected ): void {
		$this->assertSame( $expected, $this->parse( $markdown ) );
	}

	/**
	 * @return array<string, string[]>
	 */
	public function formatting_cases(): array {
		return array(
			'paragraphs'      => array( "one\ntwo\n\nthree", '<p>one<br>two</p><p>three</p>' ),
			'bold italic'     => array( '**b** *i* __b__ _i_ ~~s~~', '<p><strong>b</strong> <em>i</em> <strong>b</strong> <em>i</em> <del>s</del></p>' ),
			'snake case'      => array( 'my_var_name stays', '<p>my_var_name stays</p>' ),
			'inline code'     => array( 'use `**not bold**`', '<p>use <code>**not bold**</code></p>' ),
			'fenced code'     => array( "```\n<b>x</b>\n  y\n```", "<pre><code>&lt;b&gt;x&lt;/b&gt;\n  y</code></pre>" ),
			'unclosed fence'  => array( "```\ncode", '<pre><code>code</code></pre>' ),
			'quote'           => array( "> quoted\n> more", '<blockquote><p>quoted<br>more</p></blockquote>' ),
			'bullets'         => array( "- a\n- b", '<ul><li>a</li><li>b</li></ul>' ),
			'numbers'         => array( "1. a\n2. b", '<ol><li>a</li><li>b</li></ol>' ),
			'link'            => array( '[site](https://example.com)', '<p><a href="https://example.com" rel="nofollow ugc noopener">site</a></p>' ),
			'bare url'        => array( 'see https://example.com/a_b.', '<p>see <a href="https://example.com/a_b" rel="nofollow ugc noopener">https://example.com/a_b</a>.</p>' ),
			'stored entities' => array( 'a &lt; b &amp;&amp; c &gt; d', '<p>a &lt; b &amp;&amp; c &gt; d</p>' ),
			'no headings'     => array( '# not a heading', '<p># not a heading</p>' ),
		);
	}

	/**
	 * @dataProvider xss_cases
	 *
	 * @param string $payload Malicious input.
	 */
	public function test_xss_payloads_are_neutralised( string $payload ): void {
		$html    = $this->parse( $payload );
		$allowed = ( new Parser() )->allowed_html();
		$dom     = new \DOMDocument();

		// Parse the output as a browser would and inspect every real element.
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();

		foreach ( $dom->getElementsByTagName( '*' ) as $element ) {
			$tag = strtolower( $element->nodeName );

			if ( in_array( $tag, array( 'html', 'body' ), true ) ) {
				continue;
			}

			$this->assertArrayHasKey( $tag, $allowed, "Unexpected <{$tag}> in: {$html}" );

			foreach ( $element->attributes as $attribute ) {
				$name = strtolower( $attribute->nodeName );

				$this->assertArrayHasKey( $name, $allowed[ $tag ], "Unexpected {$name}= on <{$tag}> in: {$html}" );

				if ( 'href' === $name ) {
					$this->assertMatchesRegularExpression( '#^(https?://|mailto:)#i', $attribute->nodeValue, $html );
				}
			}
		}

		$this->assertSame( $html, wp_kses( $html, $allowed ), 'Output must already be kses-clean.' );
	}

	/**
	 * @return array<string, string[]>
	 */
	public function xss_cases(): array {
		return array(
			'script tag'           => array( '<script>alert(1)</script>' ),
			'encoded script'       => array( '&lt;script&gt;alert(1)&lt;/script&gt;' ),
			'img onerror'          => array( '<img src=x onerror=alert(1)>' ),
			'javascript link'      => array( '[click](javascript:alert(1))' ),
			'entity js link'       => array( '[click](jav&#x61;script:alert(1))' ),
			'data link'            => array( '[x](data:text/html;base64,PHNjcmlwdD4=)' ),
			'vbscript link'        => array( '[x](vbscript:msgbox)' ),
			'quote breakout'       => array( '[x](https://a.com/"onmouseover="alert(1))' ),
			'attr in label'        => array( '[<img src=x onerror=alert(1)>](https://a.com)' ),
			'svg'                  => array( '<svg onload=alert(1)>' ),
			'iframe in code fence' => array( "```\n</code></pre><iframe src=//evil>\n```" ),
			'code span breakout'   => array( '`</code><script>x</script>`' ),
			'bare js url'          => array( 'javascript:alert(1)' ),
			'nul byte'             => array( "<scr\0ipt>alert(1)</scr\0ipt>" ),
			'nested quotes'        => array( str_repeat( '>', 50 ) . ' <script>x</script>' ),
			'placeholder forgery'  => array( "\x000\x00 `<b>`" ),
			'style tag'            => array( '<style>*{display:none}</style>' ),
		);
	}

	public function test_pathological_input_does_not_lose_content(): void {
		$input = str_repeat( '*a', 20000 );

		$this->assertStringContainsString( 'a', $this->parse( $input ) );
	}
}
