
/* frontend/assets/admin.js */

jQuery(document).ready(function($) {
    const apiBaseUrl = InnovopediaBriefAdmin.apiBaseUrl;
    const nonce = InnovopediaBriefAdmin.nonce;
    const ajaxUrl = InnovopediaBriefAdmin.ajaxUrl;

    // Function to fetch and display analytics
    const fetchAnalytics = async () => {
        const $analyticsDiv = $('#innovopedia-admin-analytics');
        $analyticsDiv.html('<em>Loading analytics...</em>');
        try {
            const response = await fetch(`${ajaxUrl}?action=innovopedia_brief_analytics&nonce=${nonce}`);
            const data = await response.json();

            if (data.success) {
                const stats = JSON.parse(data.data); // Backend sends JSON string inside data.data
                $analyticsDiv.empty();
                if (stats) {
                    $analyticsDiv.append(`<p><strong>Total Briefings Generated:</strong> ${stats.total_briefings_generated}</p>`);
                    $analyticsDiv.append(`<p><strong>Total Feedback Submitted:</strong> ${stats.total_feedback_submitted}</p>`);
                    $analyticsDiv.append(`<p><strong>Average Rating:</strong> ${stats.average_rating || 'N/A'}</p>`);
                    $analyticsDiv.append(`<p><strong>Briefings by Topic:</strong></p>`);
                    const $topicList = $('<ul></ul>');
                    for (const topic in stats.briefings_by_topic) {
                        $topicList.append(`<li>${topic}: ${stats.briefings_by_topic[topic]}</li>`);
                    }
                    $analyticsDiv.append($topicList);
                } else {
                    $analyticsDiv.html('<p>No analytics data available.</p>');
                }
            } else {
                throw new Error(data.data || 'Failed to fetch analytics');
            }
        } catch (error) {
            console.error("Error fetching analytics:", error);
            $analyticsDiv.html(`<p style="color:red;">Error loading analytics: ${error.message}</p>`);
        }
    };

    // Function to check API health
    const checkApiHealth = async () => {
        const $apiHealthDiv = $('#innovopedia-brief-admin-api-health');
        $apiHealthDiv.html('<p>Checking API health...</p>');
        try {
            const response = await fetch(`${apiBaseUrl}/health`);
            if (response.ok) {
                $apiHealthDiv.html('<p class="api-health-ok">API is healthy and reachable.</p>');
            } else {
                $apiHealthDiv.html('<p class="api-health-error">API is not reachable or returned an error.</p>');
            }
        } catch (error) {
            console.error("Error checking API health:", error);
            $apiHealthDiv.html('<p class="api-health-error">API is not reachable. Check console for details.</p>');
        }
    };

    // Function to fetch and display content moderation status
    const fetchContentModeration = async () => {
        const $moderationDiv = $('#innovopedia-brief-admin-moderation');
        $moderationDiv.html('<em>Loading moderation status...</em>');
        try {
            const response = await fetch(`${apiBaseUrl}/admin-moderation`); // Assuming this endpoint exists
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const moderationData = await response.json();
            $moderationDiv.empty();
            if (moderationData && moderationData.items && moderationData.items.length > 0) {
                const $ul = $('<ul></ul>');
                moderationData.items.forEach(item => {
                    const statusClass = item.is_moderated ? 'moderated-true' : 'moderated-false';
                    const statusText = item.is_moderated ? 'Moderated' : 'Pending';
                    $ul.append(`
                        <li>
                            <span>${item.content.substring(0, 100)}...</span>
                            <span class="moderate-status ${statusClass}">${statusText}</span>
                        </li>
                    `);
                });
                $moderationDiv.append($ul);
            } else {
                $moderationDiv.html('<p>No content moderation data available.</p>');
            }
        } catch (error) {
            console.error("Error fetching moderation data:", error);
            $moderationDiv.html(`<p style="color:red;">Error loading moderation data: ${error.message}</p>`);
        }
    };

    // Initial loads on dashboard page load
    if ($('#innovopedia-admin-analytics').length) {
        fetchAnalytics();
        checkApiHealth();
        fetchContentModeration();
    }

    // Export CSV buttons
    $('.innovopedia-admin-export').on('click', function() {
        const type = $(this).data('type');
        window.location.href = `${ajaxUrl}?action=innovopedia_brief_export_csv&type=${type}&nonce=${nonce}`;
    });

    // Trend Analysis Button
    $('#innovopedia-admin-analyze').on('click', async function() {
        const $button = $(this);
        const $resultDiv = $('#innovopedia-admin-analyze-result');
        $button.prop('disabled', true).html('<span class="spinner"></span> Running...');
        $resultDiv.empty();

        try {
            const response = await fetch(`${ajaxUrl}?action=innovopedia_brief_analyze_trend&nonce=${nonce}`);
            const data = await response.json();

            if (data.success) {
                $resultDiv.html(`<p style="color: green;">Trend analysis initiated: ${data.data}</p>`);
            } else {
                throw new Error(data.data || 'Failed to initiate trend analysis');
            }
        } catch (error) {
            console.error("Error triggering trend analysis:", error);
            $resultDiv.html(`<p style="color:red;">Error: ${error.message}</p>`);
        } finally {
            $button.prop('disabled', false).html('Run Trend Analysis');
        }
    });

    // Trend Report Scheduling
    $('#innovopedia-trend-schedule-form').on('submit', async function(e) {
        e.preventDefault();
        const $form = $(this);
        const $statusSpan = $('#innovopedia-trend-schedule-status');
        const $submitButton = $form.find('button[type="submit"]');
        const scheduleType = $form.find('input[name="trend_schedule"]:checked').val();

        $submitButton.prop('disabled', true).html('<span class="spinner"></span> Saving...');
        $statusSpan.empty();

        try {
            // Assuming a new API endpoint for setting schedule
            const response = await fetch(`${apiBaseUrl}/admin-set-trend-schedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce // Pass nonce for security if backend is WordPress based, or adjust for your API auth
                },
                body: JSON.stringify({ schedule: scheduleType })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const result = await response.json();
            $statusSpan.html(`<span style="color: green;">${result.message || 'Schedule saved successfully!'}</span>`);
        } catch (error) {
            console.error("Error saving trend schedule:", error);
            $statusSpan.html(`<span style="color:red;">Error: ${error.message}</span>`);
        } finally {
            $submitButton.prop('disabled', false).html('Save Schedule');
        }
    });

    // Fetch and set initial trend schedule (if exists)
    const fetchTrendSchedule = async () => {
        try {
            const response = await fetch(`${apiBaseUrl}/admin-get-trend-schedule`); // Assuming endpoint to get current schedule
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.schedule) {
                $(\`input[name="trend_schedule"][value="${data.schedule}"]\`).prop('checked', true);
            }
        } catch (error) {
            console.error("Error fetching trend schedule:", error);
            // Optionally display an error message for schedule fetch
        }
    };
    fetchTrendSchedule();
});
