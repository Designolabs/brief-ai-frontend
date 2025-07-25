/* Innovopedia Brief Admin Dashboard JS */
jQuery(function($){
    // Fetch analytics on load
    function loadAnalytics() {
        $.post(InnovopediaBriefAdmin.ajaxUrl, {
            action: 'innovopedia_brief_analytics',
            nonce: InnovopediaBriefAdmin.nonce
        }, function(resp){
            let html = '';
            if(resp.success) {
                let stats;
                try { stats = JSON.parse(resp.data); } catch(e){ stats = resp.data; }
                if(typeof stats === 'object') {
                    html += '<table class="widefat"><tbody>';
                    for(let k in stats) {
                        html += '<tr><th>'+k+'</th><td>'+stats[k]+'</td></tr>';
                    }
                    html += '</tbody></table>';
                } else {
                    html += '<pre>'+resp.data+'</pre>';
                }
            } else {
                html = '<span style="color:red">Failed to load analytics.</span>';
            }
            $('#innovopedia-admin-analytics').html(html);
        });
    }
    loadAnalytics();

    // Fetch moderation stories
    function fetchModerationStories() {
        const container = $('#innovopedia-brief-admin-moderation');
        container.html('<em>Loading recent stories...</em>');
        // For now, stub with empty or sample data if endpoint not available
        $.get(InnovopediaBrief.apiBaseUrl + '/get-briefing-advanced', function(data){
            const stories = (data.stories||[]).slice(0, 10);
            if(!stories.length) { container.html('<span class="error">No stories found.</span>'); return; }
            let html = '<table class="innovopedia-moderation-table"><tr><th>Title</th><th>Summary</th><th>Actions</th></tr>';
            stories.forEach(function(story, i){
                html += '<tr data-row="'+i+'">'+
                    '<td>'+escapeHtml(story.title)+'</td>'+
                    '<td>'+escapeHtml(story.summary)+'</td>'+
                    '<td>'+
                        '<button class="moderation-flag-btn" data-id="'+escapeHtml(story.id||story.title)+'" data-row="'+i+'">Flag</button> '+
                        '<button class="moderation-remove-btn" data-id="'+escapeHtml(story.id||story.title)+'" data-row="'+i+'">Remove</button>'+ 
                    '</td>'+
                '</tr>';
            });
            html += '</table>';
            container.html(html);

            // Wire up moderation actions
            container.find('.moderation-flag-btn').on('click', function(){
                const id = $(this).data('id');
                const rowIdx = $(this).data('row');
                $.ajax({
                    url: InnovopediaBrief.apiBaseUrl + '/flag-story', // stub endpoint
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: id }),
                    success: function(){
                        container.find('tr[data-row="'+rowIdx+'"]').fadeOut(400, function(){ $(this).remove(); });
                        alert('Story flagged.');
                    },
                    error: function(){
                        alert('Failed to flag story (stubbed).');
                    }
                });
            });
            container.find('.moderation-remove-btn').on('click', function(){
                const id = $(this).data('id');
                const rowIdx = $(this).data('row');
                $.ajax({
                    url: InnovopediaBrief.apiBaseUrl + '/remove-story', // stub endpoint
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: id }),
                    success: function(){
                        container.find('tr[data-row="'+rowIdx+'"]').fadeOut(400, function(){ $(this).remove(); });
                        alert('Story removed.');
                    },
                    error: function(){
                        alert('Failed to remove story (stubbed).');
                    }
                });
            });
        }).fail(function(){
            container.html('<span class="error">Failed to load stories.</span>');
        });
    }
    fetchModerationStories();

    // Trend report scheduling
    $('#innovopedia-trend-schedule-form').on('submit', function(e){
        e.preventDefault();
        var schedule = $(this).find('input[name="trend_schedule"]:checked').val();
        var status = $('#innovopedia-trend-schedule-status').text('Saving...');
        $.ajax({
            url: InnovopediaBrief.apiBaseUrl + '/set-trend-schedule', // stub endpoint
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ schedule: schedule }),
            success: function(){
                status.text('Saved! Trend reports will be sent ' + schedule + '.');
            },
            error: function(){
                status.text('Failed to save schedule (stubbed).');
            }
        });
    });

    // Export CSV
    $('.innovopedia-admin-export').on('click', function(){
        let type = $(this).data('type');
        window.location = InnovopediaBriefAdmin.ajaxUrl+'?action=innovopedia_brief_export_csv&nonce='+InnovopediaBriefAdmin.nonce+'&type='+type;
    });

    // Trend analysis
    $('#innovopedia-admin-analyze').on('click', function(){
        let btn = $(this);
        btn.prop('disabled', true).text('Analyzing...');
        $('#innovopedia-admin-analyze-result').html('');
        $.post(InnovopediaBriefAdmin.ajaxUrl, {
            action: 'innovopedia_brief_analyze_trend',
            nonce: InnovopediaBriefAdmin.nonce
        }, function(resp){
            btn.prop('disabled', false).text('Run Trend Analysis');
            if(resp.success) {
                let res;
                try { res = JSON.parse(resp.data); } catch(e){ res = resp.data; }
                $('#innovopedia-admin-analyze-result').html('<pre>'+JSON.stringify(res, null, 2)+'</pre>');
            } else {
                $('#innovopedia-admin-analyze-result').html('<span style="color:red">Failed to analyze trends.</span>');
            }
        });
    });
});
