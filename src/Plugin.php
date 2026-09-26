<?php
/**
 * Main plugin class.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

use Convocept\Assets\FrontAssets;
use Convocept\Comments\EditWindow;
use Convocept\Comments\Sort;
use Convocept\Comments\Submission;
use Convocept\Comments\ThreadQuery;
use Convocept\Database\Migrator;
use Convocept\Integration\BlockTheme;
use Convocept\Integration\ClassicTheme;
use Convocept\Markdown\Parser;
use Convocept\Moderation\Pipeline;
use Convocept\Render\CommentPresenter;
use Convocept\Render\TemplateLoader;
use Convocept\Render\ThreadRenderer;
use Convocept\Rest\Controller;
use Convocept\Security\FormToken;
use Convocept\Security\IpResolver;
use Convocept\Security\RateLimiter;
use Convocept\Settings\Settings;

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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Submissions can arrive on any request type (wp-comments-post.php, REST).
		$this->submission()->register();

		// Theme output only exists outside wp-admin; admin requests skip it entirely.
		if ( ! is_admin() ) {
			( new ClassicTheme( $this->renderer() ) )->register();
			( new BlockTheme( $this->renderer() ) )->register();
			( new FrontAssets( $this->renderer(), $this->edit_window() ) )->register();
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
					new CommentPresenter( $this->markdown() ),
					new FormToken()
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
				return new Submission( $this->ips(), $this->limiter(), new FormToken(), new Pipeline(), $this->edit_window() );
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
