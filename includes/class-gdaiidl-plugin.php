<?php
/**
 * Main plugin class.
 *
 * @package GDAIIDL_Plugin
 */

defined( 'ABSPATH' ) || exit;

final class GDAIIDL_Plugin {

	const OPTION_KEY            = 'gdaiidl_settings';
	const VERSION_OPTION        = 'gdaiidl_version';
	const META_FEATURED_ENABLED     = '_gdaiidl_featured_enabled';
	const META_FEATURED_TEXT        = '_gdaiidl_featured_text';
	const META_FEATURED_SOURCE_TYPE = '_gdaiidl_featured_source_type';
	const META_MEDIA_SOURCE_TYPE    = '_gdaiidl_media_source_type';
	const META_AVERAGE_COLOR        = '_gdaiidl_avg_color';

	/**
	 * Singleton instance.
	 *
	 * @var GDAIIDL_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Per-request settings cache.
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	/**
	 * Machine-readable ImageObject and VideoObject records collected during front-end rendering.
	 *
	 * @var array
	 */
	private $machine_readable_media = array();

	/**
	 * Whether the front-end asset configuration has already been prepared.
	 *
	 * @var bool
	 */
	private $frontend_assets_prepared = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return GDAIIDL_Plugin
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
		self::migrate_legacy_options();

		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', true );
		}

		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			add_option( self::VERSION_OPTION, GDAIIDL_VERSION, '', true );
		} else {
			update_option( self::VERSION_OPTION, GDAIIDL_VERSION );
		}

		if ( function_exists( 'wp_set_options_autoload' ) ) {
			wp_set_options_autoload( array( self::OPTION_KEY, self::VERSION_OPTION ), true );
		}
	}

	/**
	 * Return storage keys used by versions released before 2.0.1.
	 *
	 * These compatibility-only names are assembled from their historical
	 * components and are never used for newly stored data. They exist solely
	 * to migrate and remove data created by earlier GitHub releases.
	 *
	 * @return array
	 */
	private static function legacy_storage_keys() {
		$legacy_prefix = 'gd_' . 'ai_';

		return array(
			'settings'         => $legacy_prefix . 'image_labels_settings',
			'version'          => $legacy_prefix . 'image_labels_version',
			'featured_enabled' => '_' . $legacy_prefix . 'featured_enabled',
			'featured_text'    => '_' . $legacy_prefix . 'featured_text',
			'average_color'    => '_' . $legacy_prefix . 'avg_color',
		);
	}

	/**
	 * Copy legacy options to the new uniquely prefixed option names.
	 *
	 * @return void
	 */
	private static function migrate_legacy_options() {
		$legacy = self::legacy_storage_keys();

		if ( false === get_option( self::OPTION_KEY, false ) ) {
			$legacy_settings = get_option( $legacy['settings'], false );

			if ( is_array( $legacy_settings ) ) {
				add_option( self::OPTION_KEY, $legacy_settings, '', true );
			}
		}

		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			$legacy_version = get_option( $legacy['version'], '' );

			if ( '' !== $legacy_version ) {
				add_option( self::VERSION_OPTION, (string) $legacy_version, '', true );
			}
		}

		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			delete_option( $legacy['settings'] );
		}

		if ( false !== get_option( self::VERSION_OPTION, false ) ) {
			delete_option( $legacy['version'] );
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
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_content_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 99 );
		add_action( 'wp_footer', array( $this, 'print_late_frontend_styles' ), 1 );

		add_filter( 'render_block_core/image', array( $this, 'render_image_block_label' ), 10, 2 );
		add_filter( 'render_block_core/video', array( $this, 'render_video_block_label' ), 10, 2 );
		add_filter( 'wp_get_attachment_image', array( $this, 'render_featured_attachment_label' ), 20, 5 );
		add_filter( 'post_thumbnail_html', array( $this, 'render_featured_image_label' ), 20, 5 );
		add_action( 'wp_footer', array( $this, 'render_machine_readable_source_data' ), 99 );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_media', array( $this, 'enqueue_media_admin_assets' ), 20 );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'handle_settings_update' ), 10, 3 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'clear_attachment_color_cache' ), 10, 2 );

		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_source_type_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_source_type_field' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'add_media_source_status_column' ), 10, 2 );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_source_status_column' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'add_media_source_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_media_source_bulk_action' ), 10, 3 );
		add_action( 'restrict_manage_posts', array( $this, 'render_media_source_status_filter' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_media_library_by_source_status' ) );
		add_filter( 'posts_where', array( $this, 'filter_media_library_mime_types_where' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_media_source_bulk_notice' ) );

		add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_frontend_script_from_delay' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GDAIIDL_FILE ), array( $this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_images'             => true,
			'enable_videos'             => true,
			'label_text'               => 'AI-generated',
			'label_text_modified'      => 'AI-modified',
			'machine_readable_enabled' => false,
			'media_library_auto_label' => false,
			'load_assets_only_when_needed' => true,
			'digital_source_type'       => 'generated',
			'position'                 => 'bottom-right',
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
			'touch_compact_mode'  => true,
			'icon_style'          => 'monogram',
			'custom_icon_id'       => 0,
			'custom_selectors'     => '',
			'location_rules_enabled' => false,
			'icon_only_selectors'    => '',
			'hidden_selectors'       => '',
			'icon_size_value'      => 16,
			'icon_size_unit'       => 'px',
			'icon_tooltip_enabled'  => false,
			'background_color_mode' => 'auto',
			'font_family_mode'      => 'inherit',
			'font_family_custom'    => '',
			'video_separate_design'  => false,
			'video_label_text'        => '',
			'video_label_text_modified' => '',
			'video_alignment'         => 'right',
			'video_background_color'  => '#171717',
			'video_background_opacity'=> 78,
			'video_text_color'        => '#ffffff',
			'video_border_color'      => '#ffffff',
			'video_border_width'      => 1,
			'video_border_radius'     => 3,
			'video_font_size'         => 9,
			'video_font_weight'       => 600,
			'video_padding_vertical'  => 3,
			'video_padding_horizontal'=> 5,
			'video_text_transform'    => 'none',
		);
	}

	/**
	 * Supported standardized digital source types.
	 *
	 * This precise provenance vocabulary is intentionally more granular than the
	 * publisher-facing Media Library statuses below. The editor can retain or emit
	 * exact generative-edit/enhancement provenance while the public classification
	 * UI stays simpler: AI-generated / AI-modified / No AI used.
	 *
	 * The labels follow the IPTC Digital Source Type vocabulary. The URLs are
	 * the corresponding Schema.org enumeration members used by the
	 * digitalSourceType property.
	 *
	 * @return array
	 */
	private function digital_source_types() {
		return array(
			'generated' => array(
				'label' => __( 'Created using generative AI', 'ai-image-disclosure-labels' ),
				'badge' => __( 'AI-generated', 'ai-image-disclosure-labels' ),
				'uri'   => 'https://schema.org/TrainedAlgorithmicMediaDigitalSource',
			),
			'edited'    => array(
				'label' => __( 'Edited using generative AI', 'ai-image-disclosure-labels' ),
				'badge' => __( 'AI-edited', 'ai-image-disclosure-labels' ),
				'uri'   => 'https://schema.org/CompositeWithTrainedAlgorithmicMediaDigitalSource',
			),
			'enhanced'  => array(
				'label' => __( 'Enhanced using AI', 'ai-image-disclosure-labels' ),
				'badge' => __( 'AI-enhanced', 'ai-image-disclosure-labels' ),
				'uri'   => 'https://schema.org/AlgorithmicallyEnhancedDigitalSource',
			),
		);
	}

	/**
	 * Build the ordered source-type option list used by the block editor.
	 *
	 * The list starts with the inherit choice (empty value) so a per-image
	 * override can fall back to the global default, followed by every
	 * registered digital source type. Keeping this in one place means new
	 * source types automatically appear in both editor panels.
	 *
	 * @return array List of { label, value } pairs.
	 */
	private function source_type_select_options() {
		$options = array(
			array(
				'label' => __( 'Use global default', 'ai-image-disclosure-labels' ),
				'value' => '',
			),
		);

		foreach ( $this->digital_source_types() as $key => $data ) {
			$options[] = array(
				'label' => $data['label'],
				'value' => $key,
			);
		}

		return $options;
	}

	/**
	 * Media Library classification choices.
	 *
	 * The empty value means the attachment has not been classified. "No AI
	 * used" is an explicit publisher declaration, but it deliberately does not
	 * map to an AI digitalSourceType URI: this plugin cannot prove forensic
	 * originality or authenticity.
	 *
	 * @return array Ordered map of value => status data.
	 */
	private function media_source_statuses() {
		return array(
			''          => array(
				'label' => __( 'Not classified', 'ai-image-disclosure-labels' ),
				'badge' => __( 'Unclassified', 'ai-image-disclosure-labels' ),
			),
			'no-ai'     => array(
				'label' => __( 'No AI used', 'ai-image-disclosure-labels' ),
				'badge' => __( 'No AI', 'ai-image-disclosure-labels' ),
			),
			'generated' => array(
				'label' => __( 'AI-generated', 'ai-image-disclosure-labels' ),
				'badge' => __( 'AI-generated', 'ai-image-disclosure-labels' ),
			),
			'modified'  => array(
				'label' => __( 'AI-modified', 'ai-image-disclosure-labels' ),
				'badge' => __( 'AI-modified', 'ai-image-disclosure-labels' ),
			),
		);
	}

	/**
	 * Sanitize a Media Library classification.
	 *
	 * @param mixed  $value       Candidate status.
	 * @param string $fallback    Fallback status.
	 * @param bool   $allow_empty Whether an empty/unclassified value is valid.
	 * @return string
	 */
	private function sanitize_media_source_status( $value, $fallback = '', $allow_empty = true ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';

		if ( '' === $value ) {
			return $allow_empty ? '' : $fallback;
		}

		/* 2.3.x stored these as separate publisher-facing states. */
		if ( in_array( $value, array( 'edited', 'enhanced' ), true ) ) {
			$value = 'modified';
		}

		$statuses = $this->media_source_statuses();

		return isset( $statuses[ $value ] ) ? $value : $fallback;
	}

	/**
	 * Read the complete Media Library classification stored on an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Empty string when unclassified or invalid.
	 */
	private function attachment_media_status_raw( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return '';
		}

		$value   = sanitize_key( (string) get_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE, true ) );
		$allowed = array( 'no-ai', 'generated', 'modified', 'edited', 'enhanced' );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	private function attachment_media_status( $attachment_id ) {
		return $this->sanitize_media_source_status( $this->attachment_media_status_raw( $attachment_id ), '', true );
	}

	/**
	 * Public read-only access for the optional AI-analysis module.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Publisher-facing status.
	 */
	public function get_attachment_ai_status( $attachment_id ) {
		return $this->attachment_media_status( $attachment_id );
	}

	/**
	 * Whether a MIME type is supported by publisher-facing media classification.
	 *
	 * AI-assisted analysis intentionally remains image-only; this helper is for
	 * manual/status disclosure features shared by images and videos.
	 *
	 * @param string $mime_type Attachment MIME type.
	 * @return bool
	 */
	private function is_supported_disclosure_mime_type( $mime_type ) {
		$mime_type = (string) $mime_type;

		return 0 === strpos( $mime_type, 'image/' ) || 0 === strpos( $mime_type, 'video/' );
	}


	/**
	 * Whether disclosure UI/output is enabled for a supported MIME type.
	 *
	 * Turning a media type off preserves stored attachment metadata; it only
	 * disables editor controls and public disclosure output for that type.
	 *
	 * @param string $mime_type Attachment MIME type.
	 * @return bool
	 */
	private function is_disclosure_mime_type_enabled( $mime_type ) {
		$settings  = $this->settings();
		$mime_type = (string) $mime_type;

		if ( 0 === strpos( $mime_type, 'image/' ) ) {
			return ! empty( $settings['enable_images'] );
		}

		if ( 0 === strpos( $mime_type, 'video/' ) ) {
			return ! empty( $settings['enable_videos'] );
		}

		return false;
	}

	/**
	 * Whether an attachment is an image or video supported by disclosure tools.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_supported_disclosure_attachment( $attachment_id ) {
		return $this->is_supported_disclosure_mime_type( get_post_mime_type( (int) $attachment_id ) );
	}

	/**
	 * Set the publisher-facing attachment status from a trusted internal action.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status        One of generated, modified, no-ai or empty.
	 * @param bool   $purge_cache   Whether to purge page caches after a change.
	 * @return bool
	 */
	public function set_attachment_ai_status( $attachment_id, $status, $purge_cache = true ) {
		$attachment_id = absint( $attachment_id );
		$status        = $this->sanitize_media_source_status( $status, '__invalid__', true );

		if (
			$attachment_id <= 0 ||
			'__invalid__' === $status ||
			! $this->is_supported_disclosure_attachment( $attachment_id )
		) {
			return false;
		}

		$previous = $this->attachment_media_status( $attachment_id );

		if ( $previous === $status ) {
			return true;
		}

		if ( '' === $status ) {
			delete_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE );
		} else {
			update_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE, $status );
		}

		if ( $purge_cache ) {
			$this->maybe_purge_page_cache();
		}

		return true;
	}

	/**
	 * Read the AI digital source type stored on an attachment.
	 *
	 * Explicit "No AI used" and unclassified attachments intentionally resolve
	 * to an empty source type and therefore produce no AI digitalSourceType.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Empty string when no AI source type is declared.
	 */
	private function attachment_source_type( $attachment_id ) {
		/* Preserve precise legacy provenance where 2.3.x recorded it. */
		$status = $this->attachment_media_status_raw( $attachment_id );
		$types  = $this->digital_source_types();

		return isset( $types[ $status ] ) ? $status : '';
	}

	/**
	 * Resolve the configurable public label text for a publisher-facing status.
	 *
	 * Only positive AI classifications have visible default text. Explicit
	 * "No AI used" and unclassified/unknown states intentionally return an
	 * empty string and therefore never create an automatic visible label.
	 *
	 * @param string $status Publisher-facing status.
	 * @return string
	 */
	private function label_text_for_media_status( $status, $media = 'image' ) {
		$status   = $this->sanitize_media_source_status( $status, '', true );
		$settings = $this->settings();
		$is_video = 'video' === $media;

		if ( 'generated' === $status ) {
			if ( $is_video && ! empty( $settings['video_label_text'] ) ) {
				return trim( (string) $settings['video_label_text'] );
			}
			return isset( $settings['label_text'] ) ? trim( (string) $settings['label_text'] ) : '';
		}

		if ( 'modified' === $status ) {
			if ( $is_video && ! empty( $settings['video_label_text_modified'] ) ) {
				return trim( (string) $settings['video_label_text_modified'] );
			}
			return isset( $settings['label_text_modified'] ) ? trim( (string) $settings['label_text_modified'] ) : '';
		}

		return '';
	}

	/**
	 * Map a precise technical source type to the simpler public status.
	 *
	 * @param string $source_type Digital source type.
	 * @return string generated, modified, or empty.
	 */
	private function public_status_for_source_type( $source_type ) {
		$source_type = $this->sanitize_digital_source_type( $source_type, '', true );

		if ( 'generated' === $source_type ) {
			return 'generated';
		}

		if ( in_array( $source_type, array( 'edited', 'enhanced' ), true ) ) {
			return 'modified';
		}

		return '';
	}

	/**
	 * Resolve the default visible label for one explicitly marked image use.
	 *
	 * Per-use source type wins. Otherwise a positive Media Library status is
	 * respected, followed by the global source-type default. A custom per-image
	 * text is resolved by the caller before this fallback is used.
	 *
	 * @param string $source_type   Optional per-use source type.
	 * @param int    $attachment_id Attachment ID when available.
	 * @return string
	 */
	private function default_label_text_for_usage( $source_type = '', $attachment_id = 0, $media = 'image' ) {
		$source_type = $this->sanitize_digital_source_type( $source_type, '', true );

		if ( '' !== $source_type ) {
			return $this->label_text_for_media_status( $this->public_status_for_source_type( $source_type ), $media );
		}

		$attachment_id = (int) $attachment_id;

		if ( $attachment_id > 0 ) {
			$media_status = $this->attachment_media_status( $attachment_id );

			if ( in_array( $media_status, array( 'generated', 'modified' ), true ) ) {
				return $this->label_text_for_media_status( $media_status, $media );
			}
		}

		$settings       = $this->settings();
		$global_source  = isset( $settings['digital_source_type'] ) ? $settings['digital_source_type'] : 'generated';
		$public_status  = $this->public_status_for_source_type( $global_source );

		return $this->label_text_for_media_status( $public_status, $media );
	}

	/**
	 * Whether the opt-in automatic Media Library labelling is active.
	 *
	 * @return bool
	 */
	private function media_auto_label_enabled() {
		$settings = $this->settings();

		return ! empty( $settings['media_library_auto_label'] );
	}

	/**
	 * Add a source-type selector to the attachment details and edit screens.
	 *
	 * @param array   $form_fields Existing attachment form fields.
	 * @param WP_Post $post        Attachment post object.
	 * @return array
	 */
	public function add_attachment_source_type_field( $form_fields, $post ) {
		if ( ! ( $post instanceof WP_Post ) || 'attachment' !== $post->post_type ) {
			return $form_fields;
		}

		if ( ! $this->is_supported_disclosure_attachment( $post->ID ) || ! $this->is_disclosure_mime_type_enabled( $post->post_mime_type ) ) {
			return $form_fields;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $form_fields;
		}

		$current = $this->attachment_media_status( $post->ID );

		/*
		 * The name must use the attachments[ID][key] array notation so the
		 * value is delivered to the attachment_fields_to_save handler as
		 * $attachment['gdaiidl_media_source_type']. The id is only used to
		 * associate the WordPress-rendered label with the control.
		 */
		$field_name = sprintf( 'attachments[%d][gdaiidl_media_source_type]', (int) $post->ID );
		$field_id   = sprintf( 'attachments-%d-gdaiidl_media_source_type', (int) $post->ID );
		$statuses   = $this->media_source_statuses();
		$options    = '';

		foreach ( $statuses as $value => $data ) {
			$options .= sprintf(
				'<option value="%1$s" data-gdaiidl-status="%2$s" data-gdaiidl-badge="%3$s"%4$s>%5$s</option>',
				esc_attr( $value ),
				esc_attr( '' !== $value ? $value : 'unclassified' ),
				esc_attr( $data['badge'] ),
				selected( $current, $value, false ),
				esc_html( $data['label'] )
			);
		}

		$current_data = isset( $statuses[ $current ] ) ? $statuses[ $current ] : $statuses[''];
		$current_key  = '' !== $current ? $current : 'unclassified';

		$html = sprintf(
			'<div class="gdaiidl-media-source-control" data-gdaiidl-media-source-control><div class="gdaiidl-media-source-row"><select class="gdaiidl-media-source-select" name="%1$s" id="%2$s">%3$s</select><span class="gdaiidl-status-chip gdaiidl-status-%4$s" data-gdaiidl-status-badge aria-live="polite">%5$s</span></div><p class="gdaiidl-media-source-help">%6$s</p></div>',
			esc_attr( $field_name ),
			esc_attr( $field_id ),
			$options,
			esc_attr( $current_key ),
			esc_html( $current_data['badge'] ),
			esc_html__( 'Classify this image or video once for the Media Library, block inheritance and structured source data. “No AI used” is a publisher declaration, not an authenticity check.', 'ai-image-disclosure-labels' )
		);

		$form_fields['gdaiidl_media_source_type'] = array(
			'label' => __( 'AI status', 'ai-image-disclosure-labels' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * Persist the attachment source-type selector.
	 *
	 * @param array $post       Attachment post data (array form).
	 * @param array $attachment Submitted attachment fields.
	 * @return array
	 */
	public function save_attachment_source_type_field( $post, $attachment ) {
		if ( ! is_array( $post ) || ! isset( $post['ID'] ) ) {
			return $post;
		}

		if ( ! array_key_exists( 'gdaiidl_media_source_type', (array) $attachment ) ) {
			return $post;
		}

		$attachment_id = (int) $post['ID'];

		if ( ! current_user_can( 'edit_post', $attachment_id ) && ! current_user_can( 'upload_files' ) ) {
			return $post;
		}

		if ( ! $this->is_supported_disclosure_attachment( $attachment_id ) ) {
			return $post;
		}

		$previous     = $this->attachment_media_status( $attachment_id );
		$previous_raw = $this->attachment_media_status_raw( $attachment_id );
		$raw_value = is_string( $attachment['gdaiidl_media_source_type'] )
			? sanitize_key( wp_unslash( $attachment['gdaiidl_media_source_type'] ) )
			: '';

		if ( '' === $raw_value ) {
			$value = '';
			delete_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE );
		} else {
			$statuses = $this->media_source_statuses();

			/* Unknown/tampered values must not erase a valid existing classification. */
			if ( ! isset( $statuses[ $raw_value ] ) ) {
				return $post;
			}

			$value = $raw_value;

			/*
			 * 2.3.x knew whether a modification was generative or merely an
			 * algorithmic enhancement. The simplified 2.4 UI intentionally
			 * collapses both to “AI-modified”, but an ordinary attachment save
			 * must not throw that more precise legacy provenance away.
			 */
			if ( 'modified' === $value && in_array( $previous_raw, array( 'edited', 'enhanced' ), true ) ) {
				/* Keep the precise stored value; the publisher-facing value is still modified. */
			} else {
				update_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE, $value );
			}
		}

		/*
		 * A changed Media Library mark can alter machine-readable output and,
		 * when automatic labelling is enabled, the visible page. Clear caches
		 * so the change is reflected immediately. The value is unknown to page
		 * caches, so a domain-wide purge is the safe choice.
		 */
		if ( $previous !== $value ) {
			$this->maybe_purge_page_cache();
		}

		return $post;
	}

	/**
	 * Add an AI status column to the Media Library list view.
	 *
	 * @param array $columns  Existing columns.
	 * @param bool  $detached Whether the list contains unattached media.
	 * @return array
	 */
	public function add_media_source_status_column( $columns, $detached = false ) {
		unset( $detached );
		$settings = $this->settings();
		if ( empty( $settings['enable_images'] ) && empty( $settings['enable_videos'] ) ) {
			return $columns;
		}

		$new_columns = array();

		foreach ( (array) $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['gdaiidl_ai_status'] = __( 'AI status', 'ai-image-disclosure-labels' );
			}
		}

		if ( ! isset( $new_columns['gdaiidl_ai_status'] ) ) {
			$new_columns['gdaiidl_ai_status'] = __( 'AI status', 'ai-image-disclosure-labels' );
		}

		return $new_columns;
	}

	/**
	 * Render the Media Library AI status column.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment ID.
	 * @return void
	 */
	public function render_media_source_status_column( $column_name, $post_id ) {
		if ( 'gdaiidl_ai_status' !== $column_name ) {
			return;
		}

		if ( ! $this->is_supported_disclosure_attachment( $post_id ) ) {
			echo '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' .
				esc_html__( 'Not an image or video', 'ai-image-disclosure-labels' ) .
			'</span>';
			return;
		}

		if ( ! $this->is_disclosure_mime_type_enabled( get_post_mime_type( $post_id ) ) ) {
			echo '<span class="gdaiidl-status-chip gdaiidl-status-disabled">' . esc_html__( 'Disabled', 'ai-image-disclosure-labels' ) . '</span>';
			return;
		}

		$status   = $this->attachment_media_status( $post_id );
		$statuses = $this->media_source_statuses();
		$data     = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses[''];
		$class    = '' !== $status ? $status : 'unclassified';

		printf(
			'<span class="gdaiidl-status-chip gdaiidl-status-%1$s" title="%2$s">%3$s</span>',
			esc_attr( $class ),
			esc_attr( $data['label'] ),
			esc_html( $data['badge'] )
		);
	}

	/**
	 * Add grouped Media Library bulk actions for publisher-declared AI status.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function add_media_source_bulk_actions( $actions ) {
		$settings = $this->settings();
		if ( empty( $settings['enable_images'] ) && empty( $settings['enable_videos'] ) ) {
			return $actions;
		}

		$actions[ __( 'AI source status', 'ai-image-disclosure-labels' ) ] = array(
			'gdaiidl_set_generated' => __( 'Mark as AI-generated', 'ai-image-disclosure-labels' ),
			'gdaiidl_set_modified'  => __( 'Mark as AI-modified', 'ai-image-disclosure-labels' ),
			'gdaiidl_set_no_ai'     => __( 'Mark as no AI used', 'ai-image-disclosure-labels' ),
			'gdaiidl_clear_status'  => __( 'Clear AI classification', 'ai-image-disclosure-labels' ),
		);

		return $actions;
	}

	/**
	 * Apply one of the custom Media Library bulk actions.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Selected action.
	 * @param array  $post_ids     Selected attachment IDs.
	 * @return string
	 */
	public function handle_media_source_bulk_action( $redirect_url, $action, $post_ids ) {
		$action_map = array(
			'gdaiidl_set_generated' => 'generated',
			'gdaiidl_set_modified'  => 'modified',
			'gdaiidl_set_no_ai'     => 'no-ai',
			'gdaiidl_clear_status'  => '',
		);

		if ( ! isset( $action_map[ $action ] ) ) {
			return $redirect_url;
		}

		$new_status = $action_map[ $action ];
		$statuses   = $this->media_source_statuses();

		if ( '' !== $new_status && ! isset( $statuses[ $new_status ] ) ) {
			return $redirect_url;
		}

		$updated = 0;
		$skipped = 0;
		$changed = false;

		foreach ( array_map( 'absint', (array) $post_ids ) as $attachment_id ) {
			if (
				$attachment_id <= 0 ||
				! current_user_can( 'edit_post', $attachment_id ) ||
				! $this->is_supported_disclosure_attachment( $attachment_id ) ||
				! $this->is_disclosure_mime_type_enabled( get_post_mime_type( $attachment_id ) )
			) {
				++$skipped;
				continue;
			}

			$previous = $this->attachment_media_status( $attachment_id );

			if ( $previous === $new_status ) {
				++$updated;
				continue;
			}

			if ( '' === $new_status ) {
				delete_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE );
			} else {
				update_post_meta( $attachment_id, self::META_MEDIA_SOURCE_TYPE, $new_status );
			}

			$changed = true;
			++$updated;
		}

		if ( $changed ) {
			$this->maybe_purge_page_cache();
		}

		$redirect_url = remove_query_arg(
			array( 'gdaiidl_ai_updated', 'gdaiidl_ai_skipped' ),
			$redirect_url
		);

		return add_query_arg(
			array(
				'gdaiidl_ai_updated' => $updated,
				'gdaiidl_ai_skipped' => $skipped,
			),
			$redirect_url
		);
	}

	/**
	 * Show a status filter in the Media Library list view.
	 *
	 * @param string $post_type Current post type.
	 * @param string $which     Filter location.
	 * @return void
	 */
	public function render_media_source_status_filter( $post_type, $which = '' ) {
		$settings = $this->settings();
		if ( empty( $settings['enable_images'] ) && empty( $settings['enable_videos'] ) ) {
			return;
		}

		if ( 'attachment' !== $post_type || ( '' !== $which && 'bar' !== $which ) ) {
			return;
		}

		$current = '';
		if ( isset( $_GET['gdaiidl_ai_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current = sanitize_key( wp_unslash( $_GET['gdaiidl_ai_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$statuses = $this->media_source_statuses();
		?>
		<label for="gdaiidl-ai-status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by AI status', 'ai-image-disclosure-labels' ); ?></label>
		<select name="gdaiidl_ai_status" id="gdaiidl-ai-status-filter">
			<option value=""><?php esc_html_e( 'All AI statuses', 'ai-image-disclosure-labels' ); ?></option>
			<option value="unclassified" <?php selected( $current, 'unclassified' ); ?>><?php esc_html_e( 'Unclassified', 'ai-image-disclosure-labels' ); ?></option>
			<?php foreach ( $statuses as $value => $data ) : ?>
				<?php if ( '' === $value ) { continue; } ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $data['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply the Media Library AI-status filter to the main attachment query.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public function filter_media_library_by_source_status( $query ) {
		if (
			! is_admin() ||
			! ( $query instanceof WP_Query ) ||
			! $query->is_main_query() ||
			'attachment' !== $query->get( 'post_type' ) ||
			! isset( $_GET['gdaiidl_ai_status'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['gdaiidl_ai_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $status ) {
			return;
		}

		/* Restrict the custom AI-status filter to supported image/video media. */
		$query->set( 'gdaiidl_disclosure_media_only', true );

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		if ( 'unclassified' === $status ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_MEDIA_SOURCE_TYPE,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::META_MEDIA_SOURCE_TYPE,
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => self::META_MEDIA_SOURCE_TYPE,
					'value'   => array( 'no-ai', 'generated', 'modified', 'edited', 'enhanced' ),
					'compare' => 'NOT IN',
				),
			);
		} else {
			$statuses = $this->media_source_statuses();

			if ( ! isset( $statuses[ $status ] ) || '' === $status ) {
				return;
			}

			$meta_query[] = array(
				'key'     => self::META_MEDIA_SOURCE_TYPE,
				'value'   => 'modified' === $status ? array( 'modified', 'edited', 'enhanced' ) : $status,
				'compare' => 'modified' === $status ? 'IN' : '=',
			);
		}

		$query->set( 'meta_query', $meta_query );
	}


	/**
	 * Restrict an active AI-status list query to image and video attachments.
	 *
	 * This composes with WordPress' own media-type selector: selecting Images or
	 * Videos still narrows the query further, while the default "All media"
	 * view excludes unrelated audio/document attachments from AI-status results.
	 *
	 * @param string   $where Existing SQL WHERE clause.
	 * @param WP_Query $query Current query.
	 * @return string
	 */
	public function filter_media_library_mime_types_where( $where, $query ) {
		if (
			! is_admin() ||
			! ( $query instanceof WP_Query ) ||
			! $query->is_main_query() ||
			! $query->get( 'gdaiidl_disclosure_media_only' )
		) {
			return $where;
		}

		global $wpdb;

		$settings = $this->settings();
		$images_enabled = ! empty( $settings['enable_images'] );
		$videos_enabled = ! empty( $settings['enable_videos'] );

		if ( ! $images_enabled && ! $videos_enabled ) {
			return $where . ' AND 1=0';
		}

		if ( $images_enabled && $videos_enabled ) {
			return $where . $wpdb->prepare(
				' AND ( %i.post_mime_type LIKE %s OR %i.post_mime_type LIKE %s )',
				$wpdb->posts,
				$wpdb->esc_like( 'image/' ) . '%',
				$wpdb->posts,
				$wpdb->esc_like( 'video/' ) . '%'
			);
		}

		return $where . $wpdb->prepare(
			' AND %i.post_mime_type LIKE %s',
			$wpdb->posts,
			$wpdb->esc_like( $images_enabled ? 'image/' : 'video/' ) . '%'
		);
	}

	/**
	 * Show the result of an AI-status bulk action.
	 *
	 * @return void
	 */
	public function render_media_source_bulk_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'upload' !== $screen->id || ! isset( $_GET['gdaiidl_ai_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$updated = absint( wp_unslash( $_GET['gdaiidl_ai_updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped = isset( $_GET['gdaiidl_ai_skipped'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? absint( wp_unslash( $_GET['gdaiidl_ai_skipped'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 0;
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of media items updated. */
					esc_html( _n( '%d media classification processed.', '%d media classifications processed.', $updated, 'ai-image-disclosure-labels' ) ),
					(int) $updated
				);
				if ( $skipped > 0 ) {
					echo ' ';
					printf(
						/* translators: %d: number of selected items skipped. */
						esc_html( _n( '%d item was skipped.', '%d items were skipped.', $skipped, 'ai-image-disclosure-labels' ) ),
						(int) $skipped
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Validate a digital source type key.
	 *
	 * @param mixed  $value         Candidate source type.
	 * @param string $fallback      Fallback key.
	 * @param bool   $allow_inherit Whether an empty value is allowed.
	 * @return string
	 */
	private function sanitize_digital_source_type( $value, $fallback = 'generated', $allow_inherit = false ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';

		if ( $allow_inherit && '' === $value ) {
			return '';
		}

		$types = $this->digital_source_types();

		return isset( $types[ $value ] ) ? $value : $fallback;
	}

	/**
	 * Resolve the effective source type for one marked media use.
	 *
	 * @param mixed $override Optional per-use source type.
	 * @return string Empty when machine-readable output is disabled.
	 */
	private function resolved_digital_source_type( $override = '' ) {
		$settings = $this->settings();

		if ( empty( $settings['machine_readable_enabled'] ) ) {
			return '';
		}

		$override = $this->sanitize_digital_source_type( $override, '', true );

		if ( '' !== $override ) {
			return $override;
		}

		return $this->sanitize_digital_source_type(
			isset( $settings['digital_source_type'] ) ? $settings['digital_source_type'] : 'generated'
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
	/**
	 * Public cache-purge bridge for optional internal modules.
	 *
	 * @return void
	 */
	public function flush_disclosure_caches() {
		$this->maybe_purge_page_cache();
	}

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
		do_action( 'gdaiidl_purge_caches' );
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
		self::migrate_legacy_options();

		$installed_version = (string) get_option( self::VERSION_OPTION, '' );

		if ( '' !== $installed_version && version_compare( $installed_version, GDAIIDL_VERSION, '>=' ) ) {
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

		update_option( self::VERSION_OPTION, GDAIIDL_VERSION, true );

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
		$post_types = apply_filters( 'gdaiidl_post_types', array( 'post', 'page' ) );
		$post_types = is_array( $post_types ) ? array_map( 'sanitize_key', $post_types ) : array( 'post', 'page' );
		$post_types = array_values( array_unique( array_filter( $post_types, 'post_type_exists' ) ) );

		return $post_types ? $post_types : array( 'post', 'page' );
	}


	/**
	 * Convert a newline-separated selector setting to a compact array.
	 *
	 * @param string $value Saved selector list.
	 * @return array
	 */
	private function selector_lines( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		$selectors = array_values( array_unique( array_filter( array_map( 'trim', explode( "\n", $value ) ) ) ) );

		return array_slice( $selectors, 0, 30 );
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

		$selectors = apply_filters( 'gdaiidl_featured_selectors', array_merge( $user_selectors, $selectors ) );

		return is_array( $selectors ) ? array_values( array_filter( array_map( 'sanitize_text_field', $selectors ) ) ) : array();
	}

	/**
	 * Read metadata and lazily migrate a value from an earlier GitHub release.
	 *
	 * @param int    $post_id   Post or attachment ID.
	 * @param string $new_key   Current metadata key.
	 * @param string $legacy_id Key name in legacy_storage_keys().
	 * @return mixed
	 */
	private function get_compatible_post_meta( $post_id, $new_key, $legacy_id ) {
		$value = get_post_meta( $post_id, $new_key, true );

		if ( '' !== $value ) {
			return $value;
		}

		$legacy     = self::legacy_storage_keys();
		$legacy_key = isset( $legacy[ $legacy_id ] ) ? $legacy[ $legacy_id ] : '';

		if ( '' === $legacy_key ) {
			return $value;
		}

		$legacy_value = get_post_meta( $post_id, $legacy_key, true );

		if ( '' === $legacy_value ) {
			return $value;
		}

		update_post_meta( $post_id, $new_key, $legacy_value );
		delete_post_meta( $post_id, $legacy_key );

		return $legacy_value;
	}

	/**
	 * Delete current and legacy metadata for one logical field.
	 *
	 * @param int    $post_id   Post or attachment ID.
	 * @param string $new_key   Current metadata key.
	 * @param string $legacy_id Key name in legacy_storage_keys().
	 * @return void
	 */
	private function delete_compatible_post_meta( $post_id, $new_key, $legacy_id ) {
		delete_post_meta( $post_id, $new_key );

		$legacy = self::legacy_storage_keys();

		if ( isset( $legacy[ $legacy_id ] ) ) {
			delete_post_meta( $post_id, $legacy[ $legacy_id ] );
		}
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

			register_post_meta(
				$post_type,
				self::META_FEATURED_SOURCE_TYPE,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_key',
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
			'gdaiidl/v1',
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
							'sanitize_callback' => 'sanitize_text_field',
						),
						'source_type' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				'schema' => array( $this, 'rest_featured_label_schema' ),
			)
		);

		register_rest_route(
			'gdaiidl/v1',
			'/attachment/(?P<id>\\d+)/ai-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_attachment_ai_status' ),
				'permission_callback' => array( $this, 'rest_attachment_ai_status_permissions_check' ),
			)
		);
	}

	/**
	 * Check access to the attachment AI-status endpoint used by the editor.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function rest_attachment_ai_status_permissions_check( $request ) {
		$attachment_id = absint( $request['id'] );
		$post          = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type || ! $this->is_supported_disclosure_mime_type( $post->post_mime_type ) ) {
			return new WP_Error(
				'gdaiidl_invalid_attachment',
				__( 'The image or video attachment could not be found.', 'ai-image-disclosure-labels' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'gdaiidl_forbidden_attachment',
				__( 'You are not allowed to edit this image or video.', 'ai-image-disclosure-labels' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Return Media Library AI classification for one image or video attachment.
	 *
	 * This is editor-only inheritance data. It does not create a per-block or
	 * per-post override, so later Media Library changes can continue to flow
	 * through to usages that still inherit from the attachment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_attachment_ai_status( $request ) {
		return rest_ensure_response( $this->attachment_ai_status_rest_data( absint( $request['id'] ) ) );
	}

	/**
	 * Build editor inheritance data for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private function attachment_ai_status_rest_data( $attachment_id ) {
		$raw_status  = $this->attachment_media_status_raw( $attachment_id );
		$status      = $this->attachment_media_status( $attachment_id );
		$source_type = $this->attachment_source_type( $attachment_id );
		$statuses    = $this->media_source_statuses();
		$types       = $this->digital_source_types();

		return array(
			'attachment_id'    => (int) $attachment_id,
			'raw_status'       => $raw_status,
			'status'           => $status,
			'status_label'     => isset( $statuses[ $status ]['label'] ) ? $statuses[ $status ]['label'] : '',
			'source_type'      => $source_type,
			'source_type_label'=> isset( $types[ $source_type ]['label'] ) ? $types[ $source_type ]['label'] : '',
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
				'gdaiidl_invalid_post',
				__( 'The post or page could not be found.', 'ai-image-disclosure-labels' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'gdaiidl_forbidden',
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
		$post_id          = absint( $request['id'] );
		$enabled          = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		$has_text         = $request->has_param( 'text' );
		$has_source_type  = $request->has_param( 'source_type' );
		$text             = $has_text ? $this->sanitize_label_text( $request->get_param( 'text' ) ) : '';
		$source_type      = $has_source_type
			? sanitize_key( (string) $request->get_param( 'source_type' ) )
			: '';
		$before           = $this->featured_label_rest_data( $post_id );

		if ( $has_source_type && '' !== $source_type ) {
			$types = $this->digital_source_types();

			if ( ! isset( $types[ $source_type ] ) ) {
				return new WP_Error(
					'gdaiidl_invalid_source_type',
					__( 'The selected AI source type is not supported.', 'ai-image-disclosure-labels' ),
					array( 'status' => 400 )
				);
			}
		}

		if ( $enabled ) {
			$result = update_post_meta( $post_id, self::META_FEATURED_ENABLED, '1' );

			if ( false === $result && '1' !== (string) $this->get_compatible_post_meta( $post_id, self::META_FEATURED_ENABLED, 'featured_enabled' ) ) {
				return new WP_Error(
					'gdaiidl_save_failed',
					__( 'The label could not be saved.', 'ai-image-disclosure-labels' ),
					array( 'status' => 500 )
				);
			}
		} else {
			$this->delete_compatible_post_meta( $post_id, self::META_FEATURED_ENABLED, 'featured_enabled' );
		}

		if ( $has_text ) {
			if ( '' !== $text ) {
				$text_result = update_post_meta( $post_id, self::META_FEATURED_TEXT, $text );

				if ( false === $text_result && $text !== (string) $this->get_compatible_post_meta( $post_id, self::META_FEATURED_TEXT, 'featured_text' ) ) {
					return new WP_Error(
						'gdaiidl_text_save_failed',
						__( 'The custom label text could not be saved.', 'ai-image-disclosure-labels' ),
						array( 'status' => 500 )
					);
				}
			} else {
				$this->delete_compatible_post_meta( $post_id, self::META_FEATURED_TEXT, 'featured_text' );
			}
		}

		if ( $has_source_type ) {
			if ( '' !== $source_type ) {
				$source_result = update_post_meta( $post_id, self::META_FEATURED_SOURCE_TYPE, $source_type );

				if ( false === $source_result && $source_type !== (string) get_post_meta( $post_id, self::META_FEATURED_SOURCE_TYPE, true ) ) {
					return new WP_Error(
						'gdaiidl_source_type_save_failed',
						__( 'The machine-readable source type could not be saved.', 'ai-image-disclosure-labels' ),
						array( 'status' => 500 )
					);
				}
			} else {
				delete_post_meta( $post_id, self::META_FEATURED_SOURCE_TYPE );
			}
		}

		$after = $this->featured_label_rest_data( $post_id );

		if ( $before !== $after ) {
			clean_post_cache( $post_id );
			$this->maybe_purge_page_cache();
		}

		return rest_ensure_response( $after );
	}

	/**
	 * Return the endpoint response data.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function featured_label_rest_data( $post_id ) {
		return array(
			'enabled'     => '1' === (string) $this->get_compatible_post_meta( $post_id, self::META_FEATURED_ENABLED, 'featured_enabled' ),
			'text'        => (string) $this->get_compatible_post_meta( $post_id, self::META_FEATURED_TEXT, 'featured_text' ),
			'source_type' => $this->sanitize_digital_source_type( get_post_meta( $post_id, self::META_FEATURED_SOURCE_TYPE, true ), '', true ),
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
			'title'      => 'gdaiidl-featured-label',
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
				'source_type' => array(
					'type'        => 'string',
					'description' => 'Optional per-post digital source type override.',
				),
			),
		);
	}

	/**
	 * Register custom disclosure attributes for core/image and core/video.
	 *
	 * @param array  $args       Block registration arguments.
	 * @param string $block_type Block type name.
	 * @return array
	 */
	public function register_image_block_attributes( $args, $block_type ) {
		if ( ! in_array( $block_type, array( 'core/image', 'core/video' ), true ) ) {
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

		$args['attributes']['gdAiSourceType'] = array(
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
			'gdaiidl-editor',
			GDAIIDL_URL . 'assets/editor.js',
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
			$this->asset_version( 'assets/editor.js' ),
			true
		);

		wp_localize_script(
			'gdaiidl-editor',
			'gdaiidlEditorConfig',
			array(
				'enableImages'           => ! empty( $settings['enable_images'] ),
				'enableVideos'           => ! empty( $settings['enable_videos'] ),
				'defaultText'            => $settings['label_text'], // Backward-compatible alias for generated.
				'defaultTexts'           => array(
					'generated' => $settings['label_text'],
					'modified'  => $settings['label_text_modified'],
				),
				'defaultVideoTexts'      => array(
					'generated' => ! empty( $settings['video_label_text'] ) ? $settings['video_label_text'] : $settings['label_text'],
					'modified'  => ! empty( $settings['video_label_text_modified'] ) ? $settings['video_label_text_modified'] : $settings['label_text_modified'],
				),
				'defaultSourceType'      => $settings['digital_source_type'],
				'position'               => $settings['position'],
				'videoAlignment'         => ! empty( $settings['video_separate_design'] ) ? $settings['video_alignment'] : ( false !== strpos( (string) $settings['position'], 'left' ) ? 'left' : 'right' ),
				'allowedPostTypes'        => $this->post_types(),
				'machineReadableEnabled' => ! empty( $settings['machine_readable_enabled'] ),
				'sourceTypeOptions'      => $this->source_type_select_options(),
				'metaEnabled'             => self::META_FEATURED_ENABLED,
				'metaText'                => self::META_FEATURED_TEXT,
				'restPath'                => '/gdaiidl/v1/post/',
				'attachmentStatusPath'    => '/gdaiidl/v1/attachment/',
			)
		);

		wp_set_script_translations( 'gdaiidl-editor', 'ai-image-disclosure-labels' );

		wp_enqueue_style(
			'gdaiidl-editor',
			GDAIIDL_URL . 'assets/editor.css',
			array(),
			$this->asset_version( 'assets/editor.css' )
		);

		wp_add_inline_style( 'gdaiidl-editor', $this->dynamic_badge_css( true ) );
	}

	/**
	 * Enqueue preview styles inside the block-editor content canvas.
	 *
	 * WordPress 7.1 always renders the post-editor canvas in an iframe. Assets
	 * added through enqueue_block_assets are also loaded inside that iframe on
	 * supported WordPress versions. Keep the existing editor stylesheet above
	 * for the editor UI and backward-compatible non-iframe rendering.
	 *
	 * @return void
	 */
	public function enqueue_editor_content_assets() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->post_type, $this->post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'gdaiidl-editor-content',
			GDAIIDL_URL . 'assets/editor-content.css',
			array(),
			$this->asset_version( 'assets/editor-content.css' )
		);

		wp_add_inline_style( 'gdaiidl-editor-content', $this->dynamic_badge_css( true ) );
	}

	/**
	 * Register front-end assets and enqueue them when the current page needs them.
	 *
	 * The performance option performs an early scan of the queried posts. A second
	 * safeguard in label_html() enqueues the assets when a disclosure is rendered
	 * later by a Query block, theme loop or other dynamic template.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$this->register_frontend_assets();

		$settings = $this->settings();

		if ( ! empty( $settings['load_assets_only_when_needed'] ) && ! $this->page_may_contain_disclosure() ) {
			return;
		}

		$this->activate_frontend_assets();
	}

	/**
	 * Register the front-end stylesheet and script.
	 *
	 * @return void
	 */
	private function register_frontend_assets() {
		if ( ! wp_style_is( 'gdaiidl-frontend', 'registered' ) ) {
			wp_register_style(
				'gdaiidl-frontend',
				GDAIIDL_URL . 'assets/frontend.css',
				array(),
				$this->asset_version( 'assets/frontend.css' )
			);
		}

		if ( ! wp_script_is( 'gdaiidl-frontend', 'registered' ) ) {
			wp_register_script(
				'gdaiidl-frontend',
				GDAIIDL_URL . 'assets/frontend.js',
				array(),
				$this->asset_version( 'assets/frontend.js' ),
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}
	}

	/**
	 * Determine whether the main queried posts already reveal a disclosure.
	 *
	 * @return bool
	 */
	private function page_may_contain_disclosure() {
		$settings = $this->settings();

		if ( empty( $settings['enable_images'] ) && empty( $settings['enable_videos'] ) ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return false;
		}

		global $wp_query;

		$posts = array();

		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			$posts = $wp_query->posts;
		}

		if ( is_singular( $this->post_types() ) ) {
			$queried_post = get_post( get_queried_object_id() );

			if ( $queried_post instanceof WP_Post ) {
				$posts[] = $queried_post;
			}
		}

		$auto_label = $this->media_auto_label_enabled();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( ! empty( $settings['enable_images'] ) && $this->get_compatible_post_meta( $post->ID, self::META_FEATURED_ENABLED, 'featured_enabled' ) ) {
				return true;
			}

			if (
				! empty( $settings['enable_images'] ) &&
				$auto_label &&
				in_array( $this->attachment_media_status( (int) get_post_thumbnail_id( $post->ID ) ), array( 'generated', 'modified' ), true )
			) {
				return true;
			}

			$content = (string) $post->post_content;
			$has_relevant_block = ( ! empty( $settings['enable_images'] ) && has_block( 'core/image', $content ) )
				|| ( ! empty( $settings['enable_videos'] ) && has_block( 'core/video', $content ) );

			if ( $has_relevant_block && $this->blocks_contain_disclosure( parse_blocks( $content ), $auto_label ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively inspect parsed blocks for a marked core/image or core/video block.
	 *
	 * @param array $blocks     Parsed blocks.
	 * @param bool  $auto_label Whether Media Library automatic labelling is active.
	 * @return bool
	 */
	private function blocks_contain_disclosure( $blocks, $auto_label = false ) {
		$settings = $this->settings();

		foreach ( (array) $blocks as $block ) {
			if (
				isset( $block['blockName'], $block['attrs'] ) &&
				in_array( $block['blockName'], array( 'core/image', 'core/video' ), true ) &&
				is_array( $block['attrs'] ) &&
				( ( 'core/image' === $block['blockName'] && ! empty( $settings['enable_images'] ) ) || ( 'core/video' === $block['blockName'] && ! empty( $settings['enable_videos'] ) ) )
			) {
				if ( ! empty( $block['attrs']['gdAiLabel'] ) ) {
					return true;
				}

				if (
					$auto_label &&
					! empty( $block['attrs']['id'] ) &&
					in_array( $this->attachment_media_status( (int) $block['attrs']['id'] ), array( 'generated', 'modified' ), true )
				) {
					return true;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->blocks_contain_disclosure( $block['innerBlocks'], $auto_label ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue the front-end assets and prepare the JavaScript configuration.
	 *
	 * @return void
	 */
	private function activate_frontend_assets() {
		$this->register_frontend_assets();

		if ( ! wp_style_is( 'gdaiidl-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'gdaiidl-frontend' );
			wp_add_inline_style( 'gdaiidl-frontend', $this->dynamic_badge_css( false ) );
		}

		if ( $this->frontend_assets_prepared ) {
			return;
		}

		$settings               = $this->settings();
		$minimum_width          = isset( $settings['minimum_image_width'] ) ? (int) $settings['minimum_image_width'] : 0;
		$minimum_text_width     = isset( $settings['minimum_text_width'] ) ? (int) $settings['minimum_text_width'] : 0;
		$needs_size_filter      = 0 < $minimum_width || 0 < $minimum_text_width;
		$location_rules_enabled = ! empty( $settings['location_rules_enabled'] );
		$enable_theme_fallback  = false;
		$featured_label_text    = '';
		$featured_attachment_id = 0;
		$featured_auto_color    = array();

		if ( ! empty( $settings['enable_images'] ) && is_singular( $this->post_types() ) ) {
			$post_id = get_queried_object_id();

			if ( $post_id && $this->get_compatible_post_meta( $post_id, self::META_FEATURED_ENABLED, 'featured_enabled' ) ) {
				$custom_text            = $this->get_compatible_post_meta( $post_id, self::META_FEATURED_TEXT, 'featured_text' );
				$custom_text            = is_string( $custom_text ) ? trim( sanitize_text_field( $custom_text ) ) : '';
				$featured_attachment_id = (int) get_post_thumbnail_id( $post_id );
				$featured_source_type   = get_post_meta( $post_id, self::META_FEATURED_SOURCE_TYPE, true );
				$featured_label_text    = '' !== $custom_text
					? $custom_text
					: $this->default_label_text_for_usage( $featured_source_type, $featured_attachment_id );
				$enable_theme_fallback  = '' !== $featured_label_text;

				if ( $enable_theme_fallback ) {
					$this->register_machine_readable_image(
						$featured_attachment_id,
						$featured_source_type
					);
				}
			}
		}

		$auto_color = isset( $settings['background_color_mode'] ) && 'auto' === $settings['background_color_mode'];

		if ( $auto_color && $featured_attachment_id > 0 ) {
			$featured_auto_color = $this->auto_color_data( $featured_attachment_id );
		}

		$touch_compact_mode = ! empty( $settings['touch_compact_mode'] );

		if ( ! $needs_size_filter && ! $enable_theme_fallback && ! $auto_color && ! $location_rules_enabled && ! $touch_compact_mode ) {
			$this->frontend_assets_prepared = true;
			return;
		}

		wp_enqueue_script( 'gdaiidl-frontend' );

		wp_localize_script(
			'gdaiidl-frontend',
			'gdaiidlFrontendConfig',
			array(
				'labelText'            => $featured_label_text,
				'preset'               => $settings['preset'],
				'minimumImageWidth'    => $minimum_width,
				'minimumTextWidth'     => $minimum_text_width,
				'smallImageMode'       => isset( $settings['small_image_mode'] ) ? $settings['small_image_mode'] : 'icon',
				'touchCompactMode'     => ! empty( $settings['touch_compact_mode'] ),
				'featuredFallback'     => $enable_theme_fallback,
				'iconHtml'             => $this->icon_markup( isset( $settings['icon_style'] ) ? $settings['icon_style'] : 'monogram' ),
				'featuredSelectors'    => $this->featured_selectors(),
				'locationRulesEnabled' => $location_rules_enabled,
				'iconOnlySelectors'    => $this->selector_lines( isset( $settings['icon_only_selectors'] ) ? $settings['icon_only_selectors'] : '' ),
				'hiddenSelectors'      => $this->selector_lines( isset( $settings['hidden_selectors'] ) ? $settings['hidden_selectors'] : '' ),
				'iconSizeValue'        => isset( $settings['icon_size_value'] ) ? (float) $settings['icon_size_value'] : 16,
				'iconSizeUnit'         => isset( $settings['icon_size_unit'] ) ? $settings['icon_size_unit'] : 'px',
				'tooltipEnabled'       => ! empty( $settings['icon_tooltip_enabled'] ),
				'autoColor'            => $auto_color,
				'backgroundOpacity'    => isset( $settings['background_opacity'] ) ? max( 0, min( 100, (int) $settings['background_opacity'] ) ) : 78,
				'featuredAutoColor'    => $featured_auto_color,
				'tooltipButtonLabel'   => '' !== $featured_label_text
					/* translators: %s: the disclosure label text, e.g. "AI-generated". */
					? sprintf( __( 'Show disclosure: %s', 'ai-image-disclosure-labels' ), $featured_label_text )
					: __( 'Show AI disclosure', 'ai-image-disclosure-labels' ),
			)
		);

		$this->frontend_assets_prepared = true;
	}

	/**
	 * Print a stylesheet that was first requested after wp_head had completed.
	 *
	 * @return void
	 */
	public function print_late_frontend_styles() {
		if ( wp_style_is( 'gdaiidl-frontend', 'enqueued' ) && ! wp_style_is( 'gdaiidl-frontend', 'done' ) ) {
			wp_print_styles( 'gdaiidl-frontend' );
		}
	}

	/**
	 * Add the badge to explicitly marked core/image blocks.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function render_image_block_label( $block_content, $block ) {
		if ( empty( $this->settings()['enable_images'] ) ) {
			return $block_content;
		}

		$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( false !== strpos( $block_content, 'gd-ai-image-label' ) ) {
			return $block_content;
		}

		$explicit      = ! empty( $attributes['gdAiLabel'] );
		$attachment_id = isset( $attributes['id'] ) ? (int) $attributes['id'] : 0;
		$custom_text   = isset( $attributes['gdAiLabelText'] ) ? $attributes['gdAiLabelText'] : '';
		$source_type   = isset( $attributes['gdAiSourceType'] ) ? $attributes['gdAiSourceType'] : '';

		/*
		 * When the block is not explicitly marked, the optional Media Library
		 * automatic labelling can still add a disclosure if the attachment is
		 * marked in the Library. The badge text then follows the marked type.
		 */
		if ( ! $explicit ) {
			$media_status      = $this->attachment_media_status( $attachment_id );
			$media_source_type = $this->attachment_source_type( $attachment_id );

			if ( ! in_array( $media_status, array( 'generated', 'modified' ), true ) ) {
				return $block_content;
			}

			/* Precise legacy provenance is emitted when it is known. */
			if ( '' !== $media_source_type ) {
				$this->register_machine_readable_image(
					$attachment_id,
					$media_source_type,
					$this->extract_image_url( $block_content )
				);
			}

			if ( ! $this->media_auto_label_enabled() ) {
				return $block_content;
			}

			$custom_text = $this->label_text_for_media_status( $media_status );
			$source_type = '';
		} elseif ( '' === trim( sanitize_text_field( (string) $custom_text ) ) ) {
			$custom_text = $this->default_label_text_for_usage( $source_type, $attachment_id );
		}

		$label_html = $this->label_html( $custom_text, $attachment_id );

		if ( '' === $label_html ) {
			return $block_content;
		}

		$this->register_machine_readable_image(
			$attachment_id,
			$source_type,
			$this->extract_image_url( $block_content )
		);

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
	 * Add a disclosure directly below a marked core/video block.
	 *
	 * Unlike image labels, video disclosures deliberately stay outside the video
	 * surface so they never obscure native playback controls or moving content.
	 * Media Library inheritance follows the same opt-in rules used for images.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block.
	 * @return string
	 */
	public function render_video_block_label( $block_content, $block ) {
		if ( empty( $this->settings()['enable_videos'] ) ) {
			return $block_content;
		}

		$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( false !== strpos( $block_content, 'gd-ai-video-disclosure-row' ) ) {
			return $block_content;
		}

		$explicit      = ! empty( $attributes['gdAiLabel'] );
		$attachment_id = isset( $attributes['id'] ) ? (int) $attributes['id'] : 0;
		$custom_text   = isset( $attributes['gdAiLabelText'] ) ? $attributes['gdAiLabelText'] : '';
		$source_type   = isset( $attributes['gdAiSourceType'] ) ? $attributes['gdAiSourceType'] : '';
		$video_url     = $this->extract_video_url( $block_content );

		if ( ! $explicit ) {
			$media_status      = $this->attachment_media_status( $attachment_id );
			$media_source_type = $this->attachment_source_type( $attachment_id );

			if ( ! in_array( $media_status, array( 'generated', 'modified' ), true ) ) {
				return $block_content;
			}

			if ( '' !== $media_source_type ) {
				$this->register_machine_readable_video( $attachment_id, $media_source_type, $video_url );
			}

			if ( ! $this->media_auto_label_enabled() ) {
				return $block_content;
			}

			$custom_text = $this->label_text_for_media_status( $media_status, 'video' );
			$source_type = '';
		} elseif ( '' === trim( sanitize_text_field( (string) $custom_text ) ) ) {
			$custom_text = $this->default_label_text_for_usage( $source_type, $attachment_id, 'video' );
		}

		/* Video files are not sampled for automatic image-based badge colors. */
		$label_html = $this->label_html( $custom_text, 0, 'video' );

		if ( '' === $label_html ) {
			return $block_content;
		}

		$this->register_machine_readable_video( $attachment_id, $source_type, $video_url );

		$settings  = $this->settings();
		$alignment = ! empty( $settings['video_separate_design'] ) && isset( $settings['video_alignment'] )
			? (string) $settings['video_alignment']
			: ( false !== strpos( (string) $settings['position'], 'left' ) ? 'left' : 'right' );
		if ( ! in_array( $alignment, array( 'left', 'center', 'right' ), true ) ) {
			$alignment = 'right';
		}
		$row_html  = sprintf(
			'<div class="gd-ai-video-disclosure-row gd-ai-video-align-%1$s">%2$s</div>',
			esc_attr( $alignment ),
			$label_html
		);

		/* Core/video puts the caption after </video>; insert before that caption. */
		if ( preg_match( '/<\/video>/i', $block_content ) ) {
			return preg_replace( '/<\/video>/i', '</video>' . $row_html, $block_content, 1 );
		}

		if ( preg_match( '/<\/figure>\s*$/i', $block_content ) ) {
			return preg_replace( '/<\/figure>\s*$/i', $row_html . '</figure>', $block_content, 1 );
		}

		return $block_content . $row_html;
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
		if ( empty( $this->settings()['enable_images'] ) ) {
			return $html;
		}

		unset( $size, $icon, $attr );

		if ( '' === $html || is_admin() || wp_doing_ajax() || is_feed() ) {
			return $html;
		}

		$library_source_type = $this->attachment_source_type( (int) $attachment_id );

		if ( '' !== $library_source_type ) {
			$this->register_machine_readable_image(
				(int) $attachment_id,
				$library_source_type,
				$this->extract_image_url( $html )
			);
		}

		if (
			false !== strpos( $html, 'gd-ai-featured-wrap' ) ||
			false === strpos( $html, 'wp-post-image' )
		) {
			return $html;
		}

		/*
		 * On singular views the queried object is authoritative. In post loops,
		 * query blocks and archive cards, get_the_ID() identifies the post whose
		 * image is currently being rendered. This lets themes that call
		 * wp_get_attachment_image() directly receive the same disclosure as
		 * themes that use get_the_post_thumbnail().
		 */
		$post_id = is_singular( $this->post_types() ) ? get_queried_object_id() : get_the_ID();

		if (
			! $post_id ||
			! in_array( get_post_type( $post_id ), $this->post_types(), true ) ||
			(int) $attachment_id !== (int) get_post_thumbnail_id( $post_id )
		) {
			return $html;
		}

		$plan = $this->featured_label_plan( $post_id, (int) $attachment_id );

		if ( false === $plan ) {
			return $html;
		}

		$label_html = $this->label_html( $plan['text'], (int) $attachment_id );

		if ( '' === $label_html ) {
			return $html;
		}

		$this->register_machine_readable_image(
			(int) $attachment_id,
			$plan['source_type'],
			$this->extract_image_url( $html )
		);

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
		if ( empty( $this->settings()['enable_images'] ) ) {
			return $html;
		}

		unset( $size, $attr );

		if (
			'' === $html ||
			is_admin() ||
			wp_doing_ajax() ||
			is_feed() ||
			false !== strpos( $html, 'gd-ai-featured-wrap' )
		) {
			return $html;
		}

		$library_source_type = $this->attachment_source_type( (int) $post_thumbnail_id );

		if ( '' !== $library_source_type ) {
			$this->register_machine_readable_image(
				(int) $post_thumbnail_id,
				$library_source_type,
				$this->extract_image_url( $html )
			);
		}

		$plan = $this->featured_label_plan( (int) $post_id, (int) $post_thumbnail_id );

		if ( false === $plan ) {
			return $html;
		}

		$label_html = $this->label_html( $plan['text'], (int) $post_thumbnail_id );

		if ( '' === $label_html ) {
			return $html;
		}

		$this->register_machine_readable_image(
			(int) $post_thumbnail_id,
			$plan['source_type'],
			$this->extract_image_url( $html )
		);

		return '<span class="gd-ai-image-frame gd-ai-featured-wrap">' . $html . $label_html . '</span>';
	}

	/**
	 * Decide whether and how a featured image should be labelled.
	 *
	 * Explicit per-post marking takes precedence and keeps its custom text and
	 * source-type override. When the post is not explicitly marked, optional
	 * Media Library automatic labelling applies if the featured attachment is
	 * marked in the Library.
	 *
	 * @param int $post_id       Post ID.
	 * @param int $attachment_id Featured attachment ID.
	 * @return array|false Array with text and source_type keys, or false.
	 */
	private function featured_label_plan( $post_id, $attachment_id ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return false;
		}

		if ( $this->get_compatible_post_meta( $post_id, self::META_FEATURED_ENABLED, 'featured_enabled' ) ) {
			$custom_text = $this->get_compatible_post_meta( $post_id, self::META_FEATURED_TEXT, 'featured_text' );
			$custom_text = is_string( $custom_text ) ? trim( sanitize_text_field( $custom_text ) ) : '';
			$source_type = get_post_meta( $post_id, self::META_FEATURED_SOURCE_TYPE, true );

			return array(
				'text'        => '' !== $custom_text ? $custom_text : $this->default_label_text_for_usage( $source_type, $attachment_id ),
				'source_type' => $source_type,
			);
		}

		if ( ! $this->media_auto_label_enabled() ) {
			return false;
		}

		$media_status = $this->attachment_media_status( $attachment_id );

		if ( ! in_array( $media_status, array( 'generated', 'modified' ), true ) ) {
			return false;
		}

		return array(
			'text'        => $this->label_text_for_media_status( $media_status ),
			'source_type' => '',
		);
	}

	/**
	 * Extract the first image URL from rendered markup.
	 *
	 * @param string $html Rendered image markup.
	 * @return string
	 */
	private function extract_image_url( $html ) {
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( (string) $html );

			if ( $processor->next_tag( 'img' ) ) {
				return esc_url_raw( (string) $processor->get_attribute( 'src' ) );
			}
		}

		if ( preg_match( '/<img\b[^>]*\bsrc=(?:"([^"]+)"|\'([^\']+)\')/i', (string) $html, $matches ) ) {
			return esc_url_raw( html_entity_decode( ! empty( $matches[1] ) ? $matches[1] : $matches[2], ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}


	/**
	 * Extract the primary video URL from rendered core/video markup.
	 *
	 * @param string $html Rendered video markup.
	 * @return string
	 */
	private function extract_video_url( $html ) {
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( (string) $html );

			if ( $processor->next_tag( 'video' ) ) {
				$src = esc_url_raw( (string) $processor->get_attribute( 'src' ) );
				if ( '' !== $src ) {
					return $src;
				}
			}

			$processor = new WP_HTML_Tag_Processor( (string) $html );
			if ( $processor->next_tag( 'source' ) ) {
				return esc_url_raw( (string) $processor->get_attribute( 'src' ) );
			}
		}

		if ( preg_match( '/<(?:video|source)\b[^>]*\bsrc=(?:"([^"]+)"|\'([^\']+)\')/i', (string) $html, $matches ) ) {
			return esc_url_raw( html_entity_decode( ! empty( $matches[1] ) ? $matches[1] : $matches[2], ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Return a stable identity URL for an external image.
	 *
	 * Cloudflare Images appends a delivery-variant segment after the immutable
	 * image ID. Different crops and widths are still renditions of the same
	 * underlying asset, so the final variant segment is ignored for the
	 * deduplication key. Other URL structures are left untouched.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private function machine_readable_identity_url( $url ) {
		$url   = esc_url_raw( (string) $url );
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		if ( ! preg_match( '~^(/cdn-cgi/imagedelivery/[^/]+/[^/]+)(?:/[^/?#]+)?$~', $parts['path'], $matches ) ) {
			return $url;
		}

		$scheme = ! empty( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';

		return $scheme . '://' . $parts['host'] . $port . $matches[1];
	}

	/**
	 * Register one image for the page-level machine-readable JSON-LD graph.
	 *
	 * WordPress attachment IDs are used as the primary identity. This keeps a
	 * full-size image, responsive thumbnail, crop or CDN rendition together as
	 * one ImageObject without changing any visible image markup. External
	 * images fall back to a conservative normalized-URL identity.
	 *
	 * @param int    $attachment_id WordPress attachment ID when available.
	 * @param mixed  $source_type   Optional per-image source type override.
	 * @param string $fallback_url  Image URL for external or unregistered images.
	 * @return void
	 */
	private function register_machine_readable_image( $attachment_id, $source_type = '', $fallback_url = '' ) {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$attachment_id = (int) $attachment_id;

		/*
		 * Resolution order: an explicit per-usage source type wins; otherwise
		 * the attachment's Media Library mark is used; otherwise the global
		 * default from resolved_digital_source_type().
		 */
		$source_type = $this->sanitize_digital_source_type( $source_type, '', true );

		if ( '' === $source_type && $attachment_id > 0 ) {
			$source_type = $this->attachment_source_type( $attachment_id );

			/*
			 * A generic AI-modified Library status deliberately does not guess
			 * whether the standardized source type was generative editing or
			 * algorithmic enhancement. An explicit no-AI declaration likewise
			 * suppresses an inherited global AI source type.
			 */
			if ( '' === $source_type && in_array( $this->attachment_media_status( $attachment_id ), array( 'modified', 'no-ai' ), true ) ) {
				return;
			}
		}

		$source_type = $this->resolved_digital_source_type( $source_type );
		$types       = $this->digital_source_types();

		if ( '' === $source_type || ! isset( $types[ $source_type ] ) ) {
			return;
		}

		$attachment_url = $attachment_id > 0 ? wp_get_attachment_url( $attachment_id ) : '';
		$url            = '' !== (string) $attachment_url ? $attachment_url : $fallback_url;
		$url            = esc_url_raw( (string) $url );

		if ( '' === $url ) {
			return;
		}

		if ( $attachment_id > 0 ) {
			$key         = 'attachment:' . $attachment_id;
			$identity_url = $url;
		} else {
			$identity_url = $this->machine_readable_identity_url( $url );
			$key          = 'url:' . md5( $identity_url );
		}

		$this->machine_readable_media[ $key ] = array(
			'@type'             => 'ImageObject',
			'@id'               => $identity_url . '#gdaiidl-source',
			'contentUrl'        => $url,
			'digitalSourceType' => $types[ $source_type ]['uri'],
		);
	}


	/**
	 * Register one video for the page-level machine-readable JSON-LD graph.
	 *
	 * @param int    $attachment_id WordPress attachment ID when available.
	 * @param mixed  $source_type   Optional per-video source type override.
	 * @param string $fallback_url  Video URL for external or unregistered videos.
	 * @return void
	 */
	private function register_machine_readable_video( $attachment_id, $source_type = '', $fallback_url = '' ) {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}

		$attachment_id = (int) $attachment_id;
		$source_type   = $this->sanitize_digital_source_type( $source_type, '', true );

		if ( '' === $source_type && $attachment_id > 0 ) {
			$source_type = $this->attachment_source_type( $attachment_id );

			if ( '' === $source_type && in_array( $this->attachment_media_status( $attachment_id ), array( 'modified', 'no-ai' ), true ) ) {
				return;
			}
		}

		$source_type = $this->resolved_digital_source_type( $source_type );
		$types       = $this->digital_source_types();

		if ( '' === $source_type || ! isset( $types[ $source_type ] ) ) {
			return;
		}

		$attachment_url = $attachment_id > 0 ? wp_get_attachment_url( $attachment_id ) : '';
		$url            = '' !== (string) $attachment_url ? $attachment_url : $fallback_url;
		$url            = esc_url_raw( (string) $url );

		if ( '' === $url ) {
			return;
		}

		$key = $attachment_id > 0 ? 'video-attachment:' . $attachment_id : 'video-url:' . md5( $url );

		$this->machine_readable_media[ $key ] = array(
			'@type'             => 'VideoObject',
			'@id'               => $url . '#gdaiidl-source',
			'contentUrl'        => $url,
			'digitalSourceType' => $types[ $source_type ]['uri'],
		);
	}

	/**
	 * Print one deduplicated Schema.org JSON-LD graph for marked images/videos.
	 *
	 * @return void
	 */
	public function render_machine_readable_source_data() {
		if ( empty( $this->machine_readable_media ) ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $this->machine_readable_media ),
		);

		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		if ( false === $json ) {
			return;
		}

		wp_print_inline_script_tag(
			$json,
			array(
				'type'  => 'application/ld+json',
				'class' => 'gdaiidl-machine-readable-source',
			)
		);

		$this->machine_readable_media = array();
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

		$cached = $this->get_compatible_post_meta( $attachment_id, self::META_AVERAGE_COLOR, 'average_color' );

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

		if ( ! $file || ! is_readable( $file ) ) {
			update_post_meta( $attachment_id, self::META_AVERAGE_COLOR, 'none' );

			return null;
		}

		$file_size = @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- local file can disappear between checks; handled below.
		if ( false === $file_size || $file_size <= 0 || $file_size > 5 * MB_IN_BYTES ) {
			update_post_meta( $attachment_id, self::META_AVERAGE_COLOR, 'none' );

			return null;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bounded local attachment file.
		if ( false === $contents || '' === $contents || strlen( $contents ) > 5 * MB_IN_BYTES ) {
			update_post_meta( $attachment_id, self::META_AVERAGE_COLOR, 'none' );

			return null;
		}

		$image = @imagecreatefromstring( $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- GD emits warnings for unsupported formats; handled via the false return.

		if ( ! $image ) {
			update_post_meta( $attachment_id, self::META_AVERAGE_COLOR, 'none' );

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

		update_post_meta( $attachment_id, self::META_AVERAGE_COLOR, $color['r'] . ',' . $color['g'] . ',' . $color['b'] );

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
	 * Return automatic badge colors for an attachment.
	 *
	 * The server-side sample is also passed to the JavaScript-created featured
	 * image fallback. This avoids relying on canvas access to a cross-origin CDN
	 * image, which browsers may block even though the image itself is visible.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Empty array when automatic colors are unavailable.
	 */
	private function auto_color_data( $attachment_id ) {
		$settings = $this->settings();

		if ( empty( $settings['background_color_mode'] ) || 'auto' !== $settings['background_color_mode'] ) {
			return array();
		}

		$color = $this->attachment_average_color( $attachment_id );

		if ( null === $color ) {
			return array();
		}

		$alpha = round( max( 0, min( 100, (int) $settings['background_opacity'] ) ) / 100, 2 );
		$rgb   = $color['r'] . ',' . $color['g'] . ',' . $color['b'];
		$rgba  = 'rgba(' . $rgb . ',' . $alpha . ')';

		return array(
			'background' => $rgba,
			'border'     => $rgba,
			'text'       => $this->readable_text_color( $color ),
		);
	}

	/**
	 * Build inline CSS variables for automatic badge colors.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Empty string when automatic colors are unavailable.
	 */
	private function auto_color_style( $attachment_id ) {
		$colors = $this->auto_color_data( $attachment_id );

		if ( empty( $colors ) ) {
			return '';
		}

		return sprintf(
			'--gd-ai-label-bg:%1$s;--gd-ai-label-color:%2$s;--gd-ai-label-border-color:%3$s;',
			$colors['background'],
			$colors['text'],
			$colors['border']
		);
	}

	/**
	 * Build badge HTML.
	 *
	 * @param string $custom_text  Optional per-image text.
	 * @param int    $attachment_id Attachment ID used for automatic color sampling.
	 * @return string
	 */
	private function label_html( $custom_text = '', $attachment_id = 0, $media = 'image' ) {
		$text     = $this->sanitize_label_text( $custom_text );
		$settings = $this->settings();

		if ( '' === $text ) {
			return '';
		}

		if ( ! is_admin() && ! wp_doing_ajax() && ! is_feed() ) {
			$this->activate_frontend_assets();
		}

		$preset_class = in_array( $settings['preset'], array( 'subtle', 'light', 'pill' ), true )
			? ' gd-ai-preset-' . $settings['preset']
			: ' gd-ai-preset-custom';

		$media_class      = 'video' === $media ? ' gd-ai-media-video' : ' gd-ai-media-image';
		$tooltip_enabled = ! empty( $settings['icon_tooltip_enabled'] );
		$tooltip_class   = $tooltip_enabled ? ' gd-ai-tooltip-enabled' : '';
		$touch_class     = ! empty( $settings['touch_compact_mode'] ) ? ' gd-ai-touch-compact' : '';
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
			'<span class="gd-ai-image-label%1$s%2$s%3$s%9$s" role="note" aria-label="%4$s"%8$s>%5$s<span class="gd-ai-label-text">%6$s</span>%7$s</span>',
			esc_attr( $preset_class ),
			esc_attr( $tooltip_class ),
			esc_attr( $touch_class ),
			esc_attr( $text ),
			$icon_markup,
			esc_html( $text ),
			$tooltip_markup,
			$style_attr,
			esc_attr( $media_class )
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
		$this->delete_compatible_post_meta( (int) $attachment_id, self::META_AVERAGE_COLOR, 'average_color' );

		return $metadata;
	}

	/**
	 * Register plugin settings.
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

		$output['enable_images'] = ! empty( $input['enable_images'] );
		$output['enable_videos'] = ! empty( $input['enable_videos'] );

		$output['label_text'] = isset( $input['label_text'] )
			? $this->sanitize_label_text( $input['label_text'] )
			: $defaults['label_text'];

		$output['label_text_modified'] = isset( $input['label_text_modified'] )
			? $this->sanitize_label_text( $input['label_text_modified'] )
			: $defaults['label_text_modified'];

		$output['machine_readable_enabled'] = ! empty( $input['machine_readable_enabled'] );
		$output['media_library_auto_label'] = ! empty( $input['media_library_auto_label'] );
		$output['load_assets_only_when_needed'] = ! empty( $input['load_assets_only_when_needed'] );
		$output['digital_source_type'] = $this->sanitize_digital_source_type(
			isset( $input['digital_source_type'] ) ? $input['digital_source_type'] : $defaults['digital_source_type']
		);

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

		$output['touch_compact_mode'] = ! empty( $input['touch_compact_mode'] );

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


		$output['location_rules_enabled'] = ! empty( $input['location_rules_enabled'] );

		foreach ( array( 'icon_only_selectors', 'hidden_selectors' ) as $selector_key ) {
			$output[ $selector_key ] = '';

			if ( isset( $input[ $selector_key ] ) && is_string( $input[ $selector_key ] ) ) {
				$lines = array_map( 'sanitize_text_field', explode( "\n", $input[ $selector_key ] ) );
				$lines = array_values( array_unique( array_filter( array_map( 'trim', $lines ) ) ) );
				$lines = array_slice( $lines, 0, 30 );
				$lines = array_filter(
					$lines,
					static function ( $line ) {
						return strlen( $line ) <= 240 && false === strpos( $line, '<' ) && false === strpos( $line, '{' );
					}
				);

				$output[ $selector_key ] = implode( "\n", $lines );
			}
		}


		$output['video_separate_design'] = ! empty( $input['video_separate_design'] );
		$output['video_label_text'] = isset( $input['video_label_text'] ) ? $this->sanitize_label_text( $input['video_label_text'] ) : '';
		$output['video_label_text_modified'] = isset( $input['video_label_text_modified'] ) ? $this->sanitize_label_text( $input['video_label_text_modified'] ) : '';
		$video_alignments = array( 'left', 'center', 'right' );
		$output['video_alignment'] = isset( $input['video_alignment'] ) && in_array( $input['video_alignment'], $video_alignments, true ) ? $input['video_alignment'] : $defaults['video_alignment'];
		foreach ( array( 'video_background_color', 'video_text_color', 'video_border_color' ) as $video_color_key ) {
			$video_color = isset( $input[ $video_color_key ] ) ? sanitize_hex_color( $input[ $video_color_key ] ) : '';
			$output[ $video_color_key ] = $video_color ? $video_color : $defaults[ $video_color_key ];
		}
		$output['video_background_opacity'] = $this->clamp_int( $input, 'video_background_opacity', 0, 100, $defaults['video_background_opacity'] );
		$output['video_border_width'] = $this->clamp_int( $input, 'video_border_width', 0, 5, $defaults['video_border_width'] );
		$output['video_border_radius'] = $this->clamp_int( $input, 'video_border_radius', 0, 999, $defaults['video_border_radius'] );
		$output['video_font_size'] = $this->clamp_int( $input, 'video_font_size', 8, 24, $defaults['video_font_size'] );
		$video_font_weight = isset( $input['video_font_weight'] ) ? (int) $input['video_font_weight'] : $defaults['video_font_weight'];
		$output['video_font_weight'] = in_array( $video_font_weight, array( 400, 500, 600, 700 ), true ) ? $video_font_weight : $defaults['video_font_weight'];
		$output['video_padding_vertical'] = $this->clamp_int( $input, 'video_padding_vertical', 0, 20, $defaults['video_padding_vertical'] );
		$output['video_padding_horizontal'] = $this->clamp_int( $input, 'video_padding_horizontal', 0, 30, $defaults['video_padding_horizontal'] );
		$output['video_text_transform'] = isset( $input['video_text_transform'] ) && in_array( $input['video_text_transform'], array( 'none', 'uppercase' ), true ) ? $input['video_text_transform'] : 'none';

		if ( $output['minimum_text_width'] > 0 && $output['minimum_text_width'] < $output['minimum_image_width'] ) {
			$output['minimum_text_width'] = $output['minimum_image_width'];
		}

		return $output;
	}

	/**
	 * Sanitize one visible disclosure text and enforce the same 80-character
	 * limit used by the settings and editor controls.
	 *
	 * @param mixed $value Candidate text.
	 * @return string
	 */
	private function sanitize_label_text( $value ) {
		$text = trim( sanitize_text_field( (string) $value ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, 80, 'UTF-8' );
		}

		if ( preg_match_all( '/./us', $text, $characters ) ) {
			return implode( '', array_slice( $characters[0], 0, 80 ) );
		}

		return substr( $text, 0, 80 );
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
			__( 'AI Image & Video Disclosure Labels', 'ai-image-disclosure-labels' ),
			__( 'AI Image & Video Labels', 'ai-image-disclosure-labels' ),
			'manage_options',
			'gdaiidl-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue the small, scoped Media Library attachment UI assets.
	 *
	 * This method is also hooked to wp_enqueue_media so attachment-detail
	 * modals receive the same styling regardless of which admin screen opened
	 * the WordPress media frame.
	 *
	 * @return void
	 */
	public function enqueue_media_admin_assets() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'gdaiidl-media-admin',
			GDAIIDL_URL . 'assets/media-admin.css',
			array(),
			$this->asset_version( 'assets/media-admin.css' )
		);

		wp_enqueue_script(
			'gdaiidl-media-admin',
			GDAIIDL_URL . 'assets/media-admin.js',
			array(),
			$this->asset_version( 'assets/media-admin.js' ),
			true
		);
	}

	/**
	 * Build an asset version that also changes when a file changes inside a
	 * same-version test build. Official releases still retain the plugin version
	 * prefix, while the file timestamp prevents stale browser/admin caches.
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
	 * Enqueue settings-page assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		/*
		 * The list view does not necessarily initialize the media modal, so load
		 * the small scoped assets explicitly there. Other screens are covered by
		 * the wp_enqueue_media action below whenever WordPress opens a media UI.
		 */
		if ( 'upload.php' === $hook_suffix || 'media-new.php' === $hook_suffix ) {
			$this->enqueue_media_admin_assets();
		}

		if ( 'settings_page_gdaiidl-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();

		$admin_css_version = $this->asset_version( 'assets/admin.css' );
		$admin_js_version  = $this->asset_version( 'assets/admin.js' );

		wp_enqueue_style(
			'gdaiidl-admin',
			GDAIIDL_URL . 'assets/admin.css',
			array(),
			$admin_css_version
		);

		wp_enqueue_script(
			'gdaiidl-admin',
			GDAIIDL_URL . 'assets/admin.js',
			array( 'media-editor', 'wp-i18n' ),
			$admin_js_version,
			true
		);

		wp_set_script_translations( 'gdaiidl-admin', 'ai-image-disclosure-labels' );

		$settings    = $this->settings();
		$custom_icon = $this->custom_icon_data();

		wp_localize_script(
			'gdaiidl-admin',
			'gdaiidlAdminConfig',
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
			<h1><?php esc_html_e( 'AI Image & Video Disclosure Labels', 'ai-image-disclosure-labels' ); ?> <span class="gd-ai-badge-chip"><?php esc_html_e( 'Supports AI disclosure', 'ai-image-disclosure-labels' ); ?></span></h1>
			<p class="gd-ai-lead">
				<?php esc_html_e( 'Add clear AI disclosures to images, videos, or both. Choose the media types you use first; existing content and stored classifications remain unchanged when a media type is disabled.', 'ai-image-disclosure-labels' ); ?>
			</p>

			<div class="gd-ai-notice gd-ai-notice-eu">
				<strong><?php esc_html_e( 'EU AI Act transparency rules apply from August 2, 2026', 'ai-image-disclosure-labels' ); ?></strong>
				<p><?php esc_html_e( 'Article 50 of the EU AI Act (Regulation (EU) 2024/1689) requires providers of certain generative AI systems to add machine-readable marks to generated or manipulated outputs. Separate disclosure duties apply to deployers in specified cases, including deepfakes and certain public-interest text. This plugin can add a visible label and, if enabled below, a publisher-supplied structured-data declaration. It does not determine your legal obligations, verify the origin of media or create C2PA Content Credentials, and it is not legal advice.', 'ai-image-disclosure-labels' ); ?></p>
			</div>

			<?php $gdaiidl_detected_caches = $this->detected_cache_systems(); ?>
			<?php if ( ! empty( $gdaiidl_detected_caches ) ) : ?>
				<p class="gd-ai-cache-status">
					<?php
					printf(
						/* translators: %s: comma-separated list of detected caching plugins, e.g. "WP Rocket, Cloudflare". */
						esc_html__( 'Caching detected: %s. The page cache is cleared automatically whenever you save these settings or change a label, so your changes are visible immediately.', 'ai-image-disclosure-labels' ),
						esc_html( implode( ', ', $gdaiidl_detected_caches ) )
					);
					?>
				</p>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'gdaiidl_settings_group' ); ?>

				<div class="gd-ai-settings-grid">
					<main class="gd-ai-settings-main">
						<div class="gd-ai-section-heading" role="separator">
							<h2><?php esc_html_e( 'Essential settings', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Start here. These settings decide which media types the plugin manages and what visitors see.', 'ai-image-disclosure-labels' ); ?></p>
						</div>


						<section class="gd-ai-card gd-ai-essential-card">
							<h2><?php esc_html_e( 'Use disclosures for', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Enable one or both media types. Turning a type off hides its editor and Media Library disclosure controls and prevents public labels for that type, but it does not delete existing classifications.', 'ai-image-disclosure-labels' ); ?></p>
							<div class="gd-ai-media-type-grid">
								<label class="gd-ai-toggle-field"><input type="checkbox" id="gd-ai-enable-images" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_images]" value="1" <?php checked( ! empty( $s['enable_images'] ) ); ?>><span><strong><?php esc_html_e( 'Images', 'ai-image-disclosure-labels' ); ?></strong><small><?php esc_html_e( 'Image blocks, featured images and image attachments.', 'ai-image-disclosure-labels' ); ?></small></span></label>
								<label class="gd-ai-toggle-field"><input type="checkbox" id="gd-ai-enable-videos" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_videos]" value="1" <?php checked( ! empty( $s['enable_videos'] ) ); ?>><span><strong><?php esc_html_e( 'Videos', 'ai-image-disclosure-labels' ); ?></strong><small><?php esc_html_e( 'WordPress Video blocks and video attachments. The badge is shown below the video, not over playback controls.', 'ai-image-disclosure-labels' ); ?></small></span></label>
							</div>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Visible label texts', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Set the default wording used across enabled media. Individual Image and Video blocks can still override the text for a specific use.', 'ai-image-disclosure-labels' ); ?></p>
							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'AI-generated text', 'ai-image-disclosure-labels' ); ?></span>
								<input
									type="text"
									id="gd-ai-label-text"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[label_text]"
									value="<?php echo esc_attr( $s['label_text'] ); ?>"
									maxlength="80"
								>
								<small><?php esc_html_e( 'Used for enabled media classified as AI-generated and for generated per-use source types unless a custom text is set.', 'ai-image-disclosure-labels' ); ?></small>
							</label>

							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'AI-modified text', 'ai-image-disclosure-labels' ); ?></span>
								<input
									type="text"
									id="gd-ai-label-text-modified"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[label_text_modified]"
									value="<?php echo esc_attr( $s['label_text_modified'] ); ?>"
									maxlength="80"
								>
								<small><?php esc_html_e( 'Used for the public AI-modified status and, by default, for more precise AI-edited or AI-enhanced provenance. The technical structured data can remain more specific.', 'ai-image-disclosure-labels' ); ?></small>
							</label>


							<div class="gd-ai-video-text-options">
								<h3><?php esc_html_e( 'Optional video wording', 'ai-image-disclosure-labels' ); ?></h3>
								<p class="description"><?php esc_html_e( 'Leave these empty to reuse the image/general wording above. Set them only if you want labels such as “AI-generated video”.', 'ai-image-disclosure-labels' ); ?></p>
								<div class="gd-ai-field-grid">
									<label class="gd-ai-field"><span><?php esc_html_e( 'AI-generated video text', 'ai-image-disclosure-labels' ); ?></span><input type="text" id="gd-ai-video-label-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_label_text]" value="<?php echo esc_attr( $s['video_label_text'] ); ?>" maxlength="80" placeholder="<?php echo esc_attr( $s['label_text'] ); ?>"></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'AI-modified video text', 'ai-image-disclosure-labels' ); ?></span><input type="text" id="gd-ai-video-label-text-modified" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_label_text_modified]" value="<?php echo esc_attr( $s['video_label_text_modified'] ); ?>" maxlength="80" placeholder="<?php echo esc_attr( $s['label_text_modified'] ); ?>"></label>
								</div>
							</div>

							<p class="description"><strong><?php esc_html_e( 'No label for non-AI or unknown status:', 'ai-image-disclosure-labels' ); ?></strong> <?php esc_html_e( 'Media marked “No AI used” or left “Not classified” never receives an automatic visible text label. Leaving either AI text field empty also suppresses the automatic visible text for that AI category.', 'ai-image-disclosure-labels' ); ?></p>
						</section>


						<div class="gd-ai-section-heading" role="separator">
							<h2><?php esc_html_e( 'Design and previews', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'The image badge is the default design. Videos can inherit it or use their own simpler below-video style.', 'ai-image-disclosure-labels' ); ?></p>
						</div>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Three layout presets', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Selecting a preset applies matching values. You can fine-tune every detail afterwards.', 'ai-image-disclosure-labels' ); ?></p>

							<div class="gd-ai-presets">
								<label class="gd-ai-preset-card">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="subtle" <?php checked( $s['preset'], 'subtle' ); ?>>
									<span class="gd-ai-preset-visual gd-ai-preset-subtle"><?php echo esc_html( $s['label_text'] ); ?></span>
									<strong><?php esc_html_e( 'Subtle badge (recommended)', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Compact, dark, slightly transparent, with a fine border.', 'ai-image-disclosure-labels' ); ?></small>
								</label>

								<label class="gd-ai-preset-card">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="light" <?php checked( $s['preset'], 'light' ); ?>>
									<span class="gd-ai-preset-visual gd-ai-preset-light"><?php echo esc_html( $s['label_text'] ); ?></span>
									<strong><?php esc_html_e( 'Light badge', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Near-white background, dark text and a crisp fine border.', 'ai-image-disclosure-labels' ); ?></small>
								</label>

								<label class="gd-ai-preset-card">
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset]" value="pill" <?php checked( $s['preset'], 'pill' ); ?>>
									<span class="gd-ai-preset-visual gd-ai-preset-pill"><?php echo esc_html( $s['label_text'] ); ?></span>
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
										<option value="icon" <?php selected( $s['small_image_mode'], 'icon' ); ?>><?php esc_html_e( 'Show AI symbol (recommended)', 'ai-image-disclosure-labels' ); ?></option>
										<option value="hide" <?php selected( $s['small_image_mode'], 'hide' ); ?>><?php esc_html_e( 'Show nothing', 'ai-image-disclosure-labels' ); ?></option>
									</select>
								</label>

								<label class="gd-ai-toggle-field gd-ai-field-wide">
									<input type="checkbox" id="gd-ai-touch-compact-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[touch_compact_mode]" value="1" <?php checked( ! empty( $s['touch_compact_mode'] ) ); ?>>
									<span>
										<strong><?php esc_html_e( 'Prefer the compact symbol on touch-first devices (recommended)', 'ai-image-disclosure-labels' ); ?></strong>
										<small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'on', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'For phones and tablets, browser pointer, hover and touch capabilities are used instead of WordPress user-agent sniffing, so large Android tablets are handled more reliably.', 'ai-image-disclosure-labels' ); ?></small>
									</span>
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
							<p class="description"><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong>180 px</strong> <?php esc_html_e( 'for any label,', 'ai-image-disclosure-labels' ); ?> <strong>500 px</strong> <?php esc_html_e( 'for the full text label and', 'ai-image-disclosure-labels' ); ?> <strong>16 px</strong> <?php esc_html_e( 'for the symbol.', 'ai-image-disclosure-labels' ); ?></p>
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
									<strong><?php esc_html_e( 'Automatic color from the image (recommended)', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'on', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'The badge uses the average color of each image so it blends in; the text switches automatically between dark and light for readability. The color is computed once per image and cached. If it cannot be determined, the fixed colors below are used.', 'ai-image-disclosure-labels' ); ?></small>
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
										<option value="inherit" <?php selected( $s['font_family_mode'], 'inherit' ); ?>><?php esc_html_e( 'Inherit from theme (recommended)', 'ai-image-disclosure-labels' ); ?></option>
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
							<h2><?php esc_html_e( 'Video badge design', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Video disclosures are shown below the player so they do not cover playback controls. You can keep one shared badge style or customize videos separately.', 'ai-image-disclosure-labels' ); ?></p>
							<label class="gd-ai-toggle-field">
								<input type="checkbox" id="gd-ai-video-separate-design" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_separate_design]" value="1" <?php checked( ! empty( $s['video_separate_design'] ) ); ?>>
								<span>
									<strong><?php esc_html_e( 'Use a separate design for video badges', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'off', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'Videos then reuse the image badge styling for a consistent look. Turn this on only when you want a different video style; the dedicated controls appear immediately below.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>
							<div class="gd-ai-video-design-fields" <?php echo empty( $s['video_separate_design'] ) ? 'hidden' : ''; ?>>
								<div class="gd-ai-field-grid">
									<label class="gd-ai-field"><span><?php esc_html_e( 'Alignment below video', 'ai-image-disclosure-labels' ); ?></span><select id="gd-ai-video-alignment" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_alignment]"><option value="left" <?php selected( $s['video_alignment'], 'left' ); ?>><?php esc_html_e( 'Left', 'ai-image-disclosure-labels' ); ?></option><option value="center" <?php selected( $s['video_alignment'], 'center' ); ?>><?php esc_html_e( 'Center', 'ai-image-disclosure-labels' ); ?></option><option value="right" <?php selected( $s['video_alignment'], 'right' ); ?>><?php esc_html_e( 'Right', 'ai-image-disclosure-labels' ); ?></option></select></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Background color', 'ai-image-disclosure-labels' ); ?></span><input type="color" id="gd-ai-video-background-color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_background_color]" value="<?php echo esc_attr( $s['video_background_color'] ); ?>"></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Background opacity', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-video-background-opacity" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_background_opacity]" value="<?php echo esc_attr( $s['video_background_opacity'] ); ?>" min="0" max="100"><em>%</em></span></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Text color', 'ai-image-disclosure-labels' ); ?></span><input type="color" id="gd-ai-video-text-color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_text_color]" value="<?php echo esc_attr( $s['video_text_color'] ); ?>"></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Border color', 'ai-image-disclosure-labels' ); ?></span><input type="color" id="gd-ai-video-border-color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_border_color]" value="<?php echo esc_attr( $s['video_border_color'] ); ?>"></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Border width', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-video-border-width" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_border_width]" value="<?php echo esc_attr( $s['video_border_width'] ); ?>" min="0" max="5"><em>px</em></span></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Corner radius', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-video-border-radius" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_border_radius]" value="<?php echo esc_attr( $s['video_border_radius'] ); ?>" min="0" max="999"><em>px</em></span></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Font size', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-video-font-size" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_font_size]" value="<?php echo esc_attr( $s['video_font_size'] ); ?>" min="8" max="24"><em>px</em></span></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Font weight', 'ai-image-disclosure-labels' ); ?></span><select id="gd-ai-video-font-weight" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_font_weight]"><?php foreach ( array( 400, 500, 600, 700 ) as $weight ) : ?><option value="<?php echo (int) $weight; ?>" <?php selected( (int) $s['video_font_weight'], $weight ); ?>><?php echo (int) $weight; ?></option><?php endforeach; ?></select></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Vertical padding', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-video-padding-vertical" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_padding_vertical]" value="<?php echo esc_attr( $s['video_padding_vertical'] ); ?>" min="0" max="20"><em>px</em></span></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Horizontal padding', 'ai-image-disclosure-labels' ); ?></span><span class="gd-ai-number-wrap"><input type="number" id="gd-ai-video-padding-horizontal" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_padding_horizontal]" value="<?php echo esc_attr( $s['video_padding_horizontal'] ); ?>" min="0" max="30"><em>px</em></span></label>
									<label class="gd-ai-field"><span><?php esc_html_e( 'Text transform', 'ai-image-disclosure-labels' ); ?></span><select id="gd-ai-video-text-transform" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[video_text_transform]"><option value="none" <?php selected( $s['video_text_transform'], 'none' ); ?>><?php esc_html_e( 'Normal', 'ai-image-disclosure-labels' ); ?></option><option value="uppercase" <?php selected( $s['video_text_transform'], 'uppercase' ); ?>><?php esc_html_e( 'Uppercase', 'ai-image-disclosure-labels' ); ?></option></select></label>
								</div>
							</div>
						</section>


						<div class="gd-ai-section-heading" role="separator">
							<h2><?php esc_html_e( 'Site integration & fine tuning', 'ai-image-disclosure-labels' ); ?></h2>
						</div>

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

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Location-specific display', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Use these optional rules when a full text label would be too prominent in a particular layout, or when a disclosure would not fit at all. The rules only change disclosures that already exist; they never mark an image as AI-generated. The normal responsive display remains unchanged everywhere that does not match a rule.', 'ai-image-disclosure-labels' ); ?></p>
							<label class="gd-ai-toggle-field">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[location_rules_enabled]" value="1" <?php checked( ! empty( $s['location_rules_enabled'] ) ); ?>>
								<span>
									<strong><?php esc_html_e( 'Enable selector-based display rules', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Apply icon-only or hidden overrides inside selected page elements. Full labels remain the default elsewhere.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>

							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'Always show symbol only', 'ai-image-disclosure-labels' ); ?></span>
								<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[icon_only_selectors]" rows="4" placeholder="body.home .ai-label-disclosure-symbol-only" spellcheck="false"><?php echo esc_textarea( $s['icon_only_selectors'] ); ?></textarea>
							</label>
							<p class="description"><?php esc_html_e( 'Enter one CSS selector per line. Use this for large hero cards, overlay tiles or other places where the text could suggest that the surrounding article or section was AI-generated. A selector may target the image, its label frame or any parent container.', 'ai-image-disclosure-labels' ); ?></p>
							<p class="description"><?php esc_html_e( 'For a stable editor-controlled rule, add a custom class such as “ai-label-disclosure-symbol-only” to the outer Group, Cover, Query or layout container that contains the marked image. Enter the class without a dot in the block editor, but as “.ai-label-disclosure-symbol-only” here. Prefix it with a page body class, for example “body.home .ai-label-disclosure-symbol-only”, when it should apply only on the posts homepage.', 'ai-image-disclosure-labels' ); ?></p>
							<p class="description"><?php esc_html_e( 'Prefer a custom layout class over post IDs, attachment IDs or automatically generated content classes. Those identifiers can change when a different post or image is displayed.', 'ai-image-disclosure-labels' ); ?></p>

							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'Hide label completely', 'ai-image-disclosure-labels' ); ?></span>
								<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hidden_selectors]" rows="3" placeholder="body.home .very-small-thumbnail-list" spellcheck="false"><?php echo esc_textarea( $s['hidden_selectors'] ); ?></textarea>
							</label>
							<p class="description"><?php esc_html_e( 'Use hidden rules sparingly, normally only where even the compact symbol cannot be displayed clearly. Hiding a disclosure removes the visible notice in that location. Hidden rules take priority when an element matches both lists.', 'ai-image-disclosure-labels' ); ?></p>
						</section>

						<div class="gd-ai-section-heading" role="separator">
							<h2><?php esc_html_e( 'Advanced options', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'These settings are useful for structured data, automatic inheritance and theme-specific edge cases. Most sites can leave the defaults unchanged.', 'ai-image-disclosure-labels' ); ?></p>
						</div>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Machine-readable source information', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Optionally add a structured declaration to the HTML source for every enabled image or video marked by this plugin.', 'ai-image-disclosure-labels' ); ?></p>

							<label class="gd-ai-toggle-field">
								<input type="checkbox" id="gd-ai-machine-readable-enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[machine_readable_enabled]" value="1" <?php checked( ! empty( $s['machine_readable_enabled'] ) ); ?>>
								<span>
									<strong><?php esc_html_e( 'Add machine-readable AI source information to marked media', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Adds a publisher-supplied Schema.org digitalSourceType declaration using the IPTC Digital Source Type vocabulary. It is included in the HTML source and does not change the visible page.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>

							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'Default digital source type', 'ai-image-disclosure-labels' ); ?></span>
								<select id="gd-ai-digital-source-type" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[digital_source_type]">
									<?php foreach ( $this->digital_source_types() as $gdaiidl_source_key => $gdaiidl_source ) : ?>
										<option value="<?php echo esc_attr( $gdaiidl_source_key ); ?>" <?php selected( $s['digital_source_type'], $gdaiidl_source_key ); ?>><?php echo esc_html( $gdaiidl_source['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<small><?php esc_html_e( 'Used unless a different source type is selected for an individual media use.', 'ai-image-disclosure-labels' ); ?></small>
							</label>

							<p class="description"><strong><?php esc_html_e( 'Important:', 'ai-image-disclosure-labels' ); ?></strong> <?php esc_html_e( 'This is a self-declared transparency signal. It does not modify the media file, create or verify C2PA Content Credentials, prove origin or authenticity, or replace machine-readable markings supplied by the provider of a generative AI system. Legal duties depend on the content, your role and the applicable law.', 'ai-image-disclosure-labels' ); ?></p>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Media Library classification', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Each image or video in the Media Library has an "AI status" selector (Attachment details or Edit media): AI-generated, AI-modified, no AI used, or unclassified. The status travels with the attachment, which is useful for featured images and media reused across posts.', 'ai-image-disclosure-labels' ); ?></p>
							<p><?php esc_html_e( 'The Media Library list view also includes an AI status column, a status filter and grouped bulk actions. When machine-readable output is enabled, AI classifications can supply structured source data wherever the plugin can identify the attachment. "No AI used" intentionally adds no AI digital source type.', 'ai-image-disclosure-labels' ); ?></p>
							<p class="description"><?php esc_html_e( 'The public status is deliberately simple. If older content or an advanced per-use setting contains more precise provenance (for example generative editing versus algorithmic enhancement), the plugin keeps that technical distinction for structured data. A new generic AI-modified status does not guess which precise technical source type applies.', 'ai-image-disclosure-labels' ); ?></p>

							<label class="gd-ai-toggle-field">
								<input type="checkbox" id="gd-ai-media-library-auto-label" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[media_library_auto_label]" value="1" <?php checked( ! empty( $s['media_library_auto_label'] ) ); ?>>
								<span>
									<strong><?php esc_html_e( 'Automatically show a visible disclosure on media marked in the Media Library', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'When enabled, any Image block, Video block or featured image whose Media Library entry is marked as AI-generated or AI-modified shows a visible disclosure automatically, even without marking it in the editor. Explicitly marked uses keep their own text. Video disclosures are placed below the video. Leave this off to keep disclosures strictly opt-in per media use.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>

							<p class="description"><?php esc_html_e( 'Automatic labels use the matching editable text from the Visible label texts section above. No automatic visible label is produced for “No AI used” or “Not classified”. Mark an individual Image block, Video block or featured image in the editor when you need wording that differs from the defaults.', 'ai-image-disclosure-labels' ); ?></p>
						</section>


						<div class="gd-ai-section-heading" role="separator">
							<h2><?php esc_html_e( 'Security, privacy and performance', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Safe defaults for frontend loading and external AI requests. Experimental analysis has its own cost and privacy controls below.', 'ai-image-disclosure-labels' ); ?></p>
						</div>


						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Security and privacy defaults', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Normal disclosure labels are local WordPress output and do not require an external service. Optional AI-assisted image analysis is disabled by default. API credentials are handled server-side and are not exposed to site visitors or localized into frontend JavaScript.', 'ai-image-disclosure-labels' ); ?></p>
							<p class="description"><?php esc_html_e( 'If you enable external AI analysis, review the selected provider’s privacy, retention and billing terms. The experimental section below shows the controls that can trigger requests or automatic classification.', 'ai-image-disclosure-labels' ); ?></p>
						</section>

						<section class="gd-ai-card">
							<h2><?php esc_html_e( 'Performance', 'ai-image-disclosure-labels' ); ?></h2>
							<label class="gd-ai-toggle-field">
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[load_assets_only_when_needed]" value="1" <?php checked( ! empty( $s['load_assets_only_when_needed'] ) ); ?>>
								<span>
									<strong><?php esc_html_e( 'Load frontend assets only when a disclosure may be needed (recommended)', 'ai-image-disclosure-labels' ); ?></strong>
									<small><?php esc_html_e( 'Recommended:', 'ai-image-disclosure-labels' ); ?> <strong><?php esc_html_e( 'on', 'ai-image-disclosure-labels' ); ?></strong>. <?php esc_html_e( 'The plugin loads its CSS and JavaScript only when enabled image/video disclosure content may be present. Turn this off only if a theme, page builder or cache setup causes labels to appear without their styling or responsive behaviour.', 'ai-image-disclosure-labels' ); ?></small>
								</span>
							</label>
						</section>

						<div class="gd-ai-section-heading" role="separator">
							<h2><?php esc_html_e( 'Experimental AI analysis', 'ai-image-disclosure-labels' ); ?></h2>
							<p><?php esc_html_e( 'Optional image-only assistance. Nothing here is required for normal image or video disclosure.', 'ai-image-disclosure-labels' ); ?></p>
						</div>

						<?php if ( class_exists( 'GDAIIDL_AI_Analysis' ) ) { GDAIIDL_AI_Analysis::instance()->render_settings_card(); } ?>

						<?php submit_button( __( 'Save settings', 'ai-image-disclosure-labels' ) ); ?>
					</main>

					<aside class="gd-ai-settings-preview">
						<div class="gd-ai-card gd-ai-sticky">
							<h2><?php esc_html_e( 'Live previews', 'ai-image-disclosure-labels' ); ?></h2>
							<label class="gd-ai-field gd-ai-field-wide">
								<span><?php esc_html_e( 'Preview label', 'ai-image-disclosure-labels' ); ?></span>
								<select id="gd-ai-preview-text-type">
									<option value="generated"><?php esc_html_e( 'AI-generated', 'ai-image-disclosure-labels' ); ?></option>
									<option value="modified"><?php esc_html_e( 'AI-modified', 'ai-image-disclosure-labels' ); ?></option>
								</select>
							</label>
							<h3><?php esc_html_e( 'Image preview', 'ai-image-disclosure-labels' ); ?></h3>
							<div id="gd-ai-preview" class="gd-ai-preview">
								<div class="gd-ai-preview-scene" aria-hidden="true">
									<span class="gd-ai-preview-sun"></span>
									<span class="gd-ai-preview-hill gd-ai-preview-hill-one"></span>
									<span class="gd-ai-preview-hill gd-ai-preview-hill-two"></span>
								</div>
								<span id="gd-ai-preview-label" class="gd-ai-preview-label"><?php echo esc_html( $s['label_text'] ); ?></span>
							</div>


							<h3><?php esc_html_e( 'Video preview', 'ai-image-disclosure-labels' ); ?></h3>
							<div id="gd-ai-video-preview" class="gd-ai-video-preview">
								<div class="gd-ai-video-preview-screen" aria-hidden="true"><span class="dashicons dashicons-controls-play"></span></div>
								<div id="gd-ai-video-preview-row" class="gd-ai-video-preview-row gd-ai-video-preview-align-<?php echo esc_attr( $s['video_alignment'] ); ?>"><span id="gd-ai-video-preview-label" class="gd-ai-video-preview-label"><?php echo esc_html( ! empty( $s['video_label_text'] ) ? $s['video_label_text'] : $s['label_text'] ); ?></span></div>
							</div>
							<p class="description"><?php esc_html_e( 'The video label is always outside the playback surface. When separate video design is off, this preview inherits the image badge design.', 'ai-image-disclosure-labels' ); ?></p>

							<h3><?php esc_html_e( 'Symbol preview', 'ai-image-disclosure-labels' ); ?></h3>
							<div id="gd-ai-symbol-preview" class="gd-ai-symbol-preview">
								<span id="gd-ai-preview-symbol" class="gd-ai-preview-symbol" tabindex="<?php echo ! empty( $s['icon_tooltip_enabled'] ) ? '0' : '-1'; ?>" role="button" aria-expanded="false" aria-disabled="<?php echo ! empty( $s['icon_tooltip_enabled'] ) ? 'false' : 'true'; ?>">
									<span id="gd-ai-preview-symbol-icon" class="gd-ai-preview-symbol-icon"><?php echo wp_kses( $this->icon_markup( $s['icon_style'] ), $this->allowed_icon_html() ); ?></span>
									<span id="gd-ai-preview-symbol-tooltip" class="gd-ai-preview-symbol-tooltip"><?php echo esc_html( $s['label_text'] ); ?></span>
								</span>
							</div>
							<p class="description"><?php esc_html_e( 'The image preview shows the overlay badge; the video preview shows the below-video layout; the symbol preview shows the compact image treatment.', 'ai-image-disclosure-labels' ); ?></p>
						</div>
					</aside>
				</div>
			</form>

			<div class="gd-ai-plugin-meta">
				<p>
					<strong>
						<?php
						printf(
							/* translators: %s: current plugin version. */
							esc_html__( 'Version %s', 'ai-image-disclosure-labels' ),
							esc_html( GDAIIDL_VERSION )
						);
						?>
					</strong>
					<span aria-hidden="true"> &middot; </span>
					<?php esc_html_e( 'Developed by', 'ai-image-disclosure-labels' ); ?>
					<a href="https://drissner.media/" target="_blank" rel="noopener noreferrer">Gerald Drißner</a>
				</p>
				<p>
					<a href="https://www.paypal.com/paypalme/drissner" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Donate', 'ai-image-disclosure-labels' ); ?></a>
					<span aria-hidden="true"> &bull; </span>
					<a href="https://drissner.media/kontakt" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support', 'ai-image-disclosure-labels' ); ?></a>
				</p>
			</div>
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
			? '.gd-ai-editor-preview, .gd-ai-editor-video-preview'
			: 'html body .gd-ai-image-frame, html body .gd-ai-featured-theme-fallback, html body .gd-ai-video-disclosure-row';

		$video_separate = ! empty( $s['video_separate_design'] );

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


		if ( $video_separate ) {
			$video_bg_color = sanitize_hex_color( $s['video_background_color'] );
			$video_text_color = sanitize_hex_color( $s['video_text_color'] );
			$video_border_color = sanitize_hex_color( $s['video_border_color'] );
			$video_alpha = round( max( 0, min( 100, (int) $s['video_background_opacity'] ) ) / 100, 2 );
			$video_target = $editor ? '.gd-ai-editor-video-preview' : 'html body .gd-ai-video-disclosure-row';
			$css .= sprintf(
				'%1$s{--gd-ai-label-bg:%2$s;--gd-ai-label-color:%3$s;--gd-ai-label-border-width:%4$dpx;--gd-ai-label-border-color:%5$s;--gd-ai-label-radius:%6$dpx;--gd-ai-label-font-size:%7$dpx;--gd-ai-label-font-weight:%8$d;--gd-ai-label-padding-y:%9$dpx;--gd-ai-label-padding-x:%10$dpx;--gd-ai-label-transform:%11$s;}',
				$video_target,
				$this->hex_to_rgba( $video_bg_color ? $video_bg_color : '#171717', $video_alpha ),
				$video_text_color ? $video_text_color : '#ffffff',
				max( 0, min( 5, (int) $s['video_border_width'] ) ),
				$video_border_color ? $video_border_color : '#ffffff',
				max( 0, min( 999, (int) $s['video_border_radius'] ) ),
				max( 8, min( 24, (int) $s['video_font_size'] ) ),
				in_array( (int) $s['video_font_weight'], array( 400, 500, 600, 700 ), true ) ? (int) $s['video_font_weight'] : 600,
				max( 0, min( 20, (int) $s['video_padding_vertical'] ) ),
				max( 0, min( 30, (int) $s['video_padding_horizontal'] ) ),
				in_array( $s['video_text_transform'], array( 'none', 'uppercase' ), true ) ? $s['video_text_transform'] : 'none'
			);
		}

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
				/* Video disclosures are flow content, not image overlays; image-width thresholds do not apply. */
				$css .= '.gd-ai-video-disclosure-row > .gd-ai-image-label{visibility:visible;}';
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
		$script_path = plugin_basename( GDAIIDL_DIR . 'assets/frontend.js' );

		/*
		 * WP Rocket matches Delay JavaScript exclusions against script URLs,
		 * not WordPress enqueue handles. Build the URL fragment from the actual
		 * installed plugin directory so renamed development folders and the
		 * canonical WordPress.org folder are both handled correctly.
		 */
		$exclusions[] = '/' . ltrim( wp_normalize_path( $script_path ), '/' );

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
			'<a href="' . esc_url( admin_url( 'options-general.php?page=gdaiidl-settings' ) ) . '">' . esc_html__( 'Settings', 'ai-image-disclosure-labels' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Add donation and support links below the plugin description.
	 *
	 * @param array  $plugin_meta Existing plugin row metadata.
	 * @param string $plugin_file Plugin basename.
	 * @return array
	 */
	public function add_plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( plugin_basename( GDAIIDL_FILE ) !== $plugin_file ) {
			return $plugin_meta;
		}

		$plugin_meta[] = '<a href="' . esc_url( 'https://www.paypal.com/paypalme/drissner' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'ai-image-disclosure-labels' ) . '</a>';
		$plugin_meta[] = '<a href="' . esc_url( 'https://drissner.media/kontakt' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'ai-image-disclosure-labels' ) . '</a>';

		return $plugin_meta;
	}
}
