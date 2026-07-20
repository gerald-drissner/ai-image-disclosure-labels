<?php
/**
 * Uninstall routine for AI Image Disclosure & Labels.
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

	delete_post_meta_by_key( '_gdaiidl_featured_enabled' );
	delete_post_meta_by_key( '_gdaiidl_featured_text' );
	delete_post_meta_by_key( '_gdaiidl_avg_color' );

	/* Remove data left by GitHub releases published before version 2.0.1. */
	$legacy_prefix = 'gd_' . 'ai_';
	delete_option( $legacy_prefix . 'image_labels_settings' );
	delete_option( $legacy_prefix . 'image_labels_version' );
	delete_post_meta_by_key( '_' . $legacy_prefix . 'featured_enabled' );
	delete_post_meta_by_key( '_' . $legacy_prefix . 'featured_text' );
	delete_post_meta_by_key( '_' . $legacy_prefix . 'avg_color' );
}

if ( is_multisite() ) {
	$gdaiidl_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $gdaiidl_site_ids as $gdaiidl_site_id ) {
		switch_to_blog( $gdaiidl_site_id );
		gdaiidl_uninstall_site();
		restore_current_blog();
	}
} else {
	gdaiidl_uninstall_site();
}
