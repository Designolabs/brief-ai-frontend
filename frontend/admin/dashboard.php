<?php
// Admin Dashboard Page
function innovopedia_brief_admin_page() {
    ?>
    <div class="wrap innovopedia-admin-wrap">
        <h1>Innovopedia Brief Dashboard</h1>

        <div class="innovopedia-admin-grid">
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-chart-bar"></span> Analytics</h2>
                <div id="innovopedia-admin-analytics"><em>Loading...</em></div>
            </section>
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-heart"></span> API Health</h2>
                <div id="innovopedia-brief-admin-api-health"><em>Checking...</em></div>
            </section>
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-admin-tools"></span> AI Training</h2>
                <button class="button button-primary" id="innovopedia-admin-train-ai">Train AI on Website Content</button>
                <div id="innovopedia-admin-train-ai-result"></div>
            </section>
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-backup"></span> Trend Report Scheduling</h2>
                <form id="innovopedia-trend-schedule-form">
                    <label><input type="radio" name="trend_schedule" value="daily"> Daily</label>
                    <label><input type="radio" name="trend_schedule" value="weekly"> Weekly</label>
                    <label><input type="radio" name="trend_schedule" value="monthly"> Monthly</label>
                    <button type="submit" class="button button-primary">Save Schedule</button>
                    <span id="innovopedia-trend-schedule-status"></span>
                </form>
            </section>
            <section class="innovopedia-admin-section wide-section">
                <h2><span class="dashicons dashicons-admin-comments"></span> Content Moderation</h2>
                <button class="button" id="innovopedia-admin-refresh-moderation"><span class="dashicons dashicons-update"></span> Refresh</button>
                <div id="innovopedia-brief-admin-moderation" class="data-list"><em>Loading moderation status...</em></div>
            </section>
            <section class="innovopedia-admin-section wide-section">
                <h2><span class="dashicons dashicons-format-chat"></span> User Feedback</h2>
                <button class="button" id="innovopedia-admin-refresh-feedback"><span class="dashicons dashicons-update"></span> Refresh</button>
                <div id="innovopedia-brief-admin-feedback-list" class="data-list"><em>Loading feedback...</em></div>
            </section>
            <section class="innovopedia-admin-section wide-section">
                <h2><span class="dashicons dashicons-editor-help"></span> User Questions</h2>
                <button class="button" id="innovopedia-admin-refresh-questions"><span class="dashicons dashicons-update"></span> Refresh</button>
                <div id="innovopedia-brief-admin-questions-list" class="data-list"><em>Loading questions...</em></div>
            </section>
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-download"></span> Export Data</h2>
                <button class="button innovopedia-admin-export" data-type="feedback">Download Feedback CSV</button>
                <button class="button innovopedia-admin-export" data-type="questions">Download Questions CSV</button>
            </section>
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-chart-line"></span> Manual Trend Analysis</h2>
                <button class="button button-primary" id="innovopedia-admin-analyze">Run Trend Analysis Now</button>
                <div id="innovopedia-admin-analyze-result"></div>
            </section>
        </div>
    </div>
    <?php
}

// Enqueue admin JS for AJAX actions
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_innovopedia-brief') return;
    
    wp_enqueue_script('innovopedia-brief-admin', INNOVOPEDIA_BRIEF_URL.'assets/admin.js', ['jquery'], INNOVOPEDIA_BRIEF_VERSION, true);
    $options = get_option('innovopedia_brief_settings', []);
    wp_localize_script('innovopedia-brief-admin', 'InnovopediaBriefAdmin', [
        'apiBaseUrl' => $options['api_base_url'] ?? '',
        'apiKey'     => $options['api_key'] ?? '',
        'nonce'      => wp_create_nonce('innovopedia_brief_admin'),
        'ajaxUrl'    => admin_url('admin-ajax.php'),
    ]);
    wp_enqueue_style('innovopedia-brief-admin-style', INNOVOPEDIA_BRIEF_URL.'assets/admin.css', [], INNOVOPEDIA_BRIEF_VERSION);
});

// AJAX: Download CSV proxy
add_action('wp_ajax_innovopedia_brief_export_csv', function() {
    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_die('Permission denied.');

    $type = sanitize_text_field($_GET['type']);
    $options = get_option('innovopedia_brief_settings', []);
    $base_url = $options['api_base_url'] ?? '';
    $api_key = $options['api_key'] ?? '';

    if (empty($base_url)) wp_die('API URL not configured.');

    $endpoint = $type === 'questions' ? '/admin-questions' : '/admin-feedback';
    $url = rtrim($base_url, '/') . $endpoint;
    $args = ['headers' => ['X-API-Key' => $api_key]];
    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) wp_die('API error: ' . $response->get_error_message());
    
    $body = wp_remote_retrieve_body($response);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="innovopedia_' . $type . '.csv"');
    echo $body;
    exit;
});

// AJAX: Proxied GET request for dashboard data
function innovopedia_brief_ajax_proxy_handler() {
    $endpoint_map = [
        'innovopedia_brief_analytics' => '/admin-stats',
        'innovopedia_brief_analyze_trend' => '/analyze-trend',
        // Add other simple GET proxies here if needed
    ];
    $action = current_action();
    $endpoint = $endpoint_map[$action] ?? null;

    if (!$endpoint) return;

    check_ajax_referer('innovopedia_brief_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

    $options = get_option('innovopedia_brief_settings', []);
    $api_base_url = $options['api_base_url'] ?? '';
    $api_key = $options['api_key'] ?? '';

    if (empty($api_base_url)) wp_send_json_error(['message' => 'API Base URL not set.']);

    $url = rtrim($api_base_url, '/') . $endpoint;
    $args = ['headers' => ['X-API-Key' => $api_key]];
    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    } else {
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code >= 200 && $http_code < 300) {
            wp_send_json_success(json_decode($body));
        } else {
            wp_send_json_error(['message' => "API Error ($http_code): $body"]);
        }
    }
}
add_action('wp_ajax_innovopedia_brief_analytics', 'innovopedia_brief_ajax_proxy_handler');
add_action('wp_ajax_innovopedia_brief_analyze_trend', 'innovopedia_brief_ajax_proxy_handler');
