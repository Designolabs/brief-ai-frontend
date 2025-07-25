<?php
/*
Plugin Name: Innovopedia Brief
Description: AI-powered news briefing popup with advanced UI/UX and admin dashboard.
Version: 1.2.0
Author: Designolabs Studio
Text Domain: innovopedia-brief
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'INNOVOPEDIA_BRIEF_VERSION', '1.2.0' );
define( 'INNOVOPEDIA_BRIEF_DIR', plugin_dir_path( __FILE__ ) );
define( 'INNOVOPEDIA_BRIEF_URL', plugin_dir_url( __FILE__ ) );

require_once INNOVOPEDIA_BRIEF_DIR . 'includes/shortcode.php';
require_once INNOVOPEDIA_BRIEF_DIR . 'includes/assets.php';
require_once INNOVOPEDIA_BRIEF_DIR . 'admin/dashboard.php';
require_once INNOVOPEDIA_BRIEF_DIR . 'admin/settings.php';

// --- Activation / Deactivation ---
register_activation_hook( __FILE__, 'innovopedia_brief_activate' );
function innovopedia_brief_activate() {
    $defaults = [
        'api_base_url' => '', 'api_key' => '', 'primary_color' => '#00bcd4',
        'secondary_color' => '#4CAF50', 'border_radius' => '15px', 'trend_schedule' => 'daily',
        'content_sources' => ['post', 'page'], 'cache_duration' => 5
    ];
    add_option('innovopedia_brief_settings', $defaults, '', 'yes');
    if (!wp_next_scheduled('innovopedia_brief_daily_trend_analysis')) {
        wp_schedule_event(time(), 'daily', 'innovopedia_brief_daily_trend_analysis');
    }
}

register_deactivation_hook( __FILE__, 'innovopedia_brief_deactivate' );
function innovopedia_brief_deactivate() {
    wp_clear_scheduled_hook('innovopedia_brief_daily_trend_analysis');
    wp_clear_scheduled_hook('innovopedia_brief_weekly_trend_analysis');
    wp_clear_scheduled_hook('innovopedia_brief_monthly_trend_analysis');
}

// --- Admin Menu ---
add_action( 'admin_menu', 'innovopedia_brief_add_admin_menu' );
function innovopedia_brief_add_admin_menu() {
    add_menu_page('Innovopedia Brief', 'Innovopedia Brief', 'manage_options', 'innovopedia-brief', 'innovopedia_brief_admin_page', 'dashicons-megaphone', 26);
    add_submenu_page('innovopedia-brief', 'Settings', 'Settings', 'manage_options', 'innovopedia-brief-settings', 'innovopedia_brief_settings_page');
}

// --- AJAX Endpoints ---
// Briefing Data with Caching (for frontend)
add_action('wp_ajax_nopriv_innovopedia_brief_get_data', 'innovopedia_brief_get_data_with_caching');
add_action('wp_ajax_innovopedia_brief_get_data', 'innovopedia_brief_get_data_with_caching');
function innovopedia_brief_get_data_with_caching() {
    $options = get_option('innovopedia_brief_settings');
    $cache_duration = intval($options['cache_duration'] ?? 5) * MINUTE_IN_SECONDS;
    $transient_key = 'innovopedia_brief_data';

    if (false !== ($cached_data = get_transient($transient_key))) {
        wp_send_json_success(json_decode($cached_data));
        return;
    }

    $api_base_url = $options['api_base_url'] ?? '';
    $api_key = $options['api_key'] ?? '';
    if (empty($api_base_url)) wp_send_json_error(['message' => 'API not configured.']);

    $url = rtrim($api_base_url, '/') . '/get-briefing-advanced';
    $response = wp_remote_get($url, ['headers' => ['X-API-Key' => $api_key]]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    } else {
        $body = wp_remote_retrieve_body($response);
        set_transient($transient_key, $body, $cache_duration);
        wp_send_json_success(json_decode($body));
    }
}

// Story Interaction (Like)
add_action('wp_ajax_nopriv_innovopedia_brief_like_story', 'innovopedia_brief_like_story_handler');
add_action('wp_ajax_innovopedia_brief_like_story', 'innovopedia_brief_like_story_handler');
function innovopedia_brief_like_story_handler() {
    check_ajax_referer('innovopedia_brief_nonce', 'nonce');
    $story_id = sanitize_text_field($_POST['story_id']);
    
    $options = get_option('innovopedia_brief_settings');
    $api_base_url = $options['api_base_url'] ?? '';
    $api_key = $options['api_key'] ?? '';
    if (empty($api_base_url)) wp_send_json_error(['message' => 'API not configured.']);

    $url = rtrim($api_base_url, '/') . '/v1/story-interaction';
    $args = [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json', 'X-API-Key' => $api_key],
        'body' => json_encode(['story_id' => $story_id, 'action' => 'like']),
    ];
    wp_remote_post($url, $args); // Fire and forget
    wp_send_json_success();
}

// Send Email Summary
add_action('wp_ajax_innovopedia_brief_send_email_summary', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

    $recipients = sanitize_textarea_field($_POST['recipients']);
    $emails = array_map('trim', explode(',', $recipients));
    $emails = array_filter($emails, 'is_email');

    if (empty($emails)) wp_send_json_error(['message' => 'No valid email addresses provided.']);
    
    $briefing_data_json = get_transient('innovopedia_brief_data');
    if(false === $briefing_data_json) wp_send_json_error(['message' => 'No cached briefing data found. Please open the frontend popup first to generate data.']);
    
    $data = json_decode($briefing_data_json, true);
    $subject = get_bloginfo('name') . ' - Daily Briefing';
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $body = '<h1>' . esc_html($data['greeting']) . '</h1>';
    $body .= '<p>' . esc_html($data['intro']) . '</p>';
    foreach($data['stories'] as $story) {
        $body .= '<h2>' . esc_html($story['title']) . '</h2>';
        $body .= '<p>' . esc_html($story['summary']) . '</p>';
    }

    wp_mail($emails, $subject, $body, $headers);
    wp_send_json_success(['message' => 'Email summary sent to ' . count($emails) . ' recipients.']);
});

// Interactive Moderation
add_action('wp_ajax_innovopedia_brief_moderate_content', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
    
    $item_id = sanitize_text_field($_POST['item_id']);
    $status = sanitize_text_field($_POST['status']);
    if (!in_array($status, ['approved', 'rejected'])) wp_send_json_error(['message' => 'Invalid status.']);

    $options = get_option('innovopedia_brief_settings');
    $url = rtrim($options['api_base_url'], '/') . '/v1/moderate-content';
    $args = [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json', 'X-API-Key' => $options['api_key']],
        'body' => json_encode(['item_id' => $item_id, 'status' => $status]),
    ];
    $response = wp_remote_post($url, $args);
    // Handle response if needed, for now just proxying
    wp_send_json_success(['message' => 'Action sent to API.']);
});
