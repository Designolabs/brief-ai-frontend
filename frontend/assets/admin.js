jQuery(document).ready(function($) {
    const { ajaxUrl, nonce, apiBaseUrl, apiKey } = InnovopediaBriefAdmin;

    const showSpinner = ($button) => $button.prop('disabled', true).find('.dashicons').addClass('dashicons-update spin');
    const hideSpinner = ($button) => $button.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spin');
    const showResult = ($el, message, isSuccess = true) => {
        $el.text(message).removeClass('error success').addClass(isSuccess ? 'success' : 'error').show();
        setTimeout(() => $el.fadeOut(), 5000);
    };

    // Generic function to fetch data for dashboard sections
    const fetchData = (action, targetSelector, renderer) => {
        const $target = $(targetSelector);
        $target.html('<em><span class="spinner-border spinner-border-sm"></span> Loading...</em>');
        $.post(ajaxUrl, { action, nonce, _ajax_nonce: nonce })
            .done(response => {
                if (response.success) {
                    renderer(response.data);
                } else {
                    $target.html(`<em style="color:red;">${response.data.message || 'Error'}</em>`);
                }
            })
            .fail(() => $target.html('<em style="color:red;">Request failed.</em>'));
    };

    // Renderers for different data types
    const renderAnalytics = (data) => {
        const $target = $('#innovopedia-admin-analytics');
        if (!data) return $target.html('<em>No data.</em>');
        let html = '';
        for (const [key, value] of Object.entries(data)) {
            let displayValue = value;
            if (typeof value === 'object' && value !== null) {
                displayValue = `<ul>${Object.entries(value).map(([k,v]) => `<li>${k}: ${v}</li>`).join('')}</ul>`;
            }
            html += `<p><strong>${key.replace(/_/g, ' ')}:</strong> ${displayValue}</p>`;
        }
        $target.html(html);
    };

    const renderListData = (selector, data) => {
        const $target = $(selector);
        if (!data || data.length === 0) return $target.html('<em>No data available.</em>');
        const html = data.map(item => `
            <div class="data-list-item">
                <p>${item.content || item.text || 'No content'}</p>
                <div class="meta">
                    ${item.is_moderated !== undefined ? `<span class="status-${item.is_moderated ? 'moderated' : 'pending'}">${item.is_moderated ? 'Moderated' : 'Pending'}</span>` : ''}
                    ${item.timestamp ? `<span>${new Date(item.timestamp).toLocaleString()}</span>` : ''}
                </div>
            </div>
        `).join('');
        $target.html(html);
    };

    // API Health Check
    const checkApiHealth = () => {
        const $healthDiv = $('#innovopedia-brief-admin-api-health');
        if (!apiBaseUrl) return $healthDiv.html('<p class="api-health-error">API URL not set.</p>');
        
        $.ajax({
            url: `${apiBaseUrl}/health`,
            headers: { 'X-API-Key': apiKey },
            method: 'GET'
        })
        .done(() => $healthDiv.html('<p class="api-health-ok">API is healthy.</p>'))
        .fail(() => $healthDiv.html('<p class="api-health-error">API is not reachable.</p>'));
    };

    // Trend Schedule Management
    const initTrendSchedule = () => {
        $.post(ajaxUrl, { action: 'innovopedia_brief_get_trend_schedule', nonce, _ajax_nonce: nonce })
            .done(response => {
                if (response.success) {
                    $(`input[name="trend_schedule"][value="${response.data.schedule}"]`).prop('checked', true);
                }
            });
    };
    $('#innovopedia-trend-schedule-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const schedule = $form.find('input[name="trend_schedule"]:checked').val();
        const $status = $('#innovopedia-trend-schedule-status');
        showSpinner($form.find('button'));

        $.post(ajaxUrl, { action: 'innovopedia_brief_set_trend_schedule', schedule, nonce, _ajax_nonce: nonce })
            .done(response => showResult($status, response.data.message, response.success))
            .fail(() => showResult($status, 'Request failed.', false))
            .always(() => hideSpinner($form.find('button')));
    });

    // Button-triggered actions
    const setupButtonAction = (buttonId, action, resultSelector) => {
        $(buttonId).on('click', function() {
            const $button = $(this);
            const $result = $(resultSelector);
            showSpinner($button);

            $.post(ajaxUrl, { action, nonce, _ajax_nonce: nonce })
                .done(response => showResult($result, response.data.message || JSON.stringify(response.data), response.success))
                .fail(() => showResult($result, 'Request failed.', false))
                .always(() => hideSpinner($button));
        });
    };

    // Initial data loads
    checkApiHealth();
    fetchData('innovopedia_brief_analytics', '#innovopedia-admin-analytics', renderAnalytics);
    fetchData('innovopedia_brief_get_moderation', '#innovopedia-brief-admin-moderation', data => renderListData('#innovopedia-brief-admin-moderation', data));
    fetchData('innovopedia_brief_get_feedback', '#innovopedia-brief-admin-feedback-list', data => renderListData('#innovopedia-brief-admin-feedback-list', data));
    fetchData('innovopedia_brief_get_questions', '#innovopedia-brief-admin-questions-list', data => renderListData('#innovopedia-brief-admin-questions-list', data));
    initTrendSchedule();

    // Refresh buttons
    $('#innovopedia-admin-refresh-moderation').on('click', () => fetchData('innovopedia_brief_get_moderation', '#innovopedia-brief-admin-moderation', data => renderListData('#innovopedia-brief-admin-moderation', data)));
    $('#innovopedia-admin-refresh-feedback').on('click', () => fetchData('innovopedia_brief_get_feedback', '#innovopedia-brief-admin-feedback-list', data => renderListData('#innovopedia-brief-admin-feedback-list', data)));
    $('#innovopedia-admin-refresh-questions').on('click', () => fetchData('innovopedia_brief_get_questions', '#innovopedia-brief-admin-questions-list', data => renderListData('#innovopedia-brief-admin-questions-list', data)));
    
    // Setup analyze and train buttons
    setupButtonAction('#innovopedia-admin-analyze', 'innovopedia_brief_analyze_trend', '#innovopedia-admin-analyze-result');
    setupButtonAction('#innovopedia-admin-train-ai', 'innovopedia_brief_train_ai', '#innovopedia-admin-train-ai-result');

    // Export CSV
    $('.innovopedia-admin-export').on('click', function() {
        const type = $(this).data('type');
        window.location.href = `${ajaxUrl}?action=innovopedia_brief_export_csv&type=${type}&nonce=${nonce}&_ajax_nonce=${nonce}`;
    });
});
