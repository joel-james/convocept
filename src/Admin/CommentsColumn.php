<?php
/**
 * Comments screen column.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Admin;

use Convocept\Comments\Submission;
use Convocept\Comments\ThreadQuery;
use Convocept\Votes\Votes;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Convocept" column to wp-admin's Comments screen.
 *
 * Shows why Convocept held or rejected a comment (the moderation log),
 * plus its votes and pinned state, so moderators decide with context.
 *
 * @since 1.0.0
 */
class CommentsColumn {

	/**
	 * Column key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COLUMN = 'convocept';

	/**
	 * Vote service.
	 *
	 * @since 1.0.0
	 * @var Votes
	 */
	private $votes;

	/**
	 * Create the column.
	 *
	 * @since 1.0.0
	 *
	 * @param Votes $votes Vote service.
	 */
	public function __construct( Votes $votes ) {
		$this->votes = $votes;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_edit-comments_columns', array( $this, 'add_column' ) );
		add_action( 'manage_comments_custom_column', array( $this, 'render' ), 10, 2 );
	}

	/**
	 * Add the column after the comment text.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( $columns ) {
		$columns                 = (array) $columns;
		$columns[ self::COLUMN ] = __( 'Convocept', 'convocept' );

		return $columns;
	}

	/**
	 * Print the column for one comment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column     Column key.
	 * @param int    $comment_id Comment ID.
	 * @return void
	 */
	public function render( $column, $comment_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$comment = get_comment( (int) $comment_id );

		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		$lines = array();

		foreach ( $this->decisions( (int) $comment_id ) as $decision ) {
			$lines[] = sprintf(
				'<strong>%1$s</strong>: %2$s',
				esc_html( $this->verdict_label( $decision['verdict'] ) ),
				esc_html( $decision['reason'] )
			);
		}

		$votes = $this->votes->count( $comment );

		if ( $votes > 0 ) {
			/* translators: %s: Number of upvotes. */
			$lines[] = esc_html( sprintf( _n( '%s upvote', '%s upvotes', $votes, 'convocept' ), number_format_i18n( $votes ) ) );
		}

		if ( '1' === (string) get_comment_meta( (int) $comment_id, ThreadQuery::PINNED_META_KEY, true ) ) {
			$lines[] = esc_html__( 'Pinned', 'convocept' );
		}

		echo $lines ? implode( '<br>', $lines ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part escaped above.
	}

	/**
	 * Read the stored moderation decisions for a comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return array<int, array{verdict: string, reason: string}>
	 */
	private function decisions( int $comment_id ): array {
		$log = json_decode( (string) get_comment_meta( $comment_id, Submission::LOG_META_KEY, true ), true );

		if ( ! is_array( $log ) ) {
			return array();
		}

		$decisions = array();

		foreach ( $log as $entry ) {
			if ( is_array( $entry ) && isset( $entry['verdict'], $entry['reason'] ) ) {
				$decisions[] = array(
					'verdict' => (string) $entry['verdict'],
					'reason'  => (string) $entry['reason'],
				);
			}
		}

		return $decisions;
	}

	/**
	 * Get a readable label for a verdict.
	 *
	 * @since 1.0.0
	 *
	 * @param string $verdict Verdict.
	 * @return string
	 */
	private function verdict_label( string $verdict ): string {
		$labels = array(
			'approve' => __( 'Approved', 'convocept' ),
			'hold'    => __( 'Held', 'convocept' ),
			'spam'    => __( 'Spam', 'convocept' ),
			'trash'   => __( 'Trashed', 'convocept' ),
		);

		return $labels[ $verdict ] ?? $verdict;
	}
}
