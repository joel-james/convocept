<?php
/**
 * Settings service.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

defined( 'ABSPATH' ) || exit;

/**
 * Stores plugin settings and renders their admin UI.
 *
 * All options live in a single autoloaded array option rather than one row
 * per setting, which keeps the options table clean and makes uninstall a
 * single delete. The UI is added as a section on Settings > Discussion so
 * comment configuration stays in the place users already look for it.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Option name holding every plugin setting.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'convocept_settings';

	/**
	 * Settings cached for the current request.
	 *
	 * Populated on first read so repeated get() calls during a page render
	 * do not repeatedly parse the option.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Get the default value for every setting.
	 *
	 * Also acts as the canonical list of valid keys: sanitize() builds its
	 * result from these defaults, so unknown keys are discarded.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Default settings.
	 *
	 *     @type bool   $enabled       Whether the app replaces native comments.
	 *     @type int    $edit_window   Minutes an author may edit; 0 disables.
	 *     @type string $live_updates  One of 'off', 'polling', 'heartbeat'.
	 *     @type int    $poll_interval Seconds between polls.
	 *     @type bool   $load_styles   Whether to enqueue the default stylesheet.
	 * }
	 */
	public function defaults() {
		return array(
			'enabled'       => true,
			'edit_window'   => 5,
			'live_updates'  => 'polling',
			'poll_interval' => 30,
			'load_styles'   => true,
		);
	}

	/**
	 * Get all settings, with defaults filled in for missing keys.
	 *
	 * @since 1.0.0
	 *
	 * @return array Complete settings array.
	 */
	public function all() {
		if ( null === $this->cache ) {
			$saved = get_option( self::OPTION, array() );

			// Guard against a corrupted or hand-edited option value.
			$this->cache = wp_parse_args(
				is_array( $saved ) ? $saved : array(),
				$this->defaults()
			);
		}

		return $this->cache;
	}

	/**
	 * Get a single setting by key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Setting key, as listed in defaults().
	 * @return mixed Setting value, or null when the key is unknown.
	 */
	public function get( $key ) {
		$all = $this->all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Register the admin hooks this service needs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Register the option, its section, and its fields.
	 *
	 * Hooked to `admin_init` because the Settings API is only available
	 * once the admin request is under way.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_setting() {
		// Registering against the 'discussion' group means core renders and
		// saves the fields for us, including nonce and capability checks.
		register_setting(
			'discussion',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'convocept_section',
			__( 'Convocept', 'convocept' ),
			array( $this, 'render_section_intro' ),
			'discussion'
		);

		$fields = array(
			'enabled'       => __( 'Enable modern comments', 'convocept' ),
			'edit_window'   => __( 'Edit window (minutes)', 'convocept' ),
			'live_updates'  => __( 'Live updates', 'convocept' ),
			'poll_interval' => __( 'Poll interval (seconds)', 'convocept' ),
			'load_styles'   => __( 'Load default styles', 'convocept' ),
		);

		/**
		 * Filters the settings fields shown on Settings > Discussion.
		 *
		 * Reserved for add-ons that ship their own options; note that any
		 * added key must also survive sanitize(), which discards unknown keys.
		 *
		 * @since 1.0.0
		 *
		 * @param array $fields Map of setting key to field label.
		 */
		$fields = apply_filters( 'convocept_settings_fields', $fields );

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'convocept_' . $key,
				$label,
				array( $this, 'render_field' ),
				'discussion',
				'convocept_section',
				array(
					'key' => $key,
					// Lets core associate the <label> with the input.
					'label_for' => 'convocept_' . $key,
				)
			);
		}
	}

	/**
	 * Render the explanatory copy below the section heading.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__( 'Settings for the modern comment interface. All comments remain stored in native WordPress tables.', 'convocept' ) . '</p>';
	}

	/**
	 * Render one settings field.
	 *
	 * The control type is chosen from the setting key, so each field stays
	 * a single source of truth rather than duplicating markup per option.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Field arguments supplied by add_settings_field().
	 *
	 *     @type string $key Setting key to render.
	 * }
	 * @return void
	 */
	public function render_field( $args ) {
		$key   = $args['key'];
		$value = $this->get( $key );
		$name  = esc_attr( self::OPTION . '[' . $key . ']' );
		$id    = esc_attr( 'convocept_' . $key );

		switch ( $key ) {
			// Boolean toggles.
			case 'enabled':
			case 'load_styles':
				printf(
					'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false )
				);

				if ( 'load_styles' === $key ) {
					echo '<p class="description">' . esc_html__( 'Disable if your theme provides its own styles for the comment app.', 'convocept' ) . '</p>';
				}
				break;

			// Minutes an author may edit their own comment.
			case 'edit_window':
				printf(
					'<input type="number" min="0" max="1440" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) (int) $value )
				);
				echo '<p class="description">' . esc_html__( 'How long logged-in users can edit their own comment. 0 disables editing.', 'convocept' ) . '</p>';
				break;

			// Seconds between polls when live updates use polling.
			case 'poll_interval':
				printf(
					'<input type="number" min="10" max="600" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) (int) $value )
				);
				break;

			// How new comments reach readers who are already on the page.
			case 'live_updates':
				$options = array(
					'off'       => __( 'Off', 'convocept' ),
					'polling'   => __( 'Polling', 'convocept' ),
					'heartbeat' => __( 'Heartbeat API', 'convocept' ),
				);

				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );

				foreach ( $options as $opt => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt ),
						selected( $value, $opt, false ),
						esc_html( $label )
					);
				}

				echo '</select>';
				break;
		}
	}

	/**
	 * Sanitize submitted settings before they are stored.
	 *
	 * Builds the saved value from defaults() and copies across only known
	 * keys, each cast and range-clamped, so nothing unexpected is persisted
	 * even if the form is tampered with.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw value submitted from the settings form.
	 * @return array Clean settings array safe to store.
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = $this->defaults();

		// Unchecked checkboxes are absent from the payload entirely.
		$clean['enabled']     = ! empty( $input['enabled'] );
		$clean['load_styles'] = ! empty( $input['load_styles'] );

		// Clamp to a day; anything longer is indistinguishable from "always".
		$clean['edit_window'] = isset( $input['edit_window'] )
			? min( 1440, max( 0, (int) $input['edit_window'] ) )
			: 5;

		// Only accept known modes; fall back to the default otherwise.
		$modes                 = array( 'off', 'polling', 'heartbeat' );
		$clean['live_updates'] = ( isset( $input['live_updates'] ) && in_array( $input['live_updates'], $modes, true ) )
			? $input['live_updates']
			: 'polling';

		// Floor of 10s keeps a misconfigured site from hammering the server.
		$clean['poll_interval'] = isset( $input['poll_interval'] )
			? min( 600, max( 10, (int) $input['poll_interval'] ) )
			: 30;

		return $clean;
	}

	/**
	 * Get the subset of settings that is safe to expose publicly.
	 *
	 * These values only describe display behavior and are already inlined
	 * into every rendered page, so serving them to anonymous visitors over
	 * REST discloses nothing new.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Public settings, keyed for the JavaScript app.
	 *
	 *     @type int    $editWindow   Edit window length in minutes.
	 *     @type string $liveUpdates  Live update mode.
	 *     @type int    $pollInterval Poll interval in seconds.
	 * }
	 */
	public function public_subset() {
		return array(
			'editWindow'   => (int) $this->get( 'edit_window' ),
			'liveUpdates'  => (string) $this->get( 'live_updates' ),
			'pollInterval' => (int) $this->get( 'poll_interval' ),
		);
	}
}
