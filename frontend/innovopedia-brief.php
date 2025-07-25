<?php
/*
Plugin Name: Innovopedia Brief
Description: AI-powered news briefing popup with advanced UI/UX and admin dashboard.
Version: 1.0.1
Author: Designolabs Studio
Text Domain: innovopedia-brief
Domain Path: /languages
Text Domain: innovopedia-brief
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'INNOVOPEDIA_BRIEF_VERSION', '1.0.0' );
define( 'INNOVOPEDIA_BRIEF_DIR', plugin_dir_path( __FILE__ ) );
define( 'INNOVOPEDIA_BRIEF_URL', plugin_dir_url( __FILE__ ) );

// Include core files
require_once INNOVOPEDIA_BRIEF_DIR . 'includes/shortcode.php';
require_once INNOVOPEDIA_BRIEF_DIR . 'includes/assets.php';
require_once INNOVOPEDIA_BRIEF_DIR . 'admin/dashboard.php';
require_once INNOVOPEDIA_BRIEF_DIR . 'admin/settings.php';

// Activation hook
function innovopedia_brief_activate() {
    // Set default options, etc.
}
register_activation_hook( __FILE__, 'innovopedia_brief_activate' );

// Deactivation hook
function innovopedia_brief_deactivate() {
    // Clean up, etc.
}
register_deactivation_hook( __FILE__, 'innovopedia_brief_deactivate' );

// Uninstall hook
function innovopedia_brief_uninstall() {
    // Clean up, etc.
}
register_uninstall_hook( __FILE__, 'innovopedia_brief_uninstall' );

// Add settings link
function innovopedia_brief_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=innovopedia-brief' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'innovopedia_brief_add_settings_link' );

// Add admin menu
function innovopedia_brief_add_admin_menu() {
    add_menu_page(
        'Innovopedia Brief',
        'Innovopedia Brief',
        'manage_options',
        'innovopedia-brief',
        'innovopedia_brief_admin_page',
        'dashicons-admin-generic',
        25
    );
}
add_action( 'admin_menu', 'innovopedia_brief_add_admin_menu' );

// Register settings
function innovopedia_brief_register_settings() {
    register_setting( 'innovopedia_brief_settings_group', 'innovopedia_brief_settings' );
    add_options_page(
        'Innovopedia Brief Settings',
        'Innovopedia Brief',
        'manage_options',
        'innovopedia-brief-settings',
        'innovopedia_brief_settings_page'
    );
}
add_action( 'admin_init', 'innovopedia_brief_register_settings' );

// Admin page
