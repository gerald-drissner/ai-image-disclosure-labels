<?php
/**
 * Uninstall routine for AI Image & Video Disclosure Labels.
 *
 * Removes all plugin options and per-post label metadata. Runs only when the
 * plugin is deleted through the WordPress admin, never on deactivation.
 *
 * @package GDAIIDL_Plugin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove plugin data for the current site.
 *
 * @return void
 */
function gdaiidl_uninstall_site() {
	delete_option( 'gdaiidl_settings' );
	delete_option( 'gdaiidl_version' );
	delete_option( 'gdaiidl_ai_analysis_settings' );
	delete_option( 'gdaiidl_ai_analysis_secrets' );
	delete_option( 'gdaiidl_ai_analysis_jobs' );
	delete_option( 'gdaiidl_ai_analysis_cost_stats' );
	wp_clear_scheduled_hook( 'gdaiidl_process_ai_analysis_job' );

	delete_post_meta_by_key( '_gdaiidl_featured_enabled' );
	delete_post_meta_by_key( '_gdaiidl_featured_text' );
	delete_post_meta_by_key( '_gdaiidl_featured_source_type' );
	delete_post_meta_by_key( '_gdaiidl_media_source_type' );
	delete_post_meta_by_key( '_gdaiidl_avg_color' );
	delete_post_meta_by_key( '_gdaiidl_ai_analysis' );
	delete_post_meta_by_key( '_gdaiidl_ai_analysis_class' );

	/* Remove data left by GitHub releases published before version 2.0.1. */
	$legacy_prefix = 'gd_' . 'ai_';
	delete_option( $legacy_prefix . 'image_labels_settings' );
	delete_option( $legacy_prefix . 'image_labels_version' );
	delete_post_meta_by_key( '_' . $legacy_prefix . 'featured_enabled' );
	delete_post_meta_by_key( '_' . $legacy_prefix . 'featured_text' );
	delete_post_meta_by_key( '_' . $legacy_prefix . 'avg_color' );
}

if ( is_multisite() ) {
	$gdaiidl_offset = 0;
	$gdaiidl_batch  = 100;

	do {
		$gdaiidl_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $gdaiidl_batch,
				'offset' => $gdaiidl_offset,
			)
		);

		foreach ( $gdaiidl_site_ids as $gdaiidl_site_id ) {
			switch_to_blog( $gdaiidl_site_id );
			gdaiidl_uninstall_site();
			restore_current_blog();
		}

		$gdaiidl_count   = count( $gdaiidl_site_ids );
		$gdaiidl_offset += $gdaiidl_count;
	} while ( $gdaiidl_count === $gdaiidl_batch );
} else {
	gdaiidl_uninstall_site();
}
