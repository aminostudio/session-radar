<?php
/**
 * Plugin Name: Amino Orbit
 * Plugin URI: https://aminostudio.fr/
 * Description: A lightweight, privacy-conscious WordPress dashboard widget for authenticated sessions and recent back-office activity.
 * Version: 0.3.2
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Amino Studio
 * Author URI: https://aminostudio.fr/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: amino-orbit
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMINO_ORBIT_VERSION', '0.3.2' );
define( 'AMINO_ORBIT_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-amino-orbit.php';

register_activation_hook( __FILE__, array( 'Amino_Orbit', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Amino_Orbit', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Amino_Orbit', 'init' ) );
