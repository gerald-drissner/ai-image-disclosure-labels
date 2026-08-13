<?php
/**
 * Plugin Name:       AI Image Disclosure & Labels
 * Plugin URI:        https://github.com/gerald-drissner/ai-image-disclosure-labels
 * Description:       Adds visible AI disclosure labels, compact symbols and optional machine-readable source data to selected images.
 * Version:           2.1.4
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Gerald Drißner
 * Author URI:        https://drissner.media/
 * Text Domain:       ai-image-disclosure-labels
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'GDAIIDL_VERSION', '2.1.4' );
define( 'GDAIIDL_FILE', __FILE__ );
define( 'GDAIIDL_DIR', plugin_dir_path( __FILE__ ) );
define( 'GDAIIDL_URL', plugin_dir_url( __FILE__ ) );

require_once GDAIIDL_DIR . 'includes/class-gdaiidl-plugin.php';

register_activation_hook( __FILE__, array( 'GDAIIDL_Plugin', 'activate' ) );

GDAIIDL_Plugin::instance();
