<?php
/**
 * Optional AI-assisted Media Library analysis.
 *
 * @package GDAIIDL_AI_Analysis
 */

defined( 'ABSPATH' ) || exit;

final class GDAIIDL_AI_Analysis {

	const OPTION_KEY         = 'gdaiidl_ai_analysis_settings';
	const SECRET_OPTION      = 'gdaiidl_ai_analysis_secrets';
	const JOBS_OPTION        = 'gdaiidl_ai_analysis_jobs';
	const COST_STATS_OPTION  = 'gdaiidl_ai_analysis_cost_stats';
	const META_RESULT        = '_gdaiidl_ai_analysis';
	const META_CLASS         = '_gdaiidl_ai_analysis_class';
	const CRON_HOOK          = 'gdaiidl_process_ai_analysis_job';

	/** @var GDAIIDL_AI_Analysis|null */
	private static $instance = null;

	/** @var array|null */
	private $settings_cache = null;

	/** @var array|null */
	private $secrets_cache = null;

	/**
	 * Singleton.
	 *
	 * @return GDAIIDL_AI_Analysis
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation defaults. Secret/job options are deliberately non-autoloaded.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
		}
		if ( false === get_option( self::SECRET_OPTION, false ) ) {
			add_option( self::SECRET_OPTION, array(), '', false );
		}
		if ( false === get_option( self::JOBS_OPTION, false ) ) {
			add_option( self::JOBS_OPTION, array(), '', false );
		}
		if ( false === get_option( self::COST_STATS_OPTION, false ) ) {
			add_option( self::COST_STATS_OPTION, array(), '', false );
		}

		if ( function_exists( 'wp_set_options_autoload' ) ) {
			wp_set_options_autoload(
				array( self::OPTION_KEY, self::SECRET_OPTION, self::JOBS_OPTION, self::COST_STATS_OPTION ),
				false
			);
		}
	}

	/**
	 * Defaults contain no model names and no provider prices.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'                         => false,
			'provider'                        => 'custom',
			'model'                           => '',
			'cloudflare_account_id'           => '',
			'custom_endpoint'                 => '',
			'compatible_endpoint'             => '',
			'compatible_models_endpoint'      => '',
			'analysis_max_dimension'          => 1024,
			'auto_analyze_uploads'            => false,
			'auto_apply_visual'               => false,
			'auto_apply_verified'             => false,
			'auto_apply_threshold'            => 95,
			'max_images_per_job'              => 500,
			'batch_size'                      => 3,
			'max_known_cost_usd'              => 5.0,
			'pricing_mode'                    => 'auto',
			'manual_input_per_million_usd'    => '',
			'manual_output_per_million_usd'   => '',
			'manual_fixed_per_request_usd'    => '',
		);
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'ensure_storage' ), 2 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'register_privacy_policy_content' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_filter( 'manage_media_columns', array( $this, 'add_analysis_column' ), 20, 2 );
		add_action( 'manage_media_custom_column', array( $this, 'render_analysis_column' ), 20, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ), 20 );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 20, 3 );
		add_action( 'restrict_manage_posts', array( $this, 'render_analysis_filter' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_media_library' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_bulk_notice' ) );

		add_action( 'add_attachment', array( $this, 'maybe_queue_new_attachment' ), 30 );
		add_action( self::CRON_HOOK, array( $this, 'process_job' ), 10, 1 );

		add_action( 'wp_ajax_gdaiidl_ai_fetch_models', array( $this, 'ajax_fetch_models' ) );
		add_action( 'wp_ajax_gdaiidl_ai_test_model', array( $this, 'ajax_test_model' ) );
		add_action( 'wp_ajax_gdaiidl_ai_prepare_job', array( $this, 'ajax_prepare_job' ) );
		add_action( 'wp_ajax_gdaiidl_ai_start_job', array( $this, 'ajax_start_job' ) );
		add_action( 'wp_ajax_gdaiidl_ai_job_status', array( $this, 'ajax_job_status' ) );
		add_action( 'wp_ajax_gdaiidl_ai_cancel_job', array( $this, 'ajax_cancel_job' ) );
	}

	/** Ensure non-autoloaded storage exists after plugin updates. */
	public function ensure_storage() {
		foreach ( array( self::OPTION_KEY, self::SECRET_OPTION, self::JOBS_OPTION, self::COST_STATS_OPTION ) as $option ) {
			if ( false === get_option( $option, false ) ) {
				self::activate();
				break;
			}
		}
	}

	/**
	 * Provider labels.
	 *
	 * @return array
	 */
	private function providers() {
		$providers = array();

		if ( $this->has_wordpress_ai_client() ) {
			$providers['wordpress_ai_client'] = __( 'WordPress AI Client (configured Connectors)', 'ai-image-disclosure-labels' );
		}

		$providers += array(
			'openai'            => __( 'OpenAI API', 'ai-image-disclosure-labels' ),
			'gemini'            => __( 'Google Gemini API', 'ai-image-disclosure-labels' ),
			'anthropic'         => __( 'Anthropic Claude API', 'ai-image-disclosure-labels' ),
			'cloudflare'        => __( 'Cloudflare Workers AI', 'ai-image-disclosure-labels' ),
			'openai_compatible' => __( 'OpenAI-compatible HTTPS endpoint', 'ai-image-disclosure-labels' ),
			'custom'            => __( 'Custom HTTPS analysis endpoint', 'ai-image-disclosure-labels' ),
		);

		if ( $this->has_private_gd_cloudflare_connector() ) {
			$providers['gd_cloudflare_connector'] = __( 'GD Cloudflare AI Connector', 'ai-image-disclosure-labels' );
		}

		return $providers;
	}

	/**
	 * Whether the WordPress 7.0+ AI Client has at least one registered provider plugin.
	 *
	 * Credentials remain owned by WordPress Core's Connectors API. This plugin never
	 * reads or duplicates those stored API keys.
	 *
	 * @return bool
	 */
	private function has_wordpress_ai_client() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! is_object( $registry ) || ! method_exists( $registry, 'getRegisteredProviderIds' ) ) {
				return false;
			}
			$provider_ids = $registry->getRegisteredProviderIds();
			return is_array( $provider_ids ) && ! empty( $provider_ids );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Check whether WordPress AI Client can currently route our image-input text task.
	 * The support check is deterministic and does not make an inference request.
	 *
	 * @param string $model_preference Optional model preference.
	 * @return bool
	 */
	private function wordpress_ai_client_supports_analysis( $model_preference = '' ) {
		if ( ! $this->has_wordpress_ai_client() || ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) ) {
			return false;
		}

		try {
			$test_image = $this->compatibility_test_image();
			$data_uri   = 'data:' . $test_image['mime_type'] . ';base64,' . $test_image['base64'];
			$builder    = wp_ai_client_prompt()
				->with_text( 'Check image-input support.' )
				->with_file( $data_uri );
			if ( '' !== trim( (string) $model_preference ) ) {
				$builder->using_model_preference( trim( (string) $model_preference ) );
			}
			return (bool) $builder->is_supported_for_text_generation();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Whether the private GD Cloudflare AI Connector exposes the API needed here.
	 *
	 * Nothing related to this private integration is shown unless all required
	 * connector entry points are actually available on the current site.
	 *
	 * @return bool
	 */
	private function has_private_gd_cloudflare_connector() {
		return defined( 'GD_CLOUDFLARE_AI_CONNECTOR_VERSION' )
			&& function_exists( '\\GD\\CloudflareAIConnector\\get_api_token' )
			&& function_exists( '\\GD\\CloudflareAIConnector\\get_account_id' )
			&& function_exists( '\\GD\\CloudflareAIConnector\\available_text_models' )
			&& class_exists( '\\GD\\CloudflareAIConnector\\HTTP\\Direct_Client' )
			&& method_exists( '\\GD\\CloudflareAIConnector\\HTTP\\Direct_Client', 'generateText' );
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	private function settings() {
		if ( is_array( $this->settings_cache ) ) {
			return $this->settings_cache;
		}

		$saved = get_option( self::OPTION_KEY, array() );
		$this->settings_cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

		return $this->settings_cache;
	}

	/**
	 * Get stored secrets without ever exposing them to JavaScript.
	 *
	 * @return array
	 */
	private function secrets() {
		if ( is_array( $this->secrets_cache ) ) {
			return $this->secrets_cache;
		}

		$value = get_option( self::SECRET_OPTION, array() );
		$this->secrets_cache = is_array( $value ) ? $value : array();

		return $this->secrets_cache;
	}

	/**
	 * Return a secret, with wp-config.php constants taking precedence.
	 *
	 * @param string $provider Provider key.
	 * @return string
	 */
	private function api_key( $provider ) {
		$constants = array(
			'openai'            => 'GDAIIDL_OPENAI_API_KEY',
			'gemini'            => 'GDAIIDL_GEMINI_API_KEY',
			'anthropic'         => 'GDAIIDL_ANTHROPIC_API_KEY',
			'cloudflare'        => 'GDAIIDL_CLOUDFLARE_API_TOKEN',
			'openai_compatible' => 'GDAIIDL_OPENAI_COMPATIBLE_API_KEY',
			'custom'            => 'GDAIIDL_CUSTOM_API_TOKEN',
		);

		if ( isset( $constants[ $provider ] ) && defined( $constants[ $provider ] ) ) {
			$value = constant( $constants[ $provider ] );
			return is_string( $value ) ? trim( $value ) : '';
		}

		$secrets = $this->secrets();
		$key     = $provider . '_api_key';

		return isset( $secrets[ $key ] ) && is_string( $secrets[ $key ] ) ? trim( $secrets[ $key ] ) : '';
	}

	/**
	 * Register normal and secret options.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'gdaiidl_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		register_setting(
			'gdaiidl_settings_group',
			self::SECRET_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_secrets' ),
			)
		);
	}

	/**
	 * Sanitize AI-analysis settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$output['enabled'] = ! empty( $input['enabled'] );
		$providers = $this->providers();
		$provider = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : $defaults['provider'];
		$output['provider'] = isset( $providers[ $provider ] ) ? $provider : $defaults['provider'];
		$output['model'] = isset( $input['model'] ) ? substr( sanitize_text_field( $input['model'] ), 0, 220 ) : '';
		$output['cloudflare_account_id'] = isset( $input['cloudflare_account_id'] ) ? substr( sanitize_text_field( $input['cloudflare_account_id'] ), 0, 120 ) : '';
		$output['custom_endpoint'] = isset( $input['custom_endpoint'] ) ? $this->sanitize_https_endpoint( $input['custom_endpoint'] ) : '';
		$output['compatible_endpoint'] = isset( $input['compatible_endpoint'] ) ? $this->sanitize_https_endpoint( $input['compatible_endpoint'] ) : '';
		$output['compatible_models_endpoint'] = isset( $input['compatible_models_endpoint'] ) ? $this->sanitize_https_endpoint( $input['compatible_models_endpoint'] ) : '';

		$output['analysis_max_dimension'] = $this->clamp_int( $input, 'analysis_max_dimension', 256, 2048, 1024 );
		$output['auto_analyze_uploads'] = ! empty( $input['auto_analyze_uploads'] );
		$output['auto_apply_visual'] = ! empty( $input['auto_apply_visual'] );
		$output['auto_apply_verified'] = ! empty( $input['auto_apply_verified'] );
		$output['auto_apply_threshold'] = $this->clamp_int( $input, 'auto_apply_threshold', 50, 100, 95 );
		$output['max_images_per_job'] = $this->clamp_int( $input, 'max_images_per_job', 0, 100000, 500 );
		$output['batch_size'] = $this->clamp_int( $input, 'batch_size', 1, 10, 3 );
		$output['max_known_cost_usd'] = $this->clamp_float( $input, 'max_known_cost_usd', 0, 100000, 5.0 );

		$pricing_modes = array( 'auto', 'manual_tokens', 'fixed', 'none' );
		$pricing_mode = isset( $input['pricing_mode'] ) ? sanitize_key( $input['pricing_mode'] ) : 'auto';
		$output['pricing_mode'] = in_array( $pricing_mode, $pricing_modes, true ) ? $pricing_mode : 'auto';

		foreach ( array( 'manual_input_per_million_usd', 'manual_output_per_million_usd', 'manual_fixed_per_request_usd' ) as $key ) {
			$output[ $key ] = '';
			if ( isset( $input[ $key ] ) && '' !== trim( (string) $input[ $key ] ) && is_numeric( $input[ $key ] ) ) {
				$output[ $key ] = (string) max( 0, (float) $input[ $key ] );
			}
		}

		$this->settings_cache = null;
		delete_transient( $this->model_cache_key( $output['provider'], $output ) );

		return $output;
	}

	/**
	 * Sanitize secrets. Blank fields preserve an existing key; explicit clear
	 * checkboxes remove it.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_secrets( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$existing = $this->secrets();
		$output   = $existing;

		$constant_map = array(
			'openai' => 'GDAIIDL_OPENAI_API_KEY', 'gemini' => 'GDAIIDL_GEMINI_API_KEY',
			'anthropic' => 'GDAIIDL_ANTHROPIC_API_KEY', 'cloudflare' => 'GDAIIDL_CLOUDFLARE_API_TOKEN',
			'openai_compatible' => 'GDAIIDL_OPENAI_COMPATIBLE_API_KEY', 'custom' => 'GDAIIDL_CUSTOM_API_TOKEN',
		);

		foreach ( array_keys( $this->providers() ) as $provider ) {
			$key       = $provider . '_api_key';
			if ( isset( $constant_map[ $provider ] ) && defined( $constant_map[ $provider ] ) ) {
				unset( $output[ $key ] );
				continue;
			}
			$clear_key = 'clear_' . $key;

			if ( ! empty( $input[ $clear_key ] ) ) {
				unset( $output[ $key ] );
				continue;
			}

			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) {
				$value = trim( wp_unslash( $input[ $key ] ) );
				if ( '' !== $value ) {
					$output[ $key ] = substr( preg_replace( '/[\x00-\x1F\x7F]/', '', $value ), 0, 1000 );
				}
			}
		}

		$this->secrets_cache = null;
		return $output;
	}

	/**
	 * Sanitize an administrator-supplied external endpoint. API credentials and
	 * image payloads must never be sent over clear-text HTTP.
	 *
	 * @param mixed $value Candidate URL.
	 * @return string
	 */
	private function sanitize_https_endpoint( $value ) {
		$url = esc_url_raw( trim( (string) $value ), array( 'https' ) );
		if ( '' === $url || 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			return '';
		}

		return substr( $url, 0, 2048 );
	}

	/** @return int */
	private function clamp_int( $input, $key, $min, $max, $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return (int) $default;
		}
		return max( $min, min( $max, (int) $input[ $key ] ) );
	}

	/** @return float */
	private function clamp_float( $input, $key, $min, $max, $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return (float) $default;
		}
		return max( (float) $min, min( (float) $max, (float) $input[ $key ] ) );
	}

	/**
	 * Suggest privacy-policy text when external AI analysis may be used.
	 *
	 * @return void
	 */
	public function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( 'When optional AI-assisted image analysis is enabled and an analysis is requested, this site may send a temporary resized copy of the selected Media Library image together with a short classification prompt to the external AI provider configured by the site administrator. The original Media Library file is not modified by this analysis.', 'ai-image-disclosure-labels' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The provider may receive image content that contains personal data. The site stores the returned classification suggestion, confidence, provider/model identifiers, limited explanation, usage/cost metadata and analysis time in WordPress attachment metadata. API credentials are used server-side and are not exposed to visitors or localized to browser JavaScript. No image is sent by this feature unless analysis is requested or automatic analysis of new uploads has been explicitly enabled.', 'ai-image-disclosure-labels' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Data handling, retention, geographic processing and billing are governed by the terms and privacy documentation of the configured external provider or custom endpoint. Site administrators should review those policies before enabling the feature and should update this privacy notice to identify the provider they actually use.', 'ai-image-disclosure-labels' ) . '</p>';

		wp_add_privacy_policy_content( __( 'AI Image & Video Disclosure Labels – optional external AI analysis', 'ai-image-disclosure-labels' ), wp_kses_post( $content ) );
	}

	/**
	 * Render the settings card inside the plugin's main settings form.
	 *
	 * @return void
	 */
	public function render_settings_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s         = $this->settings();
		$providers = $this->providers();
		$nonce     = wp_create_nonce( 'gdaiidl_ai_admin' );
		?>
		<section class="gd-ai-card gdaiidl-ai-analysis-card" id="gdaiidl-ai-analysis">
			<h2><?php esc_html_e( 'AI-assisted image analysis', 'ai-image-disclosure-labels' ); ?> <span class="gdaiidl-experimental-chip"><?php esc_html_e( 'Experimental', 'ai-image-disclosure-labels' ); ?></span></h2>
			<p><?php esc_html_e( 'Experimental, optional assistance for assessing whether an image looks AI-generated or AI-modified. The result is stored as an analysis suggestion, separate from the publisher-facing AI status. Keep manual editorial review in the workflow.', 'ai-image-disclosure-labels' ); ?></p>
			<div class="gd-ai-notice gdaiidl-ai-caution">
				<strong><?php esc_html_e( 'AI detection is evidence, not proof.', 'ai-image-disclosure-labels' ); ?></strong>
				<p><?php esc_html_e( 'General vision models can produce false positives and false negatives. “Probably not AI” never becomes “No AI used” automatically. The plugin only auto-applies AI-generated or AI-modified suggestions when you explicitly enable that option and the configured confidence threshold is met.', 'ai-image-disclosure-labels' ); ?></p>
				<p><?php esc_html_e( 'Built-in provider adapters perform visual AI analysis; they do not pretend to cryptographically verify C2PA, SynthID or other proprietary watermarks. A custom endpoint is a service you operate or choose yourself. Only such an endpoint can report technical provenance verification here, and only when it actually checks a provenance or watermark signal rather than asking a vision model to guess.', 'ai-image-disclosure-labels' ); ?></p>
			</div>

			<label class="gd-ai-toggle-field">
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
				<span><strong><?php esc_html_e( 'Enable external AI analysis', 'ai-image-disclosure-labels' ); ?></strong><small><?php esc_html_e( 'Images are sent to the configured external service only when analysis is requested or automatic analysis of new uploads is enabled. Provider API charges and privacy terms apply.', 'ai-image-disclosure-labels' ); ?></small></span>
			</label>

			<div class="gd-ai-field-grid gdaiidl-ai-provider-grid">
				<label class="gd-ai-field">
					<span><?php esc_html_e( 'Provider', 'ai-image-disclosure-labels' ); ?></span>
					<select id="gdaiidl-ai-provider" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]">
						<?php foreach ( $providers as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<div class="gd-ai-field gd-ai-field-wide gdaiidl-ai-model-control">
					<label for="gdaiidl-ai-model"><span id="gdaiidl-ai-model-label"><?php esc_html_e( 'Model / policy ID (manual entry)', 'ai-image-disclosure-labels' ); ?></span></label>
					<input type="text" id="gdaiidl-ai-model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $s['model'] ); ?>" maxlength="220" autocomplete="off">
					<small id="gdaiidl-ai-model-help"><?php esc_html_e( 'You can type or paste an exact model/policy ID here. Or fetch the current catalogue below and choose a model there; the selected catalogue ID is copied into this field.', 'ai-image-disclosure-labels' ); ?></small>

					<div class="gdaiidl-ai-catalogue-control">
						<label for="gdaiidl-ai-model-filter"><span><?php esc_html_e( 'Search fetched catalogue', 'ai-image-disclosure-labels' ); ?></span></label>
						<div class="gdaiidl-ai-catalogue-combobox">
							<input type="search" id="gdaiidl-ai-model-filter" disabled role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="gdaiidl-ai-model-results" placeholder="<?php echo esc_attr__( 'Type to search the fetched catalogue…', 'ai-image-disclosure-labels' ); ?>">
							<div id="gdaiidl-ai-model-results" class="gdaiidl-ai-model-results" role="listbox" hidden></div>
						</div>
						<small><?php esc_html_e( 'After fetching, click this field to open the alphabetically sorted catalogue or start typing to filter it immediately. Choosing a result copies the exact runtime model ID into the manual field above. The plugin does not hard-code, rename, rank or substitute model IDs.', 'ai-image-disclosure-labels' ); ?></small>
					</div>

					<div class="gdaiidl-ai-model-actions">
						<button type="button" class="button" id="gdaiidl-ai-fetch-models"><?php esc_html_e( 'Fetch current models', 'ai-image-disclosure-labels' ); ?></button>
						<button type="button" class="button" id="gdaiidl-ai-test-model"><?php esc_html_e( 'Test selected model', 'ai-image-disclosure-labels' ); ?></button>
						<button type="button" class="button" id="gdaiidl-ai-reset-model"><?php esc_html_e( 'Reset model', 'ai-image-disclosure-labels' ); ?></button>
					</div>

					<small><?php esc_html_e( 'Vision-capable does not automatically mean suitable for synthetic-media detection. Check the selected provider/model’s current capability documentation and validate it on your own material before enabling automatic classification. Some general multimodal providers explicitly warn against relying on their vision models for AI-image detection.', 'ai-image-disclosure-labels' ); ?></small>
					<small><?php esc_html_e( 'Use “Test selected model” before a bulk job. The test sends a tiny built-in test image—not a Media Library image—and verifies that the saved credentials, endpoint, selected model and current image-input format return usable classification JSON. It tests compatibility, not detection accuracy. The test performs one external API request and may incur a small provider charge.', 'ai-image-disclosure-labels' ); ?></small>
					<div id="gdaiidl-ai-model-status" class="gdaiidl-ai-model-status" role="status" aria-live="polite" hidden></div>
				</div>
			</div>

			<?php if ( $this->has_wordpress_ai_client() ) : ?>
			<div class="gdaiidl-ai-provider-fields gdaiidl-ai-wordpress-client-info" data-provider="wordpress_ai_client">
				<p class="description"><strong><?php esc_html_e( 'WordPress AI Client detected.', 'ai-image-disclosure-labels' ); ?></strong> <?php esc_html_e( 'This option reuses AI provider plugins and credentials configured under Settings → Connectors. No provider API key is stored, copied or requested by this plugin. Leave the model preference empty to let WordPress choose any compatible configured model automatically. If you enter or choose a model ID, WordPress treats it as a preference and can fall back to another compatible model if necessary.', 'ai-image-disclosure-labels' ); ?></p>
				<p class="description"><a href="<?php echo esc_url( admin_url( 'options-general.php?page=connectors-wp-admin' ) ); ?>"><?php esc_html_e( 'Open Settings → Connectors', 'ai-image-disclosure-labels' ); ?></a></p>
			</div>
			<?php endif; ?>
			<div class="gdaiidl-ai-provider-fields" data-provider="cloudflare">
				<label class="gd-ai-field gd-ai-field-wide"><span><?php esc_html_e( 'Cloudflare Account ID', 'ai-image-disclosure-labels' ); ?></span><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cloudflare_account_id]" value="<?php echo esc_attr( $s['cloudflare_account_id'] ); ?>" maxlength="120"></label>
			</div>
			<?php if ( $this->has_private_gd_cloudflare_connector() ) : ?>
			<div class="gdaiidl-ai-provider-fields" data-provider="gd_cloudflare_connector">
				<p class="description"><strong><?php esc_html_e( 'Existing GD Cloudflare AI Connector detected.', 'ai-image-disclosure-labels' ); ?></strong> <?php esc_html_e( 'Its Cloudflare credentials, Account ID, Gateway mode and routing are reused automatically. “Fetch current models” uses the connector’s current live Cloudflare catalogue. That catalogue can contain Cloudflare-hosted Workers AI models as well as third-party provider routes exposed through Cloudflare AI Gateway. When this provider is selected, this plugin sends the request through the GD Cloudflare connector; it does not call Google, OpenAI, Anthropic or another provider directly.', 'ai-image-disclosure-labels' ); ?></p>
			</div>
			<?php endif; ?>
			<div class="gdaiidl-ai-provider-fields" data-provider="custom">
				<label class="gd-ai-field gd-ai-field-wide"><span><?php esc_html_e( 'Custom analysis endpoint', 'ai-image-disclosure-labels' ); ?></span><input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_endpoint]" value="<?php echo esc_attr( $s['custom_endpoint'] ); ?>" placeholder="https://example.workers.dev/analyse"></label>
			</div>
			<div class="gdaiidl-ai-provider-fields" data-provider="openai_compatible">
				<label class="gd-ai-field gd-ai-field-wide"><span><?php esc_html_e( 'Chat Completions endpoint', 'ai-image-disclosure-labels' ); ?></span><input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[compatible_endpoint]" value="<?php echo esc_attr( $s['compatible_endpoint'] ); ?>" placeholder="https://example.com/v1/chat/completions"></label>
				<label class="gd-ai-field gd-ai-field-wide"><span><?php esc_html_e( 'Models endpoint (optional)', 'ai-image-disclosure-labels' ); ?></span><input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[compatible_models_endpoint]" value="<?php echo esc_attr( $s['compatible_models_endpoint'] ); ?>" placeholder="https://example.com/v1/models"></label>
			</div>

			<div id="gdaiidl-ai-auth-section">
				<h3><?php esc_html_e( 'API authentication', 'ai-image-disclosure-labels' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Keys are stored server-side and are never localized to browser JavaScript. For stronger secret management, define the documented GDAIIDL_* constants in wp-config.php; a constant takes precedence over a database value.', 'ai-image-disclosure-labels' ); ?></p>
				<div class="gdaiidl-ai-secret-fields">
					<?php foreach ( $providers as $provider_key => $provider_label ) : ?>
						<?php if ( in_array( $provider_key, array( 'gd_cloudflare_connector', 'wordpress_ai_client' ), true ) ) { continue; } ?>
						<?php $this->render_secret_field( $provider_key, $provider_label ); ?>
					<?php endforeach; ?>
				</div>
			</div>

			<details class="gdaiidl-ai-help">
				<summary><?php esc_html_e( 'Privacy and external services', 'ai-image-disclosure-labels' ); ?></summary>
				<div>
					<p><?php esc_html_e( 'AI analysis is optional and off by default. When it runs, a temporary resized image and a short prompt leave your WordPress server and are processed by the provider you selected. Images can contain personal or confidential information, so review the provider’s current terms, privacy/data-use rules and regional requirements before enabling analysis.', 'ai-image-disclosure-labels' ); ?></p>
					<ul>
						<li><a href="https://openai.com/policies/" target="_blank" rel="noopener noreferrer">OpenAI terms &amp; policies</a></li>
						<li><a href="https://ai.google.dev/gemini-api/terms" target="_blank" rel="noopener noreferrer">Google Gemini API terms</a></li>
						<li><a href="https://privacy.anthropic.com/" target="_blank" rel="noopener noreferrer">Anthropic privacy &amp; commercial policy center</a></li>
						<li><a href="https://www.cloudflare.com/policies/" target="_blank" rel="noopener noreferrer">Cloudflare policies</a></li>
					</ul>
					<p class="description"><?php esc_html_e( 'For an OpenAI-compatible or custom endpoint, you are responsible for reviewing and documenting that service’s own terms and privacy policy.', 'ai-image-disclosure-labels' ); ?></p>
				</div>
			</details>

			<?php if ( $this->has_wordpress_ai_client() ) : ?>
			<details class="gdaiidl-ai-help gdaiidl-ai-provider-fields" data-provider="wordpress_ai_client">
				<summary><?php esc_html_e( 'Using WordPress AI Client and Connectors', 'ai-image-disclosure-labels' ); ?></summary>
				<div>
					<p><?php esc_html_e( 'WordPress 7.0+ includes a provider-agnostic AI Client. Provider plugins register themselves with the AI Client, while credentials are managed centrally under Settings → Connectors. This plugin sends the image and classification prompt through wp_ai_client_prompt(); WordPress selects a compatible configured model and supplies the connector credentials internally.', 'ai-image-disclosure-labels' ); ?></p>
					<p><?php esc_html_e( 'The model field is optional for this provider. Leaving it blank is the most portable mode. If a model is entered or chosen from the fetched catalogue, it is passed to WordPress as a model preference rather than a hard requirement, so WordPress may use another compatible model when the preferred model is unavailable.', 'ai-image-disclosure-labels' ); ?></p>
				</div>
			</details>
			<?php endif; ?>

			<details class="gdaiidl-ai-help">
				<summary><?php esc_html_e( 'How do I get an API key?', 'ai-image-disclosure-labels' ); ?></summary>
				<div>
					<p><strong>OpenAI:</strong> <?php esc_html_e( 'Create a secret API key in the OpenAI Platform dashboard, then paste it into the OpenAI field above. The plugin uses the server-side Responses API for image input.', 'ai-image-disclosure-labels' ); ?></p>
					<p><strong>Google Gemini:</strong> <?php esc_html_e( 'Create an API key in Google AI Studio. The plugin uses the Gemini Models API for discovery and generateContent for image analysis.', 'ai-image-disclosure-labels' ); ?></p>
					<p><strong>Anthropic:</strong> <?php esc_html_e( 'Create an API key in the Anthropic Console. Model discovery uses /v1/models and analysis uses the Messages API with image input.', 'ai-image-disclosure-labels' ); ?></p>
					<p><strong>Cloudflare Workers AI:</strong> <?php esc_html_e( 'In the Cloudflare dashboard open Workers AI → Use REST API, create an API token with the current Workers AI permissions required by Cloudflare, and copy the Account ID. The direct adapter lists Cloudflare Text Generation models because model schemas and capabilities change. Choose a model whose current Cloudflare documentation lists Vision, then run “Test selected model” before a bulk job.', 'ai-image-disclosure-labels' ); ?></p>
					<?php if ( $this->has_private_gd_cloudflare_connector() ) : ?>
					<p><strong><?php esc_html_e( 'GD Cloudflare AI Connector:', 'ai-image-disclosure-labels' ); ?></strong> <?php esc_html_e( 'No additional API key is needed here. This plugin calls the connector’s own Direct Client, so the connector continues to control credentials, Account ID, AI Gateway/Workers AI routing, retries and model-specific request formats.', 'ai-image-disclosure-labels' ); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Provider dashboards, model names and permissions change. These instructions intentionally describe the workflow rather than a fixed model or price. Dedicated Cloudflare Image-to-Text models can use task-specific request schemas; use a custom Worker/endpoint if you need one of those models.', 'ai-image-disclosure-labels' ); ?></p>
				</div>
			</details>

			<details class="gdaiidl-ai-help">
				<summary><?php esc_html_e( 'Using a Cloudflare Worker or another custom HTTPS service', 'ai-image-disclosure-labels' ); ?></summary>
				<div>
					<p><?php esc_html_e( 'Choose “Custom HTTPS analysis endpoint”. Your endpoint can route to Workers AI, AI Gateway, OpenAI, Gemini, Claude, a dedicated detector, or your own model policy. Protect it with a bearer token and paste that token into the Custom provider key field.', 'ai-image-disclosure-labels' ); ?></p>
					<p class="description"><?php esc_html_e( 'Security note: custom and OpenAI-compatible endpoints must use HTTPS. Requests also use WordPress’s safe HTTP API, which validates arbitrary URLs and normally rejects loopback or private/LAN destinations to reduce SSRF risk. Protect custom endpoints with authentication.', 'ai-image-disclosure-labels' ); ?></p>
					<p><strong><?php esc_html_e( 'Quick Cloudflare Worker setup:', 'ai-image-disclosure-labels' ); ?></strong></p>
					<ol>
						<li><?php esc_html_e( 'In Cloudflare, open Workers & Pages, create a Worker application and deploy the initial Worker.', 'ai-image-disclosure-labels' ); ?></li>
						<li><?php esc_html_e( 'Add a Workers AI binding named AI in the Worker settings (or in Wrangler). Cloudflare exposes that binding to Worker code as env.AI.', 'ai-image-disclosure-labels' ); ?></li>
						<li><?php esc_html_e( 'Make the Worker accept the JSON contract shown below. For action="analyze", call the current model or your own routing policy and map its answer to the plugin response fields. Workers AI model input schemas can differ, so follow the current schema for the model you actually choose rather than copying an old model-specific example.', 'ai-image-disclosure-labels' ); ?></li>
						<li><?php esc_html_e( 'Optionally require a private bearer token at the Worker. In WordPress, select Custom HTTPS analysis endpoint, enter the workers.dev or custom-domain endpoint, your model/policy name and the same token.', 'ai-image-disclosure-labels' ); ?></li>
						<li><?php esc_html_e( 'Optionally support action="models" for dynamic model/policy discovery and return usage or cost_usd when your router knows the real request cost.', 'ai-image-disclosure-labels' ); ?></li>
					</ol>
					<p class="description"><a href="https://developers.cloudflare.com/workers-ai/configuration/bindings/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Cloudflare: Workers AI bindings', 'ai-image-disclosure-labels' ); ?></a> · <a href="https://developers.cloudflare.com/workers/get-started/dashboard/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Cloudflare: create a Worker in the dashboard', 'ai-image-disclosure-labels' ); ?></a></p>
					<p><?php esc_html_e( 'For analysis, the plugin POSTs JSON containing action="analyze", the configured model/policy, a short classification prompt and a resized base64 image. Return the fields below. resolved_model, usage, cost_usd, pricing, evidence and verified_provenance are optional.', 'ai-image-disclosure-labels' ); ?></p>
					<pre class="gdaiidl-ai-contract">{
  "classification": "likely_ai_generated",
  "confidence": 0.94,
  "reason": "Short evidence summary",
  "verified_provenance": false,
  "evidence": ["visual_analysis"],
  "resolved_model": "provider/model-version",
  "usage": {"input_tokens": 1200, "output_tokens": 60},
  "cost_usd": 0.0002
}</pre>
					<p><?php esc_html_e( 'Set verified_provenance=true only when your custom service actually checked a technical provenance or watermark signal. Do not set it merely because an AI model returned a high confidence score. Visual confidence and technical provenance verification are separate signals.', 'ai-image-disclosure-labels' ); ?></p>
					<p><?php esc_html_e( 'For model discovery, the plugin sends action="models". Return {"models":[{"id":"model-or-policy","label":"Optional label"}]}. A model may optionally include pricing data, but the plugin never requires it.', 'ai-image-disclosure-labels' ); ?></p>
				</div>
			</details>

			<details class="gdaiidl-ai-help">
				<summary><?php esc_html_e( 'Cost estimates and dynamic pricing', 'ai-image-disclosure-labels' ); ?></summary>
				<div>
					<p><?php esc_html_e( 'No provider prices are hard-coded into this plugin. Model names and rates change too quickly. Where an official model catalogue or your custom endpoint supplies machine-readable pricing, the plugin can use it. Otherwise it uses provider-reported request cost, observed historical cost, the manual rates below, or shows “Cost estimate unavailable”.', 'ai-image-disclosure-labels' ); ?></p>
					<p><?php esc_html_e( 'For bulk classification, a fast/low-cost vision model is usually the sensible choice. Premium or high-reasoning models can cost several times more and are normally unnecessary for this constrained task. Any displayed estimate is advisory; the provider’s billing system remains authoritative.', 'ai-image-disclosure-labels' ); ?></p>
					<p><?php esc_html_e( 'If the provider exposes no machine-readable price, the plugin will not invent one. Enter the current token/request rates manually if you want a pre-job estimate. For custom endpoints, returning cost_usd or machine-readable pricing produces the best estimate automatically.', 'ai-image-disclosure-labels' ); ?></p>
				</div>
			</details>

			<h3><?php esc_html_e( 'Analysis behaviour', 'ai-image-disclosure-labels' ); ?></h3>
			<div class="gd-ai-field-grid">
				<label class="gd-ai-field"><span><?php esc_html_e( 'Analysis image size', 'ai-image-disclosure-labels' ); ?></span><select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[analysis_max_dimension]"><?php foreach ( array( 512, 768, 1024, 1536, 2048 ) as $dim ) : ?><option value="<?php echo (int) $dim; ?>" <?php selected( (int) $s['analysis_max_dimension'], $dim ); ?>><?php echo esc_html( 1024 === $dim ? $dim . ' px (' . __( 'recommended', 'ai-image-disclosure-labels' ) . ')' : $dim . ' px' ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( '1024 px', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'A temporary resized copy is sent for visual analysis; the original Media Library file is not changed. Larger values send more data and usually cost more.', 'ai-image-disclosure-labels' ); ?></small></label>
			</div>

			<h3><?php esc_html_e( 'Automatic actions — optional', 'ai-image-disclosure-labels' ); ?></h3>
			<p class="description"><?php esc_html_e( 'You can leave every automatic action below off and still use AI analysis as a suggestion. That is the safest default. Automatic actions never classify an image as “No AI used”.', 'ai-image-disclosure-labels' ); ?></p>
			<label class="gd-ai-toggle-field"><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_analyze_uploads]" value="1" <?php checked( ! empty( $s['auto_analyze_uploads'] ) ); ?>><span><strong><?php esc_html_e( 'Analyse each new image upload automatically', 'ai-image-disclosure-labels' ); ?></strong><small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'off', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'Leave this off unless you specifically want every new image analysed. After WordPress creates a new image attachment, the plugin sends a resized copy to the configured AI service and stores the returned suggestion. This can create an external API request and a charge for every upload.', 'ai-image-disclosure-labels' ); ?></small></span></label>

			<div class="gdaiidl-auto-apply-visual">
				<label class="gd-ai-toggle-field"><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_apply_visual]" value="1" <?php checked( ! empty( $s['auto_apply_visual'] ) ); ?>><span><strong><?php esc_html_e( 'Automatically turn high-confidence model suggestions into an AI status', 'ai-image-disclosure-labels' ); ?></strong><small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'off', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'Leave this off unless you want automatic classification. After any manual, bulk or automatic analysis, an unclassified image can be classified as AI-generated or AI-modified when the model’s reported confidence reaches the threshold below. Existing classifications are never overwritten.', 'ai-image-disclosure-labels' ); ?></small></span></label>
				<label class="gd-ai-field gdaiidl-confidence-field"><span><?php esc_html_e( 'Minimum model confidence for automatic classification', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_apply_threshold]" value="<?php echo esc_attr( $s['auto_apply_threshold'] ); ?>" min="50" max="100" step="1"><em>%</em></span><small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( '95%', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'Higher values are more cautious; lower values automate more suggestions. This is the model’s self-reported confidence, not a calibrated forensic probability, so 95% does not mean a 95% chance that the classification is correct.', 'ai-image-disclosure-labels' ); ?></small></label>
			</div>

			<div class="gdaiidl-ai-provider-fields gdaiidl-verified-provenance-option" data-provider="custom">
				<label class="gd-ai-toggle-field"><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_apply_verified]" value="1" <?php checked( ! empty( $s['auto_apply_verified'] ) ); ?>><span><strong><?php esc_html_e( 'Automatically apply technically verified provenance returned by my custom endpoint', 'ai-image-disclosure-labels' ); ?></strong><small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'off', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'Leave this off unless your custom service really verifies a technical provenance or watermark signal and returns the JSON boolean verified_provenance=true. A normal vision-model confidence score is not verified provenance.', 'ai-image-disclosure-labels' ); ?></small></span></label>
			</div>

			<h3><?php esc_html_e( 'Bulk-job safety limits', 'ai-image-disclosure-labels' ); ?></h3>
			<div class="gd-ai-field-grid">
				<label class="gd-ai-field"><span><?php esc_html_e( 'Maximum images per job', 'ai-image-disclosure-labels' ); ?></span><input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_images_per_job]" value="<?php echo esc_attr( $s['max_images_per_job'] ); ?>" min="0" max="100000" step="1"><small><?php esc_html_e( '0 means no image-count limit. A conservative limit protects large libraries from accidental API use.', 'ai-image-disclosure-labels' ); ?></small></label>
				<label class="gd-ai-field"><span><?php esc_html_e( 'Images per background batch', 'ai-image-disclosure-labels' ); ?></span><input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[batch_size]" value="<?php echo esc_attr( $s['batch_size'] ); ?>" min="1" max="10" step="1"><small><?php esc_html_e( 'Small batches reduce PHP timeouts and provider rate-limit spikes.', 'ai-image-disclosure-labels' ); ?></small></label>
				<label class="gd-ai-field"><span><?php esc_html_e( 'Maximum known cost per job (USD)', 'ai-image-disclosure-labels' ); ?></span><input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_known_cost_usd]" value="<?php echo esc_attr( $s['max_known_cost_usd'] ); ?>" min="0" step="0.01"><small><?php esc_html_e( '0 disables this safety stop. It can only be enforced for costs the provider reports or the plugin can calculate; it cannot guarantee your provider’s final bill.', 'ai-image-disclosure-labels' ); ?></small></label>
			</div>

			<h3><?php esc_html_e( 'Pricing fallback', 'ai-image-disclosure-labels' ); ?></h3>
			<label class="gd-ai-field gd-ai-field-wide"><span><?php esc_html_e( 'How should unknown pricing be handled?', 'ai-image-disclosure-labels' ); ?></span><select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pricing_mode]">
				<option value="auto" <?php selected( $s['pricing_mode'], 'auto' ); ?>><?php esc_html_e( 'Automatic/provider-reported where available (recommended)', 'ai-image-disclosure-labels' ); ?></option>
				<option value="manual_tokens" <?php selected( $s['pricing_mode'], 'manual_tokens' ); ?>><?php esc_html_e( 'Manual input/output token rates', 'ai-image-disclosure-labels' ); ?></option>
				<option value="fixed" <?php selected( $s['pricing_mode'], 'fixed' ); ?>><?php esc_html_e( 'Manual fixed price per request', 'ai-image-disclosure-labels' ); ?></option>
				<option value="none" <?php selected( $s['pricing_mode'], 'none' ); ?>><?php esc_html_e( 'Do not estimate', 'ai-image-disclosure-labels' ); ?></option>
			</select><small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'Automatic/provider-reported', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'Use provider-reported pricing where available. Manual values are only estimates and never affect provider billing.', 'ai-image-disclosure-labels' ); ?></small></label>
			<div class="gd-ai-field-grid gdaiidl-ai-pricing-manual" data-pricing="manual_tokens">
				<label class="gd-ai-field"><span><?php esc_html_e( 'Input USD / 1M tokens', 'ai-image-disclosure-labels' ); ?></span><input type="number" step="0.000001" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[manual_input_per_million_usd]" value="<?php echo esc_attr( $s['manual_input_per_million_usd'] ); ?>"></label>
				<label class="gd-ai-field"><span><?php esc_html_e( 'Output USD / 1M tokens', 'ai-image-disclosure-labels' ); ?></span><input type="number" step="0.000001" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[manual_output_per_million_usd]" value="<?php echo esc_attr( $s['manual_output_per_million_usd'] ); ?>"></label>
			</div>
			<div class="gdaiidl-ai-pricing-manual" data-pricing="fixed"><label class="gd-ai-field"><span><?php esc_html_e( 'Fixed USD / request', 'ai-image-disclosure-labels' ); ?></span><input type="number" step="0.000001" min="0" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[manual_fixed_per_request_usd]" value="<?php echo esc_attr( $s['manual_fixed_per_request_usd'] ); ?>"></label></div>

			<div class="gdaiidl-ai-library-jobs" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<h3><?php esc_html_e( 'Analyse the Media Library', 'ai-image-disclosure-labels' ); ?></h3>
				<p><?php esc_html_e( 'Jobs run in small background batches through WP-Cron. Save these settings before starting a job. Existing publisher classifications are never overwritten by automatic visual analysis.', 'ai-image-disclosure-labels' ); ?></p>
				<p class="gdaiidl-ai-job-buttons"><button type="button" class="button button-secondary gdaiidl-ai-start-job" data-scope="unclassified"><?php esc_html_e( 'Analyse all unclassified images', 'ai-image-disclosure-labels' ); ?></button> <button type="button" class="button gdaiidl-ai-start-job" data-scope="all"><?php esc_html_e( 'Analyse entire Media Library', 'ai-image-disclosure-labels' ); ?></button></p>
				<div id="gdaiidl-ai-job-status" class="gdaiidl-ai-job-status" aria-live="polite"><?php echo wp_kses_post( $this->latest_job_status_html() ); ?></div>
			</div>
		</section>
		<?php
	}

	/**
	 * Render one masked secret field.
	 *
	 * @param string $provider Provider.
	 * @param string $label    Label.
	 * @return void
	 */
	private function render_secret_field( $provider, $label ) {
		$constant_map = array(
			'openai'            => 'GDAIIDL_OPENAI_API_KEY',
			'gemini'            => 'GDAIIDL_GEMINI_API_KEY',
			'anthropic'         => 'GDAIIDL_ANTHROPIC_API_KEY',
			'cloudflare'        => 'GDAIIDL_CLOUDFLARE_API_TOKEN',
			'openai_compatible' => 'GDAIIDL_OPENAI_COMPATIBLE_API_KEY',
			'custom'            => 'GDAIIDL_CUSTOM_API_TOKEN',
		);
		$key       = $provider . '_api_key';
		$constant  = isset( $constant_map[ $provider ] ) ? $constant_map[ $provider ] : '';
		$from_const = '' !== $constant && defined( $constant );
		$stored    = isset( $this->secrets()[ $key ] ) && '' !== (string) $this->secrets()[ $key ];
		?>
		<div class="gdaiidl-ai-secret gdaiidl-ai-provider-fields" data-provider="<?php echo esc_attr( $provider ); ?>">
			<label class="gd-ai-field gd-ai-field-wide">
				<span><?php echo esc_html( $label . ' – ' . __( 'API key / token', 'ai-image-disclosure-labels' ) ); ?></span>
				<input type="password" name="<?php echo esc_attr( self::SECRET_OPTION . '[' . $key . ']' ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $from_const ? __( 'Supplied by wp-config.php', 'ai-image-disclosure-labels' ) : ( $stored ? __( 'Saved – leave blank to keep', 'ai-image-disclosure-labels' ) : __( 'Paste secret here', 'ai-image-disclosure-labels' ) ) ); ?>" <?php disabled( $from_const ); ?>>
				<?php if ( $stored && ! $from_const ) : ?><small><label><input type="checkbox" name="<?php echo esc_attr( self::SECRET_OPTION . '[clear_' . $key . ']' ); ?>" value="1"> <?php esc_html_e( 'Remove saved key on Save Changes', 'ai-image-disclosure-labels' ); ?></label></small><?php endif; ?>
				<?php if ( $from_const ) : ?><small><?php echo esc_html( sprintf( __( 'Using constant %s.', 'ai-image-disclosure-labels' ), $constant ) ); ?></small><?php endif; ?>
			</label>
		</div>
		<?php
	}


	/**
	 * Version an asset by release plus file modification time. This keeps official
	 * release URLs stable while preventing stale browser caches during repeated
	 * same-version pre-release test builds.
	 *
	 * @param string $relative_path Path relative to the plugin directory.
	 * @return string
	 */
	private function asset_version( $relative_path ) {
		$path  = GDAIIDL_DIR . ltrim( (string) $relative_path, '/\\' );
		$mtime = is_file( $path ) ? @filemtime( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- asset can disappear during an atomic deploy; version falls back safely.

		return false !== $mtime ? GDAIIDL_VERSION . '-' . (string) $mtime : GDAIIDL_VERSION;
	}

	/**
	 * Admin assets for settings and Media Library list.
	 *
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'settings_page_gdaiidl-settings', 'upload.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'gdaiidl-ai-admin', GDAIIDL_URL . 'assets/ai-admin.css', array(), $this->asset_version( 'assets/ai-admin.css' ) );
		wp_enqueue_script( 'gdaiidl-ai-admin', GDAIIDL_URL . 'assets/ai-admin.js', array( 'jquery' ), $this->asset_version( 'assets/ai-admin.js' ), true );
		wp_localize_script(
			'gdaiidl-ai-admin',
			'gdaiidlAiAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gdaiidl_ai_admin' ),
				'i18n'    => array(
					'fetching'       => __( 'Fetching current models…', 'ai-image-disclosure-labels' ),
					'testing'        => __( 'Testing the selected model…', 'ai-image-disclosure-labels' ),
					'testOk'         => __( 'Model test succeeded.', 'ai-image-disclosure-labels' ),
					'noModels'       => __( 'No models were returned. You can still enter a model manually.', 'ai-image-disclosure-labels' ),
					'saveKey'        => __( 'If you just entered a new API key, save the settings first and try again.', 'ai-image-disclosure-labels' ),
					'confirmCost'    => __( 'This operation can send images to an external AI provider and may incur API charges.', 'ai-image-disclosure-labels' ),
					'unknownCost'    => __( 'Cost estimate unavailable.', 'ai-image-disclosure-labels' ),
					'jobStarted'     => __( 'Background analysis job started.', 'ai-image-disclosure-labels' ),
					'requestFailed'  => __( 'Request failed.', 'ai-image-disclosure-labels' ),
					'manualModelLabel' => __( 'Model / policy ID (manual entry)', 'ai-image-disclosure-labels' ),
					'manualModelHelp'  => __( 'You can type or paste an exact model/policy ID here. Or fetch the current catalogue below and choose a model there; the selected catalogue ID is copied into this field.', 'ai-image-disclosure-labels' ),
					'wpModelLabel'     => __( 'Model preference (optional)', 'ai-image-disclosure-labels' ),
					'wpModelHelp'      => __( 'Leave blank to let WordPress select any compatible configured model. Or fetch compatible models below and choose one as a preference; WordPress may fall back if needed.', 'ai-image-disclosure-labels' ),
					'chooseModel'    => __( 'Choose a model from the fetched catalogue…', 'ai-image-disclosure-labels' ),
					'loadedAt'       => __( 'model(s) loaded', 'ai-image-disclosure-labels' ),
					'selectedHint'   => __( 'Choose a model returned by the live catalogue below, then test it. The plugin does not guess or substitute model IDs.', 'ai-image-disclosure-labels' ),
				),
			)
		);
	}

	/**
	 * Add Media Library analysis column.
	 *
	 * @param array $columns Columns.
	 * @param bool  $detached Detached.
	 * @return array
	 */
	public function add_analysis_column( $columns, $detached = false ) {
		unset( $detached );
		$out = array();
		foreach ( (array) $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'gdaiidl_ai_status' === $key ) {
				$out['gdaiidl_ai_analysis'] = __( 'AI analysis', 'ai-image-disclosure-labels' );
			}
		}
		if ( ! isset( $out['gdaiidl_ai_analysis'] ) ) {
			$out['gdaiidl_ai_analysis'] = __( 'AI analysis', 'ai-image-disclosure-labels' );
		}
		return $out;
	}

	/**
	 * Render analysis result.
	 *
	 * @param string $column Column.
	 * @param int    $post_id Attachment.
	 * @return void
	 */
	public function render_analysis_column( $column, $post_id ) {
		if ( 'gdaiidl_ai_analysis' !== $column ) {
			return;
		}
		if ( 0 !== strpos( (string) get_post_mime_type( $post_id ), 'image/' ) ) {
			echo '&mdash;';
			return;
		}
		$result = $this->get_analysis_result( $post_id );
		if ( ! $result ) {
			echo '<span class="gdaiidl-analysis-chip gdaiidl-analysis-none">' . esc_html__( 'Not analysed', 'ai-image-disclosure-labels' ) . '</span>';
			return;
		}
		$class = isset( $result['classification'] ) ? $this->sanitize_classification( $result['classification'] ) : 'failed';
		$label = $this->classification_label( $class );
		if ( isset( $result['verified_provenance'] ) && true === $result['verified_provenance'] ) {
			$label = __( 'Verified provenance', 'ai-image-disclosure-labels' ) . ' · ' . $label;
		}
		$confidence = isset( $result['confidence'] ) && is_numeric( $result['confidence'] ) ? (int) round( (float) $result['confidence'] * 100 ) : null;
		$title_bits = array();
		if ( ! empty( $result['provider'] ) ) { $title_bits[] = $result['provider']; }
		if ( ! empty( $result['resolved_model'] ) ) { $title_bits[] = $result['resolved_model']; }
		elseif ( ! empty( $result['model'] ) ) { $title_bits[] = $result['model']; }
		if ( ! empty( $result['evidence'] ) && is_array( $result['evidence'] ) ) { $title_bits[] = implode( ', ', array_map( 'sanitize_text_field', $result['evidence'] ) ); }
		if ( ! empty( $result['reason'] ) ) { $title_bits[] = $result['reason']; }
		printf(
			'<span class="gdaiidl-analysis-chip gdaiidl-analysis-%1$s" title="%2$s">%3$s%4$s</span>',
			esc_attr( str_replace( '_', '-', $class ) ),
			esc_attr( implode( ' · ', $title_bits ) ),
			esc_html( $label ),
			null !== $confidence ? esc_html( ' · ' . $confidence . '%' ) : ''
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		if ( ! current_user_can( $this->analysis_capability() ) ) {
			return $actions;
		}
		$actions[ __( 'AI analysis', 'ai-image-disclosure-labels' ) ] = array(
			'gdaiidl_analyze_selected'   => __( 'Analyse selected with AI', 'ai-image-disclosure-labels' ),
			'gdaiidl_reanalyze_selected' => __( 'Re-analyse selected with AI', 'ai-image-disclosure-labels' ),
			'gdaiidl_accept_selected'    => __( 'Apply selected AI suggestions', 'ai-image-disclosure-labels' ),
			'gdaiidl_clear_analysis'     => __( 'Clear AI analysis', 'ai-image-disclosure-labels' ),
		);
		return $actions;
	}

	/**
	 * Handle Media Library bulk actions.
	 *
	 * @param string $redirect Redirect.
	 * @param string $action Action.
	 * @param array  $post_ids IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect, $action, $post_ids ) {
		$known = array( 'gdaiidl_analyze_selected', 'gdaiidl_reanalyze_selected', 'gdaiidl_accept_selected', 'gdaiidl_clear_analysis' );
		if ( ! in_array( $action, $known, true ) ) {
			return $redirect;
		}

		if ( ! current_user_can( $this->analysis_capability() ) ) {
			return add_query_arg( 'gdaiidl_analysis_error', rawurlencode( __( 'You do not have permission to run paid AI analysis.', 'ai-image-disclosure-labels' ) ), $redirect );
		}

		$ids = array();
		foreach ( array_map( 'absint', (array) $post_ids ) as $id ) {
			if ( $id > 0 && current_user_can( 'edit_post', $id ) && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ) {
				$ids[] = $id;
			}
		}

		$processed = 0;
		$job_id = '';
		if ( 'gdaiidl_clear_analysis' === $action ) {
			foreach ( $ids as $id ) {
				delete_post_meta( $id, self::META_RESULT );
				delete_post_meta( $id, self::META_CLASS );
				++$processed;
			}
		} elseif ( 'gdaiidl_accept_selected' === $action ) {
			foreach ( $ids as $id ) {
				if ( $this->apply_suggestion( $id ) ) {
					++$processed;
				}
			}
			if ( $processed > 0 ) {
				GDAIIDL_Plugin::instance()->flush_disclosure_caches();
			}
		} else {
			if ( ! $this->is_configured() ) {
				return add_query_arg( 'gdaiidl_analysis_error', rawurlencode( __( 'AI analysis is not fully configured. Save the provider and complete its required configuration first.', 'ai-image-disclosure-labels' ) ), $redirect );
			}
			if ( 'gdaiidl_analyze_selected' === $action ) {
				$ids = array_values( array_filter( $ids, array( $this, 'attachment_needs_analysis' ) ) );
			}
			$job_id = $this->create_job( 'selected', $ids );
			$processed = count( $ids );
		}

		return add_query_arg(
			array(
				'gdaiidl_analysis_processed' => $processed,
				'gdaiidl_analysis_job'       => $job_id,
			),
			remove_query_arg( array( 'gdaiidl_analysis_processed', 'gdaiidl_analysis_job', 'gdaiidl_analysis_error' ), $redirect )
		);
	}

	/**
	 * Filter dropdown.
	 *
	 * @param string $post_type Post type.
	 * @param string $which Which.
	 * @return void
	 */
	public function render_analysis_filter( $post_type, $which = '' ) {
		if ( 'attachment' !== $post_type || ( '' !== $which && 'bar' !== $which ) ) {
			return;
		}
		$current = isset( $_GET['gdaiidl_ai_analysis'] ) ? sanitize_key( wp_unslash( $_GET['gdaiidl_ai_analysis'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$options = array(
			''                    => __( 'All AI analysis results', 'ai-image-disclosure-labels' ),
			'not_analyzed'        => __( 'Not analysed', 'ai-image-disclosure-labels' ),
			'likely_ai_generated' => __( 'Likely AI-generated', 'ai-image-disclosure-labels' ),
			'likely_ai_modified'  => __( 'Likely AI-modified', 'ai-image-disclosure-labels' ),
			'likely_non_ai'       => __( 'Likely non-AI', 'ai-image-disclosure-labels' ),
			'uncertain'           => __( 'Uncertain', 'ai-image-disclosure-labels' ),
			'failed'              => __( 'Analysis failed', 'ai-image-disclosure-labels' ),
		);
		echo '<label class="screen-reader-text" for="gdaiidl-ai-analysis-filter">' . esc_html__( 'Filter by AI analysis', 'ai-image-disclosure-labels' ) . '</label>';
		echo '<select name="gdaiidl_ai_analysis" id="gdaiidl-ai-analysis-filter">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Filter main Media Library query.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function filter_media_library( $query ) {
		if ( ! is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) || ! isset( $_GET['gdaiidl_ai_analysis'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$value = sanitize_key( wp_unslash( $_GET['gdaiidl_ai_analysis'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $value ) {
			return;
		}
		$query->set( 'post_mime_type', 'image' );
		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();
		if ( 'not_analyzed' === $value ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => self::META_CLASS, 'compare' => 'NOT EXISTS' ),
				array(
					'key'     => self::META_CLASS,
					'value'   => array( 'likely_ai_generated', 'likely_ai_modified', 'likely_non_ai', 'uncertain', 'failed' ),
					'compare' => 'NOT IN',
				),
			);
		} elseif ( in_array( $value, array( 'likely_ai_generated', 'likely_ai_modified', 'likely_non_ai', 'uncertain', 'failed' ), true ) ) {
			$meta_query[] = array( 'key' => self::META_CLASS, 'value' => $value, 'compare' => '=' );
		} else {
			return;
		}
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Bulk action notices.
	 *
	 * @return void
	 */
	public function render_bulk_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}
		if ( isset( $_GET['gdaiidl_analysis_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gdaiidl_analysis_error'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! isset( $_GET['gdaiidl_analysis_processed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$count = absint( wp_unslash( $_GET['gdaiidl_analysis_processed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job = isset( $_GET['gdaiidl_analysis_job'] ) ? sanitize_key( wp_unslash( $_GET['gdaiidl_analysis_job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = $job ? sprintf( __( 'AI analysis queued for %d selected image(s). It will run in background batches.', 'ai-image-disclosure-labels' ), $count ) : sprintf( __( '%d selected image(s) processed.', 'ai-image-disclosure-labels' ), $count );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Queue new upload when opted in.
	 *
	 * @param int $attachment_id Attachment.
	 * @return void
	 */
	public function maybe_queue_new_attachment( $attachment_id ) {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) || empty( $s['auto_analyze_uploads'] ) || ! $this->is_configured() ) {
			return;
		}
		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return;
		}
		$this->create_job( 'selected', array( absint( $attachment_id ) ), 30 );
	}

	/**
	 * Model discovery AJAX.
	 *
	 * @return void
	 */
	public function ajax_fetch_models() {
		$this->check_ajax_admin();
		$context = array(
			'provider'                   => isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '',
			'cloudflare_account_id'      => isset( $_POST['cloudflare_account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['cloudflare_account_id'] ) ) : '',
			'custom_endpoint'            => isset( $_POST['custom_endpoint'] ) ? $this->sanitize_https_endpoint( wp_unslash( $_POST['custom_endpoint'] ) ) : '',
			'compatible_models_endpoint' => isset( $_POST['compatible_models_endpoint'] ) ? $this->sanitize_https_endpoint( wp_unslash( $_POST['compatible_models_endpoint'] ) ) : '',
		);
		$result = $this->fetch_models( $context, true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'models' => $result, 'checked_at' => current_time( 'mysql' ) ) );
	}


	/**
	 * Verify that the selected provider/model accepts the plugin's current image request format.
	 * Uses a tiny built-in image so no Media Library content is sent during this compatibility test.
	 *
	 * @return void
	 */
	public function ajax_test_model() {
		$this->check_ajax_admin();
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$model    = isset( $_POST['model'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['model'] ) ), 0, 220 ) : '';
		$providers = $this->providers();
		if ( ! isset( $providers[ $provider ] ) || ( 'wordpress_ai_client' !== $provider && '' === trim( $model ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a provider and model before testing.', 'ai-image-disclosure-labels' ) ), 400 );
		}

		$s = wp_parse_args(
			array(
				'provider'                   => $provider,
				'model'                      => $model,
				'cloudflare_account_id'      => isset( $_POST['cloudflare_account_id'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['cloudflare_account_id'] ) ), 0, 120 ) : '',
				'custom_endpoint'            => isset( $_POST['custom_endpoint'] ) ? $this->sanitize_https_endpoint( wp_unslash( $_POST['custom_endpoint'] ) ) : '',
				'compatible_endpoint'        => isset( $_POST['compatible_endpoint'] ) ? $this->sanitize_https_endpoint( wp_unslash( $_POST['compatible_endpoint'] ) ) : '',
				'compatible_models_endpoint' => isset( $_POST['compatible_models_endpoint'] ) ? $this->sanitize_https_endpoint( wp_unslash( $_POST['compatible_models_endpoint'] ) ) : '',
			),
			$this->settings()
		);

		if ( 'cloudflare' === $provider && '' === trim( (string) $s['cloudflare_account_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Cloudflare Account ID is required.', 'ai-image-disclosure-labels' ) ), 400 );
		}
		if ( 'custom' === $provider && '' === trim( (string) $s['custom_endpoint'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Custom endpoint is required.', 'ai-image-disclosure-labels' ) ), 400 );
		}
		if ( 'openai_compatible' === $provider && '' === trim( (string) $s['compatible_endpoint'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Chat Completions endpoint is required.', 'ai-image-disclosure-labels' ) ), 400 );
		}
		if ( 'wordpress_ai_client' === $provider ) {
			if ( ! $this->has_wordpress_ai_client() ) {
				wp_send_json_error( array( 'message' => __( 'The WordPress AI Client or its provider integration is no longer available on this site.', 'ai-image-disclosure-labels' ) ), 400 );
			}
			if ( ! $this->wordpress_ai_client_supports_analysis( $model ) ) {
				wp_send_json_error( array( 'message' => __( 'WordPress could not find a configured AI model that supports text generation with image input. Configure a compatible provider under Settings → Connectors, or leave the model preference blank and try again.', 'ai-image-disclosure-labels' ) ), 400 );
			}
		} elseif ( 'gd_cloudflare_connector' === $provider ) {
			if ( ! $this->has_private_gd_cloudflare_connector() ) {
				wp_send_json_error( array( 'message' => __( 'The GD Cloudflare AI Connector is no longer available on this site.', 'ai-image-disclosure-labels' ) ), 400 );
			}
			$live_models = $this->fetch_models( array( 'provider' => $provider ), false );
			if ( is_array( $live_models ) ) {
				$live_ids = array();
				foreach ( $live_models as $live_model ) {
					if ( is_array( $live_model ) && ! empty( $live_model['id'] ) ) { $live_ids[] = (string) $live_model['id']; }
				}
				if ( ! in_array( $model, $live_ids, true ) ) {
					wp_send_json_error( array( 'message' => __( 'The selected model ID is not present in the connector’s current live catalogue. Click “Fetch current models”, choose one of the returned IDs, and test it. The plugin does not invent or substitute model names.', 'ai-image-disclosure-labels' ) ), 400 );
				}
			}
		} elseif ( ! in_array( $provider, array( 'custom', 'openai_compatible', 'wordpress_ai_client' ), true ) && '' === $this->api_key( $provider ) ) {
			wp_send_json_error( array( 'message' => __( 'Save the API key/token first, then run the model test.', 'ai-image-disclosure-labels' ) ), 400 );
		}

		$response = $this->call_provider( $provider, $model, $this->compatibility_test_image(), $this->analysis_prompt(), $s );
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( 'gd_cloudflare_connector' === $provider ) {
				$message .= ' ' . __( 'Refresh the live model catalogue and choose another returned ID if necessary. No replacement model is guessed automatically.', 'ai-image-disclosure-labels' );
			}
			wp_send_json_error( array( 'message' => $message ), 400 );
		}
		$result = $this->normalize_analysis_response( $response, $provider, $model );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Model test succeeded. The provider accepted an image and returned usable classification JSON.', 'ai-image-disclosure-labels' ),
				'classification' => $result['classification'],
				'resolved_model' => $result['resolved_model'],
				'checked_at'     => current_time( 'mysql' ),
			)
		);
	}

	/** AJAX prepare job. */
	public function ajax_prepare_job() {
		$this->check_ajax_admin();
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
		if ( ! in_array( $scope, array( 'all', 'unclassified' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid analysis scope.', 'ai-image-disclosure-labels' ) ), 400 );
		}
		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Save a provider and complete its required configuration before starting analysis.', 'ai-image-disclosure-labels' ) ), 400 );
		}
		$count    = $this->count_scope_images( $scope );
		$limit    = (int) $this->settings()['max_images_per_job'];
		$eligible = $limit > 0 ? min( $count, $limit ) : $count;
		$estimate = $this->estimate_cost_for_count( $eligible );
		wp_send_json_success( array( 'count' => $count, 'eligible' => $eligible, 'estimate' => $estimate ) );
	}

	/** AJAX start job. */
	public function ajax_start_job() {
		$this->check_ajax_admin();
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
		if ( ! in_array( $scope, array( 'all', 'unclassified' ), true ) || ! $this->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Analysis is not fully configured.', 'ai-image-disclosure-labels' ) ), 400 );
		}
		$job_id = $this->create_job( $scope, array() );
		if ( '' === $job_id ) {
			wp_send_json_error( array( 'message' => __( 'No eligible images were found, or the analysis job could not be created.', 'ai-image-disclosure-labels' ) ), 500 );
		}
		wp_send_json_success( array( 'job_id' => $job_id, 'html' => $this->job_status_html( $job_id ) ) );
	}

	/** AJAX status. */
	public function ajax_job_status() {
		$this->check_ajax_admin();
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		wp_send_json_success( array( 'html' => $this->job_status_html( $job_id ), 'job' => $this->get_job( $job_id ) ) );
	}

	/** AJAX cancel. */
	public function ajax_cancel_job() {
		$this->check_ajax_admin();
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		$jobs = $this->jobs();
		if ( isset( $jobs[ $job_id ] ) && in_array( $jobs[ $job_id ]['status'], array( 'queued', 'running' ), true ) ) {
			$jobs[ $job_id ]['status'] = 'cancelled';
			$jobs[ $job_id ]['updated_at'] = time();
			$this->save_jobs( $jobs );
		}
		wp_send_json_success( array( 'html' => $this->job_status_html( $job_id ) ) );
	}

	/**
	 * Check AJAX auth.
	 *
	 * @return void
	 */
	private function check_ajax_admin() {
		check_ajax_referer( 'gdaiidl_ai_admin', 'nonce' );
		if ( ! current_user_can( $this->analysis_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage AI analysis.', 'ai-image-disclosure-labels' ) ), 403 );
		}
	}

	/** Capability required to spend external AI resources. */
	private function analysis_capability() {
		return (string) apply_filters( 'gdaiidl_ai_analysis_capability', 'manage_options' );
	}

	/**
	 * Configuration readiness.
	 *
	 * @return bool
	 */
	private function is_configured() {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) ) {
			return false;
		}
		$provider = $s['provider'];
		if ( 'wordpress_ai_client' === $provider ) {
			return $this->wordpress_ai_client_supports_analysis( isset( $s['model'] ) ? $s['model'] : '' );
		}
		if ( '' === trim( (string) $s['model'] ) ) {
			return false;
		}
		if ( 'cloudflare' === $provider && '' === trim( (string) $s['cloudflare_account_id'] ) ) {
			return false;
		}
		if ( 'custom' === $provider && '' === trim( (string) $s['custom_endpoint'] ) ) {
			return false;
		}
		if ( 'openai_compatible' === $provider && '' === trim( (string) $s['compatible_endpoint'] ) ) {
			return false;
		}
		if ( 'gd_cloudflare_connector' === $provider ) {
			if ( ! $this->has_private_gd_cloudflare_connector() ) {
				return false;
			}
			$token   = call_user_func( '\\GD\\CloudflareAIConnector\\get_api_token' );
			$account = call_user_func( '\\GD\\CloudflareAIConnector\\get_account_id' );
			return is_string( $token ) && '' !== trim( $token ) && is_string( $account ) && '' !== trim( $account );
		}
		if ( in_array( $provider, array( 'custom', 'openai_compatible' ), true ) ) {
			return true;
		}
		return '' !== $this->api_key( $provider );
	}

	/**
	 * Create a job.
	 *
	 * @param string $scope Scope.
	 * @param array  $ids Selected IDs.
	 * @param int    $delay Delay seconds.
	 * @return string
	 */
	private function create_job( $scope, $ids = array(), $delay = 1 ) {
		if ( ! in_array( $scope, array( 'selected', 'all', 'unclassified' ), true ) ) {
			return '';
		}
		$s = $this->settings();
		$limit = (int) $s['max_images_per_job'];
		if ( 'selected' === $scope ) {
			$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			if ( $limit > 0 ) {
				$ids = array_slice( $ids, 0, $limit );
			}
			$total = count( $ids );
			if ( 0 === $total ) {
				return '';
			}
		} else {
			$total = $this->count_scope_images( $scope );
			if ( $limit > 0 ) {
				$total = min( $total, $limit );
			}
			if ( 0 === $total ) {
				return '';
			}
		}

		$job_id = 'job_' . substr( wp_hash( microtime( true ) . '|' . wp_rand() ), 0, 16 );
		$jobs = $this->jobs();
		$jobs[ $job_id ] = array(
			'id' => $job_id,
			'status' => 'queued',
			'scope' => $scope,
			'ids' => 'selected' === $scope ? $ids : array(),
			'cursor' => 0,
			'position' => 0,
			'total' => $total,
			'processed' => 0,
			'generated' => 0,
			'modified' => 0,
			'non_ai' => 0,
			'uncertain' => 0,
			'failed' => 0,
			'known_cost' => 0.0,
			'unknown_cost_count' => 0,
			'provider' => $s['provider'],
			'model' => $s['model'],
			'config_fingerprint' => $this->analysis_config_fingerprint( $s ),
			'created_at' => time(),
			'updated_at' => time(),
			'last_error' => '',
		);
		$this->save_jobs( $jobs );
		wp_schedule_single_event( time() + max( 1, (int) $delay ), self::CRON_HOOK, array( $job_id ) );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		return $job_id;
	}

	/**
	 * Process one background batch.
	 *
	 * @param string $job_id Job ID.
	 * @return void
	 */
	public function process_job( $job_id ) {
		$job_id = sanitize_key( $job_id );
		$lock_key = 'gdaiidl_ai_lock_' . substr( md5( $job_id ), 0, 20 );
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

		$jobs = $this->jobs();
		if ( ! isset( $jobs[ $job_id ] ) || ! in_array( $jobs[ $job_id ]['status'], array( 'queued', 'running' ), true ) ) {
			delete_transient( $lock_key );
			return;
		}
		$job =& $jobs[ $job_id ];
		$job['status'] = 'running';
		$job['updated_at'] = time();
		$this->save_jobs( $jobs );

		$s = $this->settings();
		$current_fingerprint = $this->analysis_config_fingerprint( $s );
		if ( empty( $job['config_fingerprint'] ) ) {
			$job['config_fingerprint'] = $current_fingerprint;
		} elseif ( ! hash_equals( (string) $job['config_fingerprint'], $current_fingerprint ) ) {
			$job['status'] = 'stopped_config';
			$job['last_error'] = __( 'AI provider, model or analysis settings changed after this job was queued. Start a new job so every result has a consistent configuration.', 'ai-image-disclosure-labels' );
			$job['updated_at'] = time();
			$this->save_jobs( $jobs );
			delete_transient( $lock_key );
			return;
		}
		$batch_size = max( 1, min( 10, (int) $s['batch_size'] ) );
		$remaining = max( 0, (int) $job['total'] - (int) $job['processed'] );
		$batch_size = min( $batch_size, $remaining );
		if ( $batch_size <= 0 ) {
			$job['status'] = 'completed';
			$job['updated_at'] = time();
			$this->save_jobs( $jobs );
			delete_transient( $lock_key );
			return;
		}

		$ids = $this->next_job_ids( $job, $batch_size );
		if ( empty( $ids ) ) {
			$job['status'] = 'completed';
			$job['updated_at'] = time();
			$this->save_jobs( $jobs );
			delete_transient( $lock_key );
			return;
		}

		$applied_in_batch = false;
		foreach ( $ids as $attachment_id ) {
			$current_jobs = $this->jobs();
			if ( isset( $current_jobs[ $job_id ] ) && 'cancelled' === $current_jobs[ $job_id ]['status'] ) {
				$job['status'] = 'cancelled';
				break;
			}

			$request_settings = $this->settings();
			if ( ! hash_equals( (string) $job['config_fingerprint'], $this->analysis_config_fingerprint( $request_settings ) ) ) {
				$job['status'] = 'stopped_config';
				$job['last_error'] = __( 'AI provider, model or analysis settings changed while this job was running. Start a new job to continue with the new configuration.', 'ai-image-disclosure-labels' );
				break;
			}

			$result = $this->analyze_attachment( $attachment_id, $request_settings );
			$current_jobs = $this->jobs();
			if ( isset( $current_jobs[ $job_id ] ) && 'cancelled' === $current_jobs[ $job_id ]['status'] ) {
				$job['status'] = 'cancelled';
			}
			++$job['processed'];
			$job['cursor'] = max( (int) $job['cursor'], (int) $attachment_id );
			$job['position'] = (int) $job['position'] + 1;

			if ( is_wp_error( $result ) ) {
				++$job['failed'];
				$job['last_error'] = $result->get_error_message();
				$this->store_failed_result( $attachment_id, $result->get_error_message() );
			} else {
				$class = $result['classification'];
				if ( 'likely_ai_generated' === $class ) { ++$job['generated']; }
				elseif ( 'likely_ai_modified' === $class ) { ++$job['modified']; }
				elseif ( 'likely_non_ai' === $class ) { ++$job['non_ai']; }
				else { ++$job['uncertain']; }
				if ( ! empty( $result['auto_applied'] ) ) { $applied_in_batch = true; }
				if ( isset( $result['cost_usd'] ) && is_numeric( $result['cost_usd'] ) ) {
					$job['known_cost'] += (float) $result['cost_usd'];
				} else {
					++$job['unknown_cost_count'];
				}
			}

			$max_cost = (float) $s['max_known_cost_usd'];
			if ( $max_cost > 0 && (float) $job['known_cost'] >= $max_cost ) {
				$job['status'] = 'stopped_cost';
				break;
			}
		}

		if ( $applied_in_batch ) {
			GDAIIDL_Plugin::instance()->flush_disclosure_caches();
		}

		$current_jobs = $this->jobs();
		if ( isset( $current_jobs[ $job_id ] ) && 'cancelled' === $current_jobs[ $job_id ]['status'] ) {
			$job['status'] = 'cancelled';
		}
		$job['updated_at'] = time();
		if ( 'running' === $job['status'] ) {
			if ( (int) $job['processed'] >= (int) $job['total'] || count( $ids ) < $batch_size ) {
				$job['status'] = 'completed';
			} else {
				$job['status'] = 'queued';
			}
		}
		$this->save_jobs( $jobs );
		delete_transient( $lock_key );

		if ( 'queued' === $job['status'] ) {
			wp_schedule_single_event( time() + 10, self::CRON_HOOK, array( $job_id ) );
		}
	}

	/**
	 * Next IDs for a job.
	 *
	 * @param array $job Job.
	 * @param int   $limit Limit.
	 * @return array
	 */
	private function next_job_ids( $job, $limit ) {
		if ( 'selected' === $job['scope'] ) {
			return array_slice( (array) $job['ids'], (int) $job['position'], $limit );
		}
		return $this->query_scope_ids_after( $job['scope'], (int) $job['cursor'], $limit );
	}

	/**
	 * Count images for query-based scope.
	 *
	 * @param string $scope Scope.
	 * @return int
	 */
	private function count_scope_images( $scope ) {
		global $wpdb;
		if ( 'unclassified' === $scope ) {
			$sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s) WHERE p.post_type = 'attachment' AND p.post_status <> 'trash' AND p.post_mime_type LIKE 'image/%%' AND (pm.meta_id IS NULL OR pm.meta_value = '' OR pm.meta_value NOT IN ('no-ai','generated','modified','edited','enhanced'))";
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, GDAIIDL_Plugin::META_MEDIA_SOURCE_TYPE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status <> 'trash' AND post_mime_type LIKE 'image/%%'";
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Query image IDs after cursor, avoiding OFFSET on large libraries.
	 *
	 * @param string $scope Scope.
	 * @param int    $after_id Cursor.
	 * @param int    $limit Limit.
	 * @return array
	 */
	private function query_scope_ids_after( $scope, $after_id, $limit ) {
		global $wpdb;
		$limit = max( 1, min( 50, (int) $limit ) );
		$after_id = max( 0, (int) $after_id );
		if ( 'unclassified' === $scope ) {
			$sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s) WHERE p.post_type = 'attachment' AND p.post_status <> 'trash' AND p.post_mime_type LIKE 'image/%%' AND p.ID > %d AND (pm.meta_id IS NULL OR pm.meta_value = '' OR pm.meta_value NOT IN ('no-ai','generated','modified','edited','enhanced')) ORDER BY p.ID ASC LIMIT %d";
			$prepared = $wpdb->prepare( $sql, GDAIIDL_Plugin::META_MEDIA_SOURCE_TYPE, $after_id, $limit );
		} else {
			$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status <> 'trash' AND post_mime_type LIKE 'image/%%' AND ID > %d ORDER BY ID ASC LIMIT %d";
			$prepared = $wpdb->prepare( $sql, $after_id, $limit );
		}
		return array_map( 'absint', (array) $wpdb->get_col( $prepared ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Analyze one attachment.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $settings      Consistent settings snapshot for this request.
	 * @return array|WP_Error
	 */
	private function analyze_attachment( $attachment_id, $settings = null ) {
		$attachment_id = absint( $attachment_id );
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		if ( $attachment_id <= 0 || 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return new WP_Error( 'gdaiidl_not_image', __( 'The selected attachment is not an image.', 'ai-image-disclosure-labels' ) );
		}
		$image = $this->prepare_analysis_image( $attachment_id, (int) $s['analysis_max_dimension'] );
		if ( is_wp_error( $image ) ) {
			return $image;
		}
		$response = $this->call_provider( $s['provider'], $s['model'], $image, $this->analysis_prompt(), $s );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$result = $this->normalize_analysis_response( $response, $s['provider'], $s['model'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$cost = $this->resolve_request_cost( $result, $s );
		$result['cost_usd'] = $cost['cost'];
		$result['cost_source'] = $cost['source'];
		$result['pricing_checked_at'] = time();
		$result['analysed_at'] = time();
		update_post_meta( $attachment_id, self::META_RESULT, $result );
		update_post_meta( $attachment_id, self::META_CLASS, $result['classification'] );
		$this->record_cost_stat( $result );

		$result['auto_applied'] = false;
		$can_auto_apply_verified = 'custom' === $s['provider'] && ! empty( $s['auto_apply_verified'] ) && isset( $result['verified_provenance'] ) && true === $result['verified_provenance'];
		$can_auto_apply_visual = ! empty( $s['auto_apply_visual'] ) && (float) $result['confidence'] >= ( (float) $s['auto_apply_threshold'] / 100 );
		if ( $can_auto_apply_verified || $can_auto_apply_visual ) {
			$result['auto_applied'] = $this->apply_suggestion( $attachment_id, $can_auto_apply_verified );
			if ( $result['auto_applied'] ) {
				update_post_meta( $attachment_id, self::META_RESULT, $result );
			}
		}
		return $result;
	}

	/**
	 * Tiny built-in PNG used only by the provider/model compatibility test.
	 * It contains no Media Library or visitor data.
	 *
	 * @return array
	 */
	private function compatibility_test_image() {
		return array(
			'mime_type' => 'image/png',
			'base64'    => 'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAA60lEQVR42u2ayw7EIAhFq/HD/XNm0WTSZGrHlOsrHrZs7gEEaQ1mdqxs8VjcAAAAAADGWio5QghTCS3NK0oIgFZnoKb+WlvNOaSEAAAAAADazwFVC28xT1Ij6TnnkkuLkfpIP+10aTGSUP2D9FsMCUPsrP6KIVk54hD1Qobt58Dr8KuSsHcGnOGXJIG7EAAAALAwgJlJ2qjnVrd9CTmT4Aw/GfAlwR9+WQZeMEjUK1dKM/u7E19Xyul24q+mBwytdD3AL8atS2utPmx1+ydCGwUAAADGWlUbne3dBCUEgNAC70YBAAAAALYG+ABThmsPVC7IsQAAAABJRU5ErkJggg==', // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- static test fixture, not encoded at runtime.
		);
	}

	/**
	 * Prepare a temporary resized JPEG/PNG copy for analysis.
	 *
	 * @param int $attachment_id ID.
	 * @param int $max_dimension Maximum analysis-copy dimension in pixels.
	 * @return array|WP_Error
	 */
	private function prepare_analysis_image( $attachment_id, $max_dimension = 1024 ) {
		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
			return new WP_Error( 'gdaiidl_missing_image', __( 'The original image file is not readable.', 'ai-image-disclosure-labels' ) );
		}
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$max = max( 256, min( 2048, (int) $max_dimension ) );
		$size = $editor->get_size();
		if ( is_array( $size ) && ( (int) $size['width'] > $max || (int) $size['height'] > $max ) ) {
			$resized = $editor->resize( $max, $max, false );
			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
		}
		$editor->set_quality( 82 );
		$tmp = wp_tempnam( 'gdaiidl-ai-analysis.jpg' );
		if ( ! $tmp ) {
			return new WP_Error( 'gdaiidl_temp_failed', __( 'Could not create a temporary analysis image.', 'ai-image-disclosure-labels' ) );
		}
		$saved = $editor->save( $tmp, 'image/jpeg' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return is_wp_error( $saved ) ? $saved : new WP_Error( 'gdaiidl_temp_failed', __( 'Could not write the temporary analysis image.', 'ai-image-disclosure-labels' ) );
		}
		$max_bytes = 8 * MB_IN_BYTES;
		$temp_size = @filesize( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- temporary file can disappear; handled below.
		if ( false === $temp_size || $temp_size <= 0 || $temp_size > $max_bytes ) {
			@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $saved['path'] !== $tmp && file_exists( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return new WP_Error( 'gdaiidl_temp_size', __( 'The temporary analysis image is unexpectedly large or unreadable.', 'ai-image-disclosure-labels' ) );
		}
		$data = file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bounded temporary file.
		@unlink( $saved['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $saved['path'] !== $tmp && file_exists( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( false === $data || '' === $data || strlen( $data ) > $max_bytes ) {
			return new WP_Error( 'gdaiidl_read_failed', __( 'Could not safely read the temporary analysis image.', 'ai-image-disclosure-labels' ) );
		}
		return array( 'mime_type' => 'image/jpeg', 'base64' => base64_encode( $data ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Strict prompt. The model is never asked to prove authenticity.
	 *
	 * @return string
	 */
	private function analysis_prompt() {
		return 'Assess only the visible image for signs relevant to AI provenance. Return JSON only with keys classification, confidence, reason, limitations. classification must be exactly one of likely_ai_generated, likely_ai_modified, likely_non_ai, uncertain. likely_ai_generated means the image appears substantially or fully synthetic. likely_ai_modified means a pre-existing image appears materially modified with AI. Do not treat absence of obvious artifacts as proof that no AI was used. If evidence is weak or ambiguous, choose uncertain. confidence must be a number from 0 to 1 and is only your self-assessed confidence, not a calibrated forensic probability. Keep reason and limitations short.';
	}

	/**
	 * Provider dispatch.
	 *
	 * @param string $provider Provider.
	 * @param string $model Model.
	 * @param array  $image Image data.
	 * @param string     $prompt Prompt.
	 * @param array|null $settings Consistent settings snapshot.
	 * @return array|WP_Error
	 */
	private function call_provider( $provider, $model, $image, $prompt, $settings = null ) {
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		switch ( $provider ) {
			case 'openai': return $this->call_openai( $model, $image, $prompt );
			case 'gemini': return $this->call_gemini( $model, $image, $prompt );
			case 'anthropic': return $this->call_anthropic( $model, $image, $prompt );
			case 'cloudflare': return $this->call_cloudflare( $model, $image, $prompt, $s );
			case 'wordpress_ai_client': return $this->call_wordpress_ai_client( $model, $image, $prompt );
			case 'gd_cloudflare_connector': return $this->call_gd_cloudflare_connector( $model, $image, $prompt );
			case 'openai_compatible': return $this->call_openai_compatible( $model, $image, $prompt, $s );
			case 'custom': return $this->call_custom( $model, $image, $prompt, $s );
		}
		return new WP_Error( 'gdaiidl_provider', __( 'Unknown AI provider.', 'ai-image-disclosure-labels' ) );
	}

	/** HTTP JSON helper. */
	private function post_json( $url, $headers, $body, $timeout = 60 ) {
		$response = wp_safe_remote_post( $url, array( 'timeout' => $timeout, 'redirection' => 2, 'limit_response_size' => 2 * MB_IN_BYTES, 'headers' => $headers, 'body' => wp_json_encode( $body ), 'data_format' => 'body' ) );
		if ( is_wp_error( $response ) ) { return $response; }
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 ) {
			$message = $this->remote_error_message( $data, $raw, $code );
			return new WP_Error( 'gdaiidl_remote_' . $code, $message );
		}
		return is_array( $data ) ? $data : new WP_Error( 'gdaiidl_json', __( 'The AI provider returned invalid JSON.', 'ai-image-disclosure-labels' ) );
	}

	/** Error extractor. */
	private function remote_error_message( $data, $raw, $code ) {
		$candidates = array();
		if ( is_array( $data ) ) {
			if ( isset( $data['error']['message'] ) ) { $candidates[] = $data['error']['message']; }
			if ( isset( $data['errors'][0]['message'] ) ) { $candidates[] = $data['errors'][0]['message']; }
			if ( isset( $data['message'] ) ) { $candidates[] = $data['message']; }
		}
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) { return sprintf( __( 'Provider error %1$d: %2$s', 'ai-image-disclosure-labels' ), $code, sanitize_text_field( $candidate ) ); }
		}
		$raw = trim( wp_strip_all_tags( (string) $raw ) );
		return sprintf( __( 'Provider returned HTTP %d.%s', 'ai-image-disclosure-labels' ), $code, '' !== $raw ? ' ' . substr( $raw, 0, 300 ) : '' );
	}

	/** OpenAI Responses API. */
	private function call_openai( $model, $image, $prompt ) {
		$key = $this->api_key( 'openai' );
		$body = array(
			'model' => $model,
			'input' => array( array( 'role' => 'user', 'content' => array(
				array( 'type' => 'input_text', 'text' => $prompt ),
				array( 'type' => 'input_image', 'image_url' => 'data:' . $image['mime_type'] . ';base64,' . $image['base64'], 'detail' => 'low' ),
			) ) ),
			'max_output_tokens' => 500,
		);
		$data = $this->post_json( 'https://api.openai.com/v1/responses', array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ), $body );
		if ( is_wp_error( $data ) ) { return $data; }
		$text = '';
		if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) { $text = $data['output_text']; }
		if ( '' === $text && ! empty( $data['output'] ) && is_array( $data['output'] ) ) {
			foreach ( $data['output'] as $item ) { if ( ! empty( $item['content'] ) ) { foreach ( $item['content'] as $content ) { if ( isset( $content['text'] ) && is_string( $content['text'] ) ) { $text .= $content['text']; } } } }
		}
		return array( 'text' => $text, 'resolved_model' => isset( $data['model'] ) ? $data['model'] : $model, 'usage' => array( 'input_tokens' => isset( $data['usage']['input_tokens'] ) ? $data['usage']['input_tokens'] : null, 'output_tokens' => isset( $data['usage']['output_tokens'] ) ? $data['usage']['output_tokens'] : null ) );
	}

	/** Gemini generateContent. */
	private function call_gemini( $model, $image, $prompt ) {
		$key = $this->api_key( 'gemini' );
		$model_path = 0 === strpos( $model, 'models/' ) ? substr( $model, 7 ) : $model;
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model_path ) . ':generateContent';
		$body = array(
			'contents' => array( array( 'role' => 'user', 'parts' => array( array( 'inlineData' => array( 'mimeType' => $image['mime_type'], 'data' => $image['base64'] ) ), array( 'text' => $prompt ) ) ) ),
			'generationConfig' => array( 'temperature' => 0.1, 'maxOutputTokens' => 500, 'responseMimeType' => 'application/json' ),
		);
		$data = $this->post_json( $url, array( 'x-goog-api-key' => $key, 'Content-Type' => 'application/json' ), $body );
		if ( is_wp_error( $data ) ) { return $data; }
		$text = '';
		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) { foreach ( $data['candidates'][0]['content']['parts'] as $part ) { if ( isset( $part['text'] ) ) { $text .= $part['text']; } } }
		return array( 'text' => $text, 'resolved_model' => $model_path, 'usage' => array( 'input_tokens' => isset( $data['usageMetadata']['promptTokenCount'] ) ? $data['usageMetadata']['promptTokenCount'] : null, 'output_tokens' => isset( $data['usageMetadata']['candidatesTokenCount'] ) ? $data['usageMetadata']['candidatesTokenCount'] : null ) );
	}

	/** Anthropic Messages API. */
	private function call_anthropic( $model, $image, $prompt ) {
		$key = $this->api_key( 'anthropic' );
		$body = array( 'model' => $model, 'max_tokens' => 500, 'temperature' => 0, 'messages' => array( array( 'role' => 'user', 'content' => array(
			array( 'type' => 'image', 'source' => array( 'type' => 'base64', 'media_type' => $image['mime_type'], 'data' => $image['base64'] ) ),
			array( 'type' => 'text', 'text' => $prompt ),
		) ) ) );
		$data = $this->post_json( 'https://api.anthropic.com/v1/messages', array( 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json' ), $body );
		if ( is_wp_error( $data ) ) { return $data; }
		$text = '';
		if ( ! empty( $data['content'] ) ) { foreach ( $data['content'] as $content ) { if ( isset( $content['type'], $content['text'] ) && 'text' === $content['type'] ) { $text .= $content['text']; } } }
		return array( 'text' => $text, 'resolved_model' => isset( $data['model'] ) ? $data['model'] : $model, 'usage' => array( 'input_tokens' => isset( $data['usage']['input_tokens'] ) ? $data['usage']['input_tokens'] : null, 'output_tokens' => isset( $data['usage']['output_tokens'] ) ? $data['usage']['output_tokens'] : null ) );
	}


	/**
	 * Use the WordPress 7.0+ provider-agnostic AI Client.
	 *
	 * Provider credentials are owned by Core's Connectors API and are never read
	 * or duplicated by this plugin. An optional model ID is passed only as a
	 * preference; WordPress remains free to route to another compatible model.
	 */
	private function call_wordpress_ai_client( $model, $image, $prompt ) {
		if ( ! $this->has_wordpress_ai_client() ) {
			return new WP_Error( 'gdaiidl_wp_ai_client_missing', __( 'The WordPress AI Client or a registered AI provider is not available.', 'ai-image-disclosure-labels' ) );
		}

		$data_uri = 'data:' . $image['mime_type'] . ';base64,' . $image['base64'];
		try {
			$builder = wp_ai_client_prompt()
				->with_text( $prompt )
				->with_file( $data_uri );

			if ( '' !== trim( (string) $model ) ) {
				$builder->using_model_preference( trim( (string) $model ) );
			}

			if ( ! $builder->is_supported_for_text_generation() ) {
				return new WP_Error( 'gdaiidl_wp_ai_client_unsupported', __( 'WordPress could not find a configured model that supports text generation with image input.', 'ai-image-disclosure-labels' ) );
			}

			$result = $builder->generate_text_result();
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$text              = method_exists( $result, 'toText' ) ? (string) $result->toText() : '';
			$resolved_model    = '';
			$resolved_provider = '';

			if ( method_exists( $result, 'getModelMetadata' ) ) {
				$model_metadata = $result->getModelMetadata();
				if ( is_object( $model_metadata ) && method_exists( $model_metadata, 'getId' ) ) {
					$resolved_model = (string) $model_metadata->getId();
				} elseif ( is_array( $model_metadata ) && isset( $model_metadata['id'] ) ) {
					$resolved_model = (string) $model_metadata['id'];
				}
			}

			if ( method_exists( $result, 'getProviderMetadata' ) ) {
				$provider_metadata = $result->getProviderMetadata();
				if ( is_object( $provider_metadata ) && method_exists( $provider_metadata, 'getId' ) ) {
					$resolved_provider = (string) $provider_metadata->getId();
				} elseif ( is_array( $provider_metadata ) ) {
					foreach ( array( 'id', 'provider', 'provider_id' ) as $key ) {
						if ( isset( $provider_metadata[ $key ] ) && is_scalar( $provider_metadata[ $key ] ) ) {
							$resolved_provider = (string) $provider_metadata[ $key ];
							break;
						}
					}
				}
			}

			$usage = array( 'input_tokens' => null, 'output_tokens' => null );
			if ( method_exists( $result, 'getTokenUsage' ) ) {
				$token_usage = $result->getTokenUsage();
				if ( is_object( $token_usage ) ) {
					if ( method_exists( $token_usage, 'getPromptTokens' ) ) { $usage['input_tokens'] = $token_usage->getPromptTokens(); }
					if ( method_exists( $token_usage, 'getCompletionTokens' ) ) { $usage['output_tokens'] = $token_usage->getCompletionTokens(); }
				}
			}
		} catch ( \Throwable $e ) {
			return new WP_Error( 'gdaiidl_wp_ai_client_exception', sanitize_text_field( $e->getMessage() ) );
		}

		return array(
			'text'              => $text,
			'resolved_model'    => $resolved_model,
			'resolved_provider' => $resolved_provider,
			'usage'             => $usage,
		);
	}

	/**
	 * Reuse the private GD Cloudflare AI Connector already configured on this site.
	 *
	 * The connector's Direct Client owns credentials, Account ID, endpoint mode,
	 * AI Gateway/Workers AI routing, retries and provider-specific payload conversion.
	 * This plugin supplies only the requested model, image, prompt and JSON schema.
	 */
	private function call_gd_cloudflare_connector( $model, $image, $prompt ) {
		if ( ! $this->has_private_gd_cloudflare_connector() ) {
			return new WP_Error( 'gdaiidl_gd_connector_missing', __( 'The GD Cloudflare AI Connector is not available.', 'ai-image-disclosure-labels' ) );
		}

		$live_models = $this->fetch_models( array( 'provider' => 'gd_cloudflare_connector' ), false );
		if ( is_array( $live_models ) ) {
			$found = false;
			foreach ( $live_models as $live_model ) {
				if ( is_array( $live_model ) && isset( $live_model['id'] ) && (string) $live_model['id'] === (string) $model ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				return new WP_Error( 'gdaiidl_gd_connector_model_not_live', __( 'The selected model ID is not present in the GD Cloudflare AI Connector’s current live catalogue. Refresh the catalogue and choose one of the exact returned IDs. No replacement model is inferred.', 'ai-image-disclosure-labels' ) );
			}
		}

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'classification' => array( 'type' => 'string', 'enum' => array( 'likely_ai_generated', 'likely_ai_modified', 'likely_non_ai', 'uncertain' ) ),
				'confidence'     => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ),
				'reason'         => array( 'type' => 'string' ),
				'limitations'    => array( 'type' => 'string' ),
			),
			'required'             => array( 'classification', 'confidence', 'reason', 'limitations' ),
		);

		$result = \GD\CloudflareAIConnector\HTTP\Direct_Client::generateText(
			array(
				'caller'          => 'ai-image-disclosure-labels',
				'model'           => $model,
				'messages'        => array(
					array(
						'role'    => 'user',
						'content' => array(
							array( 'type' => 'text', 'text' => $prompt ),
							array( 'type' => 'image_url', 'image_url' => array( 'url' => 'data:' . $image['mime_type'] . ';base64,' . $image['base64'] ) ),
						),
					),
				),
				'max_tokens'      => 500,
				'temperature'     => 0.1,
				'response_schema' => $schema,
				'schema_name'     => 'gdaiidl_image_analysis',
			)
		);

		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			$message = is_array( $result ) && isset( $result['message'] ) && is_scalar( $result['message'] )
				? sanitize_text_field( (string) $result['message'] )
				: __( 'The GD Cloudflare AI Connector returned no usable result.', 'ai-image-disclosure-labels' );
			return new WP_Error( 'gdaiidl_gd_connector_error', $message );
		}

		$summary = isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array();
		$usage   = isset( $summary['usage'] ) && is_array( $summary['usage'] ) ? $summary['usage'] : array();
		return array(
			'text'           => isset( $result['text'] ) && is_string( $result['text'] ) ? $result['text'] : '',
			'resolved_model' => isset( $summary['model'] ) && is_string( $summary['model'] ) && '' !== $summary['model'] ? $summary['model'] : $model,
			'usage'          => array(
				'input_tokens'  => isset( $usage['input_tokens'] ) ? $usage['input_tokens'] : ( isset( $usage['prompt_tokens'] ) ? $usage['prompt_tokens'] : null ),
				'output_tokens' => isset( $usage['output_tokens'] ) ? $usage['output_tokens'] : ( isset( $usage['completion_tokens'] ) ? $usage['completion_tokens'] : null ),
			),
		);
	}

	/**
	 * Cloudflare Workers AI execute-model endpoint.
	 *
	 * The direct adapter intentionally targets Cloudflare vision-capable Text Generation models
	 * through the ImageTextToText base64-image/messages schema. Dedicated Image-to-Text models
	 * can expose task-specific schemas and should be routed through the Custom endpoint adapter.
	 */
	private function call_cloudflare( $model, $image, $prompt, $settings = null ) {
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		$key = $this->api_key( 'cloudflare' );
		$url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( $s['cloudflare_account_id'] ) . '/ai/run/' . str_replace( '%2F', '/', rawurlencode( $model ) );
		$body = array(
			/* Cloudflare's ImageTextToText schema uses a base64 image plus messages. */
			'image'       => $image['base64'],
			'messages'    => array( array( 'role' => 'user', 'content' => $prompt ) ),
			'max_tokens'  => 500,
			'temperature' => 0.1,
		);
		$data = $this->post_json( $url, array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ), $body );
		if ( is_wp_error( $data ) ) { return $data; }
		$result = isset( $data['result'] ) && is_array( $data['result'] ) ? $data['result'] : array();
		$text = '';
		foreach ( array( 'response', 'answer', 'description', 'result' ) as $field ) {
			if ( isset( $result[ $field ] ) ) {
				$text = is_string( $result[ $field ] ) ? $result[ $field ] : wp_json_encode( $result[ $field ] );
				if ( '' !== trim( (string) $text ) ) { break; }
			}
		}
		$usage = isset( $result['usage'] ) && is_array( $result['usage'] ) ? $result['usage'] : array();
		return array( 'text' => $text, 'resolved_model' => $model, 'usage' => array( 'input_tokens' => isset( $usage['prompt_tokens'] ) ? $usage['prompt_tokens'] : ( isset( $usage['input_tokens'] ) ? $usage['input_tokens'] : null ), 'output_tokens' => isset( $usage['completion_tokens'] ) ? $usage['completion_tokens'] : ( isset( $usage['output_tokens'] ) ? $usage['output_tokens'] : null ) ) );
	}

	/** OpenAI-compatible Chat Completions endpoint. */
	private function call_openai_compatible( $model, $image, $prompt, $settings = null ) {
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		$headers = array( 'Content-Type' => 'application/json' );
		$key = $this->api_key( 'openai_compatible' );
		if ( '' !== $key ) { $headers['Authorization'] = 'Bearer ' . $key; }
		$body = array( 'model' => $model, 'messages' => array( array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => $prompt ), array( 'type' => 'image_url', 'image_url' => array( 'url' => 'data:' . $image['mime_type'] . ';base64,' . $image['base64'] ) ) ) ) ), 'temperature' => 0.1, 'max_tokens' => 500 );
		$data = $this->post_json( $s['compatible_endpoint'], $headers, $body );
		if ( is_wp_error( $data ) ) { return $data; }
		$text = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
		return array( 'text' => is_string( $text ) ? $text : wp_json_encode( $text ), 'resolved_model' => isset( $data['model'] ) ? $data['model'] : $model, 'usage' => array( 'input_tokens' => isset( $data['usage']['prompt_tokens'] ) ? $data['usage']['prompt_tokens'] : null, 'output_tokens' => isset( $data['usage']['completion_tokens'] ) ? $data['usage']['completion_tokens'] : null ), 'cost_usd' => isset( $data['cost_usd'] ) ? $data['cost_usd'] : null );
	}

	/** Custom endpoint contract. */
	private function call_custom( $model, $image, $prompt, $settings = null ) {
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		$headers = array( 'Content-Type' => 'application/json' );
		$key = $this->api_key( 'custom' );
		if ( '' !== $key ) { $headers['Authorization'] = 'Bearer ' . $key; }
		$body = array( 'version' => 1, 'action' => 'analyze', 'model' => $model, 'image' => array( 'mime_type' => $image['mime_type'], 'base64' => $image['base64'] ), 'prompt' => $prompt, 'response_schema' => array( 'classification' => array( 'likely_ai_generated', 'likely_ai_modified', 'likely_non_ai', 'uncertain' ), 'confidence' => '0..1', 'reason' => 'string', 'limitations' => 'string' ) );
		$data = $this->post_json( $s['custom_endpoint'], $headers, $body );
		if ( is_wp_error( $data ) ) { return $data; }
		if ( isset( $data['result'] ) && is_array( $data['result'] ) ) { $data = array_merge( $data, $data['result'] ); }
		return array( 'structured' => $data, 'resolved_model' => isset( $data['resolved_model'] ) ? $data['resolved_model'] : $model, 'usage' => isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array(), 'cost_usd' => isset( $data['cost_usd'] ) ? $data['cost_usd'] : null, 'pricing' => isset( $data['pricing'] ) ? $data['pricing'] : null, 'verified_provenance' => isset( $data['verified_provenance'] ) && true === $data['verified_provenance'], 'evidence' => isset( $data['evidence'] ) ? $data['evidence'] : array() );
	}

	/**
	 * Normalize response to canonical result.
	 *
	 * @param array  $response Provider response.
	 * @param string $provider Provider.
	 * @param string $model Requested model.
	 * @return array|WP_Error
	 */
	private function normalize_analysis_response( $response, $provider, $model ) {
		$data = isset( $response['structured'] ) && is_array( $response['structured'] ) ? $response['structured'] : null;
		if ( null === $data ) {
			$text = isset( $response['text'] ) ? trim( (string) $response['text'] ) : '';
			$data = $this->decode_json_from_text( $text );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'gdaiidl_bad_analysis', __( 'The provider did not return a usable classification JSON object.', 'ai-image-disclosure-labels' ) );
			}
		}
		$class = isset( $data['classification'] ) ? $this->sanitize_classification( $data['classification'] ) : '';
		if ( '' === $class || 'failed' === $class ) {
			return new WP_Error( 'gdaiidl_bad_class', __( 'The provider returned an unsupported classification.', 'ai-image-disclosure-labels' ) );
		}
		$confidence = isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ? (float) $data['confidence'] : 0;
		if ( $confidence > 1 && $confidence <= 100 ) { $confidence /= 100; }
		$confidence = max( 0, min( 1, $confidence ) );
		$usage = isset( $response['usage'] ) && is_array( $response['usage'] ) ? $response['usage'] : array();
		$input_tokens = isset( $usage['input_tokens'] ) && is_numeric( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : ( isset( $usage['prompt_tokens'] ) && is_numeric( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : null );
		$output_tokens = isset( $usage['output_tokens'] ) && is_numeric( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : ( isset( $usage['completion_tokens'] ) && is_numeric( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : null );
		return array(
			'classification' => $class,
			'confidence' => $confidence,
			'reason' => isset( $data['reason'] ) ? substr( sanitize_textarea_field( $data['reason'] ), 0, 700 ) : '',
			'limitations' => isset( $data['limitations'] ) ? substr( sanitize_textarea_field( $data['limitations'] ), 0, 500 ) : '',
			'provider' => $provider,
			'model' => $model,
			'resolved_model' => isset( $response['resolved_model'] ) && '' !== trim( (string) $response['resolved_model'] ) ? substr( sanitize_text_field( $response['resolved_model'] ), 0, 220 ) : $model,
			'resolved_provider' => isset( $response['resolved_provider'] ) ? substr( sanitize_text_field( $response['resolved_provider'] ), 0, 120 ) : '',
			'usage' => array( 'input_tokens' => $input_tokens, 'output_tokens' => $output_tokens ),
			'provider_cost_usd' => isset( $response['cost_usd'] ) && is_numeric( $response['cost_usd'] ) ? (float) $response['cost_usd'] : null,
			'provider_pricing' => isset( $response['pricing'] ) && is_array( $response['pricing'] ) ? $response['pricing'] : null,
			'verified_provenance' => isset( $response['verified_provenance'] ) && true === $response['verified_provenance'],
			'evidence' => $this->sanitize_evidence( isset( $response['evidence'] ) ? $response['evidence'] : array() ),
		);
	}

	/** Sanitize optional evidence labels from a custom provenance-verification endpoint. */
	private function sanitize_evidence( $value ) {
		if ( is_string( $value ) ) { $value = array( $value ); }
		if ( ! is_array( $value ) ) { return array(); }
		$out = array();
		foreach ( array_slice( $value, 0, 12 ) as $item ) {
			if ( is_scalar( $item ) ) {
				$item = substr( sanitize_text_field( (string) $item ), 0, 120 );
				if ( '' !== $item ) { $out[] = $item; }
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** JSON extractor. */
	private function decode_json_from_text( $text ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$data = json_decode( $text, true );
		if ( is_array( $data ) ) { return $data; }
		$start = strpos( $text, '{' ); $end = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			if ( is_array( $data ) ) { return $data; }
		}
		return null;
	}

	/** Canonical classification. */
	private function sanitize_classification( $value ) {
		$value = sanitize_key( (string) $value );
		$aliases = array( 'ai_generated' => 'likely_ai_generated', 'generated' => 'likely_ai_generated', 'ai_modified' => 'likely_ai_modified', 'modified' => 'likely_ai_modified', 'non_ai' => 'likely_non_ai', 'not_ai' => 'likely_non_ai', 'unknown' => 'uncertain' );
		if ( isset( $aliases[ $value ] ) ) { $value = $aliases[ $value ]; }
		return in_array( $value, array( 'likely_ai_generated', 'likely_ai_modified', 'likely_non_ai', 'uncertain', 'failed' ), true ) ? $value : '';
	}

	/** Label. */
	private function classification_label( $class ) {
		$labels = array( 'likely_ai_generated' => __( 'Likely AI-generated', 'ai-image-disclosure-labels' ), 'likely_ai_modified' => __( 'Likely AI-modified', 'ai-image-disclosure-labels' ), 'likely_non_ai' => __( 'Likely non-AI', 'ai-image-disclosure-labels' ), 'uncertain' => __( 'Uncertain', 'ai-image-disclosure-labels' ), 'failed' => __( 'Failed', 'ai-image-disclosure-labels' ) );
		return isset( $labels[ $class ] ) ? $labels[ $class ] : __( 'Not analysed', 'ai-image-disclosure-labels' );
	}

	/** Get result. */
	private function get_analysis_result( $attachment_id ) {
		$value = get_post_meta( $attachment_id, self::META_RESULT, true );
		return is_array( $value ) ? $value : array();
	}

	/** Store failure. */
	private function store_failed_result( $attachment_id, $message ) {
		$result = array( 'classification' => 'failed', 'confidence' => 0, 'reason' => substr( sanitize_text_field( $message ), 0, 700 ), 'provider' => $this->settings()['provider'], 'model' => $this->settings()['model'], 'analysed_at' => time() );
		update_post_meta( $attachment_id, self::META_RESULT, $result );
		update_post_meta( $attachment_id, self::META_CLASS, 'failed' );
	}

	/** Analysis missing. */
	private function attachment_needs_analysis( $attachment_id ) {
		return '' === (string) get_post_meta( $attachment_id, self::META_CLASS, true );
	}

	/** Apply suggestion without ever auto-declaring no-AI. */
	private function apply_suggestion( $attachment_id, $verified = false ) {
		$plugin = GDAIIDL_Plugin::instance();
		if ( '' !== $plugin->get_attachment_ai_status( $attachment_id ) ) { return false; }
		$result = $this->get_analysis_result( $attachment_id );
		if ( ! $result || empty( $result['classification'] ) || ! isset( $result['confidence'] ) ) { return false; }
		$threshold = (float) $this->settings()['auto_apply_threshold'] / 100;
		if ( ! $verified && (float) $result['confidence'] < $threshold ) { return false; }
		$status = 'likely_ai_generated' === $result['classification'] ? 'generated' : ( 'likely_ai_modified' === $result['classification'] ? 'modified' : '' );
		return '' !== $status ? $plugin->set_attachment_ai_status( $attachment_id, $status, false ) : false;
	}

	/**
	 * Resolve per-request cost with no hard-coded provider prices.
	 *
	 * @param array      $result Normalized result.
	 * @param array|null $settings Consistent settings snapshot.
	 * @return array{cost:float|null,source:string}
	 */
	private function resolve_request_cost( $result, $settings = null ) {
		if ( isset( $result['provider_cost_usd'] ) && is_numeric( $result['provider_cost_usd'] ) && (float) $result['provider_cost_usd'] >= 0 ) {
			return array( 'cost' => (float) $result['provider_cost_usd'], 'source' => 'provider_reported' );
		}
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		if ( 'none' === $s['pricing_mode'] ) { return array( 'cost' => null, 'source' => 'unavailable' ); }
		if ( 'fixed' === $s['pricing_mode'] && is_numeric( $s['manual_fixed_per_request_usd'] ) ) { return array( 'cost' => (float) $s['manual_fixed_per_request_usd'], 'source' => 'manual_fixed' ); }
		$usage = isset( $result['usage'] ) && is_array( $result['usage'] ) ? $result['usage'] : array();
		$input = isset( $usage['input_tokens'] ) && is_numeric( $usage['input_tokens'] ) ? (float) $usage['input_tokens'] : null;
		$output = isset( $usage['output_tokens'] ) && is_numeric( $usage['output_tokens'] ) ? (float) $usage['output_tokens'] : null;
		$rates = null;
		if ( 'manual_tokens' === $s['pricing_mode'] ) {
			if ( is_numeric( $s['manual_input_per_million_usd'] ) || is_numeric( $s['manual_output_per_million_usd'] ) ) {
				$rates = array( 'input_per_million' => (float) $s['manual_input_per_million_usd'], 'output_per_million' => (float) $s['manual_output_per_million_usd'] );
			}
		} elseif ( 'auto' === $s['pricing_mode'] ) {
			if ( isset( $result['provider_pricing'] ) && is_array( $result['provider_pricing'] ) ) { $rates = $this->normalize_pricing( $result['provider_pricing'] ); }
			if ( ! $rates ) { $rates = $this->cached_model_pricing( $s['provider'], $s['model'], $s ); }
		}
		if ( ! $rates || null === $input || null === $output ) { return array( 'cost' => null, 'source' => 'unavailable' ); }
		$source = 'manual_tokens' === $s['pricing_mode'] ? 'manual_token_rates' : 'provider_catalogue';
		return array( 'cost' => ( $input / 1000000 ) * $rates['input_per_million'] + ( $output / 1000000 ) * $rates['output_per_million'], 'source' => $source );
	}

	/** Pricing normalizer: only explicit numeric machine-readable fields. */
	private function normalize_pricing( $data ) {
		if ( ! is_array( $data ) ) { return null; }
		$input_keys = array( 'input_per_million', 'input_per_million_usd', 'input_price_per_million', 'input_usd_per_million' );
		$output_keys = array( 'output_per_million', 'output_per_million_usd', 'output_price_per_million', 'output_usd_per_million' );
		$input = null; $output = null;
		foreach ( $input_keys as $key ) { if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) { $input = (float) $data[ $key ]; break; } }
		foreach ( $output_keys as $key ) { if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) { $output = (float) $data[ $key ]; break; } }
		if ( null === $input || null === $output ) { return null; }
		return array( 'input_per_million' => max( 0, $input ), 'output_per_million' => max( 0, $output ) );
	}

	/** Model pricing from cached normalized catalog. */
	private function cached_model_pricing( $provider, $model, $settings = null ) {
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		$models = get_transient( $this->model_cache_key( $provider, $s ) );
		if ( ! is_array( $models ) ) { return null; }
		foreach ( $models as $item ) { if ( isset( $item['id'] ) && $item['id'] === $model && ! empty( $item['pricing'] ) ) { return $this->normalize_pricing( $item['pricing'] ); } }
		return null;
	}

	/** Record known costs for future estimates. */
	private function record_cost_stat( $result ) {
		if ( ! isset( $result['cost_usd'] ) || ! is_numeric( $result['cost_usd'] ) ) { return; }
		$key = md5( $result['provider'] . '|' . $result['model'] );
		$stats = get_option( self::COST_STATS_OPTION, array() );
		$stats = is_array( $stats ) ? $stats : array();
		if ( ! isset( $stats[ $key ] ) ) { $stats[ $key ] = array( 'count' => 0, 'cost' => 0.0, 'provider' => $result['provider'], 'model' => $result['model'], 'updated_at' => 0 ); }
		$stats[ $key ]['count'] = (int) $stats[ $key ]['count'] + 1;
		$stats[ $key ]['cost'] = (float) $stats[ $key ]['cost'] + (float) $result['cost_usd'];
		$stats[ $key ]['updated_at'] = time();
		update_option( self::COST_STATS_OPTION, $stats, false );
	}

	/** Estimate from fixed manual cost or observed actual/derived history. */
	private function estimate_cost_for_count( $count ) {
		$count = max( 0, (int) $count );
		$s = $this->settings();
		if ( 'fixed' === $s['pricing_mode'] && is_numeric( $s['manual_fixed_per_request_usd'] ) ) {
			return array( 'known' => true, 'usd' => $count * (float) $s['manual_fixed_per_request_usd'], 'source' => 'manual_fixed' );
		}
		$key = md5( $s['provider'] . '|' . $s['model'] );
		$stats = get_option( self::COST_STATS_OPTION, array() );
		if ( isset( $stats[ $key ]['count'], $stats[ $key ]['cost'] ) && (int) $stats[ $key ]['count'] > 0 ) {
			$avg = (float) $stats[ $key ]['cost'] / (int) $stats[ $key ]['count'];
			return array( 'known' => true, 'usd' => $count * $avg, 'source' => 'observed_average', 'samples' => (int) $stats[ $key ]['count'] );
		}
		return array( 'known' => false, 'usd' => null, 'source' => 'unavailable' );
	}

	/**
	 * Discover configured WordPress AI Client models that declare text generation
	 * with image input. This uses the bundled PHP AI Client registry; no model IDs
	 * or provider names are hard-coded.
	 *
	 * @return array|WP_Error
	 */
	private function fetch_wordpress_ai_client_models() {
		if ( ! $this->has_wordpress_ai_client() ) {
			return new WP_Error( 'gdaiidl_wp_ai_client_missing', __( 'The WordPress AI Client or a registered provider is not available.', 'ai-image-disclosure-labels' ) );
		}

		$requirements_class    = '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements';
		$required_option_class = '\WordPress\AiClient\Providers\Models\DTO\RequiredOption';
		$capability_class      = '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum';
		$option_class          = '\WordPress\AiClient\Providers\Models\Enums\OptionEnum';
		$modality_class        = '\WordPress\AiClient\Messages\Enums\ModalityEnum';
		if ( ! class_exists( $requirements_class ) || ! class_exists( $required_option_class ) || ! class_exists( $capability_class ) || ! class_exists( $option_class ) || ! class_exists( $modality_class ) ) {
			return new WP_Error( 'gdaiidl_wp_ai_client_registry', __( 'The installed WordPress AI Client does not expose the expected model metadata API.', 'ai-image-disclosure-labels' ) );
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			$requirements = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
				array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\RequiredOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text(), \WordPress\AiClient\Messages\Enums\ModalityEnum::image() )
					),
				)
			);
			$groups = $registry->findModelsMetadataForSupport( $requirements );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'gdaiidl_wp_ai_client_models', sanitize_text_field( $e->getMessage() ) );
		}

		$models = array();
		foreach ( (array) $groups as $group ) {
			if ( ! is_object( $group ) || ! method_exists( $group, 'getProvider' ) || ! method_exists( $group, 'getModels' ) ) {
				continue;
			}
			$provider_metadata = $group->getProvider();
			$provider_id = is_object( $provider_metadata ) && method_exists( $provider_metadata, 'getId' ) ? (string) $provider_metadata->getId() : '';
			$provider_name = is_object( $provider_metadata ) && method_exists( $provider_metadata, 'getName' ) ? (string) $provider_metadata->getName() : $provider_id;
			if ( '' !== $provider_id && method_exists( $registry, 'isProviderConfigured' ) && ! $registry->isProviderConfigured( $provider_id ) ) {
				continue;
			}
			foreach ( (array) $group->getModels() as $model_metadata ) {
				if ( ! is_object( $model_metadata ) || ! method_exists( $model_metadata, 'getId' ) ) { continue; }
				$model_id = trim( (string) $model_metadata->getId() );
				if ( '' === $model_id ) { continue; }
				$model_name = method_exists( $model_metadata, 'getName' ) ? trim( (string) $model_metadata->getName() ) : '';
				$label = '' !== $provider_name ? $provider_name . ' — ' . $model_id : $model_id;
				if ( '' !== $model_name && $model_name !== $model_id ) { $label .= ' (' . $model_name . ')'; }
				$models[] = array(
					'id' => substr( sanitize_text_field( $model_id ), 0, 220 ),
					'label' => substr( sanitize_text_field( $label ), 0, 300 ),
					'provider_id' => substr( sanitize_key( $provider_id ), 0, 80 ),
				);
			}
		}

		if ( empty( $models ) ) {
			return new WP_Error( 'gdaiidl_wp_ai_client_no_models', __( 'No configured WordPress AI Connector currently advertises a model with text generation and image input. Check Settings → Connectors.', 'ai-image-disclosure-labels' ) );
		}
		return $models;
	}

	/**
	 * Dynamic model discovery.
	 *
	 * @param array $context Request context.
	 * @param bool  $force Force refresh.
	 * @return array|WP_Error
	 */
	private function fetch_models( $context = array(), $force = false ) {
		$s = array_merge( $this->settings(), is_array( $context ) ? $context : array() );
		$provider = isset( $s['provider'] ) && isset( $this->providers()[ $s['provider'] ] ) ? $s['provider'] : $this->settings()['provider'];
		$cache_key = $this->model_cache_key( $provider, $s );
		if ( ! $force ) { $cached = get_transient( $cache_key ); if ( is_array( $cached ) ) { return $cached; } }
		$key = $this->api_key( $provider );
		$headers = array( 'Accept' => 'application/json' );
		$url = ''; $data = null;

		if ( 'wordpress_ai_client' === $provider ) {
			$models = $this->fetch_wordpress_ai_client_models();
			if ( is_wp_error( $models ) ) { return $models; }
			set_transient( $cache_key, $models, 12 * HOUR_IN_SECONDS );
			return $models;
		} elseif ( 'gd_cloudflare_connector' === $provider ) {
			if ( ! $this->has_private_gd_cloudflare_connector() ) {
				return new WP_Error( 'gdaiidl_gd_connector_missing', __( 'The GD Cloudflare AI Connector is not available.', 'ai-image-disclosure-labels' ) );
			}
			if ( $force && defined( 'GD\\CloudflareAIConnector\\MODEL_CACHE_KEY' ) ) {
				$connector_cache_key = constant( 'GD\\CloudflareAIConnector\\MODEL_CACHE_KEY' );
				if ( is_string( $connector_cache_key ) && '' !== $connector_cache_key ) {
					delete_transient( $connector_cache_key );
					delete_transient( $connector_cache_key . '_lock' );
				}
			}
			if ( ! function_exists( '\GD\CloudflareAIConnector\fetch_models' ) ) {
				return new WP_Error( 'gdaiidl_gd_connector_models', __( 'The installed GD Cloudflare AI Connector does not expose live model discovery.', 'ai-image-disclosure-labels' ) );
			}
			$list = call_user_func( '\GD\CloudflareAIConnector\fetch_models' );
			if ( ! is_array( $list ) || empty( $list ) ) {
				return new WP_Error( 'gdaiidl_gd_connector_models', __( 'The GD Cloudflare AI Connector returned no live model catalogue.', 'ai-image-disclosure-labels' ) );
			}
			$models = $this->normalize_models( 'gd_cloudflare_connector', array( 'models' => $list ) );
			if ( empty( $models ) ) {
				return new WP_Error( 'gdaiidl_no_models', __( 'The connector returned no usable model identifiers. You can still enter a current vision-capable model manually.', 'ai-image-disclosure-labels' ) );
			}
			set_transient( $cache_key, $models, 12 * HOUR_IN_SECONDS );
			return $models;
		} elseif ( 'openai' === $provider ) {
			$url = 'https://api.openai.com/v1/models'; $headers['Authorization'] = 'Bearer ' . $key;
		} elseif ( 'gemini' === $provider ) {
			$url = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1000'; $headers['x-goog-api-key'] = $key;
		} elseif ( 'anthropic' === $provider ) {
			$url = 'https://api.anthropic.com/v1/models?limit=1000'; $headers['x-api-key'] = $key; $headers['anthropic-version'] = '2023-06-01';
		} elseif ( 'cloudflare' === $provider ) {
			if ( empty( $s['cloudflare_account_id'] ) ) { return new WP_Error( 'gdaiidl_cf_account', __( 'Cloudflare Account ID is required.', 'ai-image-disclosure-labels' ) ); }
			$url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( $s['cloudflare_account_id'] ) . '/ai/models/search?per_page=100&hide_experimental=true&task=Text%20Generation'; $headers['Authorization'] = 'Bearer ' . $key;
		} elseif ( 'openai_compatible' === $provider ) {
			if ( empty( $s['compatible_models_endpoint'] ) ) { return new WP_Error( 'gdaiidl_models_endpoint', __( 'No Models endpoint is configured. Enter the model manually.', 'ai-image-disclosure-labels' ) ); }
			$url = $s['compatible_models_endpoint']; if ( '' !== $key ) { $headers['Authorization'] = 'Bearer ' . $key; }
		} elseif ( 'custom' === $provider ) {
			if ( empty( $s['custom_endpoint'] ) ) { return new WP_Error( 'gdaiidl_custom_endpoint', __( 'Custom endpoint is required.', 'ai-image-disclosure-labels' ) ); }
			$headers['Content-Type'] = 'application/json'; if ( '' !== $key ) { $headers['Authorization'] = 'Bearer ' . $key; }
			$response = wp_safe_remote_post( $s['custom_endpoint'], array( 'timeout' => 30, 'redirection' => 2, 'limit_response_size' => 5 * MB_IN_BYTES, 'headers' => $headers, 'body' => wp_json_encode( array( 'version' => 1, 'action' => 'models' ) ), 'data_format' => 'body' ) );
			$data = $this->decode_remote_json( $response );
		}
		if ( null === $data && '' !== $url ) {
			$response = wp_safe_remote_get( $url, array( 'timeout' => 30, 'redirection' => 2, 'limit_response_size' => 5 * MB_IN_BYTES, 'headers' => $headers ) );
			$data = $this->decode_remote_json( $response );
		}
		if ( is_wp_error( $data ) ) { return $data; }
		$models = $this->normalize_models( $provider, $data );
		if ( empty( $models ) ) { return new WP_Error( 'gdaiidl_no_models', __( 'The provider returned no usable model identifiers. You can enter a model manually.', 'ai-image-disclosure-labels' ) ); }
		set_transient( $cache_key, $models, 12 * HOUR_IN_SECONDS );
		return $models;
	}

	/** Remote GET/POST decoder. */
	private function decode_remote_json( $response ) {
		if ( is_wp_error( $response ) ) { return $response; }
		$code = (int) wp_remote_retrieve_response_code( $response ); $raw = wp_remote_retrieve_body( $response ); $data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 ) { return new WP_Error( 'gdaiidl_models_' . $code, $this->remote_error_message( $data, $raw, $code ) ); }
		return is_array( $data ) ? $data : new WP_Error( 'gdaiidl_models_json', __( 'The Models endpoint returned invalid JSON.', 'ai-image-disclosure-labels' ) );
	}

	/** Normalize heterogeneous model catalogues. */
	private function normalize_models( $provider, $data ) {
		$list = array();
		if ( 'gemini' === $provider && isset( $data['models'] ) ) { $list = $data['models']; }
		elseif ( 'cloudflare' === $provider && isset( $data['result'] ) ) { $list = $data['result']; }
		elseif ( 'custom' === $provider && isset( $data['models'] ) ) { $list = $data['models']; }
		elseif ( 'gd_cloudflare_connector' === $provider && isset( $data['models'] ) ) { $list = $data['models']; }
		elseif ( isset( $data['data'] ) ) { $list = $data['data']; }
		elseif ( isset( $data['models'] ) ) { $list = $data['models']; }
		$out = array();
		foreach ( array_slice( (array) $list, 0, 1000 ) as $item ) {
			if ( is_string( $item ) ) { $item = array( 'id' => $item ); }
			if ( ! is_array( $item ) ) { continue; }
			$id = '';
			foreach ( array( 'id', 'name', 'model', 'model_id' ) as $key ) { if ( isset( $item[ $key ] ) && is_string( $item[ $key ] ) && '' !== trim( $item[ $key ] ) ) { $id = trim( $item[ $key ] ); break; } }
			if ( '' === $id ) { continue; }
			if ( 'gemini' === $provider && 0 === strpos( $id, 'models/' ) ) { $id = substr( $id, 7 ); }
			if ( 'gemini' === $provider && isset( $item['supportedGenerationMethods'] ) && is_array( $item['supportedGenerationMethods'] ) && ! in_array( 'generateContent', $item['supportedGenerationMethods'], true ) ) { continue; }
			if ( 'anthropic' === $provider && isset( $item['capabilities']['image_input']['supported'] ) && ! $item['capabilities']['image_input']['supported'] ) { continue; }
			$label = isset( $item['display_name'] ) ? $item['display_name'] : ( isset( $item['label'] ) ? $item['label'] : ( isset( $item['name'] ) && is_string( $item['name'] ) ? $item['name'] : $id ) );
			$pricing = null;
			if ( isset( $item['pricing'] ) && is_array( $item['pricing'] ) ) { $pricing = $this->normalize_pricing( $item['pricing'] ); }
			if ( ! $pricing && isset( $item['unit_pricing'] ) && is_array( $item['unit_pricing'] ) ) { $pricing = $this->normalize_pricing( $item['unit_pricing'] ); }
			$model_entry = array(
				'id'      => substr( sanitize_text_field( $id ), 0, 220 ),
				'label'   => substr( sanitize_text_field( $label ), 0, 300 ),
				'pricing' => $pricing,
			);
			foreach ( array( 'task', 'capabilities' ) as $meta_key ) {
				if ( isset( $item[ $meta_key ] ) ) {
					$metadata = $this->sanitize_model_metadata_value( $item[ $meta_key ] );
					if ( null !== $metadata ) { $model_entry[ $meta_key ] = $metadata; }
				}
			}
			$out[ $id ] = $model_entry;
		}
		return array_values( $out );
	}

	/**
	 * Bound and sanitize optional provider model metadata before caching/returning it.
	 *
	 * @param mixed $value Provider value.
	 * @param int   $depth Recursion depth.
	 * @return mixed|null
	 */
	private function sanitize_model_metadata_value( $value, $depth = 0 ) {
		if ( $depth > 2 ) { return null; }
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { return $value; }
		if ( is_string( $value ) ) { return substr( sanitize_text_field( $value ), 0, 300 ); }
		if ( ! is_array( $value ) ) { return null; }
		$out = array();
		$count = 0;
		foreach ( $value as $key => $item ) {
			if ( $count >= 20 ) { break; }
			$clean = $this->sanitize_model_metadata_value( $item, $depth + 1 );
			if ( null === $clean ) { continue; }
			$clean_key = is_int( $key ) ? $key : substr( sanitize_key( (string) $key ), 0, 80 );
			$out[ $clean_key ] = $clean;
			++$count;
		}
		return $out;
	}

	/** Cache key. */
	private function model_cache_key( $provider, $settings ) {
		$connector_version = 'gd_cloudflare_connector' === $provider && defined( 'GD_CLOUDFLARE_AI_CONNECTOR_VERSION' ) ? (string) GD_CLOUDFLARE_AI_CONNECTOR_VERSION : '';
		if ( 'wordpress_ai_client' === $provider && $this->has_wordpress_ai_client() ) {
			try {
				$registry = \WordPress\AiClient\AiClient::defaultRegistry();
				$connector_version = implode( ',', (array) $registry->getRegisteredProviderIds() );
			} catch ( \Throwable $e ) {
				$connector_version = 'wordpress-ai-client';
			}
		}
		$context = GDAIIDL_VERSION . '|' . $provider . '|' . $connector_version . '|' . ( isset( $settings['cloudflare_account_id'] ) ? $settings['cloudflare_account_id'] : '' ) . '|' . ( isset( $settings['custom_endpoint'] ) ? $settings['custom_endpoint'] : '' ) . '|' . ( isset( $settings['compatible_models_endpoint'] ) ? $settings['compatible_models_endpoint'] : '' );
		return 'gdaiidl_models_' . substr( md5( $context ), 0, 24 );
	}

	/**
	 * Fingerprint settings that affect analysis semantics, provider routing or cost calculation.
	 * Secrets are deliberately excluded so credentials can be rotated without invalidating a job.
	 *
	 * @param array|null $settings Settings snapshot.
	 * @return string
	 */
	private function analysis_config_fingerprint( $settings = null ) {
		$s = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : $this->settings();
		$keys = array(
			'enabled', 'provider', 'model', 'cloudflare_account_id', 'custom_endpoint', 'compatible_endpoint',
			'compatible_models_endpoint', 'analysis_max_dimension', 'auto_apply_visual', 'auto_apply_verified',
			'auto_apply_threshold', 'pricing_mode', 'manual_input_per_million_usd',
			'manual_output_per_million_usd', 'manual_fixed_per_request_usd',
		);
		$snapshot = array();
		foreach ( $keys as $key ) {
			$snapshot[ $key ] = isset( $s[ $key ] ) ? $s[ $key ] : null;
		}
		if ( 'gd_cloudflare_connector' === $s['provider'] && $this->has_private_gd_cloudflare_connector() ) {
			$snapshot['gd_cloudflare_connector_version'] = defined( 'GD_CLOUDFLARE_AI_CONNECTOR_VERSION' ) ? (string) GD_CLOUDFLARE_AI_CONNECTOR_VERSION : '';
			if ( function_exists( '\\GD\\CloudflareAIConnector\\get_endpoint_mode' ) ) {
				$snapshot['gd_cloudflare_endpoint_mode'] = (string) call_user_func( '\\GD\\CloudflareAIConnector\\get_endpoint_mode' );
			}
			if ( function_exists( '\\GD\\CloudflareAIConnector\\get_gateway_id' ) ) {
				$snapshot['gd_cloudflare_gateway_id'] = (string) call_user_func( '\\GD\\CloudflareAIConnector\\get_gateway_id' );
			}
		}
		return hash( 'sha256', wp_json_encode( $snapshot ) );
	}

	/** Jobs storage. */
	private function jobs() { $jobs = get_option( self::JOBS_OPTION, array() ); return is_array( $jobs ) ? $jobs : array(); }
	private function get_job( $job_id ) { $jobs = $this->jobs(); return isset( $jobs[ $job_id ] ) ? $jobs[ $job_id ] : array(); }
	private function save_jobs( $jobs ) {
		uasort( $jobs, static function( $a, $b ) { return (int) $b['created_at'] <=> (int) $a['created_at']; } );
		$kept = array();
		$history_count = 0;
		foreach ( $jobs as $job_id => $job ) {
			$status = isset( $job['status'] ) ? (string) $job['status'] : '';
			if ( in_array( $status, array( 'queued', 'running' ), true ) ) {
				$kept[ $job_id ] = $job;
				continue;
			}
			if ( $history_count < 12 ) {
				$kept[ $job_id ] = $job;
				++$history_count;
			}
		}
		update_option( self::JOBS_OPTION, $kept, false );
	}

	/** Latest job HTML. */
	private function latest_job_status_html() {
		$jobs = $this->jobs();
		if ( empty( $jobs ) ) { return '<p class="description">' . esc_html__( 'No AI-analysis jobs have run yet.', 'ai-image-disclosure-labels' ) . '</p>'; }
		$first = reset( $jobs );
		return $this->job_status_html( isset( $first['id'] ) ? $first['id'] : '' );
	}

	/** Job status HTML. */
	private function job_status_html( $job_id ) {
		$job = $this->get_job( $job_id );
		if ( ! $job ) { return '<p class="description">' . esc_html__( 'No job information is available.', 'ai-image-disclosure-labels' ) . '</p>'; }
		$total = max( 1, (int) $job['total'] ); $processed = (int) $job['processed']; $percent = min( 100, (int) round( 100 * $processed / $total ) );
		$status_labels = array( 'queued' => __( 'Queued', 'ai-image-disclosure-labels' ), 'running' => __( 'Running', 'ai-image-disclosure-labels' ), 'completed' => __( 'Completed', 'ai-image-disclosure-labels' ), 'cancelled' => __( 'Cancelled', 'ai-image-disclosure-labels' ), 'stopped_cost' => __( 'Stopped at cost limit', 'ai-image-disclosure-labels' ), 'stopped_config' => __( 'Stopped because settings changed', 'ai-image-disclosure-labels' ) );
		$status = isset( $status_labels[ $job['status'] ] ) ? $status_labels[ $job['status'] ] : $job['status'];
		$html = '<div class="gdaiidl-ai-job" data-job-id="' . esc_attr( $job_id ) . '"><p><strong>' . esc_html( $status ) . '</strong> · ' . esc_html( sprintf( __( '%1$d / %2$d images (%3$d%%)', 'ai-image-disclosure-labels' ), $processed, (int) $job['total'], $percent ) ) . '</p>';
		$html .= '<div class="gdaiidl-ai-progress"><span style="width:' . esc_attr( $percent ) . '%"></span></div>';
		$html .= '<p class="description">' . esc_html( sprintf( __( 'Generated: %1$d · Modified: %2$d · Likely non-AI: %3$d · Uncertain: %4$d · Failed: %5$d', 'ai-image-disclosure-labels' ), (int) $job['generated'], (int) $job['modified'], (int) $job['non_ai'], (int) $job['uncertain'], (int) $job['failed'] ) ) . '</p>';
		$html .= '<p class="description">' . esc_html( sprintf( __( 'Known/estimated cost recorded by the plugin: $%1$.6f · Requests with unknown cost: %2$d', 'ai-image-disclosure-labels' ), (float) $job['known_cost'], (int) $job['unknown_cost_count'] ) ) . '</p>';
		if ( ! empty( $job['last_error'] ) ) { $html .= '<p class="description gdaiidl-ai-error">' . esc_html( $job['last_error'] ) . '</p>'; }
		if ( in_array( $job['status'], array( 'queued', 'running' ), true ) ) { $html .= '<p><button type="button" class="button-link-delete gdaiidl-ai-cancel-job" data-job-id="' . esc_attr( $job_id ) . '">' . esc_html__( 'Cancel job', 'ai-image-disclosure-labels' ) . '</button></p>'; }
		$html .= '</div>';
		return $html;
	}
}
