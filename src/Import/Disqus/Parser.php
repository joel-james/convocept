<?php
/**
 * Disqus export parser.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Import\Disqus;

use DOMElement;
use DOMNode;
use WP_Error;
use XMLReader;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader and DOM property names are camelCase.

/**
 * Streams a Disqus XML export, one thread or post at a time.
 *
 * Exports can hold hundreds of thousands of comments, so the file is read
 * with XMLReader and only one `<thread>` or `<post>` element is expanded
 * into memory at a time. Gzipped exports (`.gz`) are read directly.
 *
 * Format (checked against Disqus's own WordPress plugin and the isso and
 * other importers): a `<disqus>` root in the `http://disqus.com`
 * namespace, then `<category>`, `<thread dsq:id>` and `<post dsq:id>`
 * elements. Threads carry `<id>` (the page identifier, `"{post ID} {guid}"`
 * for WordPress sites), `<link>` and `<title>`. Posts carry `<message>`
 * (HTML), `<createdAt>` (UTC), `<isDeleted>`, `<isSpam>`, `<author>`
 * (`<name>`, `<email>`, `<username>`, `<isAnonymous>`), `<ipAddress>`,
 * `<thread dsq:id>` and an optional `<parent dsq:id>`.
 *
 * Entities are never substituted and the network is never used, so a
 * crafted file can't read local files or make requests (XXE).
 *
 * @since 1.0.0
 */
class Parser {

	/**
	 * Namespace of Disqus export elements.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NS = 'http://disqus.com';

	/**
	 * Namespace of Disqus internal IDs (`dsq:id`).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NS_INTERNALS = 'http://disqus.com/disqus-internals';

	/**
	 * Read an export and hand each thread and post to a callback.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $path      Export file (.xml or .gz).
	 * @param callable $on_thread Receives the array from thread().
	 * @param callable $on_post   Receives the array from post().
	 * @return true|WP_Error True when the whole file was read.
	 *
	 * @phpstan-param callable(array<string, string>): void $on_thread
	 * @phpstan-param callable(array<string, mixed>): void  $on_post
	 */
	public function parse( string $path, callable $on_thread, callable $on_post ) {
		if ( ! class_exists( XMLReader::class ) ) {
			return new WP_Error( 'convocept_import_xmlreader', __( 'The PHP XMLReader extension is required to import Disqus exports.', 'convocept' ) );
		}

		$uri    = $this->is_gzip( $path ) ? 'compress.zlib://' . $path : $path;
		$reader = new XMLReader();
		$errors = libxml_use_internal_errors( true );

		// No LIBXML_NOENT / LIBXML_DTDLOAD: entities stay unexpanded and no external DTD is loaded.
		// No LIBXML_PARSEHUGE either: libxml's size limits also cap entity amplification, and no
		// single Disqus element comes near them (the file itself can be any size).
		if ( ! $reader->open( $uri, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT ) ) {
			libxml_use_internal_errors( $errors );

			return $this->invalid();
		}

		$root_seen = false;
		$result    = true;
		$moved     = $this->read( $reader );

		while ( $moved ) {
			if ( XMLReader::ELEMENT === $reader->nodeType && 0 === $reader->depth ) {
				if ( 'disqus' !== $reader->localName ) {
					$result = $this->invalid();
					break;
				}

				$root_seen = true;
			} elseif ( XMLReader::ELEMENT === $reader->nodeType && 1 === $reader->depth && in_array( $reader->localName, array( 'thread', 'post' ), true ) ) {
				$name = $reader->localName;
				$node = @$reader->expand(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Malformed XML is reported through libxml_get_errors().

				if ( $node instanceof DOMElement ) {
					if ( 'thread' === $name ) {
						$on_thread( $this->thread( $node ) );
					} else {
						$on_post( $this->post( $node ) );
					}
				}

				// Jump past the element's children, which expand() already read.
				$moved = (bool) @$reader->next(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Malformed XML is reported through libxml_get_errors().
				continue;
			}//end if

			$moved = $this->read( $reader );
		}//end while

		if ( true === $result && ( ! $root_seen || libxml_get_errors() ) ) {
			$result = $this->invalid();
		}

		$reader->close();
		libxml_clear_errors();
		libxml_use_internal_errors( $errors );

		return $result;
	}

	/**
	 * Read the next node, treating a parse error as the end of the file.
	 *
	 * @since 1.0.0
	 *
	 * @param XMLReader $reader Reader.
	 * @return bool
	 */
	private function read( XMLReader $reader ): bool {
		return (bool) @$reader->read(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Malformed XML is reported through libxml_get_errors().
	}

	/**
	 * Build the "not a Disqus export" error.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Error
	 */
	private function invalid(): WP_Error {
		return new WP_Error( 'convocept_import_invalid', __( 'This file is not a valid Disqus XML export.', 'convocept' ) );
	}

	/**
	 * Whether a file is gzip-compressed, judged by its magic bytes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private function is_gzip( string $path ): bool {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading two bytes of a local import file.

		if ( ! $handle ) {
			return false;
		}

		$magic = fread( $handle, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- See above.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- See above.

		return "\x1f\x8b" === $magic;
	}

	/**
	 * Read a `<thread>` element.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMElement $node Thread element.
	 * @return array<string, string> {
	 *     @type string $dsq_id     Disqus thread ID.
	 *     @type string $identifier Page identifier (`<id>`), may be empty.
	 *     @type string $link       Page URL, may be empty.
	 *     @type string $title      Page title.
	 * }
	 */
	public function thread( DOMElement $node ): array {
		return array(
			'dsq_id'     => $node->getAttributeNS( self::NS_INTERNALS, 'id' ),
			'identifier' => $this->text( $node, 'id' ),
			'link'       => $this->text( $node, 'link' ),
			'title'      => $this->text( $node, 'title' ),
		);
	}

	/**
	 * Read a `<post>` element.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMElement $node Post element.
	 * @return array<string, mixed> {
	 *     @type string $dsq_id     Disqus post ID.
	 *     @type string $id         `<id>`: empty, or `wp_id=N` for comments Disqus
	 *                              originally imported from WordPress.
	 *     @type string $thread     Disqus thread ID.
	 *     @type string $parent     Disqus ID of the parent post, or ''.
	 *     @type string $message    HTML message.
	 *     @type string $created    `createdAt`, ISO 8601 in UTC.
	 *     @type bool   $deleted    Whether the post was deleted.
	 *     @type bool   $spam       Whether the post is spam.
	 *     @type bool   $approved   Whether the post is approved (true when not stated).
	 *     @type string $name       Author name.
	 *     @type string $email      Author email, may be empty.
	 *     @type string $username   Disqus username, may be empty.
	 *     @type bool   $anonymous  Whether the author was a guest.
	 *     @type string $ip         IP address, may be empty.
	 * }
	 */
	public function post( DOMElement $node ): array {
		$author = $this->child( $node, 'author' );

		return array(
			'dsq_id'    => $node->getAttributeNS( self::NS_INTERNALS, 'id' ),
			'id'        => $this->text( $node, 'id' ),
			'thread'    => $this->reference( $node, 'thread' ),
			'parent'    => $this->reference( $node, 'parent' ),
			'message'   => $this->text( $node, 'message' ),
			'created'   => $this->text( $node, 'createdAt' ),
			'deleted'   => $this->flag( $node, 'isDeleted', false ),
			'spam'      => $this->flag( $node, 'isSpam', false ),
			'approved'  => $this->flag( $node, 'isApproved', true ),
			'name'      => $author ? $this->text( $author, 'name' ) : '',
			'email'     => $author ? $this->text( $author, 'email' ) : '',
			'username'  => $author ? $this->text( $author, 'username' ) : '',
			'anonymous' => $author ? $this->flag( $author, 'isAnonymous', false ) : true,
			'ip'        => $this->text( $node, 'ipAddress' ),
		);
	}

	/**
	 * Find a direct child element by local name.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMNode $node Parent.
	 * @param string  $name Local name.
	 * @return DOMElement|null
	 */
	private function child( DOMNode $node, string $name ): ?DOMElement {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->localName === $name ) {
				return $child;
			}
		}

		return null;
	}

	/**
	 * Get a direct child's trimmed text.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMNode $node Parent.
	 * @param string  $name Child local name.
	 * @return string
	 */
	private function text( DOMNode $node, string $name ): string {
		$child = $this->child( $node, $name );

		return $child ? trim( (string) $child->textContent ) : '';
	}

	/**
	 * Get a boolean child ("true"/"false").
	 *
	 * @since 1.0.0
	 *
	 * @param DOMNode $node     Parent.
	 * @param string  $name     Child local name.
	 * @param bool    $fallback Value when the child is missing.
	 * @return bool
	 */
	private function flag( DOMNode $node, string $name, bool $fallback ): bool {
		$child = $this->child( $node, $name );

		return $child ? 'true' === strtolower( trim( (string) $child->textContent ) ) : $fallback;
	}

	/**
	 * Get the `dsq:id` a reference element (`<thread>`, `<parent>`) points to.
	 *
	 * @since 1.0.0
	 *
	 * @param DOMNode $node Parent.
	 * @param string  $name Child local name.
	 * @return string Disqus ID, or ''.
	 */
	private function reference( DOMNode $node, string $name ): string {
		$child = $this->child( $node, $name );

		return $child ? $child->getAttributeNS( self::NS_INTERNALS, 'id' ) : '';
	}
}
