<?php
/**
 * Plugin Name: AI Tools - Alt Text Generator
 * Description: Automatically generate descriptive, accessible alt text for your images using AI (BYOK) — improving accessibility compliance and SEO.
 * Version: 0.0.1
 * Author: astappiev
 * Author URI: https://github.com/astappiev
 * Update URI: false
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-ai-tools
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'AITOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AITOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'AITOOLS_VERSION', '0.0.1' );

/**
 * Add Settings link to plugins page
 */
function aitools_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wp-ai-tools' ) . '">' . __( 'Settings', 'wp-ai-tools' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aitools_add_settings_link' );


/**
 * Rename options on plugin activation.
 */
register_activation_hook( __FILE__, 'aitools_activate' );

function aitools_activate() {
	$old_option = get_option( 'aitools_text_generator_options' );
	if ( false !== $old_option ) {
		update_option( 'aitools_options', $old_option );
		delete_option( 'aitools_text_generator_options' );
	}
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once AITOOLS_PATH . 'includes/functions.php';
require_once AITOOLS_PATH . 'includes/class-wp-ai-tools-admin.php';
require_once AITOOLS_PATH . 'includes/class-wp-ai-tools-restpoint.php';
require_once AITOOLS_PATH . 'includes/providers/provider-factory.php';

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'wp-ai-tools', false, plugin_basename( __DIR__ ) . '/languages' );
} );

/**
 * Register WP-CLI commands when running under WP-CLI.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once AITOOLS_PATH . 'includes/class-wp-ai-tools-cli.php';
	WP_CLI::add_command( 'ai-alt-text', 'WP_AI_Tools_CLI' );
}

new WP_AI_Tools_Admin();
