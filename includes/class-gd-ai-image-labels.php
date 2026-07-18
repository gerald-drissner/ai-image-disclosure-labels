<?php
/**
 * Main plugin class.
 *
 * @package GD_AI_Image_Labels
 */

defined( 'ABSPATH' ) || exit;

final class GD_AI_Image_Labels {

	const OPTION_KEY            = 'gd_ai_image_labels_settings';
	const VERSION_OPTION        = 'gd_ai_image_labels_version';
	const META_FEATURED_ENABLED = '_gd_ai_featured_enabled';
	const META_FEATURED_TEXT    = '_gd_ai_featured_text';

	/**
	 * Singleton instance.
	 *
	 * @var GD_AI_Image_Labels|null
	 */
	private static $instance = null;

	/**
	 * Per-request settings cache.
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GD_AI_Image_Labels
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set defaults on first activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', true );
		}

		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			add_option( self::VERSION_OPTION, GD_AI_IMAGE_LABELS_VERSION, '', true );
		} else {
			update_option( self::VERSION_OPTION, GD_AI_IMAGE_LABELS_VERSION );
		}

		if ( function_exists( 'wp_set_options_autoload' ) ) {
			wp_set_options_autoload( array( self::OPTION_KEY, self::VERSION_OPTION ), true );
		}
	}


	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'maybe_upgrade_settings' ), 1 );
		add_action( 'init', array( $this, 'register_post_meta' ), 99 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'register_block_type_args', array( $this, 'register_image_block_attributes' ), 10, 2 );

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 99 );

		add_filter( 'render_block_core/image', array( $this, 'render_image_block_label' ), 10, 2 );
		add_filter( 'wp_get_attachment_image', array( $this, 'render_featured_attachment_label' ), 20, 5 );
		add_filter( 'post_thumbnail_html', array( $this, 'render_featured_image_label' ), 20, 5 );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'handle_settings_update' ), 10, 3 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'clear_attachment_color_cache' ), 10, 2 );

		add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_frontend_script_from_delay' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GD_AI_IMAGE_LABELS_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'label_text'         => 'AI-generated',
			'position'           => 'bottom-right',
			'preset'             => 'subtle',
			'background_color'   => '#171717',
			'background_opacity' => 78,
			'text_color'         => '#ffffff',
			'border_color'       => '#ffffff',
			'border_width'       => 1,
			'border_radius'      => 3,
			'font_size'          => 9,
			'font_weight'        => 600,
			'padding_vertical'   => 3,
			'padding_horizontal' => 5,
			'offset_vertical'    => 7,
			'offset_horizontal'  => 7,
			'text_transform'      => 'none',
			'minimum_image_width'  => 180,
			'minimum_text_width'   => 500,
			'small_image_mode'    => 'icon',
			'icon_style'          => 'monogram',
			'custom_icon_id'       => 0,
			'custom_selectors'     => '',
			'icon_size_value'      => 16,
			'icon_size_unit'       => 'px',
			'icon_tooltip_enabled'  => false,
			'background_color_mode' => 'fixed',
			'font_family_mode'      => 'inherit',
			'font_family_custom'    => '',
		);
	}

	/**
	 * Privacy-friendly font stacks. All stacks resolve locally on the
	 * visitor's device; no font file is ever downloaded from a third
	 * party, which keeps the badge GDPR-clean and render-blocking free.
	 *
	 * @return array
	 */
	public static function font_stacks() {
		return array(
			'inherit'     => '',
			'system-sans' => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif",
			'system-serif'=> "Georgia,'Times New Roman',Times,serif",
			'system-mono' => "ui-monospace,SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace",
		);
	}

	/**
	 * Preset values used by the admin interface.
	 *
	 * @return array
	 */
	private function presets() {
		return array(
			'subtle' => array(
				'background_color'   => '#171717',
				'background_opacity' => 78,
				'text_color'         => '#ffffff',
				'border_color'       => '#ffffff',
				'border_width'       => 1,
				'border_radius'      => 3,
				'font_size'          => 9,
				'font_weight'        => 600,
				'padding_vertical'   => 3,
				'padding_horizontal' => 5,
			),
			'light' => array(
				'background_color'   => '#ffffff',
				'background_opacity' => 96,
				'text_color'         => '#111111',
				'border_color'       => '#5c5c5c',
				'border_width'       => 1,
				'border_radius'      => 3,
				'font_size'          => 9,
				'font_weight'        => 600,
				'padding_vertical'   => 3,
				'padding_horizontal' => 5,
			),
			'pill' => array(
				'background_color'   => '#171717',
				'background_opacity' => 82,
				'text_color'         => '#ffffff',
				'border_color'       => '#ffffff',
				'border_width'       => 1,
				'border_radius'      => 999,
				'font_size'          => 9,
				'font_weight'        => 700,
				'padding_vertical'   => 3,
				'padding_horizontal' => 7,
			),
		);
	}

	/**
	 * Legacy preset values used only for a safe one-time migration.
	 *
	 * @return array
	 */
	private function legacy_presets() {
		return array(
			'subtle' => array(
				'background_color'   => '#171717',
				'background_opacity' => 74,
				'text_color'         => '#ffffff',
				'border_color'       => '#ffffff',
				'border_width'       => 1,
				'border_radius'      => 4,
				'font_size'          => 11,
				'font_weight'        => 600,
				'padding_vertical'   => 4,
				'padding_horizontal' => 7,
			),
			'light' => array(
				'background_color'   => '#ffffff',
				'background_opacity' => 90,
				'text_color'         => '#171717',
				'border_color'       => '#4a4a4a',
				'border_width'       => 1,
				'border_radius'      => 4,
				'font_size'          => 11,
				'font_weight'        => 600,
				'padding_vertical'   => 4,
				'padding_horizontal' => 7,
			),
			'pill' => array(
				'background_color'   => '#171717',
				'background_opacity' => 80,
				'text_color'         => '#ffffff',
				'border_color'       => '#ffffff',
				'border_width'       => 1,
				'border_radius'      => 999,
				'font_size'          => 11,
				'font_weight'        => 700,
				'padding_vertical'   => 5,
				'padding_horizontal' => 10,
			),
		);
	}

	/**
	 * Purge page caches when visible output may have changed.
	 *
	 * @return void
	 */
	private function maybe_purge_page_cache() {
		/* WP Rocket (its Cloudflare add-on also purges the CDN / APO edge cache). */
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		/* LiteSpeed Cache. */
		if ( has_action( 'litespeed_purge_all' ) || class_exists( 'LiteSpeed\\Core' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public LiteSpeed Cache purge hook.
			do_action( 'litespeed_purge_all' );
		}

		/* W3 Total Cache. */
		if ( function_exists( 'w3tc_flush_posts' ) ) {
			w3tc_flush_posts();
		} elseif ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
		}

		/* WP Super Cache. */
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		/* WP Fastest Cache. */
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		}

		/* SiteGround Optimizer. */
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		/* Cache Enabler. */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public Cache Enabler purge hook.
		do_action( 'cache_enabler_clear_complete_cache' );

		/* Breeze (Cloudways). */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public Breeze purge hook.
		do_action( 'breeze_clear_all_cache' );

		/* Nginx Helper. */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public Nginx Helper purge hook.
		do_action( 'rt_nginx_helper_purge_all' );

		/* Hummingbird. */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public Hummingbird purge hook.
		do_action( 'wphb_clear_page_cache' );

		/*
		 * Extension point for cache systems that are not covered above,
		 * for example a custom Cloudflare APO purge via the Cloudflare API.
		 */
		do_action( 'gd_ai_image_labels_purge_caches' );
	}

	/**
	 * Human-readable names of detected page cache systems.
	 *
	 * Used on the settings screen so site owners know that their cache is
	 * cleared automatically after saving.
	 *
	 * @return array
	 */
	private function detected_cache_systems() {
		$detected = array();

		if ( function_exists( 'rocket_clean_domain' ) ) {
			$detected[] = 'WP Rocket';
		}

		if ( defined( 'LSCWP_V' ) ) {
			$detected[] = 'LiteSpeed Cache';
		}

		if ( function_exists( 'w3tc_flush_posts' ) || function_exists( 'w3tc_pgcache_flush' ) ) {
			$detected[] = 'W3 Total Cache';
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			$detected[] = 'WP Super Cache';
		}

		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			$detected[] = 'WP Fastest Cache';
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			$detected[] = 'SiteGround Optimizer';
		}

		if ( class_exists( 'Cache_Enabler' ) ) {
			$detected[] = 'Cache Enabler';
		}

		if ( class_exists( 'Breeze_Admin' ) ) {
			$detected[] = 'Breeze';
		}

		if ( has_action( 'rt_nginx_helper_purge_all' ) ) {
			$detected[] = 'Nginx Helper';
		}

		if ( has_action( 'wphb_clear_page_cache' ) ) {
			$detected[] = 'Hummingbird';
		}


		return array_values( array_unique( $detected ) );
	}

	/**
	 * Upgrade old untouched presets without changing customized designs.
	 *
	 * @return void
	 */
	public function maybe_upgrade_settings() {
		$installed_version = (string) get_option( self::VERSION_OPTION, '' );

		if ( '' !== $installed_version && version_compare( $installed_version, GD_AI_IMAGE_LABELS_VERSION, '>=' ) ) {
			return;
		}

		$saved = get_option( self::OPTION_KEY, false );

		if ( is_array( $saved ) ) {
			$preset         = isset( $saved['preset'] ) ? (string) $saved['preset'] : 'subtle';
			$legacy_presets = $this->legacy_presets();
			$new_presets    = $this->presets();
			$changed        = false;

			/*
			 * Version 1.0.4 could store preset=light while retaining the compact
			 * dark preset values when the already-selected Light card was clicked.
			 * Repair only that exact known state so custom designs remain untouched.
			 */
			if (
				'light' === $preset &&
				isset( $new_presets['subtle'], $new_presets['light'] ) &&
				$this->design_matches_preset( $saved, $new_presets['subtle'] )
			) {
				$saved   = array_merge( $saved, $new_presets['light'] );
				$changed = true;
			}

			if (
				! $changed &&
				isset( $legacy_presets[ $preset ], $new_presets[ $preset ] ) &&
				$this->design_matches_preset( $saved, $legacy_presets[ $preset ] )
			) {
				$saved   = array_merge( $saved, $new_presets[ $preset ] );
				$changed = true;
			}

			if ( $changed ) {
				update_option( self::OPTION_KEY, $saved, true );
			}
		}

		update_option( self::VERSION_OPTION, GD_AI_IMAGE_LABELS_VERSION, true );

		if ( function_exists( 'wp_set_options_autoload' ) ) {
			wp_set_options_autoload( array( self::OPTION_KEY, self::VERSION_OPTION ), true );
		}

		self::$settings_cache = null;
	}

	/**
	 * Determine whether all design fields still match a known preset.
	 *
	 * @param array $settings Saved settings.
	 * @param array $preset   Preset values.
	 * @return bool
	 */
	private function design_matches_preset( $settings, $preset ) {
		foreach ( $preset as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) || (string) $settings[ $key ] !== (string) $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get saved settings with defaults.
	 *
	 * @return array
	 */
	private function settings() {
		if ( is_array( self::$settings_cache ) ) {
			return self::$settings_cache;
		}

		$saved    = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		$preset   = isset( $settings['preset'] ) ? (string) $settings['preset'] : 'subtle';
		$presets  = $this->presets();

		if ( isset( $presets[ $preset ] ) ) {
			$settings = array_merge( $settings, $presets[ $preset ] );
		}

		self::$settings_cache = $settings;

		return self::$settings_cache;
	}

	/**
	 * Return supported post types.
	 *
	 * @return array
	 */
	private function post_types() {
		$post_types = apply_filters( 'gd_ai_image_labels_post_types', array( 'post', 'page' ) );
		$post_types = is_array( $post_types ) ? array_map( 'sanitize_key', $post_types ) : array( 'post', 'page' );
		$post_types = array_values( array_unique( array_filter( $post_types, 'post_type_exists' ) ) );

		return $post_types ? $post_types : array( 'post', 'page' );
	}

	/**
	 * Return selectors used by the JavaScript featured-image fallback.
	 *
	 * @return array
	 */
	private function featured_selectors() {
		$settings       = $this->settings();
		$user_selectors = array();

		if ( isset( $settings['custom_selectors'] ) && '' !== $settings['custom_selectors'] ) {
			$user_selectors = array_filter( array_map( 'trim', explode( "\n", (string) $settings['custom_selectors'] ) ) );
		}

		$selectors = array(
			'figure.cs-entry__post-media img.wp-post-image',
			'figure.post-media img.wp-post-image',
			'.cs-entry__post-media img.wp-post-image',
			'.post-thumbnail img.wp-post-image',
			'.entry-thumbnail img.wp-post-image',
			'.featured-image img.wp-post-image',
			'img.wp-post-image',
		);

		$selectors = apply_filters( 'gd_ai_image_labels_featured_selectors', array_merge( $user_selectors, $selectors ) );

		return is_array( $selectors ) ? array_values( array_filter( array_map( 'sanitize_text_field', $selectors ) ) ) : array();
	}

	/**
	 * Register featured-image metadata for posts and pages.
	 *
	 * @return void
	 */
	public function register_post_meta() {
		foreach ( $this->post_types() as $post_type ) {
			/*
			 * These keys are deliberately not exposed through the standard post
			 * REST schema. The editor panel uses the plugin's dedicated endpoint
			 * instead, preventing the editor from overwriting a freshly saved value
			 * with a stale default during the normal post-save request.
			 */
			register_post_meta(
				$post_type,
				self::META_FEATURED_ENABLED,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_FEATURED_TEXT,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);
		}
	}

	/**
	 * Register a dedicated REST endpoint for the featured-image label.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'gd-ai-image-labels/v1',
			'/post/(?P<id>\\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_featured_label' ),
					'permission_callback' => array( $this, 'rest_featured_label_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_featured_label' ),
					'permission_callback' => array( $this, 'rest_featured_label_permissions_check' ),
					'args'                => array(
						'enabled' => array(
							'type'     => 'boolean',
							'required' => true,
						),
						'text'    => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'rest_featured_label_schema' ),
			)
		);
	}

	/**
	 * Check access to the dedicated REST endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function rest_featured_label_permissions_check( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return new WP_Error(
				'gd_ai_image_labels_invalid_post',
				__( 'The post or page could not be found.', 'ai-image-disclosure-labels' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'gd_ai_image_labels_forbidden',
				__( 'You are not allowed to edit this post.', 'ai-image-disclosure-labels' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Return the current featured-image label state.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_featured_label( $request ) {
		return rest_ensure_response( $this->featured_label_rest_data( absint( $request['id'] ) ) );
	}

	/**
	 * Persist the featured-image label immediately.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_update_featured_label( $request ) {
		$post_id = absint( $request['id'] );
		$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		$text    = sanitize_text_field( (string) $request->get_param( 'text' ) );

		if ( $enabled ) {
			$result = update_post_meta( $post_id, self::META_FEATURED_ENABLED, '1' );

			if ( false === $result && '1' !== (string) get_post_meta( $post_id, self::META_FEATURED_ENABLED, true ) ) {
				return new WP_Error(
					'gd_ai_image_labels_save_failed',
					__( 'The label could not be saved.', 'ai-image-disclosure-labels' ),
					array( 'status' => 500 )
				);
			}
		} else {
			delete_post_meta( $post_id, self::META_FEATURED_ENABLED );
		}

		if ( '' !== $text ) {
			$text_result = update_post_meta( $post_id, self::META_FEATURED_TEXT, $text );

			if ( false === $text_result && $text !== (string) get_post_meta( $post_id, self::META_FEATURED_TEXT, true ) ) {
				return new WP_Error(
					'gd_ai_image_labels_text_save_failed',
					__( 'The custom label text could not be saved.', 'ai-image-disclosure-labels' ),
					array( 'status' => 500 )
				);
			}
		} else {
			delete_post_meta( $post_id, self::META_FEATURED_TEXT );
		}

		clean_post_cache( $post_id );
		$this->maybe_purge_page_cache();

		return rest_ensure_response( $this->featured_label_rest_data( $post_id ) );
	}

	/**
	 * Return the endpoint response data.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function featured_label_rest_data( $post_id ) {
		return array(
			'enabled' => '1' === (string) get_post_meta( $post_id, self::META_FEATURED_ENABLED, true ),
			'text'    => (string) get_post_meta( $post_id, self::META_FEATURED_TEXT, true ),
		);
	}

	/**
	 * REST response schema.
	 *
	 * @return array
	 */
	public function rest_featured_label_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'gd-ai-image-featured-label',
			'type'       => 'object',
			'properties' => array(
				'enabled' => array(
					'type'        => 'boolean',
					'description' => 'Whether the featured image label is enabled.',
				),
				'text'    => array(
					'type'        => 'string',
					'description' => 'Optional per-post label text.',
				),
			),
		);
	}

	/**
	 * Register custom attributes for core/image on the server.
	 *
	 * @param array  $args       Block registration arguments.
	 * @param string $block_type Block type name.
	 * @return array
	 */
	public function register_image_block_attributes( $args, $block_type ) {
		if ( 'core/image' !== $block_type ) {
			return $args;
		}

		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}

		$args['attributes']['gdAiLabel'] = array(
			'type'    => 'boolean',
			'default' => false,
		);

		$args['attributes']['gdAiLabelText'] = array(
			'type'    => 'string',
			'default' => '',
		);

		return $args;
	}

	/**
	 * Enqueue block-editor controls and preview styles.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->post_type, $this->post_types(), true ) ) {
			return;
		}

		$settings = $this->settings();

		wp_enqueue_script(
			'gd-ai-image-labels-editor',
			GD_AI_IMAGE_LABELS_URL . 'assets/editor.js',
			array(
				'wp-api-fetch',
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-compose',
				'wp-data',
				'wp-editor',
				'wp-element',
				'wp-hooks',
				'wp-i18n',
				'wp-plugins',
			),
			GD_AI_IMAGE_LABELS_VERSION,
			true
		);

		wp_localize_script(
			'gd-ai-image-labels-editor',
			'gdAiImageLabelsEditor',
			array(
				'defaultText'      => $settings['label_text'],
				'position'         => $settings['position'],
				'allowedPostTypes' => $this->post_types(),
				'metaEnabled'      => self::META_FEATURED_ENABLED,
				'metaText'         => self::META_FEATURED_TEXT,
				'restPath'         => '/gd-ai-image-labels/v1/post/',
			)
		);

		wp_set_script_translations( 'gd-ai-image-labels-editor', 'ai-image-disclosure-labels' );

		wp_enqueue_style(
			'gd-ai-image-labels-editor',
			GD_AI_IMAGE_LABELS_URL . 'assets/editor.css',
			array(),
			GD_AI_IMAGE_LABELS_VERSION
		);

		wp_add_inline_style( 'gd-ai-image-labels-editor', $this->dynamic_badge_css( true ) );
	}

	/**
	 * Enqueue front-end CSS.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$settings = $this->settings();

		wp_enqueue_style(
			'gd-ai-image-labels',
			GD_AI_IMAGE_LABELS_URL . 'assets/frontend.css',
			array(),
			GD_AI_IMAGE_LABELS_VERSION
		);

		wp_add_inline_style( 'gd-ai-image-labels', $this->dynamic_badge_css( false ) );

		$minimum_width          = isset( $settings['minimum_image_width'] ) ? (int) $settings['minimum_image_width'] : 0;
		$minimum_text_width     = isset( $settings['minimum_text_width'] ) ? (int) $settings['minimum_text_width'] : 0;
		$needs_size_filter      = 0 < $minimum_width || 0 < $minimum_text_width;
		$enable_theme_fallback  = false;
		$featured_label_text    = '';

		if ( is_singular( $this->post_types() ) ) {
			$post_id = get_queried_object_id();

			if ( $post_id && get_post_meta( $post_id, self::META_FEATURED_ENABLED, true ) ) {
				$custom_text = get_post_meta( $post_id, self::META_FEATURED_TEXT, true );
				$custom_text = is_string( $custom_text ) ? trim( sanitize_text_field( $custom_text ) ) : '';
				$featured_label_text = '' !== $custom_text ? $custom_text : trim( $settings['label_text'] );
				$enable_theme_fallback = '' !== $featured_label_text;
			}
		}

		/*
		 * The size observer is also needed on archive and home pages because
		 * themes can render the same marked featured image at several different
		 * widths. The decision is based on the actual rendered width, not on the
		 * attachment's source dimensions or WordPress image-size name.
		 *
		 * With automatic colors the script is loaded even without size
		 * thresholds: it colors JavaScript-created fallback labels and acts
		 * as a canvas-based rescue path on servers without the GD extension.
		 */
		$auto_color = isset( $settings['background_color_mode'] ) && 'auto' === $settings['background_color_mode'];

		if ( ! $needs_size_filter && ! $enable_theme_fallback && ! $auto_color ) {
			return;
		}

		wp_enqueue_script(
			'gd-ai-image-labels-frontend',
			GD_AI_IMAGE_LABELS_URL . 'assets/frontend.js',
			array(),
			GD_AI_IMAGE_LABELS_VERSION,
			true
		);

		wp_localize_script(
			'gd-ai-image-labels-frontend',
			'gdAiImageLabelsFrontend',
			array(
				'labelText'          => $featured_label_text,
				'preset'             => $settings['preset'],
				'minimumImageWidth'  => $minimum_width,
				'minimumTextWidth'   => $minimum_text_width,
				'smallImageMode'     => isset( $settings['small_image_mode'] ) ? $settings['small_image_mode'] : 'icon',
				'featuredFallback'   => $enable_theme_fallback,
				'iconHtml'           => $this->icon_markup( isset( $settings['icon_style'] ) ? $settings['icon_style'] : 'monogram' ),
				'featuredSelectors'  => $this->featured_selectors(),
				'iconSizeValue'      => isset( $settings['icon_size_value'] ) ? (float) $settings['icon_size_value'] : 16,
				'iconSizeUnit'       => isset( $settings['icon_size_unit'] ) ? $settings['icon_size_unit'] : 'px',
				'tooltipEnabled'     => ! empty( $settings['icon_tooltip_enabled'] ),
				'autoColor'          => isset( $settings['background_color_mode'] ) && 'auto' === $settings['background_color_mode'],
				'backgroundOpacity'  => isset( $settings['background_opacity'] ) ? max( 0, min( 100, (int) $settings['background_opacity'] ) ) : 78,
				'tooltipButtonLabel' => '' !== $featured_label_text
					/* translators: %s: the disclosure label text, e.g. "AI-generated". */
					? sprintf( __( 'Show disclosure: %s', 'ai-image-disclosure-labels' ), $featured_label_text )
					: __( 'Show AI disclosure', 'ai-image-disclosure-labels' ),
			)
		);
	}

	/**
	 * Add the badge to explicitly marked core/image blocks.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function render_image_block_label( $block_content, $block ) {
		$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( empty( $attributes['gdAiLabel'] ) || false !== strpos( $block_content, 'gd-ai-image-label' ) ) {
			return $block_content;
		}

		$custom_text   = isset( $attributes['gdAiLabelText'] ) ? $attributes['gdAiLabelText'] : '';
		$attachment_id = isset( $attributes['id'] ) ? (int) $attributes['id'] : 0;
		$label_html    = $this->label_html( $custom_text, $attachment_id );

		if ( '' === $label_html ) {
			return $block_content;
		}

		$updated = $block_content;

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $updated );

			if ( $processor->next_tag( 'figure' ) ) {
				$processor->add_class( 'gd-ai-image-frame' );
				$updated = $processor->get_updated_html();
			}
		} else {
			$updated = preg_replace(
				'/<figure\b([^>]*)class=(["\'])([^"\']*)\2/i',
				'<figure$1class=$2$3 gd-ai-image-frame$2',
				$updated,
				1
			);
		}

		if ( preg_match( '/<\/figure>\s*$/i', $updated ) ) {
			return preg_replace( '/<\/figure>\s*$/i', $label_html . '</figure>', $updated, 1 );
		}

		return '<span class="gd-ai-image-frame gd-ai-image-fallback">' . $updated . $label_html . '</span>';
	}

	/**
	 * Add the badge when a theme renders the featured attachment directly.
	 *
	 * Some themes call wp_get_attachment_image() instead of the regular
	 * post-thumbnail template functions. The wp-post-image class prevents the
	 * same attachment from being labelled when it is used as a normal content image.
	 *
	 * @param string       $html          Image HTML.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|array $size          Requested image size.
	 * @param bool         $icon          Whether the image is an icon.
	 * @param array        $attr          Image attributes.
	 * @return string
	 */
	public function render_featured_attachment_label( $html, $attachment_id, $size, $icon, $attr ) {
		unset( $size, $icon, $attr );

		if (
			'' === $html ||
			is_admin() ||
			wp_doing_ajax() ||
			is_feed() ||
			false !== strpos( $html, 'gd-ai-featured-wrap' ) ||
			false === strpos( $html, 'wp-post-image' ) ||
			! is_singular( $this->post_types() )
		) {
			return $html;
		}

		$post_id = get_queried_object_id();

		if (
			! $post_id ||
			(int) $attachment_id !== (int) get_post_thumbnail_id( $post_id ) ||
			! get_post_meta( $post_id, self::META_FEATURED_ENABLED, true )
		) {
			return $html;
		}

		$custom_text = get_post_meta( $post_id, self::META_FEATURED_TEXT, true );
		$label_html  = $this->label_html( is_string( $custom_text ) ? $custom_text : '', (int) $attachment_id );

		if ( '' === $label_html ) {
			return $html;
		}

		return '<span class="gd-ai-image-frame gd-ai-featured-wrap">' . $html . $label_html . '</span>';
	}

	/**
	 * Add the badge to explicitly marked featured images.
	 *
	 * @param string       $html              Featured image HTML.
	 * @param int          $post_id           Post ID.
	 * @param int          $post_thumbnail_id Attachment ID.
	 * @param string|array $size              Image size.
	 * @param string|array $attr              Image attributes.
	 * @return string
	 */
	public function render_featured_image_label( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		unset( $size, $attr );

		if (
			'' === $html ||
			is_admin() ||
			wp_doing_ajax() ||
			is_feed() ||
			false !== strpos( $html, 'gd-ai-featured-wrap' ) ||
			! get_post_meta( $post_id, self::META_FEATURED_ENABLED, true )
		) {
			return $html;
		}

		$custom_text = get_post_meta( $post_id, self::META_FEATURED_TEXT, true );
		$label_html  = $this->label_html( is_string( $custom_text ) ? $custom_text : '', (int) $post_thumbnail_id );

		if ( '' === $label_html ) {
			return $html;
		}

		return '<span class="gd-ai-image-frame gd-ai-featured-wrap">' . $html . $label_html . '</span>';
	}

	/**
	 * Average color of an attachment, cached in attachment meta.
	 *
	 * The color is computed once per attachment from its thumbnail file by
	 * resampling it to a single pixel with GD, then stored, so the front
	 * end never pays the image-processing cost again.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Array with r, g, b keys or null when unavailable.
	 */
	private function attachment_average_color( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return null;
		}

		$cached = get_post_meta( $attachment_id, '_gd_ai_avg_color', true );

		if ( is_string( $cached ) && preg_match( '/^\d{1,3},\d{1,3},\d{1,3}$/', $cached ) ) {
			$parts = array_map( 'intval', explode( ',', $cached ) );

			return array(
				'r' => min( 255, $parts[0] ),
				'g' => min( 255, $parts[1] ),
				'b' => min( 255, $parts[2] ),
			);
		}

		if ( 'none' === $cached ) {
			return null;
		}

		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}

		$file = null;
		$src  = image_get_intermediate_size( $attachment_id, 'thumbnail' );

		if ( $src && ! empty( $src['path'] ) ) {
			$uploads = wp_get_upload_dir();
			$file    = trailingslashit( $uploads['basedir'] ) . $src['path'];
		}

		if ( ! $file || ! is_readable( $file ) ) {
			$file = get_attached_file( $attachment_id );
		}

		if ( ! $file || ! is_readable( $file ) || filesize( $file ) > 5 * MB_IN_BYTES ) {
			update_post_meta( $attachment_id, '_gd_ai_avg_color', 'none' );

			return null;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment file.
		$image    = $contents ? @imagecreatefromstring( $contents ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- GD emits warnings for unsupported formats; handled via the false return.

		if ( ! $image ) {
			update_post_meta( $attachment_id, '_gd_ai_avg_color', 'none' );

			return null;
		}

		$pixel = imagecreatetruecolor( 1, 1 );
		imagecopyresampled( $pixel, $image, 0, 0, 0, 0, 1, 1, imagesx( $image ), imagesy( $image ) );
		$rgb = imagecolorat( $pixel, 0, 0 );
		imagedestroy( $image );
		imagedestroy( $pixel );

		$color = array(
			'r' => ( $rgb >> 16 ) & 0xFF,
			'g' => ( $rgb >> 8 ) & 0xFF,
			'b' => $rgb & 0xFF,
		);

		update_post_meta( $attachment_id, '_gd_ai_avg_color', $color['r'] . ',' . $color['g'] . ',' . $color['b'] );

		return $color;
	}

	/**
	 * Convert an 8-bit sRGB channel to linear-light space.
	 *
	 * @param int|float $value Channel value from 0 to 255.
	 * @return float
	 */
	private function linearize_srgb_channel( $value ) {
		$channel = max( 0, min( 255, (float) $value ) ) / 255;

		return $channel <= 0.04045
			? $channel / 12.92
			: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
	}

	/**
	 * Calculate sRGB relative luminance.
	 *
	 * @param array $color RGB color array.
	 * @return float
	 */
	private function relative_luminance( $color ) {
		return 0.2126 * $this->linearize_srgb_channel( $color['r'] )
			+ 0.7152 * $this->linearize_srgb_channel( $color['g'] )
			+ 0.0722 * $this->linearize_srgb_channel( $color['b'] );
	}

	/**
	 * Calculate the contrast ratio between two luminance values.
	 *
	 * @param float $first  First luminance.
	 * @param float $second Second luminance.
	 * @return float
	 */
	private function contrast_ratio( $first, $second ) {
		$lighter = max( $first, $second );
		$darker  = min( $first, $second );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * Choose the better contrasting text color for a sampled background.
	 *
	 * @param array $color RGB color array.
	 * @return string
	 */
	private function readable_text_color( $color ) {
		$background = $this->relative_luminance( $color );
		$dark       = $this->relative_luminance( array( 'r' => 17, 'g' => 17, 'b' => 17 ) );
		$light      = 1.0;

		return $this->contrast_ratio( $background, $dark ) >= $this->contrast_ratio( $background, $light )
			? '#111111'
			: '#ffffff';
	}

	/**
	 * Build inline CSS variables for automatic badge colors.
	 *
	 * Text color is selected by comparing the contrast ratio of dark and
	 * light text against the sampled sRGB background color.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Empty string when automatic colors are unavailable.
	 */
	private function auto_color_style( $attachment_id ) {
		$settings = $this->settings();

		if ( empty( $settings['background_color_mode'] ) || 'auto' !== $settings['background_color_mode'] ) {
			return '';
		}

		$color = $this->attachment_average_color( $attachment_id );

		if ( null === $color ) {
			return '';
		}

		$alpha = round( max( 0, min( 100, (int) $settings['background_opacity'] ) ) / 100, 2 );
		$text  = $this->readable_text_color( $color );
		$rgb   = $color['r'] . ',' . $color['g'] . ',' . $color['b'];

		return sprintf(
			'--gd-ai-label-bg:rgba(%1$s,%2$s);--gd-ai-label-color:%3$s;--gd-ai-label-border-color:rgba(%1$s,%2$s);',
			$rgb,
			$alpha,
			$text
		);
	}

	/**
	 * Build badge HTML.
	 *
	 * @param string $custom_text  Optional per-image text.
	 * @param int    $attachment_id Attachment ID used for automatic color sampling.
	 * @return string
	 */
	private function label_html( $custom_text = '', $attachment_id = 0 ) {
		$custom_text = trim( sanitize_text_field( $custom_text ) );
		$settings    = $this->settings();
		$text        = '' !== $custom_text ? $custom_text : trim( $settings['label_text'] );

		if ( '' === $text ) {
			return '';
		}

		$preset_class = in_array( $settings['preset'], array( 'subtle', 'light', 'pill' ), true )
			? ' gd-ai-preset-' . $settings['preset']
			: ' gd-ai-preset-custom';

		$tooltip_enabled = ! empty( $settings['icon_tooltip_enabled'] );
		$tooltip_class   = $tooltip_enabled ? ' gd-ai-tooltip-enabled' : '';
		$icon_markup     = $this->icon_html();
		$tooltip_markup  = '';

		if ( $tooltip_enabled ) {
			$tooltip_id = wp_unique_id( 'gd-ai-label-tooltip-' );
			$icon_markup = sprintf(
				'<span class="gd-ai-label-trigger" role="button" tabindex="0" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s">%3$s</span>',
				esc_attr( $tooltip_id ),
				/* translators: %s: the disclosure label text, e.g. "AI-generated". */
				esc_attr( sprintf( __( 'Show disclosure: %s', 'ai-image-disclosure-labels' ), $text ) ),
				$icon_markup
			);
			$tooltip_markup = sprintf(
				'<span id="%1$s" class="gd-ai-label-tooltip" role="tooltip">%2$s</span>',
				esc_attr( $tooltip_id ),
				esc_html( $text )
			);
		}

		$auto_style = $this->auto_color_style( $attachment_id );
		$style_attr = '' !== $auto_style ? ' style="' . esc_attr( $auto_style ) . '"' : '';

		return sprintf(
			'<span class="gd-ai-image-label%1$s%2$s" role="note" aria-label="%3$s"%7$s>%4$s<span class="gd-ai-label-text">%5$s</span>%6$s</span>',
			esc_attr( $preset_class ),
			esc_attr( $tooltip_class ),
			esc_attr( $text ),
			$icon_markup,
			esc_html( $text ),
			$tooltip_markup,
			$style_attr
		);
	}

	/**
	 * Return available icon choices.
	 *
	 * @return array
	 */
	private function icon_choices() {
		return array(
			'monogram' => __( 'AI Monogram', 'ai-image-disclosure-labels' ),
			'sparkle'  => __( 'Sparkle', 'ai-image-disclosure-labels' ),
			'chip'     => __( 'Chip', 'ai-image-disclosure-labels' ),
			'custom'   => __( 'Custom symbol', 'ai-image-disclosure-labels' ),
		);
	}

	/**
	 * Return validated custom-icon attachment data.
	 *
	 * @param int|null $attachment_id Attachment ID, or null for the saved setting.
	 * @return array|null
	 */
	private function custom_icon_data( $attachment_id = null ) {
		if ( null === $attachment_id ) {
			$settings      = $this->settings();
			$attachment_id = isset( $settings['custom_icon_id'] ) ? absint( $settings['custom_icon_id'] ) : 0;
		}

		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return null;
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime, array( 'image/png', 'image/svg+xml' ), true ) ) {
			return null;
		}

		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			return null;
		}

		return array(
			'id'   => $attachment_id,
			'url'  => esc_url_raw( $url ),
			'mime' => $mime,
		);
	}

	/**
	 * Build the icon wrapper HTML.
	 *
	 * @return string
	 */
	private function icon_html() {
		$settings = $this->settings();
		$style    = isset( $settings['icon_style'] ) ? $settings['icon_style'] : 'monogram';

		return '<span class="gd-ai-label-icon" aria-hidden="true">' . $this->icon_markup( $style ) . '</span>';
	}

	/**
	 * Build built-in or custom icon markup.
	 *
	 * @param string $style Icon style.
	 * @return string
	 */
	private function icon_markup( $style ) {
		if ( 'custom' === $style ) {
			$custom = $this->custom_icon_data();

			if ( $custom ) {
				return sprintf(
					'<img class="gd-ai-icon gd-ai-icon-custom" src="%s" alt="" decoding="async">',
					esc_url( $custom['url'] )
				);
			}

			$style = 'monogram';
		}

		return $this->icon_svg_markup( $style );
	}

	/**
	 * Allowed HTML for icon output, used with wp_kses().
	 *
	 * @return array
	 */
	private function allowed_icon_html() {
		return array(
			'svg'    => array(
				'class'       => true,
				'viewbox'     => true,
				'aria-hidden' => true,
				'focusable'   => true,
				'xmlns'       => true,
			),
			'path'   => array( 'd' => true ),
			'rect'   => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			),
			'circle' => array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
			'text'   => array(
				'x'           => true,
				'y'           => true,
				'text-anchor' => true,
			),
			'img'    => array(
				'class'    => true,
				'src'      => true,
				'alt'      => true,
				'decoding' => true,
			),
		);
	}

	/**
	 * Build an inline SVG icon.
	 *
	 * @param string $style Icon style.
	 * @return string
	 */
	private function icon_svg_markup( $style ) {
		switch ( $style ) {
			case 'sparkle':
				return '<svg class="gd-ai-icon gd-ai-icon-sparkle" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5l1.6 5.1 5.1 1.6-5.1 1.6L12 16l-1.6-5.2-5.1-1.6 5.1-1.6L12 2.5z"></path><circle cx="18.2" cy="17.8" r="1.6"></circle><circle cx="7" cy="17.5" r="1.1"></circle></svg>';
			case 'chip':
				return '<svg class="gd-ai-icon gd-ai-icon-chip" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="6" y="6" width="12" height="12" rx="2.5"></rect><path d="M9.2 9.3h5.6M9.2 12h5.6M9.2 14.7h3.3M4 8h2M4 12h2M4 16h2M18 8h2M18 12h2M18 16h2M8 4v2M12 4v2M16 4v2M8 18v2M12 18v2M16 18v2"></path></svg>';
			case 'monogram':
			default:
				return '<svg class="gd-ai-icon gd-ai-icon-monogram" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="2.25" y="2.25" width="19.5" height="19.5" rx="5"></rect><text x="12" y="15" text-anchor="middle">AI</text></svg>';
		}
	}

	/**
	 * Invalidate the cached sampled color when an attachment changes.
	 *
	 * @param array $metadata      Updated attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function clear_attachment_color_cache( $metadata, $attachment_id ) {
		delete_post_meta( (int) $attachment_id, '_gd_ai_avg_color' );

		return $metadata;
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'gd_ai_image_labels',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$output['label_text'] = isset( $input['label_text'] )
			? sanitize_text_field( $input['label_text'] )
			: $defaults['label_text'];

		$positions = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
		$output['position'] = isset( $input['position'] ) && in_array( $input['position'], $positions, true )
			? $input['position']
			: $defaults['position'];

		$allowed_presets = array( 'subtle', 'light', 'pill', 'custom' );
		$output['preset'] = isset( $input['preset'] ) && in_array( $input['preset'], $allowed_presets, true )
			? $input['preset']
			: $defaults['preset'];

		/*
		 * A named preset always saves its canonical values. This makes preset
		 * selection work even if admin JavaScript is cached or blocked.
		 */
		$presets = $this->presets();

		if ( isset( $presets[ $output['preset'] ] ) ) {
			$output = array_merge( $output, $presets[ $output['preset'] ] );
		} else {
			foreach ( array( 'background_color', 'text_color', 'border_color' ) as $color_key ) {
				$color = isset( $input[ $color_key ] ) ? sanitize_hex_color( $input[ $color_key ] ) : '';
				$output[ $color_key ] = $color ? $color : $defaults[ $color_key ];
			}

			$output['background_opacity'] = $this->clamp_int( $input, 'background_opacity', 0, 100, $defaults['background_opacity'] );
			$output['border_width']       = $this->clamp_int( $input, 'border_width', 0, 5, $defaults['border_width'] );
			$output['border_radius']      = $this->clamp_int( $input, 'border_radius', 0, 999, $defaults['border_radius'] );
			$output['font_size']          = $this->clamp_int( $input, 'font_size', 8, 24, $defaults['font_size'] );
			$output['padding_vertical']   = $this->clamp_int( $input, 'padding_vertical', 0, 20, $defaults['padding_vertical'] );
			$output['padding_horizontal'] = $this->clamp_int( $input, 'padding_horizontal', 0, 30, $defaults['padding_horizontal'] );

			$font_weights = array( 400, 500, 600, 700 );
			$font_weight  = isset( $input['font_weight'] ) ? (int) $input['font_weight'] : $defaults['font_weight'];
			$output['font_weight'] = in_array( $font_weight, $font_weights, true ) ? $font_weight : $defaults['font_weight'];
		}

		/* Position offsets and text transformation remain editable in all modes. */
		$output['offset_vertical']   = $this->clamp_int( $input, 'offset_vertical', 0, 60, $defaults['offset_vertical'] );
		$output['offset_horizontal'] = $this->clamp_int( $input, 'offset_horizontal', 0, 60, $defaults['offset_horizontal'] );

		$text_transforms = array( 'none', 'uppercase' );
		$output['text_transform'] = isset( $input['text_transform'] ) && in_array( $input['text_transform'], $text_transforms, true )
			? $input['text_transform']
			: $defaults['text_transform'];

		/* 0 disables the small-image filter and always shows marked labels. */
		$output['minimum_image_width'] = $this->clamp_int(
			$input,
			'minimum_image_width',
			0,
			1200,
			$defaults['minimum_image_width']
		);

		$output['minimum_text_width'] = $this->clamp_int(
			$input,
			'minimum_text_width',
			0,
			1600,
			$defaults['minimum_text_width']
		);

		$small_image_modes = array( 'icon', 'hide' );
		$output['small_image_mode'] = isset( $input['small_image_mode'] ) && in_array( $input['small_image_mode'], $small_image_modes, true )
			? $input['small_image_mode']
			: $defaults['small_image_mode'];

		$icon_choices = array_keys( $this->icon_choices() );
		$output['icon_style'] = isset( $input['icon_style'] ) && in_array( $input['icon_style'], $icon_choices, true )
			? $input['icon_style']
			: $defaults['icon_style'];

		$custom_icon_id = isset( $input['custom_icon_id'] ) ? absint( $input['custom_icon_id'] ) : 0;
		$output['custom_icon_id'] = $this->custom_icon_data( $custom_icon_id ) ? $custom_icon_id : 0;

		$icon_size_units = array( 'px', 'percent' );
		$output['icon_size_unit'] = isset( $input['icon_size_unit'] ) && in_array( $input['icon_size_unit'], $icon_size_units, true )
			? $input['icon_size_unit']
			: $defaults['icon_size_unit'];

		if ( 'percent' === $output['icon_size_unit'] ) {
			$output['icon_size_value'] = $this->clamp_float( $input, 'icon_size_value', 1, 30, $defaults['icon_size_value'] );
		} else {
			$output['icon_size_value'] = $this->clamp_float( $input, 'icon_size_value', 8, 80, $defaults['icon_size_value'] );
		}

		if ( 'custom' === $output['icon_style'] && ! $output['custom_icon_id'] ) {
			$output['icon_style'] = 'monogram';
		}

		$output['icon_tooltip_enabled'] = ! empty( $input['icon_tooltip_enabled'] );

		$output['background_color_mode'] = isset( $input['background_color_mode'] ) && 'auto' === $input['background_color_mode'] ? 'auto' : 'fixed';

		$font_modes                  = array( 'inherit', 'system-sans', 'system-serif', 'system-mono', 'custom' );
		$output['font_family_mode']  = isset( $input['font_family_mode'] ) && in_array( $input['font_family_mode'], $font_modes, true )
			? $input['font_family_mode']
			: 'inherit';
		$output['font_family_custom'] = '';

		if ( isset( $input['font_family_custom'] ) && is_string( $input['font_family_custom'] ) ) {
			/*
			 * A CSS font-family stack only: letters, digits, spaces, commas,
			 * hyphens and single quotes. Everything else is stripped so the
			 * value can never break out of the generated CSS.
			 */
			$stack = preg_replace( "/[^A-Za-z0-9,\-' ]/", '', $input['font_family_custom'] );
			$stack = trim( preg_replace( '/\s+/', ' ', (string) $stack ) );

			$output['font_family_custom'] = substr( $stack, 0, 200 );
		}

		if ( 'custom' === $output['font_family_mode'] && '' === $output['font_family_custom'] ) {
			$output['font_family_mode'] = 'inherit';
		}

		/*
		 * Theme integration: one CSS selector per line for themes whose
		 * featured-image wrapper is not covered by the built-in selectors.
		 */
		$output['custom_selectors'] = '';

		if ( isset( $input['custom_selectors'] ) && is_string( $input['custom_selectors'] ) ) {
			$lines = array_map( 'sanitize_text_field', explode( "\n", $input['custom_selectors'] ) );
			$lines = array_filter( array_map( 'trim', $lines ) );
			$lines = array_slice( $lines, 0, 20 );
			$lines = array_filter(
				$lines,
				static function ( $line ) {
					return strlen( $line ) <= 200 && false === strpos( $line, '<' ) && false === strpos( $line, '{' );
				}
			);

			$output['custom_selectors'] = implode( "\n", $lines );
		}

		if ( $output['minimum_text_width'] > 0 && $output['minimum_text_width'] < $output['minimum_image_width'] ) {
			$output['minimum_text_width'] = $output['minimum_image_width'];
		}

		return $output;
	}

	/**
	 * Clamp an integer setting.
	 *
	 * @param array  $input   Input settings.
	 * @param string $key     Setting key.
	 * @param int    $min     Minimum.
	 * @param int    $max     Maximum.
	 * @param int    $default Default.
	 * @return int
	 */
	private function clamp_int( $input, $key, $min, $max, $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return $default;
		}

		return max( $min, min( $max, (int) $input[ $key ] ) );
	}


	/**
	 * Clamp a floating-point setting.
	 *
	 * @param array  $input   Input settings.
	 * @param string $key     Setting key.
	 * @param float  $min     Minimum.
	 * @param float  $max     Maximum.
	 * @param float  $default Default.
	 * @return float
	 */
	private function clamp_float( $input, $key, $min, $max, $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return (float) $default;
		}

		return max( (float) $min, min( (float) $max, (float) $input[ $key ] ) );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'AI Image Disclosure & Labels', 'ai-image-disclosure-labels' ),
			__( 'AI Image Labels', 'ai-image-disclosure-labels' ),
			'manage_options',
			'gd-ai-image-labels',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue settings-page assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_gd-ai-image-labels' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'gd-ai-image-labels-admin',
			GD_AI_IMAGE_LABELS_URL . 'assets/admin.css',
			array(),
			GD_AI_IMAGE_LABELS_VERSION
		);

		wp_enqueue_script(
			'gd-ai-image-labels-admin',
			GD_AI_IMAGE_LABELS_URL . 'assets/admin.js',
			array( 'media-editor', 'wp-i18n' ),
			GD_AI_IMAGE_LABELS_VERSION,
			true
		);

		wp_set_script_translations( 'gd-ai-image-labels-admin', 'ai-image-disclosure-labels' );

		$settings    = $this->settings();
		$custom_icon = $this->custom_icon_data();

		wp_localize_script(
			'gd-ai-image-labels-admin',
			'gdAiImageLabelsAdmin',
			array(
				'fontStacks'  => self::font_stacks(),
				'presets' => $this->presets(),
				'icons'   => array(
					'monogram' => $this->icon_svg_markup( 'monogram' ),
					'sparkle'  => $this->icon_svg_markup( 'sparkle' ),
					'chip'     => $this->icon_svg_markup( 'chip' ),
				),
				'customIcon' => $custom_icon,
				'currentIconStyle' => isset( $settings['icon_style'] ) ? $settings['icon_style'] : 'monogram',
				'allowedMimeTypes' => array( 'image/png', 'image/svg+xml' ),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s           = $this->settings();
		$custom_icon = $this->custom_icon_data();
		?>
		<div class="wrap gd-ai-settings">
			<h1><?php esc_html_e( 'AI Image Disclosure & Labels', 'ai-image-disclosure-labels' ); ?> <span class="gd-ai-badge-chip"><?php esc_html_e( 'Supports AI disclosure', 'ai-image-disclosure-labels' ); ?></span></h1>
			<p class="gd-ai-lead">
				<?php esc_html_e( 'The label appears only on images you explicitly mark in the editor. Existing and unmarked images remain unchanged.', 'ai-image-disclosure-labels' ); ?>
			</p>

			<div class="gd-ai-notice gd-ai-notice-eu">
				<strong><?php esc_html_e( 'EU AI Act transparency rules apply from August 2, 2026', 'ai-image-disclosure-labels' ); ?></strong>
				<p><?php esc_html_e( 'Article 50 of the EU AI Act (Regulation (EU) 2024/1689) includes transparency duties for providers and deployers of certain AI systems, including visible disclosure of deep fakes and certain other AI-generated content. The rules apply from August 2, 2026. This plugin helps you add a visible disclosure to selected images. It does not decide whether a particular image must be labeled, does not add machine-readable markings, and is not legal advice.', 'ai-image-disclosure-labels' ); ?></p>
			</div>

			<?php $gd_ai_detected_caches = $this->detected_cache_systems(); ?>
			<?php if ( ! empty( $gd_ai_detected_caches ) ) : ?>
				<p class="gd-ai-cache-status">
					<?php
					printf(
						/* translators: %s: comma-separated list of detected caching plugins, e.g. "WP Rocket, Cloudflare". */
						esc_html__( 'Caching detected: %s. The page cache is cleared automatically whenever you save these settings or change a label, so your changes are visible immediately.', 'ai-image-disclosure-labels' ),
						esc_html( implode( ', ', $gd_ai_detected_caches ) )
					);
					?>
				</p>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'gd_ai_image_labels' ); ?>

				<div class="gd-ai-settings-grid">
					<main class="gd-ai-settings-main">
						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Default text', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'The text can be overridden individually for every image and featured image.', 'ai-image-disclosure-labels' ); ?></p>
							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'Label text', 'ai-image-disclosure-labels' ); ?></span>
								<input
									type="text"
									id="gd-ai-label-text"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[label_text]"
									value="<?php echo esc_attr( $s['label_text'] ); ?>"
									maxlength="80"
								>
							</label>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Three layout presets', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Selecting a preset applies matching values. You can fine-tune every detail afterwards.', 'ai-image-disclosure-labels' ); ?></p>

							<div class="gd-ai-presets">
								<label class="gd-ai-preset-card">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="subtle" <?php checked( $s['preset'], 'subtle' ); ?>>
									<span class="gd-ai-preset-visual gd-ai-preset-subtle"><?php echo esc_html( __( 'AI-generated', 'ai-image-disclosure-labels' ) ); ?></span>
									<strong><?php esc_html_e( 'Subtle badge', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Compact, dark, slightly transparent, with a fine border.', 'ai-image-disclosure-labels' ); ?></small>
								</label>

								<label class="gd-ai-preset-card">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="light" <?php checked( $s['preset'], 'light' ); ?>>
									<span class="gd-ai-preset-visual gd-ai-preset-light"><?php echo esc_html( __( 'AI-generated', 'ai-image-disclosure-labels' ) ); ?></span>
									<strong><?php esc_html_e( 'Light badge', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Near-white background, dark text and a crisp fine border.', 'ai-image-disclosure-labels' ); ?></small>
								</label>

								<label class="gd-ai-preset-card">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="pill" <?php checked( $s['preset'], 'pill' ); ?>>
									<span class="gd-ai-preset-visual gd-ai-preset-pill"><?php echo esc_html( __( 'AI-generated', 'ai-image-disclosure-labels' ) ); ?></span>
									<strong><?php esc_html_e( 'Pill badge', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Compact, slightly bolder and fully rounded.', 'ai-image-disclosure-labels' ); ?></small>
								</label>
							</div>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Position', 'ai-image-disclosure-labels' ); ?></h2>
							<div class="gd-ai-field-grid">
								<label class="gd-ai-field">
									<span><?php esc_html_e( 'Position on the image', 'ai-image-disclosure-labels' ); ?></span>
									<select id="gd-ai-position" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[position]">
										<option value="top-left" <?php selected( $s['position'], 'top-left' ); ?>><?php esc_html_e( 'Top left', 'ai-image-disclosure-labels' ); ?></option>
										<option value="top-right" <?php selected( $s['position'], 'top-right' ); ?>><?php esc_html_e( 'Top right', 'ai-image-disclosure-labels' ); ?></option>
										<option value="bottom-left" <?php selected( $s['position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'ai-image-disclosure-labels' ); ?></option>
										<option value="bottom-right" <?php selected( $s['position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'ai-image-disclosure-labels' ); ?></option>
									</select>
								</label>

								<?php
								$this->number_field( 'offset_horizontal', __( 'Horizontal offset', 'ai-image-disclosure-labels' ), $s['offset_horizontal'], 0, 60, 'px' );
								$this->number_field( 'offset_vertical', __( 'Vertical offset', 'ai-image-disclosure-labels' ), $s['offset_vertical'], 0, 60, 'px' );
								?>
							</div>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Visibility and symbol', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'The plugin measures the actually rendered width. On very small thumbnails the label can be omitted entirely, medium-sized images show only the symbol, and large images show the full text label.', 'ai-image-disclosure-labels' ); ?></p>
							<div class="gd-ai-field-grid">
								<?php
								$this->number_field( 'minimum_image_width', __( 'Minimum width for any label', 'ai-image-disclosure-labels' ), $s['minimum_image_width'], 0, 1200, 'px' );
								$this->number_field( 'minimum_text_width', __( 'Full text label from this width', 'ai-image-disclosure-labels' ), $s['minimum_text_width'], 0, 1600, 'px' );
								?>

								<label class="gd-ai-field">
									<span><?php esc_html_e( 'Between these widths', 'ai-image-disclosure-labels' ); ?></span>
									<select id="gd-ai-small-image-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[small_image_mode]">
										<option value="icon" <?php selected( $s['small_image_mode'], 'icon' ); ?>><?php esc_html_e( 'Show AI symbol', 'ai-image-disclosure-labels' ); ?></option>
										<option value="hide" <?php selected( $s['small_image_mode'], 'hide' ); ?>><?php esc_html_e( 'Show nothing', 'ai-image-disclosure-labels' ); ?></option>
									</select>
								</label>

								<label class="gd-ai-field">
									<span><?php esc_html_e( 'Symbol size', 'ai-image-disclosure-labels' ); ?></span>
									<span class="gd-ai-size-control">
										<input type="number" id="gd-ai-icon-size-value" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_size_value]" value="<?php echo esc_attr( $s['icon_size_value'] ); ?>" min="<?php echo 'percent' === $s['icon_size_unit'] ? '1' : '8'; ?>" max="<?php echo 'percent' === $s['icon_size_unit'] ? '30' : '80'; ?>" step="0.5">
										<select id="gd-ai-icon-size-unit" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_size_unit]">
											<option value="px" <?php selected( $s['icon_size_unit'], 'px' ); ?>>px</option>
											<option value="percent" <?php selected( $s['icon_size_unit'], 'percent' ); ?>><?php esc_html_e( '% of image width', 'ai-image-disclosure-labels' ); ?></option>
										</select>
									</span>
								</label>
							</div>

							<label class="gd-ai-toggle-field">
								<input type="checkbox" id="gd-ai-icon-tooltip-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_tooltip_enabled]" value="1" <?php checked( ! empty( $s['icon_tooltip_enabled'] ) ); ?>>
								<span>
									<strong><?php esc_html_e( 'Show disclosure text for compact symbols', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Desktop: hover or keyboard focus. Touch devices: tap to open, tap again or outside the symbol to close. This applies only while the compact symbol is shown.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>

							<h3><?php esc_html_e( 'AI symbol', 'ai-image-disclosure-labels' ); ?></h3>
							<div class="gd-ai-icon-choices">
								<label class="gd-ai-icon-choice">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_style]" value="monogram" <?php checked( $s['icon_style'], 'monogram' ); ?>>
									<span class="gd-ai-icon-choice__visual"><?php echo wp_kses( $this->icon_svg_markup( 'monogram' ), $this->allowed_icon_html() ); ?></span>
									<span><?php esc_html_e( 'AI Monogram', 'ai-image-disclosure-labels' ); ?></span>
								</label>
								<label class="gd-ai-icon-choice">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_style]" value="sparkle" <?php checked( $s['icon_style'], 'sparkle' ); ?>>
									<span class="gd-ai-icon-choice__visual"><?php echo wp_kses( $this->icon_svg_markup( 'sparkle' ), $this->allowed_icon_html() ); ?></span>
									<span><?php esc_html_e( 'Sparkle', 'ai-image-disclosure-labels' ); ?></span>
								</label>
								<label class="gd-ai-icon-choice">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_style]" value="chip" <?php checked( $s['icon_style'], 'chip' ); ?>>
									<span class="gd-ai-icon-choice__visual"><?php echo wp_kses( $this->icon_svg_markup( 'chip' ), $this->allowed_icon_html() ); ?></span>
									<span><?php esc_html_e( 'Chip', 'ai-image-disclosure-labels' ); ?></span>
								</label>
								<label class="gd-ai-icon-choice">
									<input type="radio" id="gd-ai-icon-style-custom" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_style]" value="custom" <?php checked( $s['icon_style'], 'custom' ); ?>>
									<span class="gd-ai-icon-choice__visual" id="gd-ai-custom-icon-card-preview">
										<?php if ( $custom_icon ) : ?>
											<img src="<?php echo esc_url( $custom_icon['url'] ); ?>" alt="">
										<?php else : ?>
											<span class="dashicons dashicons-upload"></span>
										<?php endif; ?>
									</span>
									<span><?php esc_html_e( 'Custom symbol', 'ai-image-disclosure-labels' ); ?></span>
								</label>
							</div>

							<input type="hidden" id="gd-ai-custom-icon-id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_icon_id]" value="<?php echo esc_attr( $s['custom_icon_id'] ); ?>">
							<div class="gd-ai-custom-icon-actions">
								<button type="button" class="button" id="gd-ai-select-custom-icon"><?php esc_html_e( 'Select PNG or SVG', 'ai-image-disclosure-labels' ); ?></button>
								<button type="button" class="button-link-delete" id="gd-ai-remove-custom-icon" <?php disabled( ! $custom_icon ); ?>><?php esc_html_e( 'Remove custom symbol', 'ai-image-disclosure-labels' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'SVG files can only be selected or uploaded if your WordPress installation safely allows SVG. This plugin does not enable SVG uploads globally. Percentage values refer to the actually rendered image width.', 'ai-image-disclosure-labels' ); ?></p>
							<p class="description"><strong><?php esc_html_e( 'Recommendation:', 'ai-image-disclosure-labels' ); ?></strong> <?php esc_html_e( '180 px for any label, 500 px for the full text label and 16 px for the symbol.', 'ai-image-disclosure-labels' ); ?></p>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Badge design', 'ai-image-disclosure-labels' ); ?></h2>
							<label class="gd-ai-custom-mode">
								<input type="radio" id="gd-ai-preset-custom" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="custom" <?php checked( $s['preset'], 'custom' ); ?>>
								<span><?php esc_html_e( 'Use custom design values', 'ai-image-disclosure-labels' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'As soon as you change a design value below, this mode is selected automatically. Position and offsets remain adjustable with every preset.', 'ai-image-disclosure-labels' ); ?></p>
							<p class="description"><?php esc_html_e( 'All font options resolve locally on the visitor\'s device. No font is ever loaded from Google Fonts or any other external server, so the badge stays GDPR-friendly and adds zero loading time.', 'ai-image-disclosure-labels' ); ?></p>
							<label class="gd-ai-toggle-field">
								<input type="checkbox" id="gd-ai-background-color-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[background_color_mode]" value="auto" <?php checked( 'auto', $s['background_color_mode'] ); ?>>
								<span>
									<strong><?php esc_html_e( 'Automatic color from the image', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'The badge uses the average color of each image so it blends in; the text switches automatically between dark and light for readability. The color is computed once per image and cached. When it cannot be determined, the fixed colors below are used.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>

							<div class="gd-ai-field-grid">
								<?php
								$this->color_field( 'background_color', __( 'Background color', 'ai-image-disclosure-labels' ), $s['background_color'] );
								$this->number_field( 'background_opacity', __( 'Background opacity', 'ai-image-disclosure-labels' ), $s['background_opacity'], 0, 100, '%' );
								$this->color_field( 'text_color', __( 'Text color', 'ai-image-disclosure-labels' ), $s['text_color'] );
								$this->color_field( 'border_color', __( 'Border color', 'ai-image-disclosure-labels' ), $s['border_color'] );
								$this->number_field( 'border_width', __( 'Border width', 'ai-image-disclosure-labels' ), $s['border_width'], 0, 5, 'px' );
								$this->number_field( 'border_radius', __( 'Corner radius', 'ai-image-disclosure-labels' ), $s['border_radius'], 0, 999, 'px' );
								$this->number_field( 'font_size', __( 'Font size', 'ai-image-disclosure-labels' ), $s['font_size'], 8, 24, 'px' );
								?>

								<label class="gd-ai-field">
									<span><?php esc_html_e( 'Font weight', 'ai-image-disclosure-labels' ); ?></span>
									<select id="gd-ai-font-weight" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_weight]">
										<option value="400" <?php selected( $s['font_weight'], 400 ); ?>><?php esc_html_e( 'Normal (400)', 'ai-image-disclosure-labels' ); ?></option>
										<option value="500" <?php selected( $s['font_weight'], 500 ); ?>><?php esc_html_e( 'Medium (500)', 'ai-image-disclosure-labels' ); ?></option>
										<option value="600" <?php selected( $s['font_weight'], 600 ); ?>><?php esc_html_e( 'Semibold (600)', 'ai-image-disclosure-labels' ); ?></option>
										<option value="700" <?php selected( $s['font_weight'], 700 ); ?>><?php esc_html_e( 'Bold (700)', 'ai-image-disclosure-labels' ); ?></option>
									</select>
								</label>

								<?php
								$this->number_field( 'padding_vertical', __( 'Vertical padding', 'ai-image-disclosure-labels' ), $s['padding_vertical'], 0, 20, 'px' );
								$this->number_field( 'padding_horizontal', __( 'Horizontal padding', 'ai-image-disclosure-labels' ), $s['padding_horizontal'], 0, 30, 'px' );
								?>

								<label class="gd-ai-field">
									<span><?php esc_html_e( 'Font', 'ai-image-disclosure-labels' ); ?></span>
									<select id="gd-ai-font-family-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family_mode]">
										<option value="inherit" <?php selected( $s['font_family_mode'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from theme (default)', 'ai-image-disclosure-labels' ); ?></option>
										<option value="system-sans" <?php selected( $s['font_family_mode'], 'system-sans' ); ?>><?php esc_html_e( 'System sans-serif', 'ai-image-disclosure-labels' ); ?></option>
										<option value="system-serif" <?php selected( $s['font_family_mode'], 'system-serif' ); ?>><?php esc_html_e( 'System serif', 'ai-image-disclosure-labels' ); ?></option>
										<option value="system-mono" <?php selected( $s['font_family_mode'], 'system-mono' ); ?>><?php esc_html_e( 'System monospace', 'ai-image-disclosure-labels' ); ?></option>
										<option value="custom" <?php selected( $s['font_family_mode'], 'custom' ); ?>><?php esc_html_e( 'Custom font stack', 'ai-image-disclosure-labels' ); ?></option>
									</select>
								</label>

								<label class="gd-ai-field" id="gd-ai-font-family-custom-field" <?php echo 'custom' === $s['font_family_mode'] ? '' : 'hidden'; ?>>
									<span><?php esc_html_e( 'Custom font stack', 'ai-image-disclosure-labels' ); ?></span>
									<input
										type="text"
										id="gd-ai-font-family-custom"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family_custom]"
										value="<?php echo esc_attr( $s['font_family_custom'] ); ?>"
										placeholder="'Inter', 'Segoe UI', sans-serif"
									>
								</label>

								<label class="gd-ai-field">
									<span><?php esc_html_e( 'Letter case', 'ai-image-disclosure-labels' ); ?></span>
									<select id="gd-ai-text-transform" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[text_transform]">
										<option value="none" <?php selected( $s['text_transform'], 'none' ); ?>><?php esc_html_e( 'As entered', 'ai-image-disclosure-labels' ); ?></option>
										<option value="uppercase" <?php selected( $s['text_transform'], 'uppercase' ); ?>><?php esc_html_e( 'UPPERCASE', 'ai-image-disclosure-labels' ); ?></option>
									</select>
								</label>
							</div>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Theme integration', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'The plugin already works with the WordPress default themes and common featured-image markup. If your theme uses an unusual wrapper for the featured image, add its CSS selector here (one per line). Selectors are tried from top to bottom before the built-in ones.', 'ai-image-disclosure-labels' ); ?></p>
							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'Additional featured-image selectors', 'ai-image-disclosure-labels' ); ?></span>
								<textarea
									id="gd-ai-custom-selectors"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_selectors]"
									rows="3"
									placeholder=".my-theme-hero img.wp-post-image"
									spellcheck="false"
								><?php echo esc_textarea( $s['custom_selectors'] ); ?></textarea>
							</label>
							<p class="description"><?php esc_html_e( 'Each selector should target the featured image element itself, for example ".hero-media img.wp-post-image". Leave empty if labels already appear correctly.', 'ai-image-disclosure-labels' ); ?></p>
						</section>

						<?php submit_button( __( 'Save settings', 'ai-image-disclosure-labels' ) ); ?>
					</main>

					<aside class="gd-ai-settings-preview">
						<div class="gd-ai-card gd-ai-sticky">
							<h2><?php esc_html_e( 'Live preview', 'ai-image-disclosure-labels' ); ?></h2>
							<div id="gd-ai-preview" class="gd-ai-preview">
								<div class="gd-ai-preview-scene" aria-hidden="true">
									<span class="gd-ai-preview-sun"></span>
									<span class="gd-ai-preview-hill gd-ai-preview-hill-one"></span>
									<span class="gd-ai-preview-hill gd-ai-preview-hill-two"></span>
								</div>
								<span id="gd-ai-preview-label" class="gd-ai-preview-label"><?php echo esc_html( $s['label_text'] ); ?></span>
							</div>

							<h3><?php esc_html_e( 'Symbol preview', 'ai-image-disclosure-labels' ); ?></h3>
							<div id="gd-ai-symbol-preview" class="gd-ai-symbol-preview">
								<span id="gd-ai-preview-symbol" class="gd-ai-preview-symbol" tabindex="<?php echo ! empty( $s['icon_tooltip_enabled'] ) ? '0' : '-1'; ?>" role="button" aria-expanded="false" aria-disabled="<?php echo ! empty( $s['icon_tooltip_enabled'] ) ? 'false' : 'true'; ?>">
									<span id="gd-ai-preview-symbol-icon" class="gd-ai-preview-symbol-icon"><?php echo wp_kses( $this->icon_markup( $s['icon_style'] ), $this->allowed_icon_html() ); ?></span>
									<span id="gd-ai-preview-symbol-tooltip" class="gd-ai-preview-symbol-tooltip"><?php echo esc_html( $s['label_text'] ); ?></span>
								</span>
							</div>
							<p class="description"><?php esc_html_e( 'The upper preview shows the full text label. Below it you can see the symbol view used on medium-sized images.', 'ai-image-disclosure-labels' ); ?></p>
						</div>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a color input.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private function color_field( $key, $label, $value ) {
		printf(
			'<label class="gd-ai-field"><span>%1$s</span><input type="color" id="gd-ai-%2$s" name="%3$s[%2$s]" value="%4$s"></label>',
			esc_html( $label ),
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value )
		);
	}

	/**
	 * Render a number input.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @param string $unit  Unit.
	 * @return void
	 */
	private function number_field( $key, $label, $value, $min, $max, $unit ) {
		printf(
			'<label class="gd-ai-field"><span>%1$s</span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-%2$s" name="%3$s[%4$s]" value="%5$d" min="%6$d" max="%7$d" step="1"><em>%8$s</em></span></label>',
			esc_html( $label ),
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			(int) $value,
			(int) $min,
			(int) $max,
			esc_html( $unit )
		);
	}

	/**
	 * Build dynamic front-end or editor CSS.
	 *
	 * @param bool $editor Whether this is the editor preview.
	 * @return string
	 */
	private function dynamic_badge_css( $editor = false ) {
		$s = $this->settings();

		$background_color = sanitize_hex_color( isset( $s['background_color'] ) ? $s['background_color'] : '' );
		$text_color       = sanitize_hex_color( isset( $s['text_color'] ) ? $s['text_color'] : '' );
		$border_color     = sanitize_hex_color( isset( $s['border_color'] ) ? $s['border_color'] : '' );
		$text_transform   = isset( $s['text_transform'] ) && in_array( $s['text_transform'], array( 'none', 'uppercase' ), true ) ? $s['text_transform'] : 'none';

		$background_color = $background_color ? $background_color : '#171717';
		$text_color       = $text_color ? $text_color : '#ffffff';
		$border_color     = $border_color ? $border_color : '#ffffff';

		$alpha = round( max( 0, min( 100, (int) $s['background_opacity'] ) ) / 100, 2 );
		$bg    = $this->hex_to_rgba( $background_color, $alpha );
		$top   = 0 === strpos( $s['position'], 'top-' );
		$left  = false !== strpos( $s['position'], '-left' );

		$top_value    = $top ? (int) $s['offset_vertical'] . 'px' : 'auto';
		$bottom_value = $top ? 'auto' : (int) $s['offset_vertical'] . 'px';
		$left_value   = $left ? (int) $s['offset_horizontal'] . 'px' : 'auto';
		$right_value  = $left ? 'auto' : (int) $s['offset_horizontal'] . 'px';

		$font_stacks = self::font_stacks();
		$font_mode   = isset( $s['font_family_mode'] ) ? $s['font_family_mode'] : 'inherit';
		$font_family = '';

		if ( 'custom' === $font_mode && ! empty( $s['font_family_custom'] ) ) {
			$font_family = preg_replace( "/[^A-Za-z0-9,\-' ]/", '', $s['font_family_custom'] );
		} elseif ( isset( $font_stacks[ $font_mode ] ) ) {
			$font_family = $font_stacks[ $font_mode ];
		}

		$tooltip_top    = $top ? 'calc(100% + 6px)' : 'auto';
		$tooltip_bottom = $top ? 'auto' : 'calc(100% + 6px)';
		$tooltip_left   = $left ? '0' : 'auto';
		$tooltip_right  = $left ? 'auto' : '0';

		$icon_size_value = isset( $s['icon_size_value'] ) ? (float) $s['icon_size_value'] : 16;
		$icon_size_unit  = isset( $s['icon_size_unit'] ) && 'percent' === $s['icon_size_unit'] ? 'percent' : 'px';
		$icon_size_css   = 'percent' === $icon_size_unit
			? rtrim( rtrim( number_format( $icon_size_value, 2, '.', '' ), '0' ), '.' ) . 'cqw'
			: rtrim( rtrim( number_format( $icon_size_value, 2, '.', '' ), '0' ), '.' ) . 'px';

		$target = $editor
			? '.gd-ai-editor-preview'
			: 'html body .gd-ai-image-frame, html body .gd-ai-featured-theme-fallback';

		$css = sprintf(
			'%1$s{--gd-ai-label-bg:%2$s;--gd-ai-label-color:%3$s;--gd-ai-label-border-width:%4$dpx;--gd-ai-label-border-color:%5$s;--gd-ai-label-radius:%6$dpx;--gd-ai-label-font-size:%7$dpx;--gd-ai-label-font-weight:%8$d;--gd-ai-label-padding-y:%9$dpx;--gd-ai-label-padding-x:%10$dpx;--gd-ai-label-transform:%11$s;--gd-ai-label-top:%12$s;--gd-ai-label-right:%13$s;--gd-ai-label-bottom:%14$s;--gd-ai-label-left:%15$s;--gd-ai-label-icon-size:%16$s;--gd-ai-label-icon-padding-y:%17$dpx;--gd-ai-label-icon-padding-x:%18$dpx;--gd-ai-tooltip-top:%19$s;--gd-ai-tooltip-right:%20$s;--gd-ai-tooltip-bottom:%21$s;--gd-ai-tooltip-left:%22$s;%23$s}',
			$target,
			$bg,
			$text_color,
			max( 0, min( 5, (int) $s['border_width'] ) ),
			$border_color,
			max( 0, min( 999, (int) $s['border_radius'] ) ),
			max( 8, min( 24, (int) $s['font_size'] ) ),
			in_array( (int) $s['font_weight'], array( 400, 500, 600, 700 ), true ) ? (int) $s['font_weight'] : 600,
			max( 0, min( 20, (int) $s['padding_vertical'] ) ),
			max( 0, min( 30, (int) $s['padding_horizontal'] ) ),
			$text_transform,
			$top_value,
			$right_value,
			$bottom_value,
			$left_value,
			$icon_size_css,
			max( 2, (int) $s['padding_vertical'] ),
			max( 3, (int) $s['padding_horizontal'] - 1 ),
			$tooltip_top,
			$tooltip_right,
			$tooltip_bottom,
			$tooltip_left,
			'' !== $font_family ? '--gd-ai-label-font-family:' . $font_family . ';' : ''
		);

		if ( ! $editor ) {
			$minimum_width = isset( $s['minimum_image_width'] ) ? max( 0, (int) $s['minimum_image_width'] ) : 0;
			$text_width    = isset( $s['minimum_text_width'] ) ? max( 0, (int) $s['minimum_text_width'] ) : 0;
			$mode          = isset( $s['small_image_mode'] ) && 'hide' === $s['small_image_mode'] ? 'hide' : 'icon';
			$label_selector = '.gd-ai-image-label';

			/*
			 * Flash prevention. Server-rendered labels start without a size
			 * class, which used to make the full text visible for a moment on
			 * small images inside wrappers that are not query containers,
			 * until the JavaScript fallback had measured them.
			 *
			 * With active width thresholds every label starts hidden. Two
			 * reveal paths exist:
			 *
			 * 1. The @container rule below matches any frame that is a query
			 *    container, so those labels are revealed before first paint
			 *    and the existing container queries decide text vs. symbol.
			 * 2. Frames without a query container stay hidden until the
			 *    JavaScript fallback has measured the image and applied a
			 *    size class, so the label appears in its correct form
			 *    immediately instead of flashing text first.
			 *
			 * The JavaScript file is always enqueued when these thresholds
			 * are active, so path 2 is guaranteed to run. With thresholds
			 * disabled no hiding rule is emitted at all.
			 */
			if ( 0 < $minimum_width || 0 < $text_width ) {
				$css .= sprintf( '%s{visibility:hidden;}', $label_selector );
				$css .= sprintf(
					'@container gd-ai-label-frame (min-width:0px){%s{visibility:visible;}}',
					$label_selector
				);
				$css .= sprintf(
					'%1$s.gd-ai-label-size-text,%1$s.gd-ai-label-size-icon{visibility:visible;}',
					$label_selector
				);
			}

			if ( 0 < $minimum_width ) {
				$css .= sprintf(
					'@container gd-ai-label-frame (max-width:%1$dpx){%2$s{display:none;}}',
					max( 0, $minimum_width - 1 ),
					$label_selector
				);
			}

			if ( $text_width > $minimum_width ) {
				$range_start = $minimum_width;
				$range_end   = max( $range_start, $text_width - 1 );

				if ( 'icon' === $mode ) {
					$css .= sprintf(
						'@container gd-ai-label-frame (min-width:%1$dpx) and (max-width:%2$dpx){%3$s{display:inline-flex;padding:var(--gd-ai-label-icon-padding-y,3px) var(--gd-ai-label-icon-padding-x,4px);min-width:calc(var(--gd-ai-label-icon-size,16px) + (var(--gd-ai-label-icon-padding-x,4px) * 2));}%3$s > .gd-ai-label-icon{display:block;}%3$s > .gd-ai-label-trigger{display:inline-flex;}%3$s .gd-ai-label-trigger .gd-ai-label-icon{display:block;}%3$s > .gd-ai-label-text{display:none;}}',
						$range_start,
						$range_end,
						$label_selector
					);
				} else {
					$css .= sprintf(
						'@container gd-ai-label-frame (min-width:%1$dpx) and (max-width:%2$dpx){%3$s{display:none;}}',
						$range_start,
						$range_end,
						$label_selector
					);
				}
			}
		}

		return $css;
	}

	/**
	 * Convert a hex color to rgba().
	 *
	 * @param string $hex   Hex color.
	 * @param float  $alpha Alpha value.
	 * @return string
	 */
	private function hex_to_rgba( $hex, $alpha ) {
		$hex = ltrim( (string) $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) ) {
			$hex = '171717';
		}

		return sprintf(
			'rgba(%d,%d,%d,%s)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' )
		);
	}

	/**
	 * React to settings changes.
	 *
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function handle_settings_update( $old_value, $value, $option ) {
		unset( $old_value, $value, $option );
		self::$settings_cache = null;

		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION_KEY, true );
		}

		$this->maybe_purge_page_cache();
	}

	/**
	 * Keep the small frontend fallback available when WP Rocket delays scripts.
	 *
	 * @param array $exclusions Existing exclusions.
	 * @return array
	 */
	public function exclude_frontend_script_from_delay( $exclusions ) {
		$exclusions = is_array( $exclusions ) ? $exclusions : array();
		$exclusions[] = 'gd-ai-image-labels-frontend';
		$exclusions[] = '/gd-ai-image-labels/assets/frontend.js';

		return array_values( array_unique( $exclusions ) );
	}

	/**
	 * Add settings shortcut.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=gd-ai-image-labels' ) ) . '">' . esc_html__( 'Settings', 'ai-image-disclosure-labels' ) . '</a>'
		);

		return $links;
	}
}
