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
    $default_settings = [
        'api_base_url' => '',
        'api_key' => '',
        'primary_color' => '#00bcd4', // Default accent color
        'secondary_color' => '#4CAF50', // Default success color
        'border_radius' => '15px',
        'trend_schedule' => 'daily',
    ];
    add_option('innovopedia_brief_settings', $default_settings, '', 'yes');
    
    // Schedule initial trend analysis if not already scheduled
    if (!wp_next_scheduled('innovopedia_brief_daily_trend_analysis')) {
        wp_schedule_event(time(), 'daily', 'innovopedia_brief_daily_trend_analysis');
    }
}
register_activation_hook( __FILE__, 'innovopedia_brief_activate' );

// Deactivation hook
function innovopedia_brief_deactivate() {
    // Clean up, etc.
    wp_clear_scheduled_hook('innovopedia_brief_daily_trend_analysis');
    wp_clear_scheduled_hook('innovopedia_brief_weekly_trend_analysis');
    wp_clear_scheduled_hook('innovopedia_brief_monthly_trend_analysis');
}
register_deactivation_hook( __FILE__, 'innovopedia_brief_deactivate' );

// Uninstall hook
function innovopedia_brief_uninstall() {
    // Clean up, etc.
    delete_option('innovopedia_brief_settings');
    wp_clear_scheduled_hook('innovopedia_brief_daily_trend_analysis');
    wp_clear_scheduled_hook('innovopedia_brief_weekly_trend_analysis');
    wp_clear_scheduled_hook('innovopedia_brief_monthly_trend_analysis');
}
register_uninstall_hook( __FILE__, 'innovopedia_brief_uninstall' );

// Add settings link on plugins page
function innovopedia_brief_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=innovopedia-brief-settings' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'innovopedia_brief_add_settings_link' );

// Add admin menu and submenu
function innovopedia_brief_add_admin_menu() {
    add_menu_page(
        'Innovopedia Brief',
        'Innovopedia Brief',
        'manage_options',
        'innovopedia-brief',
        'innovopedia_brief_admin_page',
        'dashicons-megaphone',
        26
    );
    
    add_submenu_page(
        'innovopedia-brief', // Parent slug
        'Innovopedia Brief Settings',
        'Settings',
        'manage_options',
        'innovopedia-brief-settings',
        'innovopedia_brief_settings_page'
    );
}
add_action( 'admin_menu', 'innovopedia_brief_add_admin_menu' );

// Add custom cron schedules (weekly and monthly)
add_filter( 'cron_schedules', 'innovopedia_brief_custom_cron_schedules' );
function innovopedia_brief_custom_cron_schedules( $schedules ) {
    $schedules['weekly'] = array(
        'interval' => WEEK_IN_SECONDS, // 7 days
        'display'  => __( 'Once Weekly', 'innovopedia-brief' )
    );
    $schedules['monthly'] = array(
        'interval' => MONTH_IN_SECONDS, // Approx 30 days
        'display'  => __( 'Once Monthly', 'innovopedia-brief' )
    );
    return $schedules;
}

// Function to run trend analysis via API
function innovopedia_brief_perform_trend_analysis() {
    $options = get_option('innovopedia_brief_settings', []);
    $api_base_url = isset($options['api_base_url']) ? $options['api_base_url'] : '';

    if (empty($api_base_url)) {
        error_log('Innovopedia Brief: API Base URL not set for trend analysis.');
        return;
    }

    $url = rtrim($api_base_url, '/') . '/analyze-trend';
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        error_log('Innovopedia Brief: Error performing trend analysis: ' . $response->get_error_message());
    } else {
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            error_log('Innovopedia Brief: Trend analysis API returned error: ' . $http_code . ' - ' . $body);
        }
    }
}
// Hook into the custom cron events
add_action( 'innovopedia_brief_daily_trend_analysis', 'innovopedia_brief_perform_trend_analysis' );
add_action( 'innovopedia_brief_weekly_trend_analysis', 'innovopedia_brief_perform_trend_analysis' );
add_action( 'innovopedia_brief_monthly_trend_analysis', 'innovopedia_brief_perform_trend_analysis' );

// AJAX endpoint to update trend schedule from admin dashboard
add_action( 'wp_ajax_innovopedia_brief_set_trend_schedule', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce'); // Use admin nonce
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $schedule_type = isset($_POST['schedule']) ? sanitize_text_field($_POST['schedule']) : 'daily';
    $valid_schedules = ['daily', 'weekly', 'monthly'];

    if (!in_array($schedule_type, $valid_schedules)) {
        wp_send_json_error(['message' => 'Invalid schedule type.']);
    }

    $options = get_option('innovopedia_brief_settings', []);
    $old_schedule = isset($options['trend_schedule']) ? $options['trend_schedule'] : 'daily';

    if ($old_schedule !== $schedule_type) {
        // Clear all existing schedules for this plugin
        wp_clear_scheduled_hook('innovopedia_brief_daily_trend_analysis');
        wp_clear_scheduled_hook('innovopedia_brief_weekly_trend_analysis');
        wp_clear_scheduled_hook('innovopedia_brief_monthly_trend_analysis');

        // Schedule new event
        if ($schedule_type === 'daily') {
            wp_schedule_event(time(), 'daily', 'innovopedia_brief_daily_trend_analysis');
        } elseif ($schedule_type === 'weekly') {
            wp_schedule_event(time(), 'weekly', 'innovopedia_brief_weekly_trend_analysis');
        } elseif ($schedule_type === 'monthly') {
            wp_schedule_event(time(), 'monthly', 'innovopedia_brief_monthly_trend_analysis');
        }

        // Update option
        $options['trend_schedule'] = $schedule_type;
        update_option('innovopedia_brief_settings', $options);
    }

    wp_send_json_success(['message' => 'Trend analysis schedule updated to ' . $schedule_type . '.']);
});

// AJAX endpoint to get current trend schedule for admin dashboard
add_action( 'wp_ajax_innovopedia_brief_get_trend_schedule', function() {
    // No nonce check here as it's just reading a public setting, but could add if preferred
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $options = get_option('innovopedia_brief_settings', []);
    $current_schedule = isset($options['trend_schedule']) ? $options['trend_schedule'] : 'daily';
    wp_send_json_success(['schedule' => $current_schedule]);
});

// Function to train AI on website content
add_action( 'wp_ajax_innovopedia_brief_train_ai', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $options = get_option('innovopedia_brief_settings', []);
    $api_base_url = isset($options['api_base_url']) ? $options['api_base_url'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';

    if (empty($api_base_url)) {
        wp_send_json_error(['message' => 'API Base URL not set in plugin settings.']);
    }

    $train_url = rtrim($api_base_url, '/') . '/admin-train-ai';
    
    $args = [
        'method'    => 'POST',
        'headers'   => [
            'Content-Type' => 'application/json',
            'X-API-Key' => $api_key // Pass API Key in header
        ],
        'body'      => json_encode(['action' => 'train', 'source' => 'website_content']),
        'timeout'   => 60, // Increase timeout for potentially long training process
    ];

    $response = wp_remote_post($train_url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error training AI: ' . $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);

    if ($http_code === 200) {
        wp_send_json_success(['message' => 'AI training initiated successfully.', 'response' => json_decode($body)]);
    } else {
        wp_send_json_error(['message' => 'AI training failed with status ' . $http_code . ': ' . $body]);
    }
});
