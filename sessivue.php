<?php
/**
 * Plugin Name: Sessivue
 * Description: See WordPress login sessions and recent admin activity in a privacy-focused dashboard widget.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Amino Studio
 * Author URI: https://aminostudio.fr/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sessivue
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SESSIVUE_VERSION', '1.0.0' );
define( 'SESSIVUE_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sessivue.php';

register_activation_hook( __FILE__, array( 'Sessivue_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Sessivue_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Sessivue_Plugin', 'init' ) );
