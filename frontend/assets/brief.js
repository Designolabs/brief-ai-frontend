/*
 * Innovopedia Brief - Frontend JS
 * Handles popup rendering, API calls, interactivity, accessibility
 */
(function($){
    // Util: Escape HTML
    function escapeHtml(text) {
        return $('<div/>').text(text).html();
    }

    // State
    let currentTopic = null;
    let isLoading = false;
    let popupOpen = false;

    // Insert button and popup root
    function renderBriefButton(breaking) {
        if ($('#innovopedia-brief-btn').length) return;
        const btn = $(
            '<button id="innovopedia-brief-btn" class="innovopedia-pill-btn" aria-haspopup="dialog" aria-controls="innovopedia-brief-popup" tabindex="0">' +
            '<i class="fa fa-newspaper"></i> <span>Your Briefing</span>' +
            (breaking ? '<span class="innovopedia-breaking-badge" aria-label="Breaking News">Breaking</span>' : '') +
            '</button>'
        );
        btn.on('click', openPopup);
        $('#innovopedia-brief-shortcode-root').append(btn);
    }

    // Bookmarks
    function getBookmarks() {
        try { return JSON.parse(localStorage.getItem('innovopedia_bookmarks')||'[]'); } catch(e){ return []; }
    }
    function setBookmarks(arr) {
        localStorage.setItem('innovopedia_bookmarks', JSON.stringify(arr));
    }
    function isBookmarked(story) {
        return getBookmarks().some(s => s.id === story.id);
    }
    function toggleBookmark(story) {
        let bookmarks = getBookmarks();
        const idx = bookmarks.findIndex(s => s.id === story.id);
        if(idx > -1) bookmarks.splice(idx, 1);
        else bookmarks.push(story);
        setBookmarks(bookmarks);
    }


    // Create and append popup/overlay to body
    function openPopup(topic) {
        if (popupOpen) return;
        popupOpen = true;
        currentTopic = topic || null;
        const overlay = $('<div id="innovopedia-brief-overlay" tabindex="-1" aria-hidden="false"></div>');
        // Determine theme
        theme = getTheme();
        // Get personalization
        let greeting = localStorage.getItem('innovopedia_greeting') || '';
        let avatar = localStorage.getItem('innovopedia_avatar') || '';
        // Drag handle for mobile
        const dragHandle = '<div class="innovopedia-drag-handle" tabindex="-1" aria-hidden="true"></div>';
        // Avatar selector options
        const avatars = [
            '',
            '👩‍💻','🧑‍🚀','🦸‍♂️','🧙‍♀️','🧑‍🎨','🐱','🐶','🦉','🦄'
        ];
        const avatarOptions = avatars.map(a => `<button class="innovopedia-avatar-choice" data-avatar="${a}">${a||'<i class=\'fa fa-user\'></i>'}</button>`).join(' ');
        const popup = $(
            '<div id="innovopedia-brief-popup" class="innovopedia-popup'+(theme==='dark'?' innovopedia-dark':'')+'" role="dialog" aria-modal="true" aria-labelledby="innovopedia-brief-header">' +
                dragHandle +
                '<div class="innovopedia-popup-header">' +
                    (avatar ? `<span class="innovopedia-avatar" aria-label="Your avatar">${avatar}</span>` : '') +
                    `<span id="innovopedia-brief-header">${greeting ? escapeHtml(greeting) : 'Your Briefing'}</span>` +
                    '<button class="innovopedia-theme-btn" aria-label="Toggle dark mode" title="Toggle dark mode"><i class="fa fa-moon"></i></button>' +
                    '<button class="innovopedia-customize-btn" aria-label="Customize" title="Customize"><i class="fa fa-cog"></i></button>' +
                    '<button class="innovopedia-close-btn" aria-label="Close popup">&times;</button>' +
                '</div>' +
                '<div class="innovopedia-popup-content" aria-live="polite">' +
                    renderSkeletons(3) +
                '</div>' +
                '<div class="innovopedia-customize-panel" style="display:none;">'+
                    '<div><strong>Theme:</strong> '+
                        '<button class="innovopedia-theme-choice" data-theme="light">Light</button> '+
                        '<button class="innovopedia-theme-choice" data-theme="dark">Dark</button> '+
                        '<button class="innovopedia-theme-choice" data-theme="system">System</button>'+
                    '</div>'+ 
                    '<div style="margin-top:1em;"><strong>Greeting:</strong> <input type="text" class="innovopedia-greeting-input" value="'+escapeHtml(greeting)+'" maxlength="32" placeholder="e.g. Good Morning!" aria-label="Your greeting" /></div>'+
                    '<div style="margin-top:1em;"><strong>Avatar:</strong> <div class="innovopedia-avatar-choices">'+avatarOptions+'</div></div>'+ 
                '</div>'+
            '</div>'
        );
        overlay.append(popup);
        $('body').append(overlay);
        setTimeout(function(){ overlay.addClass('active'); }, 10);
        // Close logic
        overlay.on('click', function(e){
            if(e.target === this) closePopup();
        });
        popup.find('.innovopedia-close-btn').on('click', closePopup);
        $(document).on('keydown.innovopedia', function(e){
            if(e.key === 'Escape') closePopup();
        });
        // Swipe to close on mobile
        let swipeStartX;
        overlay.on('touchstart', function(e){
            swipeStartX = e.touches[0].clientX;
        });
        overlay.on('touchend', function(e){
            const swipeEndX = e.changedTouches[0].clientX;
            if (swipeEndX - swipeStartX > 50) closePopup();
        });
        // Theme toggle
        popup.find('.innovopedia-theme-btn').on('click', function(){
            theme = theme === 'dark' ? 'light' : 'dark';
            setTheme(theme);
            popup.toggleClass('innovopedia-dark', theme === 'dark');
            announceThemeChange(theme);
        });
        // Customize panel
        popup.find('.innovopedia-customize-btn').on('click', function(){
            popup.find('.innovopedia-customize-panel').toggle();
        });
        // Greeting input
        popup.find('.innovopedia-greeting-input').on('input', function(){
            let val = $(this).val().trim();
            localStorage.setItem('innovopedia_greeting', val);
            popup.find('#innovopedia-brief-header').text(val ? val : 'Your Briefing');
            showToast('Greeting updated', 'info');
        });
        // Avatar selection
        popup.find('.innovopedia-avatar-choice').on('click', function(){
            let val = $(this).data('avatar');
            localStorage.setItem('innovopedia_avatar', val);
            popup.find('.innovopedia-avatar').remove();
            if(val) {
                popup.find('.innovopedia-popup-header').prepend(`<span class="innovopedia-avatar" aria-label="Your avatar">${val}</span>`);
            }
            popup.find('.innovopedia-avatar-choice').removeClass('selected');
            $(this).addClass('selected');
            showToast('Avatar updated', 'info');
        });
        // Highlight current avatar
        if(avatar) popup.find(`.innovopedia-avatar-choice[data-avatar="${avatar}"]`).addClass('selected');
        // Theme choice
        popup.find('.innovopedia-theme-choice').on('click', function(){
            const newTheme = $(this).data('theme');
            setTheme(newTheme);
            theme = newTheme;
            popup.toggleClass('innovopedia-dark', theme === 'dark');
            announceThemeChange(theme);
        });
        fetchBriefing(currentTopic);
        // Focus trap
        setTimeout(()=>{popup.find('.innovopedia-close-btn').focus();}, 100);
    }
    function closePopup() {
        const overlay = $('#innovopedia-brief-overlay');
        overlay.removeClass('active');
        setTimeout(function(){ overlay.remove(); }, 300);
        $(document).off('keydown.innovopedia');
        popupOpen = false;
    }

    // Skeletons for loading
    function renderSkeletons(count) {
        let html = '';
        for(let i=0;i<count;i++) {
            html += '<div class="innovopedia-story-card">'+
                '<div class="innovopedia-skeleton" style="width:64px;height:64px;margin-right:1em;"></div>'+
                '<div style="flex:1;display:flex;flex-direction:column;gap:0.5em;">'+
                '<div class="innovopedia-skeleton" style="width:80%;height:1.2em;"></div>'+
                '<div class="innovopedia-skeleton" style="width:95%;height:0.95em;"></div>'+
                '</div></div>';
        }
        return html;
    }

    // Toast notification
    function showToast(msg, type) {
        let toast = $('#innovopedia-toast');
        if (!toast.length) {
            toast = $('<div id="innovopedia-toast" class="innovopedia-toast" role="status" aria-live="polite"></div>');
            $('body').append(toast);
        }
        toast.text(msg).addClass('show');
        setTimeout(()=>{ toast.removeClass('show'); }, 2600);
    }

    // Fetch briefing data
    function fetchBriefing(topic) {
        isLoading = true;
        const content = $('#innovopedia-brief-popup .innovopedia-popup-content');
        content.html('<div class="innovopedia-briefing-loading">Loading...</div>');
        let url = InnovopediaBrief.apiBaseUrl + '/get-briefing-advanced';
        if (topic) url += '?focus_topic=' + encodeURIComponent(topic);
        $.getJSON(url)
        .done(function(data){
            // Check for breaking/urgent stories
            let breaking = false;
            if (Array.isArray(data.stories)) {
                breaking = data.stories.some(story => story.urgent || story.breaking);
            }
            // Update button badge
            const btn = $('#innovopedia-brief-btn');
            if (btn.length) {
                btn.find('.innovopedia-breaking-badge').remove();
                if (breaking) btn.append('<span class="innovopedia-breaking-badge" aria-label="Breaking News">Breaking</span>');
            }
            renderBriefing(data, content);
        })
        .fail(function(){
            content.html('<div class="innovopedia-briefing-error">Failed to load briefing. Please try again later.</div>');
        })
        .always(function(){ isLoading = false; });
    }

    // Update story rendering for breaking badge
    function renderBriefing(data, content) {
        let html = '';
        // Read Later section
        const bookmarks = getBookmarks();
        html += '<div class="innovopedia-readlater-section">'+
            '<button class="innovopedia-pill-btn innovopedia-readlater-toggle" aria-expanded="false"><i class="fa fa-bookmark"></i> Read Later ('+bookmarks.length+')</button>'+ 
            '<div class="innovopedia-readlater-list" style="display:none;">'+
                (bookmarks.length ? bookmarks.map(function(story, idx){
                    return '<div class="innovopedia-story-card innovopedia-readlater-card">'+
                        (story.image ? '<img src="'+escapeHtml(story.image)+'" alt="Story image" class="innovopedia-story-img"/>' : '') +
                        '<div class="innovopedia-story-content">' +
                            '<div class="innovopedia-story-title">'+escapeHtml(story.title)+'</div>' +
                            '<div class="innovopedia-story-summary">'+escapeHtml(story.summary)+'</div>' +
                        '</div>' +
                        '<button class="innovopedia-bookmark-btn innovopedia-bookmark-remove" data-id="'+escapeHtml(story.id)+'" aria-label="Remove from Read Later"><i class="fa fa-trash"></i></button>'+ 
                    '</div>';
                }).join('') : '<div class="innovopedia-empty">No saved stories yet.</div>')+
            '</div>'+ 
        '</div>';
        // Two-column layout
        html += '<div class="innovopedia-popup-columns">';
        // Left: Main
        html += '<div class="innovopedia-popup-main">';
        if(data.greeting) html += '<div class="innovopedia-greeting">' + escapeHtml(data.greeting) + '</div>';
        if(data.intro) html += '<div class="innovopedia-intro">' + escapeHtml(data.intro) + '</div>';
        if(Array.isArray(data.stories)) {
            html += '<div class="innovopedia-stories-text">';
            data.stories.forEach(function(story, idx){
                html += '<div class="innovopedia-story-block" data-story-idx="'+idx+'">';
                html += '<ul>';
                html += '<li>'+escapeHtml(story.summary)+'</li>';
                html += '</ul>';
                html += '</div>';
            });
            html += '</div>';
        }
        // Up Next topics
        if(Array.isArray(data.topics) && data.topics.length) {
            html += '<div class="innovopedia-upnext-label">Up Next:</div>';
            html += '<div class="innovopedia-topics">';
            data.topics.forEach(function(topic){
                html += '<button class="innovopedia-pill-btn innovopedia-topic-btn" data-topic="'+escapeHtml(topic)+'">'+escapeHtml(topic)+'</button>';
            });
            html += '</div>';
        }
        html += '</div>'; // .innovopedia-popup-main
        // Right: Vertical story nav
        html += '<div class="innovopedia-popup-nav">';
        if(Array.isArray(data.stories)) {
            data.stories.forEach(function(story, idx){
                html += '<div class="innovopedia-story-nav-card" data-story-idx="'+idx+'">';
                if(story.image) html += '<img src="'+escapeHtml(story.image)+'" alt="Story image" class="innovopedia-story-nav-img"/>';
                html += '<div class="innovopedia-story-nav-title">'+escapeHtml(story.title)+'</div>';
                html += '<div class="innovopedia-story-nav-num">'+String(idx+1).padStart(2,'0')+'</div>';
                html += '</div>';
            });
        }
        html += '</div>'; // .innovopedia-popup-nav
        html += '</div>'; // .innovopedia-popup-columns
        // Wire up Read Later toggle
        content.find('.innovopedia-readlater-toggle').on('click', function(){
            const $list = content.find('.innovopedia-readlater-list');
            const expanded = $list.is(':visible');
            $list.slideToggle(200);
            $(this).attr('aria-expanded', !expanded);
        });
        // Remove from Read Later
        content.find('.innovopedia-bookmark-remove').on('click', function(){
            const id = $(this).data('id');
            let bookmarks = getBookmarks().filter(s => s.id != id);
            setBookmarks(bookmarks);
            renderBriefing(data, content);
        });
        // Bookmark/unbookmark from main list
        content.find('.innovopedia-bookmark-btn:not(.innovopedia-bookmark-remove)').on('click', function(){
            const story = JSON.parse($(this).attr('data-story'));
            toggleBookmark(story);
            renderBriefing(data, content);
        });
        // Social sharing
        content.find('.innovopedia-share-btn').on('click', function(){
            const network = $(this).data('network');
            const title = $(this).data('title');
            const url = $(this).data('url');
            let shareUrl = '';
            if(network === 'twitter') {
                shareUrl = 'https://twitter.com/intent/tweet?text='+encodeURIComponent(title)+'&url='+encodeURIComponent(url);
            } else if(network === 'linkedin') {
                shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(url);
            }
            if(shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        });
        // Follow-up question toggle
        content.find('.innovopedia-question-btn').on('click', function(){
            const $form = $(this).siblings('.innovopedia-question-form');
            $form.toggle();
            if($form.is(':visible')) $form.find('input[name="question"]').focus();
        });
        // Follow-up question submit
        content.find('.innovopedia-question-form').on('submit', function(e){
            e.preventDefault();
            const val = $(this).find('input[name="question"]').val().trim();
            if(!val) return;
            const btn = $(this).find('button');
            btn.prop('disabled', true);
            $.ajax({
                url: InnovopediaBrief.apiBaseUrl + '/submit-question',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ question: val }),
                success: () => {
                    showToast('Question submitted!', 'success');
                    btn.prop('disabled', false);
                    $(this).hide();
                    $(this).find('input[name="question"]').val('');
                },
                error: () => {
                    showToast('Failed to submit question.', 'error');
                    btn.prop('disabled', false);
                }
            });
        });
    }

    // Render briefing content
    function renderBriefing(data, content) {
        let html = '';
        // Greeting/Intro
        if(data.greeting) html += '<div class="innovopedia-greeting">' + escapeHtml(data.greeting) + '</div>';
        if(data.intro) html += '<div class="innovopedia-intro">' + escapeHtml(data.intro) + '</div>';
        // Stories
        if(Array.isArray(data.stories)) {
            html += '<div class="innovopedia-stories">';
            data.stories.forEach(function(story, idx){
                html += '<div class="innovopedia-story-card">' +
                    (story.image ? '<img src="'+escapeHtml(story.image)+'" alt="Story image" class="innovopedia-story-img"/>' : '') +
                    '<div class="innovopedia-story-content">' +
                        '<div class="innovopedia-story-title">'+(idx+1)+'. '+escapeHtml(story.title)+'</div>' +
                        '<div class="innovopedia-story-summary">'+escapeHtml(story.summary)+'</div>' +
                    '</div>' +
                '</div>';
            });
            html += '</div>';
        }
        // Up Next topics
        if(Array.isArray(data.topics) && data.topics.length) {
            html += '<div class="innovopedia-upnext-label">Up Next:</div>';
            html += '<div class="innovopedia-topics">';
            data.topics.forEach(function(topic){
                html += '<button class="innovopedia-pill-btn innovopedia-topic-btn" data-topic="'+escapeHtml(topic)+'">'+escapeHtml(topic)+'</button>';
            });
            html += '</div>';
        }
        // Feedback form
        html += '<form class="innovopedia-feedback-form" autocomplete="off">'+
            '<input type="text" name="feedback" placeholder="Your feedback..." aria-label="Feedback" required />'+
            '<button type="submit" class="innovopedia-pill-btn"><i class="fa fa-paper-plane"></i> Send</button>'+ 
        '</form>';
        // Audio auto-play and story highlighting
        html += '<div class="innovopedia-audio-loading" style="display:none;"><i class="fa fa-spinner fa-spin"></i> Loading audio...</div>';
        html += '<audio id="innovopedia-brief-audio" style="display:none;" preload="auto"></audio>';
        // Audio progress bar
        html += '<div class="innovopedia-audio-progress-bar" aria-label="Audio progress" tabindex="0"><div class="innovopedia-audio-progress-inner"></div></div>';
        // Notice
        html += '<div class="innovopedia-ai-notice"><i class="fa fa-robot"></i> This briefing is AI-generated. Content may not always be accurate.</div>';
        content.html(html);
        // Topic button click
        content.find('.innovopedia-topic-btn').on('click', function(){
            const t = $(this).data('topic');
            fetchBriefing(t);
        });
        // Audio logic
        const audioEl = content.find('#innovopedia-brief-audio')[0];
        const audioLoading = content.find('.innovopedia-audio-loading');
        const progressBar = content.find('.innovopedia-audio-progress-bar');
        const progressInner = content.find('.innovopedia-audio-progress-inner');
        audioLoading.show();
        $.get(InnovopediaBrief.apiBaseUrl + '/get-briefing-audio')
            .done(function(data){
                let audioUrl = data.audio_url || data.url || data;
                if(audioUrl) {
                    audioEl.src = audioUrl;
                    audioEl.load();
                    audioEl.oncanplay = function() {
                        audioLoading.hide();
                        audioEl.play();
                    };
                    // Progress bar logic
                    audioEl.ontimeupdate = function() {
                        if (audioEl.duration) {
                            const percent = (audioEl.currentTime / audioEl.duration) * 100;
                            progressInner.css('width', percent + '%');
                        }
                    };
                    audioEl.onended = function() {
                        progressInner.css('width', '0%');
                    };
                    // Highlight logic
                    audioEl.onplay = function() {
                        const storyCards = content.find('.innovopedia-story-card');
                        if (!storyCards.length) return;
                        let idx = 0;
                        let interval = null;
                        // Remove existing highlight
                        storyCards.removeClass('reading-highlight');
                        // Approximate per-story timing
                        const duration = audioEl.duration || 30;
                        const perStory = duration / storyCards.length;
                        function highlightNext() {
                            storyCards.removeClass('reading-highlight');
                            content.find('.innovopedia-story-block, .innovopedia-story-nav-card').removeClass('reading-highlight');
                            if (idx < storyCards.length) {
                                $(storyCards[idx]).addClass('reading-highlight');
                                content.find('.innovopedia-story-block[data-story-idx="'+idx+'"], .innovopedia-story-nav-card[data-story-idx="'+idx+'"], .innovopedia-story-card[data-story-idx="'+idx+'"]').addClass('reading-highlight');
                                idx++;
                            } else {
                                clearInterval(interval);
                                storyCards.removeClass('reading-highlight');
                                content.find('.innovopedia-story-block, .innovopedia-story-nav-card').removeClass('reading-highlight');
                            }
                        }
                        highlightNext();
                        interval = setInterval(highlightNext, perStory * 1000);
                        audioEl.onended = function() {
                            clearInterval(interval);
                            storyCards.removeClass('reading-highlight');
                            content.find('.innovopedia-story-block, .innovopedia-story-nav-card').removeClass('reading-highlight');
                        };
                    };
                } else {
                    audioLoading.hide();
                }
            })
            .fail(function(){
                audioLoading.hide();
            });
        // Feedback form submit
        content.find('.innovopedia-feedback-form').on('submit', function(e){
            e.preventDefault();
            const val = $(this).find('input[name="feedback"]').val();
            if(!val) return;
            const btn = $(this).find('button');
            btn.prop('disabled', true);
            $.ajax({
                url: InnovopediaBrief.apiBaseUrl + '/submit-feedback',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ feedback: val }),
                success: function(){
                    btn.prop('disabled', false);
                    alert('Thank you for your feedback!');
                },
                error: function(){
                    btn.prop('disabled', false);
                    alert('Failed to submit feedback.');
                }
            });
        });
        // Audio button
        content.find('.innovopedia-audio-btn').on('click', function(){
            const btn = $(this);
            btn.prop('disabled', true);
            $.get(InnovopediaBrief.apiBaseUrl + '/get-briefing-audio')
                .done(function(data){
                    let audioUrl = data.audio_url || data.url || data;
                    if(audioUrl) {
                        let audio = new Audio(audioUrl);
                        audio.play();
                    } else {
                        alert('No audio available.');
                    }
                })
                .fail(function(){
                    alert('Failed to fetch audio.');
                })
                .always(function(){
                    btn.prop('disabled', false);
                });
        });
    }

    // On DOM ready
    $(function(){
        renderBriefButton();
    });
})(jQuery);
