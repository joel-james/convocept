<?php
/**
 * Main plugin class.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

use Convocept\Admin\CommentsColumn;
use Convocept\Admin\DiscussionNotice;
use Convocept\Admin\ImportEntry;
use Convocept\Admin\SettingsPage;
use Convocept\Cli\ImportCommand;
use Convocept\Assets\FrontAssets;
use Convocept\Comments\EditWindow;
use Convocept\Comments\Sort;
use Convocept\Comments\Submission;
use Convocept\Comments\ThreadQuery;
use Convocept\Database\Migrator;
use Convocept\Import\Runner;
use Convocept\Import\Workspace;
use Convocept\Integration\BlockTheme;
use Convocept\Integration\ClassicTheme;
use Convocept\Integration\CommentLinks;
use Convocept\Integration\SeoPlugins;
use Convocept\Markdown\Parser;
use Convocept\Moderation\Pipeline;
use Convocept\Privacy\Privacy;
use Convocept\Render\CommentPresenter;
use Convocept\Render\FragmentCache;
use Convocept\Render\TemplateLoader;
use Convocept\Render\ThreadRenderer;
use Convocept\Rest\Controller;
use Convocept\Schema\StructuredData;
use Convocept\Security\FormToken;
use Convocept\Security\IpResolver;
use Convocept\Security\RateLimiter;
use Convocept\Settings\Settings;
use Convocept\Subscriptions\ManagePage;
use Convocept\Subscriptions\Mailer;
use Convocept\Subscriptions\Notifier;
use Convocept\Subscriptions\Queue;
use Convocept\Subscriptions\Repository;
use Convocept\Subscriptions\SubscribeForm;
use Convocept\Subscriptions\Subscriptions;
use Convocept\Subscriptions\Tokens;
use Convocept\Votes\Votes;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's services into WordPress.
 *
 * Services are created lazily, on first use, so a request only pays for
 * what it uses: a cached page view never builds the REST or admin
 * services, and a REST request never loads theme integration code it
 * does not call.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Services created so far, keyed by class name.
	 *
	 * @since 1.0.0
	 * @var array<string, object>
	 */
	private $services = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the shared instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private so the plugin can only be obtained through instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Register every hook the plugin uses.
	 *
	 * Called once on `plugins_loaded`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// Run pending migrations after an update, where activation hooks don't fire.
		add_action(
			'init',
			static function () {
				( new Migrator() )->maybe_migrate();
			},
			1
		);

		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		Sort::register();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Submissions can arrive on any request type (wp-comments-post.php, REST).
		$this->submission()->register();

		// IP storage applies to every way a comment arrives; exporters are looked up in wp-admin.
		$this->privacy()->register();

		// Votes belong to comments; drop them when a comment is deleted for good.
		add_action( 'deleted_comment', array( $this->votes(), 'delete_for_comment' ) );

		// Subscriptions: forms and notifications on every request type, the page on the front end.
		$this->subscribe_form()->register();
		$this->notifier()->register();

		// Comment links appear everywhere: redirects, wp-admin, emails, feeds.
		( new CommentLinks( $this->renderer() ) )->register();

		if ( is_admin() ) {
			( new CommentsColumn( $this->votes() ) )->register();
			( new SettingsPage( $this->settings() ) )->register();
			( new DiscussionNotice( $this->settings() ) )->register();
			// Importers exist only in wp-admin, WP-CLI and the import REST routes; front-end pages never load them.
			( new ImportEntry( $this->import_runner() ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command( 'convocept import', new ImportCommand( $this->import_runner() ) );
		}

		// Theme output only exists outside wp-admin; admin requests skip it entirely.
		if ( ! is_admin() ) {
			( new ClassicTheme( $this->renderer() ) )->register();
			( new BlockTheme( $this->renderer() ) )->register();
			$this->front_assets()->register();
			$this->manage_page()->register();
			$this->structured_data()->register();
			( new SeoPlugins( $this->structured_data() ) )->register();
		}

		/**
		 * Fires after Convocept has registered its hooks.
		 *
		 * Add-ons should register their own hooks here.
		 *
		 * @since 1.0.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'convocept_loaded', $this );
	}

	/**
	 * Register the REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		( new Controller( $this ) )->register();
	}

	/**
	 * Register Convocept's public query variables.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $vars Public query variables.
	 * @return string[]
	 */
	public function register_query_vars( $vars ) {
		$vars[] = Sort::QUERY_VAR;
		$vars[] = ThreadRenderer::EXPAND_QUERY_VAR;

		return $vars;
	}

	/**
	 * Get the settings service.
	 *
	 * @since 1.0.0
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->service(
			Settings::class,
			static function () {
				return new Settings();
			}
		);
	}

	/**
	 * Get the thread renderer.
	 *
	 * @since 1.0.0
	 *
	 * @return ThreadRenderer
	 */
	public function renderer(): ThreadRenderer {
		return $this->service(
			ThreadRenderer::class,
			function () {
				return new ThreadRenderer(
					$this->settings(),
					new TemplateLoader(),
					new ThreadQuery(),
					new CommentPresenter( $this->markdown(), $this->votes(), $this->settings() ),
					new FormToken(),
					new FragmentCache()
				);
			}
		);
	}

	/**
	 * Get the Markdown parser.
	 *
	 * @since 1.0.0
	 *
	 * @return Parser
	 */
	public function markdown(): Parser {
		return $this->service(
			Parser::class,
			static function () {
				return new Parser();
			}
		);
	}

	/**
	 * Get the edit window service.
	 *
	 * @since 1.0.0
	 *
	 * @return EditWindow
	 */
	public function edit_window(): EditWindow {
		return $this->service(
			EditWindow::class,
			function () {
				return new EditWindow( $this->settings() );
			}
		);
	}

	/**
	 * Get the IP resolver.
	 *
	 * @since 1.0.0
	 *
	 * @return IpResolver
	 */
	public function ips(): IpResolver {
		return $this->service(
			IpResolver::class,
			function () {
				return new IpResolver( $this->settings() );
			}
		);
	}

	/**
	 * Get the rate limiter.
	 *
	 * @since 1.0.0
	 *
	 * @return RateLimiter
	 */
	public function limiter(): RateLimiter {
		return $this->service(
			RateLimiter::class,
			static function () {
				return new RateLimiter();
			}
		);
	}

	/**
	 * Get the vote service.
	 *
	 * @since 1.0.0
	 *
	 * @return Votes
	 */
	public function votes(): Votes {
		return $this->service(
			Votes::class,
			function () {
				return new Votes( $this->ips() );
			}
		);
	}

	/**
	 * Get the comment submission service.
	 *
	 * @since 1.0.0
	 *
	 * @return Submission
	 */
	public function submission(): Submission {
		return $this->service(
			Submission::class,
			function () {
				return new Submission(
					$this->ips(),
					$this->limiter(),
					new FormToken(),
					new Pipeline(),
					$this->edit_window(),
					function ( int $post_id ): bool {
						return $this->renderer()->is_enabled_for( $post_id );
					}
				);
			}
		);
	}

	/**
	 * Get the subscription service.
	 *
	 * @since 1.0.0
	 *
	 * @return Subscriptions
	 */
	public function subscriptions(): Subscriptions {
		return $this->service(
			Subscriptions::class,
			function () {
				return new Subscriptions( new Repository(), new Tokens(), new Mailer(), new TemplateLoader(), $this->settings(), $this->limiter() );
			}
		);
	}

	/**
	 * Get the subscribe forms.
	 *
	 * @since 1.0.0
	 *
	 * @return SubscribeForm
	 */
	public function subscribe_form(): SubscribeForm {
		return $this->service(
			SubscribeForm::class,
			function () {
				return new SubscribeForm( $this->subscriptions(), new TemplateLoader(), $this->renderer(), $this->limiter(), $this->ips() );
			}
		);
	}

	/**
	 * Get the notifier.
	 *
	 * @since 1.0.0
	 *
	 * @return Notifier
	 */
	public function notifier(): Notifier {
		return $this->service(
			Notifier::class,
			function () {
				return new Notifier(
					$this->subscriptions(),
					new Queue( $this->settings() ),
					new Mailer(),
					new TemplateLoader(),
					$this->renderer(),
					new CommentPresenter( $this->markdown(), $this->votes(), $this->settings() )
				);
			}
		);
	}

	/**
	 * Get the front-end assets service.
	 *
	 * @since 1.0.0
	 *
	 * @return FrontAssets
	 */
	public function front_assets(): FrontAssets {
		return $this->service(
			FrontAssets::class,
			function () {
				return new FrontAssets( $this->renderer(), $this->edit_window(), $this->settings() );
			}
		);
	}

	/**
	 * Get the subscription page.
	 *
	 * @since 1.0.0
	 *
	 * @return ManagePage
	 */
	public function manage_page(): ManagePage {
		return $this->service(
			ManagePage::class,
			function () {
				return new ManagePage( $this->subscriptions(), new TemplateLoader(), $this->settings(), $this->front_assets() );
			}
		);
	}

	/**
	 * Get the structured data service.
	 *
	 * @since 1.0.0
	 *
	 * @return StructuredData
	 */
	public function structured_data(): StructuredData {
		return $this->service(
			StructuredData::class,
			function () {
				return new StructuredData(
					$this->settings(),
					$this->renderer(),
					new CommentPresenter( $this->markdown(), $this->votes(), $this->settings() ),
					$this->votes(),
					new FragmentCache()
				);
			}
		);
	}

	/**
	 * Get the privacy service.
	 *
	 * @since 1.0.0
	 *
	 * @return Privacy
	 */
	public function privacy(): Privacy {
		return $this->service(
			Privacy::class,
			function () {
				return new Privacy( $this->settings(), $this->subscriptions(), $this->votes() );
			}
		);
	}

	/**
	 * Get the import runner (wp-admin and WP-CLI only).
	 *
	 * @since 1.0.0
	 *
	 * @return Runner
	 */
	public function import_runner(): Runner {
		return $this->service(
			Runner::class,
			function () {
				return new Runner( new Workspace(), $this->privacy() );
			}
		);
	}

	/**
	 * Get a service, creating it on first use.
	 *
	 * @since 1.0.0
	 *
	 * @template T of object
	 * @param string   $id      Service class name.
	 * @param callable $factory Creates the service.
	 * @return object
	 *
	 * @phpstan-param class-string<T> $id
	 * @phpstan-param callable(): T $factory
	 * @phpstan-return T
	 */
	private function service( string $id, callable $factory ) {
		if ( ! isset( $this->services[ $id ] ) ) {
			$this->services[ $id ] = $factory();
		}

		return $this->services[ $id ];
	}
}
