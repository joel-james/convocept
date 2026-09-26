<?php
/**
 * Disqus importer.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Import\Disqus;

use Convocept\Import\Importer;
use Convocept\Import\Job;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Imports comments from a Disqus XML export into native WordPress comments.
 *
 * The dry run streams the export once and writes what the batches need to
 * the job's private workspace:
 *
 * - `posts.ndjson`  one line per importable comment (spam and deleted
 *                   comments are left out), read by the batches from a
 *                   byte offset, so a 100k-comment export is never parsed
 *                   twice and each batch starts instantly;
 * - `threads.json`  Disqus thread ID → WordPress post ID;
 * - `skipped.json`  Disqus ID → parent ID of the spam/deleted comments, so
 *                   their replies attach to the nearest kept ancestor.
 *
 * Idempotent: every imported comment stores its Disqus ID in
 * `_convocept_disqus_id`, and comments the official Disqus plugin already
 * synced into WordPress (`dsq_post_id` meta) or that Disqus originally took
 * from WordPress (`<id>wp_id=N</id>`) are recognised and skipped.
 *
 * @since 1.0.0
 */
class DisqusImporter implements Importer {

	/**
	 * Comment meta key holding the Disqus post ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ID_META_KEY = '_convocept_disqus_id';

	/**
	 * Comment meta key used by the official Disqus plugin's sync.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SYNC_META_KEY = 'dsq_post_id';

	/**
	 * Comment meta key holding a parent that was not imported yet when the reply was.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PARENT_META_KEY = '_convocept_disqus_parent';

	/**
	 * Value stored in `comment_agent`.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const AGENT = 'Disqus import';

	/**
	 * Replies whose parent is resolved per finishing batch.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const ORPHANS_PER_BATCH = 500;

	/**
	 * Export parser.
	 *
	 * @since 1.0.0
	 * @var Parser
	 */
	private $parser;

	/**
	 * Workspace maps loaded in this request, keyed by workspace path.
	 *
	 * @since 1.0.0
	 * @var array<string, array{threads: array<string, int>, skipped: array<string, string>}>
	 */
	private $maps = array();

	/**
	 * Create the importer.
	 *
	 * @since 1.0.0
	 *
	 * @param Parser $parser Export parser.
	 */
	public function __construct( Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * Get the importer's ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'disqus';
	}

	/**
	 * Get the name shown in Tools → Import.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Disqus (Convocept)', 'convocept' );
	}

	/**
	 * Get the description shown in Tools → Import.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Import comments from a Disqus XML export into your WordPress comments.', 'convocept' );
	}

	/**
	 * Whether the importer reads a file.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function needs_file(): bool {
		return true;
	}

	/**
	 * Whether there is anything to import (unknown until a file is given).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function detect(): bool {
		return true;
	}

	/**
	 * Stream the export once: count, sample and write the batch files.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job New job.
	 * @return Job|WP_Error
	 */
	public function prepare( Job $job ) {
		$lines_path = $job->workspace . '/posts.ndjson';
		$handle     = fopen( $lines_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a large work file in our private import folder.

		if ( ! $handle ) {
			return new WP_Error( 'convocept_import_workspace', __( 'Could not write to the import working folder.', 'convocept' ) );
		}

		$threads    = array();
		$per_thread = array();
		$skipped    = array();
		$first      = array();
		$buffer     = '';

		$result = $this->parser->parse(
			$job->file,
			static function ( array $thread ) use ( &$threads ) {
				if ( '' !== $thread['dsq_id'] ) {
					$threads[ $thread['dsq_id'] ] = $thread;
				}
			},
			static function ( array $post ) use ( $job, $handle, &$buffer, &$per_thread, &$skipped, &$first ) {
				if ( '' === $post['dsq_id'] ) {
					$job->add( 'invalid' );
					return;
				}

				if ( $post['deleted'] || $post['spam'] ) {
					$job->add( $post['deleted'] ? 'deleted' : 'spam' );
					$skipped[ $post['dsq_id'] ] = $post['parent'];
					return;
				}

				unset( $post['deleted'], $post['spam'], $post['anonymous'] );

				$buffer .= wp_json_encode( $post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";

				// Write in ~1 MB chunks: one small write per comment is slow on network and container file systems.
				if ( strlen( $buffer ) > MB_IN_BYTES ) {
					fwrite( $handle, $buffer ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- See fopen() above.
					$buffer = '';
				}

				$per_thread[ $post['thread'] ] = ( $per_thread[ $post['thread'] ] ?? 0 ) + 1;
				++$job->total;

				if ( count( $first ) < 20 ) {
					$first[] = $post;
				}
			}
		);

		fwrite( $handle, $buffer ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- See fopen() above.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- See fopen() above.

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( 0 === $job->total && 0 === $job->count( 'spam' ) + $job->count( 'deleted' ) ) {
			return new WP_Error( 'convocept_import_empty', __( 'The export contains no comments.', 'convocept' ) );
		}

		// Only threads with comments to import are looked up.
		$map = array();

		foreach ( $per_thread as $thread_id => $count ) {
			$thread_id         = (string) $thread_id;
			$post_id           = isset( $threads[ $thread_id ] ) ? $this->resolve_post( $threads[ $thread_id ] ) : 0;
			$map[ $thread_id ] = $post_id;

			if ( $post_id ) {
				$job->add( 'threads_matched' );
				$job->add( 'matched', $count );
			} else {
				$job->add( 'threads_unmatched' );
				$job->add( 'unmatched', $count );
			}
		}

		$this->write_json( $job->workspace . '/threads.json', $map );
		$this->write_json( $job->workspace . '/skipped.json', $skipped );

		$job->sample = $this->sample( $first, $map );
		$job->cursor = 0;

		return $job;
	}

	/**
	 * Find the WordPress post a Disqus thread belongs to.
	 *
	 * Tries the identifier the Disqus WordPress plugin sets
	 * (`"{post ID} {guid}"`, accepted when the post exists and its guid
	 * matches), then the thread URL on this site, then the same path on
	 * this site's domain (for sites that moved domain or to HTTPS).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $thread Thread from Parser::thread().
	 * @return int Post ID, or 0.
	 */
	public function resolve_post( array $thread ): int {
		$post_id = 0;
		$parts   = explode( ' ', trim( (string) ( $thread['identifier'] ?? '' ) ), 2 );

		if ( ctype_digit( $parts[0] ) ) {
			$post = get_post( (int) $parts[0] );

			if ( $post && $this->is_importable_post( $post ) && ( ! isset( $parts[1] ) || $post->guid === $parts[1] ) ) {
				$post_id = (int) $post->ID;
			}
		}

		$link = (string) ( $thread['link'] ?? '' );

		if ( ! $post_id && '' !== $link ) {
			$post_id = url_to_postid( $link );

			if ( ! $post_id ) {
				$path = (string) wp_parse_url( $link, PHP_URL_PATH );
				$qs   = (string) wp_parse_url( $link, PHP_URL_QUERY );

				$post_id = url_to_postid( home_url( $path . ( '' !== $qs ? '?' . $qs : '' ) ) );
			}

			$post = $post_id ? get_post( $post_id ) : null;

			if ( ! $post || ! $this->is_importable_post( $post ) ) {
				$post_id = 0;
			}
		}

		/**
		 * Filters the post a Disqus thread's comments are imported into.
		 *
		 * Use it to map threads whose URLs changed in ways Convocept can't
		 * follow. Return 0 to skip the thread.
		 *
		 * @since 1.0.0
		 *
		 * @param int                   $post_id Post ID, or 0 when none was found.
		 * @param array<string, string> $thread  Thread: 'dsq_id', 'identifier', 'link', 'title'.
		 */
		return (int) apply_filters( 'convocept_disqus_thread_post_id', $post_id, $thread );
	}

	/**
	 * Whether comments can be imported into a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private function is_importable_post( \WP_Post $post ): bool {
		return ! in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true );
	}

	/**
	 * Build the dry-run preview.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $posts First importable posts.
	 * @param array<string, int>               $map   Thread → post map.
	 * @return array<int, array<string, string>>
	 */
	private function sample( array $posts, array $map ): array {
		$sample = array();

		foreach ( $posts as $post ) {
			$post_id = $map[ $post['thread'] ] ?? 0;

			if ( ! $post_id ) {
				continue;
			}

			$sample[] = array(
				'author'  => $this->author_name( $post ),
				'date'    => (string) get_date_from_gmt( $this->gmt_date( (string) $post['created'] ), (string) get_option( 'date_format' ) ),
				'excerpt' => wp_trim_words( wp_strip_all_tags( (string) $post['message'] ), 20 ),
				'post'    => get_the_title( $post_id ),
			);

			if ( count( $sample ) >= 5 ) {
				break;
			}
		}

		return $sample;
	}

	/**
	 * Import the next batch of comments.
	 *
	 * @since 1.0.0
	 *
	 * @param Job   $job      Running job.
	 * @param int   $limit    Maximum comments to process.
	 * @param float $deadline Microtime to stop by.
	 * @return Job|WP_Error
	 */
	public function run_batch( Job $job, int $limit, float $deadline ) {
		$path = $job->workspace . '/posts.ndjson';

		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'convocept_import_workspace', __( 'The import working files are missing. Cancel the import and start again.', 'convocept' ) );
		}

		$size = (int) filesize( $path );

		if ( $job->cursor < $size ) {
			$this->import_lines( $job, $path, $limit, $deadline );
		}

		if ( $job->cursor >= $size && $this->resolve_orphans( $job ) ) {
			$job->status = Job::DONE;
		}

		return $job;
	}

	/**
	 * Import comments from the work file, starting at the job's cursor.
	 *
	 * @since 1.0.0
	 *
	 * @param Job    $job      Running job.
	 * @param string $path     Work file.
	 * @param int    $limit    Maximum comments.
	 * @param float  $deadline Microtime to stop by.
	 * @return void
	 */
	private function import_lines( Job $job, string $path, int $limit, float $deadline ): void {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a large work file in our private import folder.

		if ( ! $handle ) {
			return;
		}

		fseek( $handle, $job->cursor );

		$items = array();
		$read  = 0;

		while ( $read < $limit ) {
			$line = fgets( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets -- See fopen() above.

			if ( false === $line ) {
				break;
			}

			$items[] = array(
				'post' => json_decode( $line, true ),
				'end'  => (int) ftell( $handle ),
			);
			++$read;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- See fopen() above.

		$maps     = $this->maps( $job->workspace );
		$existing = $this->existing_ids( $items, $maps['skipped'] );

		foreach ( $items as $item ) {
			$post = $item['post'];

			if ( is_array( $post ) ) {
				$this->import_one( $job, $post, $maps, $existing );
			} else {
				$job->add( 'invalid' );
			}

			$job->cursor = $item['end'];
			++$job->processed;

			if ( microtime( true ) >= $deadline ) {
				break;
			}
		}
	}

	/**
	 * Import one comment.
	 *
	 * @since 1.0.0
	 *
	 * @param Job                                                                $job      Running job.
	 * @param array<string, mixed>                                               $post     Post from the work file.
	 * @param array{threads: array<string, int>, skipped: array<string, string>} $maps     Workspace maps.
	 * @param array<string, int>                                                 $existing Disqus ID → comment ID, updated in place.
	 * @return void
	 */
	private function import_one( Job $job, array $post, array $maps, array &$existing ): void {
		$dsq_id  = (string) $post['dsq_id'];
		$post_id = (int) ( $maps['threads'][ (string) $post['thread'] ] ?? 0 );

		if ( ! $post_id ) {
			$job->add( 'no_post' );
			return;
		}

		if ( isset( $existing[ $dsq_id ] ) ) {
			$job->add( 'existing' );
			return;
		}

		// Comments Disqus originally imported from this WordPress site.
		if ( preg_match( '/^wp_id=(\d+)$/', (string) $post['id'], $match ) ) {
			$native = get_comment( (int) $match[1] );

			if ( $native && (int) $native->comment_post_ID === $post_id ) {
				add_comment_meta( (int) $native->comment_ID, self::ID_META_KEY, $dsq_id, true );
				$existing[ $dsq_id ] = (int) $native->comment_ID;
				$job->add( 'existing' );
				return;
			}
		}

		$content = $this->content( (string) $post['message'] );

		if ( '' === $content ) {
			$job->add( 'empty' );
			return;
		}

		$parent_dsq = $this->kept_ancestor( (string) $post['parent'], $maps['skipped'] );
		$parent_id  = '' !== $parent_dsq ? (int) ( $existing[ $parent_dsq ] ?? 0 ) : 0;
		$later      = '' !== $parent_dsq && ! $parent_id;

		// A parent on another post can't be a parent here; the reply becomes top-level.
		if ( $parent_id && (int) get_comment( $parent_id )->comment_post_ID !== $post_id ) {
			$parent_id = 0;
		}

		$date_gmt    = $this->gmt_date( (string) $post['created'] );
		$ip          = (string) filter_var( $post['ip'], FILTER_VALIDATE_IP );
		$commentdata = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $this->author_name( $post ),
			'comment_author_email' => (string) sanitize_email( (string) $post['email'] ),
			'comment_author_url'   => '',
			'comment_author_IP'    => $ip,
			'comment_date'         => get_date_from_gmt( $date_gmt ),
			'comment_date_gmt'     => $date_gmt,
			'comment_content'      => $content,
			'comment_agent'        => self::AGENT,
			'comment_approved'     => $post['approved'] ? 1 : 0,
			'comment_type'         => 'comment',
			'comment_parent'       => $parent_id,
			'user_id'              => 0,
			'comment_meta'         => array( self::ID_META_KEY => $dsq_id ),
		);

		if ( $later ) {
			// The parent comes later in the file; fixed up when the import finishes.
			$commentdata['comment_meta'][ self::PARENT_META_KEY ] = $parent_dsq;
		}

		/**
		 * Filters a comment before it is imported from Disqus.
		 *
		 * Return an empty array to skip it. For example, set `user_id` for
		 * authors whose Disqus email matches a site user.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $commentdata Data for wp_insert_comment().
		 * @param array<string, mixed> $post        Disqus post: 'dsq_id', 'thread', 'parent',
		 *                                          'message', 'created', 'name', 'email',
		 *                                          'username', 'ip', 'approved'.
		 */
		$commentdata = (array) apply_filters( 'convocept_disqus_comment_data', $commentdata, $post );

		if ( ! $commentdata ) {
			$job->add( 'filtered' );
			return;
		}

		$comment_id = wp_insert_comment( wp_slash( $commentdata ) );

		if ( ! $comment_id ) {
			$job->add( 'failed' );
			return;
		}

		$existing[ $dsq_id ] = (int) $comment_id;
		$job->add( 'imported' );

		/**
		 * Fires after a comment is imported.
		 *
		 * @since 1.0.0
		 *
		 * @param int                  $comment_id New comment ID.
		 * @param string               $source     Importer ID, e.g. 'disqus'.
		 * @param array<string, mixed> $item       Source item.
		 */
		do_action( 'convocept_comment_imported', (int) $comment_id, $this->id(), $post );
	}

	/**
	 * Find the nearest ancestor that was not skipped as spam or deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $parent_id Disqus parent ID, or ''.
	 * @param array<string, string> $skipped   Skipped Disqus ID → its parent ID.
	 * @return string Disqus ID, or '' for a top-level comment.
	 */
	private function kept_ancestor( string $parent_id, array $skipped ): string {
		$guard = 0;

		// The guard stops a corrupted parent loop from spinning forever.
		while ( '' !== $parent_id && isset( $skipped[ $parent_id ] ) && $guard++ < 100 ) {
			$parent_id = (string) $skipped[ $parent_id ];
		}

		return $guard >= 100 ? '' : $parent_id;
	}

	/**
	 * Look up which Disqus IDs of a batch (and their parents) already exist as comments.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{post: mixed, end: int}> $items   Batch items.
	 * @param array<string, string>                    $skipped Skipped Disqus ID → its parent ID.
	 * @return array<string, int> Disqus ID → comment ID.
	 */
	private function existing_ids( array $items, array $skipped ): array {
		$ids = array();

		foreach ( $items as $item ) {
			if ( is_array( $item['post'] ) ) {
				$ids[] = (string) $item['post']['dsq_id'];
				$ids[] = $this->kept_ancestor( (string) $item['post']['parent'], $skipped );
			}
		}

		return $this->comment_ids_for( $ids );
	}

	/**
	 * Find the comments that hold given Disqus IDs.
	 *
	 * One query for the whole list: `meta_value` isn't indexed, so a query
	 * per ID would scan the meta rows each time. Matches both our own
	 * `_convocept_disqus_id` and the Disqus plugin's `dsq_post_id`.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int|string, mixed> $dsq_ids Disqus IDs (empty values ignored).
	 * @return array<string, int> Disqus ID → comment ID.
	 */
	private function comment_ids_for( array $dsq_ids ): array {
		global $wpdb;

		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $dsq_ids ),
					static function ( string $id ): bool {
						return '' !== $id;
					}
				)
			)
		);

		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Batched lookup during an import; placeholders are built above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value, comment_id FROM {$wpdb->commentmeta} WHERE meta_key IN (%s, %s) AND meta_value IN ({$placeholders})",
				array_merge( array( self::ID_META_KEY, self::SYNC_META_KEY ), $ids )
			)
		);
		// phpcs:enable

		$found = array();

		foreach ( (array) $rows as $row ) {
			$found[ (string) $row->meta_value ] = (int) $row->comment_id;
		}

		return $found;
	}

	/**
	 * Attach replies whose parent was imported after them.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Running job.
	 * @return bool Whether no replies are left to fix.
	 */
	private function resolve_orphans( Job $job ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Import clean-up.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_id, meta_value FROM {$wpdb->commentmeta} WHERE meta_key = %s LIMIT %d",
				self::PARENT_META_KEY,
				self::ORPHANS_PER_BATCH
			)
		);
		// phpcs:enable

		$rows = (array) $rows;

		if ( ! $rows ) {
			return true;
		}

		// One lookup for the whole chunk: meta_value isn't indexed, so per-row queries would each scan the table.
		$parents = $this->comment_ids_for( wp_list_pluck( $rows, 'meta_value' ) );

		foreach ( $rows as $row ) {
			$comment_id = (int) $row->comment_id;
			$comment    = get_comment( $comment_id );
			$parent_id  = (int) ( $parents[ (string) $row->meta_value ] ?? 0 );
			$parent     = $parent_id ? get_comment( $parent_id ) : null;

			if ( $comment && $parent && $parent->comment_post_ID === $comment->comment_post_ID && $parent_id !== $comment_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Changing only the parent; wp_update_comment() would re-filter the content.
				$wpdb->update( $wpdb->comments, array( 'comment_parent' => $parent_id ), array( 'comment_ID' => $comment_id ) );
				clean_comment_cache( $comment_id );
				$job->add( 'reparented' );
			}

			delete_comment_meta( $comment_id, self::PARENT_META_KEY );
		}

		return count( $rows ) < self::ORPHANS_PER_BATCH;
	}

	/**
	 * Turn a Disqus HTML message into comment content.
	 *
	 * Paragraphs and line breaks become blank lines and newlines (core
	 * adds the paragraphs back when displaying), and only the tags core
	 * allows in comments are kept.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Disqus HTML.
	 * @return string
	 */
	private function content( string $message ): string {
		$text = (string) preg_replace( '#<br\s*/?>#i', "\n", $message );
		$text = (string) preg_replace( '#</p>\s*<p[^>]*>#i', "\n\n", $text );
		$text = (string) preg_replace( '#</?p[^>]*>#i', '', $text );

		return trim( wp_kses( $text, 'data' ) );
	}

	/**
	 * Get a display name for a Disqus author.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $post Disqus post.
	 * @return string
	 */
	private function author_name( array $post ): string {
		$name = trim( wp_strip_all_tags( (string) ( $post['name'] ?? '' ) ) );

		if ( '' === $name ) {
			$name = trim( wp_strip_all_tags( (string) ( $post['username'] ?? '' ) ) );
		}

		$name = '' !== $name ? $name : __( 'Anonymous', 'convocept' );

		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 245 ) : substr( $name, 0, 245 );
	}

	/**
	 * Convert a Disqus `createdAt` (UTC) to a MySQL GMT date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $created Date, e.g. 2013-01-01T12:00:00Z.
	 * @return string Y-m-d H:i:s; now when the date can't be read.
	 */
	private function gmt_date( string $created ): string {
		$time = strtotime( '' !== $created && ! preg_match( '/(Z|[+-]\d{2}:?\d{2})$/', $created ) ? $created . 'Z' : $created );

		return gmdate( 'Y-m-d H:i:s', false === $time ? time() : $time );
	}

	/**
	 * Load the workspace maps.
	 *
	 * @since 1.0.0
	 *
	 * @param string $workspace Workspace path.
	 * @return array{threads: array<string, int>, skipped: array<string, string>}
	 */
	private function maps( string $workspace ): array {
		if ( ! isset( $this->maps[ $workspace ] ) ) {
			$this->maps[ $workspace ] = array(
				'threads' => $this->read_json( $workspace . '/threads.json' ),
				'skipped' => $this->read_json( $workspace . '/skipped.json' ),
			);
		}

		return $this->maps[ $workspace ];
	}

	/**
	 * Write an array as JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $path File.
	 * @param array<mixed, mixed> $data Data.
	 * @return void
	 */
	private function write_json( string $path, array $data ): void {
		// Objects, not lists: Disqus IDs are numeric strings that must stay keys.
		file_put_contents( $path, (string) wp_json_encode( (object) $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Work file in our private import folder.
	}

	/**
	 * Read a JSON object with string keys.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path File.
	 * @return array<string, mixed>
	 */
	private function read_json( string $path ): array {
		$json = is_readable( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local work file.
		$data = json_decode( (string) $json, true );
		$map  = array();

		foreach ( is_array( $data ) ? $data : array() as $key => $value ) {
			$map[ (string) $key ] = $value;
		}

		return $map;
	}
}
