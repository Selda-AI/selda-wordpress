<?php
/**
 * Plugin Name:       Selda
 * Plugin URI:        https://selda.ai
 * Description:       Turn your website into a sales engine. Every enquiry, quote request and guide download goes straight into Selda, where the follow-up is drafted for you.
 * Version:           0.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Selda
 * Author URI:        https://selda.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       selda
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SELDA_VERSION', '0.2.0' );
define( 'SELDA_FILE', __FILE__ );
define( 'SELDA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SELDA_URL', plugin_dir_url( __FILE__ ) );

/** Default endpoint. Overridable so staging can be pointed elsewhere. */
define( 'SELDA_DEFAULT_ENDPOINT', 'https://mcp.selda.ai/api/mcp' );

require_once SELDA_DIR . 'includes/class-selda-log.php';
require_once SELDA_DIR . 'includes/class-selda-api.php';
require_once SELDA_DIR . 'includes/class-selda-settings.php';
require_once SELDA_DIR . 'includes/class-selda-form.php';
require_once SELDA_DIR . 'includes/class-selda-capture.php';
require_once SELDA_DIR . 'includes/class-selda-notify.php';

add_action( 'plugins_loaded', function () {
	Selda_Log::init();
	Selda_Settings::init();
	Selda_Form::init();
	Selda_Capture::init();
	Selda_Notify::init();
} );

register_activation_hook( __FILE__, array( 'Selda_Log', 'install' ) );
