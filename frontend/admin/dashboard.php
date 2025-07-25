<?php
// Admin Dashboard Page
function innovopedia_brief_admin_page() {
    ?>
    <div class="wrap innovopedia-admin-wrap">
        <h1><span class="dashicons dashicons-megaphone"></span> Innovopedia Brief Dashboard</h1>

        <div class="innovopedia-admin-grid">
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-chart-bar"></span> Analytics</h2>
                <div id="innovopedia-admin-analytics" class="data-container"><em>Loading...</em></div>
            </section>
            
            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-heart"></span> API Health</h2>
                <div id="innovopedia-brief-admin-api-health" class="data-container"><em>Checking...</em></div>
            </section>

            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-admin-tools"></span> AI Training</h2>
                <p>Sync your selected content with the AI.</p>
                <button class="button button-primary" id="innovopedia-admin-train-ai">
                    <span class="dashicons dashicons-update"></span> Train AI
                </button>
                <div id="innovopedia-admin-train-ai-result" class="result-message" style="display:none;"></div>
            </section>

            <section class="innovopedia-admin-section">
                <h2><span class="dashicons dashicons-backup"></span> Trend Schedule</h2>
                <p>Set how often trend analysis is run.</p>
                <form id="innovopedia-trend-schedule-form">
                    <label><input type="radio" name="trend_schedule" value="daily"> Daily</label>
                    <label><input type="radio" name="trend_schedule" value="weekly"> Weekly</label>
                    <label><input type="radio" name="trend_schedule" value="monthly"> Monthly</label>
                    <button type="submit" class="button button-secondary">Save Schedule</button>
                    <span id="innovopedia-trend-schedule-status"></span>
                </form>
            </section>
            
            <section class="innovopedia-admin-section wide-section">
                <h2><span class="dashicons dashicons-email-alt"></span> Email Summary</h2>
                <p>Send the latest cached briefing to a list of recipients.</p>
                <form id="innovopedia-email-summary-form">
                    <textarea name="recipients" placeholder="Enter comma-separated email addresses..."></textarea>
                    <button type="submit" class="button button-primary">Send Email</button>
                </form>
                <div id="innovopedia-email-summary-result" class="result-message" style="display:none;"></div>
            </section>

            <section class="innovopedia-admin-section wide-section">
                <h2><span class="dashicons dashicons-admin-comments"></span> Interactive Content Moderation</h2>
                <button class="button" id="innovopedia-admin-refresh-moderation"><span class="dashicons dashicons-update"></span> Refresh</button>
                <div id="innovopedia-brief-admin-moderation" class="data-list-interactive"><em>Loading...</em></div>
            </section>
            
            <section class="innovopedia-admin-section wide-section">
                <h2><span class="dashicons dashicons-format-chat"></span> User Feedback</h2>
                <button class="button" id="innovopedia-admin-refresh-feedback"><span class="dashicons dashicons-update"></span> Refresh</button>
                <div id="innovopedia-brief-admin-feedback-list" class="data-list"><em>Loading...</em></div>
            </section>
        </div>
    </div>
    <?php
}

// Enqueue admin scripts & styles
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_innovopedia-brief') return;
    
    wp_enqueue_style('innovopedia-brief-admin-style', INNOVOPEDIA_BRIEF_URL.'assets/admin.css', [], INNOVOPEDIA_BRIEF_VERSION);
    wp_enqueue_script('innovopedia-brief-admin', INNOVOPEDIA_BRIEF_URL.'assets/admin.js', ['jquery'], INNOVOPEDIA_BRIEF_VERSION, true);
    
    $options = get_option('innovopedia_brief_settings', []);
    wp_localize_script('innovopedia-brief-admin', 'InnovopediaBriefAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('innovopedia_brief_admin'),
        'api'     => [
            'baseUrl' => $options['api_base_url'] ?? '',
            'key'     => $options['api_key'] ?? ''
        ]
    ]);
});
