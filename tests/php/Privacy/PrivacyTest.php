<?php
/**
 * Tests for the privacy tools.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Privacy;

use Convocept\Import\Disqus\DisqusImporter;
use Convocept\Import\Runner;
use Convocept\Import\Workspace;
use Convocept\Plugin;
use Convocept\Privacy\Privacy;
use Convocept\Subscriptions\Repository;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Privacy\Privacy
 * @covers \Convocept\Votes\Votes::voter_for_user
 * @covers \Convocept\Votes\Votes::for_voter
 * @covers \Convocept\Votes\Votes::delete_for_voter
 */
class PrivacyTest extends TestCase {

	/**
	 * Reset settings after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'convocept_settings' );
		Plugin::instance()->settings()->flush();
		parent::tear_down();
	}

	/**
	 * Save one setting.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private function set( string $key, $value ): void {
		update_option( 'convocept_settings', array( $key => $value ) );
		Plugin::instance()->settings()->flush();
	}

	/**
	 * The privacy service.
	 *
	 * @return Privacy
	 */
	private function privacy(): Privacy {
		return Plugin::instance()->privacy();
	}

	public function test_ip_setting_decides_what_is_stored(): void {
		$privacy = $this->privacy();

		$this->assertSame( 0, has_action( 'wp_insert_comment', array( $privacy, 'apply_ip_setting' ) ) );
		$this->assertFalse( has_filter( 'pre_comment_user_ip', array( $privacy, 'stored_ip' ) ), 'Core checks must see the full address.' );
		$this->assertSame( '203.0.113.77', $privacy->stored_ip( '203.0.113.77' ), 'Full address by default.' );

		$this->set( 'commenter_ip', 'anonymize' );
		$this->assertSame( '203.0.113.0', $privacy->stored_ip( '203.0.113.77' ) );
		$this->assertSame( '2001:db8::', $privacy->stored_ip( '2001:db8::ff00:42:8329' ) );

		$this->set( 'commenter_ip', 'none' );
		$this->assertSame( '', $privacy->stored_ip( '203.0.113.77' ) );
	}

	public function test_ip_setting_applies_to_new_comments(): void {
		$this->set( 'commenter_ip', 'anonymize' );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.23';

		$id = wp_new_comment(
			array(
				'comment_post_ID'      => self::factory()->post->create(),
				'comment_author'       => 'Reader',
				'comment_author_email' => 'reader@example.com',
				'comment_author_url'   => '',
				'comment_content'      => 'Hello there.',
				'comment_type'         => 'comment',
			),
			true
		);

		$this->assertIsInt( $id );
		$this->assertSame( '198.51.100.0', get_comment( $id )->comment_author_IP );
	}

	public function test_no_stored_ip_keeps_guests_apart_for_the_flood_check(): void {
		$this->set( 'commenter_ip', 'none' );
		$post_id = self::factory()->post->create();
		$ids     = array();

		foreach ( array( 'one', 'two' ) as $index => $name ) {
			$ids[] = wp_new_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => $name,
					'comment_author_email' => $name . '@example.com',
					'comment_author_url'   => '',
					'comment_author_IP'    => '203.0.113.' . ( $index + 1 ),
					'comment_content'      => 'Comment from ' . $name,
					'comment_type'         => 'comment',
				),
				true
			);
		}

		$this->assertIsInt( $ids[0] );
		$this->assertIsInt( $ids[1], 'A second guest a moment later is not flood.' );
		$this->assertSame( '', get_comment( $ids[1] )->comment_author_IP );
	}

	public function test_moderation_list_ip_entries_match_the_full_address(): void {
		$this->set( 'commenter_ip', 'anonymize' );
		update_option( 'moderation_keys', '198.51.100.23' );

		$id = wp_new_comment(
			array(
				'comment_post_ID'      => self::factory()->post->create(),
				'comment_author'       => 'Reader',
				'comment_author_email' => 'listed@example.com',
				'comment_author_url'   => '',
				'comment_author_IP'    => '198.51.100.23',
				'comment_content'      => 'Held by address.',
				'comment_type'         => 'comment',
			),
			true
		);

		delete_option( 'moderation_keys' );
		$this->assertIsInt( $id );
		$this->assertSame( '0', get_comment( $id )->comment_approved );
		$this->assertSame( '198.51.100.0', get_comment( $id )->comment_author_IP );
	}

	public function test_ip_setting_applies_to_imports(): void {
		$this->set( 'commenter_ip', 'none' );
		$post_id = self::factory()->post->create();
		$file    = get_temp_dir() . 'convocept-privacy-' . wp_generate_password( 8, false ) . '.xml';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents(
			$file,
			'<?xml version="1.0"?><disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals">'
			. '<thread dsq:id="1"><id>' . $post_id . '</id><link>http://example.invalid/</link></thread>'
			. '<post dsq:id="900001"><message><![CDATA[Imported]]></message><createdAt>2015-03-01T12:00:00Z</createdAt>'
			. '<isDeleted>false</isDeleted><isSpam>false</isSpam><author><name>R</name><isAnonymous>true</isAnonymous></author>'
			. '<ipAddress>203.0.113.5</ipAddress><thread dsq:id="1" /></post></disqus>'
		);

		$runner = new Runner( new Workspace(), $this->privacy() );
		$runner->start( 'disqus', $file );

		do {
			$job = $runner->next_batch( 100, 10 );
		} while ( ! is_wp_error( $job ) && 'done' !== $job->status );

		$comments = get_comments(
			array(
				'meta_key'   => DisqusImporter::ID_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Test.
				'meta_value' => '900001', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Test.
			)
		);

		$this->assertCount( 1, $comments );
		$this->assertSame( '', $comments[0]->comment_author_IP );
	}

	public function test_exporters_and_erasers_are_registered(): void {
		$privacy   = $this->privacy();
		$exporters = $privacy->register_exporters( array() );
		$erasers   = $privacy->register_erasers( array() );

		$this->assertSame( 10, has_filter( 'wp_privacy_personal_data_exporters', array( $privacy, 'register_exporters' ) ) );
		$this->assertSame( 10, has_filter( 'wp_privacy_personal_data_erasers', array( $privacy, 'register_erasers' ) ) );

		$this->assertArrayHasKey( 'convocept-subscriptions', $exporters );
		$this->assertArrayHasKey( 'convocept-votes', $exporters );
		$this->assertArrayHasKey( 'convocept-subscriptions', $erasers );
		$this->assertArrayHasKey( 'convocept-votes', $erasers );
	}

	public function test_subscriptions_are_exported_and_erased_per_address(): void {
		$repository = new Repository();
		$post_id    = self::factory()->post->create( array( 'post_title' => 'Privacy post' ) );

		$repository->upsert( 'Reader@Example.com', $post_id, 0, Repository::ACTIVE );
		$repository->upsert( 'reader@example.com', 0, 0, Repository::PENDING );
		$repository->upsert( 'other@example.com', $post_id, 0, Repository::ACTIVE );

		$export = $this->privacy()->export_subscriptions( 'reader@example.com' );

		$this->assertTrue( $export['done'] );
		$this->assertCount( 2, $export['data'] );
		$values = wp_list_pluck( $export['data'][0]['data'], 'value', 'name' );
		$this->assertSame( 'Reader@Example.com', $values['Email'] );
		$this->assertSame( 'All new comments on “Privacy post”', $values['Follows'] );
		$this->assertSame( 'Active', $values['Status'] );
		$this->assertSame( get_permalink( $post_id ), $values['Post'] );
		$this->assertSame( 'Waiting for confirmation', wp_list_pluck( $export['data'][1]['data'], 'value', 'name' )['Status'] );

		$erased = $this->privacy()->erase_subscriptions( 'READER@example.com' );

		$this->assertTrue( $erased['items_removed'] );
		$this->assertTrue( $erased['done'] );
		$this->assertSame( array(), $repository->for_email( $repository->hash_email( 'reader@example.com' ) ) );
		$this->assertCount( 1, $repository->for_email( $repository->hash_email( 'other@example.com' ) ), 'Other addresses are kept.' );
		$this->assertFalse( $this->privacy()->erase_subscriptions( 'reader@example.com' )['items_removed'] );
	}

	public function test_a_users_votes_are_exported_and_erased(): void {
		$votes   = Plugin::instance()->votes();
		$post_id = self::factory()->post->create();
		$first   = $this->comment( $post_id );
		$second  = $this->comment( $post_id );
		$user_id = self::factory()->user->create( array( 'user_email' => 'voter@example.com' ) );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
		$votes->set( get_comment( $first ), true );

		wp_set_current_user( $user_id );
		$votes->set( get_comment( $first ), true );
		$votes->set( get_comment( $second ), true );
		wp_set_current_user( 0 );

		$export = $this->privacy()->export_votes( 'voter@example.com' );

		$this->assertTrue( $export['done'] );
		$this->assertCount( 2, $export['data'] );
		$this->assertSame( get_comment_link( $first ), $export['data'][0]['data'][0]['value'] );

		$this->assertSame( array(), $this->privacy()->export_votes( 'nobody@example.com' )['data'], 'Guests cannot be found by address.' );

		$erased = $this->privacy()->erase_votes( 'voter@example.com' );

		$this->assertTrue( $erased['items_removed'] );
		$this->assertTrue( $erased['done'] );
		$this->assertSame( 1, $votes->count( get_comment( $first ) ), 'The guest vote stays and the count is refreshed.' );
		$this->assertSame( 0, $votes->count( get_comment( $second ) ) );
		$this->assertSame( array(), $votes->for_voter( $votes->voter_for_user( $user_id ), 10 ) );
	}

	public function test_policy_text_follows_the_ip_setting(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		set_current_screen( 'options-privacy' );
		$this->set( 'commenter_ip', 'none' );

		$GLOBALS['wp_current_filter'][] = 'admin_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core only accepts policy text during admin_init.
		$this->privacy()->add_policy_text();
		array_pop( $GLOBALS['wp_current_filter'] );

		$text = \WP_Privacy_Policy_Content::get_suggested_policy_text();
		$ours = wp_list_filter( $text, array( 'plugin_name' => 'Convocept' ) );

		$this->assertCount( 1, $ours );
		$policy = (string) array_values( $ours )[0]['policy_text'];
		$this->assertStringContainsString( 'is not stored with your comment', $policy );
		$this->assertStringContainsString( 'subscribe to comments', $policy );

		set_current_screen( 'front' );
	}
}
