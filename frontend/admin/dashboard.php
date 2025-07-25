<?php
// Add Innovopedia Brief admin menu and dashboard page
function innovopedia_brief_admin_menu() {
    add_menu_page(
        'Innovopedia Brief',
        'Innovopedia Brief',
        'manage_options',
        'innovopedia-brief',
        'innovopedia_brief_admin_page',
        'dashicons-megaphone',
        26
    );
}
add_action( 'admin_menu', 'innovopedia_brief_admin_menu' );

// Enqueue admin JS for AJAX actions
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_innovopedia-brief') {
        wp_enqueue_script('innovopedia-brief-admin', INNOVOPEDIA_BRIEF_URL.'assets/admin.js', ['jquery'], INNOVOPEDIA_BRIEF_VERSION, true);
        $options = get_option('innovopedia_brief_settings', []);
        wp_localize_script('innovopedia-brief-admin', 'InnovopediaBriefAdmin', [
            'apiBaseUrl' => isset($options['api_base_url']) ? $options['api_base_url'] : '',
            'apiKey' => isset($options['api_key']) ? $options['api_key'] : '',
            'nonce' => wp_create_nonce('innovopedia_brief_admin'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
        wp_enqueue_style('innovopedia-brief-admin-style', INNOVOPEDIA_BRIEF_URL.'assets/admin.css', [], INNOVOPEDIA_BRIEF_VERSION);
    }
});

function innovopedia_brief_admin_page() {
    ?>
    <div class="wrap innovopedia-admin-wrap">
        <h1>Innovopedia Brief Dashboard</h1>
        <section class="innovopedia-admin-section">
            <h2>Analytics</h2>
            <div id="innovopedia-admin-analytics">
                <em>Loading analytics...</em>
            </div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>API Health</h2>
            <div id="innovopedia-brief-admin-api-health"></div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>Content Moderation</h2>
            <button class="button" id="innovopedia-admin-refresh-moderation"><span class="dashicons dashicons-update"></span> Refresh Data</button>
            <div id="innovopedia-brief-admin-moderation">
                <em>Loading moderation status...</em>
            </div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>User Feedback</h2>
            <button class="button" id="innovopedia-admin-refresh-feedback"><span class="dashicons dashicons-update"></span> Refresh Data</button>
            <div id="innovopedia-brief-admin-feedback-list">
                <em>Loading feedback...</em>
            </div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>User Questions</h2>
            <button class="button" id="innovopedia-admin-refresh-questions"><span class="dashicons dashicons-update"></span> Refresh Data</button>
            <div id="innovopedia-brief-admin-questions-list">
                <em>Loading questions...</em>
            </div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>Trend Report Scheduling</h2>
            <div id="innovopedia-brief-admin-trend-schedule">
                <form id="innovopedia-trend-schedule-form">
                    <label><input type="radio" name="trend_schedule" value="daily" checked> Daily</label>
                    <label><input type="radio" name="trend_schedule" value="weekly"> Weekly</label>
                    <label><input type="radio" name="trend_schedule" value="monthly"> Monthly</label>
                    <button type="submit" class="button button-primary">Save Schedule</button>
                    <span id="innovopedia-trend-schedule-status"></span>
                </form>
            </div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>Export Data</h2>
            <button class="button innovopedia-admin-export" data-type="feedback">Download Feedback CSV</button>
            <button class="button innovopedia-admin-export" data-type="questions">Download Questions CSV</button>
        </section>
        <section class="innovopedia-admin-section">
            <h2>Trend Analysis</h2>
            <button class="button button-primary" id="innovopedia-admin-analyze">Run Trend Analysis</button>
            <div id="innovopedia-admin-analyze-result"></div>
        </section>
        <section class="innovopedia-admin-section">
            <h2>AI Training</h2>
            <button class="button button-primary" id="innovopedia-admin-train-ai">Train AI on Website Content</button>
            <div id="innovopedia-admin-train-ai-result"></div>
        </section>
    </div>
    <?php
}

// AJAX: Download CSV proxy
add_action('wp_ajax_innovopedia_brief_export_csv', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $options = get_option('innovopedia_brief_settings', []);
    $base = isset($options['api_base_url']) ? $options['api_base_url'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';

    $endpoint = $type === 'questions' ? '/admin-questions' : '/admin-feedback';
    $url = rtrim($base, '/').$endpoint;
    $args = [
        'headers' => [
            'X-API-Key' => $api_key
        ]
    ];
    $csv = wp_remote_get($url, $args);
    if (is_wp_error($csv)) wp_die('API error: ' . $csv->get_error_message());
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="innovopedia_brief_'.$type.'.csv"');
    echo wp_remote_retrieve_body($csv);
    exit;
});

// AJAX: Trend analysis trigger
add_action('wp_ajax_innovopedia_brief_analyze_trend', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $options = get_option('innovopedia_brief_settings', []);
    $api_base_url = isset($options['api_base_url']) ? $options['api_base_url'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    
    if (empty($api_base_url)) {
        wp_send_json_error(['message' => 'API Base URL not set in plugin settings.']);
    }

    $url = rtrim($api_base_url, '/').'/analyze-trend';
    $args = [
        'headers' => [
            'X-API-Key' => $api_key
        ]
    ];
    $res = wp_remote_get($url, $args);
    if (is_wp_error($res)) wp_send_json_error(['message' => 'API error: ' . $res->get_error_message()]);
    $body = wp_remote_retrieve_body($res);
    $http_code = wp_remote_retrieve_response_code($res);
    if ($http_code !== 200) {
        wp_send_json_error(['message' => 'API returned error: ' . $http_code . ' - ' . $body]);
    }
    wp_send_json_success(json_decode($body));
});

// AJAX: Analytics fetch
add_action('wp_ajax_innovopedia_brief_analytics', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_die();
    $options = get_option('innovopedia_brief_settings', []);
    $api_base_url = isset($options['api_base_url']) ? $options['api_base_url'] : '';
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';

    if (empty($api_base_url)) {
        wp_send_json_error(['message' => 'API Base URL not set in plugin settings.']);
    }

    $url = rtrim($api_base_url, '/').'/admin-stats';
    $args = [
        'headers' => [
            'X-API-Key' => $api_key
        ]
    ];
    $res = wp_remote_get($url, $args);
    if (is_wp_error($res)) wp_send_json_error(['message' => 'API error: ' . $res->get_error_message()]);
    $body = wp_remote_retrieve_body($res);
    $http_code = wp_remote_retrieve_response_code($res);
    if ($http_code !== 200) {
        wp_send_json_error(['message' => 'API returned error: ' . $http_code . ' - ' . $body]);
    }
    wp_send_json_success(json_decode($body));
});
