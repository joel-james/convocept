<?php
/**
 * Tests for the import REST routes (M9).
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

use Convocept\Import\Job;
use Convocept\Plugin;

/**
 * @covers \Convocept\Rest\ImportEndpoint
 */
class ImportEndpointTest extends RestTestCase {

	/**
	 * Temporary export file.
	 *
	 * @var string
	 */
	private $file = '';

	public function set_up(): void {
		parent::set_up();
		Plugin::instance()->import_runner()->cancel();
	}

	public function tear_down(): void {
		Plugin::instance()->import_runner()->cancel();

		if ( $this->file && file_exists( $this->file ) ) {
			unlink( $this->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture.
		}

		parent::tear_down();
	}

	/**
	 * Start a small Disqus import through the runner.
	 *
	 * @return void
	 */
	private function start_job(): void {
		$post_id    = self::factory()->post->create();
		$this->file = wp_tempnam( 'convocept' ) . '.xml';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents(
			$this->file,
			'<?xml version="1.0"?><disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals">'
			. '<thread dsq:id="1"><id>' . $post_id . '</id></thread>'
			. '<post dsq:id="901"><message>Hello from Disqus</message><createdAt>2015-01-01T00:00:00Z</createdAt><author><name>A</name></author><thread dsq:id="1"/></post>'
			. '</disqus>'
		);

		$this->assertInstanceOf( Job::class, Plugin::instance()->import_runner()->start( 'disqus', $this->file ) );
	}

	public function test_import_needs_import_and_moderation_rights(): void {
		$this->assertSame( 401, $this->dispatch( 'GET', 'import' )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 403, $this->dispatch( 'GET', 'import' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'POST', 'import/batch' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'DELETE', 'import' )->get_status() );
	}

	public function test_status_lists_importers_and_the_job(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$empty = $this->dispatch( 'GET', 'import' )->get_data();
		$this->assertNull( $empty['job'] );
		$this->assertSame( 'disqus', $empty['importers'][0]['id'] );
		$this->assertTrue( $empty['importers'][0]['needs_file'] );

		$this->start_job();
		$job = $this->dispatch( 'GET', 'import' )->get_data()['job'];

		$this->assertSame( 'prepared', $job['status'] );
		$this->assertSame( 1, $job['total'] );
		$this->assertArrayNotHasKey( 'workspace', $job, 'Paths never leave the server.' );
		$this->assertArrayNotHasKey( 'file', $job );
	}

	public function test_batch_runs_the_job_and_delete_clears_it(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->start_job();

		$response = $this->dispatch( 'POST', 'import/batch' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'done', $response->get_data()['job']['status'] );
		$this->assertSame( 1, $response->get_data()['job']['counts']->imported );

		$this->assertNull( $this->dispatch( 'DELETE', 'import' )->get_data()['job'] );
		$this->assertNull( Job::current() );
	}

	public function test_start_rejects_missing_or_wrong_files(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$unknown = $this->dispatch( 'POST', 'import', array( 'importer' => 'nope' ) );
		$missing = $this->dispatch( 'POST', 'import', array( 'importer' => 'disqus' ) );

		$this->assertSame( 400, $unknown->get_status() );
		$this->assertSame( 'convocept_import_file', $missing->get_data()['code'] );

		$request = new \WP_REST_Request( 'POST', '/convocept/v1/import' );
		$request->set_param( 'importer', 'disqus' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'evil.php',
					'tmp_name' => '/tmp/nothing',
					'size'     => 10,
					'error'    => 0,
				),
			)
		);
		$wrong = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $wrong->get_status() );
		$this->assertStringContainsString( '.xml', $wrong->get_data()['message'] );
	}

	public function test_a_second_import_is_refused_while_one_exists(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->start_job();

		$this->assertSame( 409, $this->dispatch( 'POST', 'import', array( 'importer' => 'disqus' ) )->get_status() );
	}
}
