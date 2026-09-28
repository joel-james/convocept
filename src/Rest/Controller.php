<?php
/**
 * REST API controller.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Plugin;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Convocept's REST routes under `convocept/v1`.
 *
 * Every route declares a permission callback and a strict argument schema,
 * and every response is marked uncacheable: they either depend on who is
 * asking or change the database.
 *
 * @since 1.0.0
 */
class Controller {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAMESPACE = 'convocept/v1';

	/**
	 * Plugin instance, used to reach services lazily.
	 *
	 * @since 1.0.0
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Create the controller.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register all routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$session    = new SessionEndpoint( $this->plugin->edit_window(), $this->plugin->votes() );
		$comments   = new CommentsEndpoint( $this->plugin );
		$preview    = new PreviewEndpoint( $this->plugin );
		$threads    = new ThreadEndpoint( $this->plugin->renderer() );
		$reactions  = new ReactionsEndpoint( $this->plugin );
		$moderation = new ModerationEndpoint( $this->plugin );
		$subscribe  = new SubscriptionsEndpoint( $this->plugin );
		$settings   = new SettingsEndpoint( $this->plugin->settings() );
		$import     = new ImportEndpoint( $this->plugin );

		register_rest_route(
			self::NAMESPACE,
			'/session',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $session, 'get' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/comments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $threads, 'page' ),
					'permission_callback' => array( $threads, 'can_read_post' ),
					'args'                => $threads->page_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $comments, 'create' ),
					'permission_callback' => array( $comments, 'can_create' ),
					'args'                => $comments->create_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/updates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $threads, 'updates' ),
				'permission_callback' => array( $threads, 'can_read_post' ),
				'args'                => $threads->updates_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)/replies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $threads, 'replies' ),
				'permission_callback' => array( $threads, 'can_read_comment' ),
				'args'                => $threads->replies_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)/context',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $threads, 'context' ),
				'permission_callback' => array( $threads, 'can_read_comment' ),
				'args'                => $threads->comment_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $comments, 'source' ),
					'permission_callback' => array( $comments, 'can_update' ),
					'args'                => $comments->source_args(),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $comments, 'update' ),
					'permission_callback' => array( $comments, 'can_update' ),
					'args'                => $comments->update_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)/reactions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $reactions, 'update' ),
				'permission_callback' => array( $reactions, 'can_react' ),
				'args'                => $reactions->args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)/moderate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $moderation, 'moderate' ),
				'permission_callback' => array( $moderation, 'can_moderate_comment' ),
				'args'                => $moderation->moderate_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/comments/(?P<id>\d+)/pin',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $moderation, 'pin' ),
				'permission_callback' => array( $moderation, 'can_moderate_comment' ),
				'args'                => $moderation->pin_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/pending',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $moderation, 'pending' ),
				'permission_callback' => array( $moderation, 'can_moderate_post' ),
				'args'                => $moderation->pending_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/subscriptions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $subscribe, 'create' ),
				'permission_callback' => '__return_true',
				'args'                => $subscribe->args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $preview, 'create' ),
				'permission_callback' => '__return_true',
				'args'                => $preview->args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $settings, 'get' ),
					'permission_callback' => array( $settings, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $settings, 'save' ),
					'permission_callback' => array( $settings, 'can_manage' ),
					'args'                => $settings->save_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $import, 'status' ),
					'permission_callback' => array( $import, 'can_import' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $import, 'start' ),
					'permission_callback' => array( $import, 'can_import' ),
					'args'                => array(
						'importer' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $import, 'cancel' ),
					'permission_callback' => array( $import, 'can_import' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $import, 'batch' ),
				'permission_callback' => array( $import, 'can_import' ),
			)
		);
	}

	/**
	 * Build an uncacheable response.
	 *
	 * Every Convocept response either depends on the visitor or reflects a
	 * change, so browsers and proxies must never store it.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data   Response data.
	 * @param int                  $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}

	/**
	 * Schema for a Markdown content argument.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $required Whether the argument is required.
	 * @return array<string, mixed>
	 */
	public static function content_arg( bool $required ): array {
		return array(
			'type'      => 'string',
			'required'  => $required,
			'minLength' => 1,
			// Leaves room for the &lt; / &gt; encoding within core's 65,525 limit.
			'maxLength' => 20000,
		);
	}
}
