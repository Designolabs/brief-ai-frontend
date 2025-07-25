jQuery(document).ready(function($) {
    const { ajaxUrl, nonce, api } = InnovopediaBriefAdmin;

    // --- Helpers ---
    const showSpinner = ($btn) => $btn.find('.dashicons').addClass('spin');
    const hideSpinner = ($btn) => $btn.find('.dashicons').removeClass('spin');
    const showResult = ($el, message, isSuccess = true) => {
        $el.text(message).removeClass('error success').addClass(isSuccess ? 'success' : 'error').slideDown();
        setTimeout(() => $el.slideUp(), 5000);
    };

    // --- Generic AJAX Handlers ---
    const proxyAjaxGet = (action, params = {}) => $.post(ajaxUrl, { action, nonce, _ajax_nonce: nonce, ...params });
    const proxyAjaxPost = (action, data) => $.post(ajaxUrl, { action, nonce, _ajax_nonce: nonce, ...data });

    // --- Renderers ---
    const renderAnalytics = (data) => {
        if (!data) return $('#innovopedia-admin-analytics').html('<em>No data.</em>');
        const html = Object.entries(data).map(([key, value]) => `<p><strong>${key.replace(/_/g, ' ')}:</strong> ${value}</p>`).join('');
        $('#innovopedia-admin-analytics').html(html || '<em>No data.</em>');
    };

    const renderFeedbackList = (data) => {
        if (!data || !data.length) return $('#innovopedia-brief-admin-feedback-list').html('<em>No feedback.</em>');
        const html = data.map(item => `<div class="data-list-item"><p>${item.content}</p><div class="meta">${new Date(item.timestamp).toLocaleString()}</div></div>`).join('');
        $('#innovopedia-brief-admin-feedback-list').html(html);
    };

    const renderModerationList = (data) => {
        if (!data || !data.length) return $('#innovopedia-brief-admin-moderation').html('<em>Nothing to moderate.</em>');
        const html = data.map(item => `
            <div class="moderation-item" data-id="${item.id}">
                <p>${item.content}</p>
                <div class="moderation-actions">
                    <button class="button button-small button-primary approve">Approve</button>
                    <button class="button button-small button-danger reject">Reject</button>
                </div>
            </div>`).join('');
        $('#innovopedia-brief-admin-moderation').html(html);
    };

    // --- Initializers ---
    const init = () => {
        // Load initial data
        checkApiHealth();
        proxyAjaxGet('innovopedia_brief_analytics').done(res => res.success && renderAnalytics(res.data));
        proxyAjaxGet('innovopedia_brief_get_feedback').done(res => res.success && renderFeedbackList(res.data));
        proxyAjaxGet('innovopedia_brief_get_moderation').done(res => res.success && renderModerationList(res.data));
        proxyAjaxGet('innovopedia_brief_get_trend_schedule').done(res => res.success && $(`input[name="trend_schedule"][value="${res.data.schedule}"]`).prop('checked', true));
        
        // Setup event handlers
        setupEventHandlers();
    };

    const checkApiHealth = () => {
        if (!api.baseUrl) return $('#innovopedia-brief-admin-api-health').html('<p class="api-health-error">API URL Not Set</p>');
        $.ajax({ url: `${api.baseUrl}/health`, headers: { 'X-API-Key': api.key } })
            .done(() => $('#innovopedia-brief-admin-api-health').html('<p class="api-health-ok">API Healthy</p>'))
            .fail(() => $('#innovopedia-brief-admin-api-health').html('<p class="api-health-error">API Unreachable</p>'));
    };

    // --- Event Handlers ---
    const setupEventHandlers = () => {
        // Refresh buttons
        $('#innovopedia-admin-refresh-feedback').on('click', () => proxyAjaxGet('innovopedia_brief_get_feedback').done(res => res.success && renderFeedbackList(res.data)));
        $('#innovopedia-admin-refresh-moderation').on('click', () => proxyAjaxGet('innovopedia_brief_get_moderation').done(res => res.success && renderModerationList(res.data)));

        // AI Training
        $('#innovopedia-admin-train-ai').on('click', function() {
            const $btn = $(this);
            const $result = $('#innovopedia-admin-train-ai-result');
            showSpinner($btn);
            proxyAjaxPost('innovopedia_brief_train_ai').always(() => hideSpinner($btn))
                .done(res => showResult($result, res.data.message, res.success))
                .fail(() => showResult($result, 'Request failed.', false));
        });

        // Trend Schedule
        $('#innovopedia-trend-schedule-form').on('submit', function(e) {
            e.preventDefault();
            const schedule = $(this).find('input:checked').val();
            proxyAjaxPost('innovopedia_brief_set_trend_schedule', { schedule })
                .done(res => showResult($('#innovopedia-trend-schedule-status'), res.data.message, res.success));
        });

        // Email Summary
        $('#innovopedia-email-summary-form').on('submit', function(e) {
            e.preventDefault();
            const recipients = $(this).find('textarea').val();
            const $result = $('#innovopedia-email-summary-result');
            if (!recipients) return showResult($result, 'Recipients cannot be empty.', false);
            proxyAjaxPost('innovopedia_brief_send_email_summary', { recipients })
                .done(res => showResult($result, res.data.message, res.success))
                .fail(err => showResult($result, err.responseJSON?.data?.message || 'Failed to send.', false));
        });

        // Interactive Moderation
        $('#innovopedia-brief-admin-moderation').on('click', '.approve, .reject', function() {
            const $btn = $(this);
            const $item = $btn.closest('.moderation-item');
            const itemId = $item.data('id');
            const status = $btn.hasClass('approve') ? 'approved' : 'rejected';

            proxyAjaxPost('innovopedia_brief_moderate_content', { item_id: itemId, status })
                .done(res => {
                    if(res.success) $item.addClass(status).find('.moderation-actions').remove();
                });
        });
    };

    init();
});
