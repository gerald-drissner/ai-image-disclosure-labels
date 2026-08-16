<?php
/**
 * Plugin Name:       AI Image & Video Disclosure Labels
 * Plugin URI:        https://github.com/gerald-drissner/ai-image-disclosure-labels
 * Description:       Adds visible AI disclosure labels for images and videos, Media Library classification, optional machine-readable source data, and optional AI-assisted image analysis.
 * Version:           3.0.1
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Gerald Drißner
 * Author URI:        https://drissner.media/
 * Text Domain:       ai-image-disclosure-labels
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'GDAIIDL_VERSION', '3.0.1' );
define( 'GDAIIDL_FILE', __FILE__ );
define( 'GDAIIDL_DIR', plugin_dir_path( __FILE__ ) );
define( 'GDAIIDL_URL', plugin_dir_url( __FILE__ ) );

require_once GDAIIDL_DIR . 'includes/class-gdaiidl-plugin.php';
require_once GDAIIDL_DIR . 'includes/class-gdaiidl-ai-analysis.php';

register_activation_hook( __FILE__, array( 'GDAIIDL_Plugin', 'activate' ) );
register_activation_hook( __FILE__, array( 'GDAIIDL_AI_Analysis', 'activate' ) );

GDAIIDL_Plugin::instance();
GDAIIDL_AI_Analysis::instance();
