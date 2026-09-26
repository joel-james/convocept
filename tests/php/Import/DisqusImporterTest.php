<?php
/**
 * Tests for the Disqus importer (M8).
 *
 * @package Convocept
 */

namespace Convocept\Tests\Import;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.SlowDBQuery -- Test fixtures on local temporary files; small test queries.

use Convocept\Import\Disqus\DisqusImporter;
use Convocept\Import\Disqus\Parser;
use Convocept\Import\Job;
use Convocept\Import\Runner;
use Convocept\Import\Workspace;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Import\Disqus\DisqusImporter
 * @covers \Convocept\Import\Disqus\Parser
 * @covers \Convocept\Import\Runner
 * @covers \Convocept\Import\Job
 * @covers \Convocept\Import\Workspace
 */
class DisqusImporterTest extends TestCase {

	/**
	 * Post the fixture's thread 2001 maps to by identifier.
	 *
	 * @var int
	 */
	private $post_a;

	/**
	 * Post the fixture's thread 2002 maps to by link.
	 *
	 * @var int
	 */
	private $post_b;

	/**
	 * Native comment Disqus originally took from WordPress (`wp_id=`).
	 *
	 * @var int
	 */
	private $native;

	/**
	 * Temporary files to delete after the test.
	 *
	 * @var string[]
	 */
	private $files = array();

	public function set_up(): void {
		parent::set_up();

		$this->post_a = self::factory()->post->create( array( 'post_title' => 'Post A' ) );
		$this->post_b = self::factory()->post->create( array( 'post_title' => 'Post B' ) );
		$this->native = $this->comment( $this->post_a, array( 'comment_content' => 'Native comment' ) );

		// A comment the official Disqus plugin already synced.
		$synced = $this->comment( $this->post_b, array( 'comment_content' => 'Synced' ) );
		add_comment_meta( $synced, 'dsq_post_id', '3010' );

		Job::clear();
	}

	public function tear_down(): void {
		( new Runner( new Workspace() ) )->cancel();

		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		parent::tear_down();
	}

	/**
	 * Write the fixture with this test's post IDs.
	 *
	 * @param bool   $gzip     Whether to gzip it.
	 * @param string $contents Contents to write instead of the fixture.
	 * @return string Path.
	 */
	private function export( bool $gzip = false, string $contents = '' ): string {
		if ( '' === $contents ) {
			$contents = strtr(
				(string) file_get_contents( dirname( __DIR__ ) . '/fixtures/disqus/export.xml' ),
				array(
					'{POST_A_ID}'         => (string) $this->post_a,
					'{POST_A_GUID}'       => get_post( $this->post_a )->guid,
					'{POST_B_LINK}'       => get_permalink( $this->post_b ),
					'{NATIVE_COMMENT_ID}' => (string) $this->native,
				)
			);
		}

		$path = wp_tempnam( 'convocept-disqus' ) . ( $gzip ? '.xml.gz' : '.xml' );
		file_put_contents( $path, $gzip ? gzencode( $contents ) : $contents );
		$this->files[] = $path;

		return $path;
	}

	/**
	 * Run the current job to the end.
	 *
	 * @param Runner $runner Runner.
	 * @param int    $batch  Items per batch.
	 * @return Job
	 */
	private function run_all( Runner $runner, int $batch = 2 ): Job {
		for ( $i = 0; $i < 1000; $i++ ) {
			$job = $runner->next_batch( $batch, 60 );
			$this->assertInstanceOf( Job::class, $job );

			if ( Job::DONE === $job->status ) {
				return $job;
			}
		}

		$this->fail( 'Import did not finish.' );
	}

	/**
	 * Find the comment imported for a Disqus post ID.
	 *
	 * @param string $dsq_id Disqus ID.
	 * @return \WP_Comment|null
	 */
	private function imported( string $dsq_id ): ?\WP_Comment {
		$comments = get_comments(
			array(
				'meta_key'   => DisqusImporter::ID_META_KEY,
				'meta_value' => $dsq_id,
				'status'     => 'all',
			)
		);

		return $comments ? $comments[0] : null;
	}

	public function test_dry_run_counts_and_samples_without_importing(): void {
		$before = (int) get_comments( array( 'count' => true ) );
		$runner = new Runner( new Workspace() );

		$job = $runner->start( 'disqus', $this->export() );

		$this->assertInstanceOf( Job::class, $job );
		$this->assertSame( Job::PREPARED, $job->status );
		$this->assertSame( 8, $job->total );
		$this->assertSame( 1, $job->count( 'spam' ) );
		$this->assertSame( 1, $job->count( 'deleted' ) );
		$this->assertSame( 7, $job->count( 'matched' ) );
		$this->assertSame( 1, $job->count( 'unmatched' ) );
		$this->assertSame( 2, $job->count( 'threads_matched' ) );
		$this->assertSame( 1, $job->count( 'threads_unmatched' ) );
		$this->assertCount( 5, $job->sample );
		$this->assertSame( 'Ada Lovelace', $job->sample[0]['author'] );
		$this->assertSame( 'Post A', $job->sample[0]['post'] );
		$this->assertSame( $before, (int) get_comments( array( 'count' => true ) ), 'Dry run imports nothing.' );

		$this->assertFileExists( $job->workspace . '/.htaccess' );
		$this->assertFileExists( $job->workspace . '/index.php' );
		$this->assertSame( 32, strlen( basename( $job->workspace ) ), 'Unguessable folder name.' );
	}

	public function test_import_maps_threads_parents_and_fields(): void {
		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export() );

		$job = $this->run_all( $runner );

		$this->assertSame( 5, $job->count( 'imported' ) );
		$this->assertSame( 2, $job->count( 'existing' ), 'wp_id= and dsq_post_id comments are recognised.' );
		$this->assertSame( 1, $job->count( 'no_post' ) );
		$this->assertSame( 1, $job->count( 'reparented' ) );
		$this->assertSame( 8, $job->processed );
		$this->assertSame( '', $job->workspace, 'Working files are deleted.' );

		$ada = $this->imported( '3001' );
		$this->assertSame( $this->post_a, (int) $ada->comment_post_ID );
		$this->assertSame( 'Ada Lovelace', $ada->comment_author );
		$this->assertSame( 'ada@example.com', $ada->comment_author_email );
		$this->assertSame( '203.0.113.5', $ada->comment_author_IP );
		$this->assertSame( '2015-03-01 12:00:00', $ada->comment_date_gmt );
		$this->assertSame( 'Disqus import', $ada->comment_agent );
		$this->assertSame( '1', $ada->comment_approved );
		$this->assertStringContainsString( '<b>bold</b>', $ada->comment_content );
		$this->assertStringContainsString( '<a href="https://example.org"', $ada->comment_content );
		$this->assertStringContainsString( "a link</a>.\n\nSecond paragraph\nnew line.", $ada->comment_content );
		$this->assertStringNotContainsString( '<script', $ada->comment_content );
		$this->assertStringNotContainsString( '<p>', $ada->comment_content );

		$grace = $this->imported( '3002' );
		$this->assertSame( (int) $ada->comment_ID, (int) $grace->comment_parent );
		$this->assertSame( '', $grace->comment_author_IP, 'Invalid IPs are dropped.' );

		$this->assertNull( $this->imported( '3003' ), 'Spam is skipped.' );
		$this->assertNull( $this->imported( '3004' ), 'Deleted comments are skipped.' );
		$this->assertSame( (int) $ada->comment_ID, (int) $this->imported( '3005' )->comment_parent, 'Reply to a deleted comment moves to its grandparent.' );

		$early = $this->imported( '3006' );
		$this->assertSame( (int) $this->imported( '3007' )->comment_ID, (int) $early->comment_parent, 'Parent that came later is attached at the end.' );
		$this->assertSame( '', get_comment_meta( (int) $early->comment_ID, DisqusImporter::PARENT_META_KEY, true ) );
		$this->assertSame( $this->post_b, (int) $early->comment_post_ID );

		$this->assertNull( $this->imported( '3008' ), 'Comments on unknown pages are skipped.' );
		$this->assertSame( '3009', get_comment_meta( $this->native, DisqusImporter::ID_META_KEY, true ) );

		$this->assertSame( '4', get_comments_number( $this->post_a ), 'Comment counts are updated.' );
		$this->assertSame( '3', get_comments_number( $this->post_b ) );
	}

	public function test_running_the_same_export_again_creates_no_duplicates(): void {
		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export() );
		$this->run_all( $runner );
		$runner->cancel();
		$total = (int) get_comments( array( 'count' => true ) );

		$runner->start( 'disqus', $this->export() );
		$job = $this->run_all( $runner, 100 );

		$this->assertSame( 0, $job->count( 'imported' ) );
		$this->assertSame( 7, $job->count( 'existing' ) );
		$this->assertSame( $total, (int) get_comments( array( 'count' => true ) ) );
	}

	public function test_imports_never_notify_subscribers(): void {
		$before = get_option( 'convocept_notification_jobs', array() );
		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export() );
		$this->run_all( $runner );

		$this->assertSame( $before, get_option( 'convocept_notification_jobs', array() ) );
		$this->assertFalse( has_filter( 'convocept_should_notify', '__return_false' ), 'Only suppressed during batches.' );
	}

	public function test_gzipped_exports_are_read(): void {
		$job = ( new Runner( new Workspace() ) )->start( 'disqus', $this->export( true ) );

		$this->assertInstanceOf( Job::class, $job );
		$this->assertSame( 8, $job->total );
	}

	public function test_invalid_files_are_rejected_and_leave_nothing_behind(): void {
		$runner = new Runner( new Workspace() );
		$root   = ( new Workspace() )->root();
		$before = is_dir( $root ) ? count( (array) glob( $root . '/*', GLOB_ONLYDIR ) ) : 0;

		$wrong = $runner->start( 'disqus', $this->export( false, '<?xml version="1.0"?><rss><channel/></rss>' ) );
		$this->assertWPError( $wrong );
		$this->assertSame( 'convocept_import_invalid', $wrong->get_error_code() );

		$broken = $runner->start( 'disqus', $this->export( false, '<?xml version="1.0"?><disqus xmlns="http://disqus.com"><post>' ) );
		$this->assertWPError( $broken );

		$this->assertNull( Job::current() );
		$this->assertSame( $before, count( (array) glob( $root . '/*', GLOB_ONLYDIR ) ), 'Failed runs delete their workspace.' );
	}

	public function test_external_entities_are_never_expanded(): void {
		$xml = '<?xml version="1.0"?><!DOCTYPE disqus [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
			. '<disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals">'
			. '<thread dsq:id="1"><id>' . $this->post_a . '</id></thread>'
			. '<post dsq:id="9"><message>&xxe;</message><createdAt>2015-01-01T00:00:00Z</createdAt><isDeleted>false</isDeleted><isSpam>false</isSpam><author><name>X</name></author><thread dsq:id="1"/></post>'
			. '</disqus>';

		$posts = array();
		( new Parser() )->parse(
			$this->export( false, $xml ),
			static function () {},
			static function ( $post ) use ( &$posts ) {
				$posts[] = $post;
			}
		);

		foreach ( $posts as $post ) {
			$this->assertStringNotContainsString( 'root:', $post['message'] );
		}
	}

	public function test_only_one_import_at_a_time(): void {
		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export() );

		$second = $runner->start( 'disqus', $this->export() );

		$this->assertWPError( $second );
		$this->assertSame( 'convocept_import_busy', $second->get_error_code() );
	}

	public function test_batches_stop_at_the_deadline_and_resume(): void {
		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export() );
		$importer = $runner->importer( 'disqus' );

		$job = $importer->run_batch( Job::current(), 100, microtime( true ) - 1 );

		$this->assertSame( 1, $job->processed, 'At least one item, then stop.' );
		$this->assertGreaterThan( 0, $job->cursor );
		$this->assertSame( Job::PREPARED, $job->status );
	}

	public function test_threads_resolve_by_identifier_link_and_moved_domain(): void {
		$importer = new DisqusImporter( new Parser() );
		$guid     = get_post( $this->post_a )->guid;

		$this->assertSame( $this->post_a, $importer->resolve_post( array( 'identifier' => $this->post_a . ' ' . $guid ) ) );
		$this->assertSame( 0, $importer->resolve_post( array( 'identifier' => $this->post_a . ' http://other.example/?p=1' ) ), 'A guid mismatch is a different site.' );
		$this->assertSame( $this->post_b, $importer->resolve_post( array( 'link' => get_permalink( $this->post_b ) ) ) );
		$this->assertSame( $this->post_b, $importer->resolve_post( array( 'link' => 'https://old-domain.example/?p=' . $this->post_b ) ), 'Old domain, same path.' );
		$this->assertSame( 0, $importer->resolve_post( array( 'link' => 'https://gone.example/nothing/' ) ) );

		add_filter(
			'convocept_disqus_thread_post_id',
			function () {
				return $this->post_b;
			}
		);
		$this->assertSame( $this->post_b, $importer->resolve_post( array( 'link' => 'https://gone.example/nothing/' ) ) );
	}

	public function test_parent_on_another_post_makes_a_top_level_comment(): void {
		$xml = '<?xml version="1.0"?><disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals">'
			. '<thread dsq:id="1"><id>' . $this->post_a . '</id></thread><thread dsq:id="2"><id>' . $this->post_b . '</id></thread>'
			. '<post dsq:id="701"><message>Parent on A</message><createdAt>2015-01-01T00:00:00Z</createdAt><author><name>A</name></author><thread dsq:id="1"/></post>'
			. '<post dsq:id="702"><message>Reply on B</message><createdAt>2015-01-02T00:00:00Z</createdAt><author><name>B</name></author><thread dsq:id="2"/><parent dsq:id="701"/></post>'
			. '</disqus>';

		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export( false, $xml ) );
		$job   = $this->run_all( $runner );
		$reply = $this->imported( '702' );

		$this->assertSame( 2, $job->count( 'imported' ) );
		$this->assertSame( 0, (int) $reply->comment_parent );
		$this->assertSame( '', get_comment_meta( (int) $reply->comment_ID, DisqusImporter::PARENT_META_KEY, true ), 'Not treated as a parent that comes later.' );
	}

	public function test_large_export_imports_in_bounded_batches(): void {
		$count = 5000;
		$xml   = '<?xml version="1.0" encoding="utf-8"?><disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals">';

		$xml .= '<thread dsq:id="1"><id>' . $this->post_a . '</id></thread>';

		// IDs from 100001 up, clear of the fixture comments made in set_up().
		for ( $i = 100001; $i <= 100000 + $count; $i++ ) {
			$parent = 0 === $i % 3 ? '<parent dsq:id="' . ( $i - 1 ) . '"/>' : '';
			$xml   .= '<post dsq:id="' . $i . '"><id/><message><![CDATA[<p>Comment ' . $i . '</p>]]></message><createdAt>2015-01-01T00:00:00Z</createdAt><isDeleted>false</isDeleted><isSpam>false</isSpam><author><name>Reader ' . $i . '</name><email>r' . $i . '@example.com</email><isAnonymous>false</isAnonymous></author><thread dsq:id="1"/>' . $parent . '</post>';
		}

		$xml   .= '</disqus>';
		$runner = new Runner( new Workspace() );
		$runner->start( 'disqus', $this->export( false, $xml ) );

		$batches = 0;

		do {
			$job = $runner->next_batch( 1000, 60 );
			++$batches;
		} while ( Job::DONE !== $job->status && $batches < 20 );

		$this->assertSame( Job::DONE, $job->status );
		$this->assertSame( $count, $job->count( 'imported' ), (string) wp_json_encode( $job->counts ) );
		$this->assertLessThanOrEqual( 6, $batches );
		$this->assertSame( (int) $this->imported( '100001' )->comment_ID, (int) $this->imported( '100002' )->comment_parent );
	}
}
