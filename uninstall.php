<?php
/**
 * Uninstall routine for AI Image Disclosure & Labels.
 *
 * Removes all plugin options and per-post label metadata. Runs only when the
 * plugin is deleted through the WordPress admin, never on deactivation.
 *
 * @package GD_AI_Image_Labels
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove plugin data for the current site.
 *
 * @return void
 */
function gd_ai_image_labels_uninstall_site() {
	delete_option( 'gd_ai_image_labels_settings' );
	delete_option( 'gd_ai_image_labels_version' );

	delete_post_meta_by_key( '_gd_ai_featured_enabled' );
	delete_post_meta_by_key( '_gd_ai_featured_text' );
	delete_post_meta_by_key( '_gd_ai_avg_color' );
}

if ( is_multisite() ) {
	$gd_ai_image_labels_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $gd_ai_image_labels_site_ids as $gd_ai_image_labels_site_id ) {
		switch_to_blog( $gd_ai_image_labels_site_id );
		gd_ai_image_labels_uninstall_site();
		restore_current_blog();
	}
} else {
	gd_ai_image_labels_uninstall_site();
}
