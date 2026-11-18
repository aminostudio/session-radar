<?php
/**
 * Plugin Name: Session Radar
 * Description: See WordPress login sessions and recent admin activity in a privacy-focused dashboard widget.
 * Version: 2.0.0-alpha.1
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Amino Studio
 * Author URI: https://aminostudio.fr/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: session-radar
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMINO_ORBIT_VERSION', '2.0.0-alpha.1' );
define( 'AMINO_ORBIT_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-amino-orbit.php';

register_activation_hook( __FILE__, array( 'Amino_Orbit', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Amino_Orbit', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Amino_Orbit', 'init' ) );
