<?php
// Register plugin settings
add_action( 'admin_init', 'innovopedia_brief_register_settings' );
function innovopedia_brief_register_settings() {
    register_setting( 'innovopedia_brief_settings_group', 'innovopedia_brief_settings' );
}

// Settings Page HTML
function innovopedia_brief_settings_page() {
    ?>
    <div class="wrap">
        <h1>Innovopedia Brief Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'innovopedia_brief_settings_group' );
            $options = get_option('innovopedia_brief_settings', []);
            
            // Helper to get option value or default
            $opt = fn($key, $default) => esc_attr($options[$key] ?? $default);
            ?>
            
            <h2>API & Caching</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="api_base_url">API Base URL</label></th>
                    <td><input type="url" id="api_base_url" name="innovopedia_brief_settings[api_base_url]" value="<?php echo $opt('api_base_url', ''); ?>" class="regular-text" placeholder="https://api.innovopedia.com"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="api_key">API Key</label></th>
                    <td><input type="password" id="api_key" name="innovopedia_brief_settings[api_key]" value="<?php echo $opt('api_key', ''); ?>" class="regular-text" placeholder="Enter your secret API key"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cache_duration">Cache Duration (Minutes)</label></th>
                    <td><input type="number" id="cache_duration" name="innovopedia_brief_settings[cache_duration]" value="<?php echo $opt('cache_duration', 5); ?>" min="1" max="1440" class="small-text">
                    <p class="description">How long to cache the briefing data to reduce API calls. Default is 5 minutes.</p></td>
                </tr>
            </table>

            <h2>Appearance Customization</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="primary_color">Primary Color</label></th>
                    <td><input type="color" id="primary_color" name="innovopedia_brief_settings[primary_color]" value="<?php echo $opt('primary_color', '#00bcd4'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="secondary_color">Secondary Color</label></th>
                    <td><input type="color" id="secondary_color" name="innovopedia_brief_settings[secondary_color]" value="<?php echo $opt('secondary_color', '#4CAF50'); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="border_radius">Border Radius</label></th>
                    <td><input type="text" id="border_radius" name="innovopedia_brief_settings[border_radius]" value="<?php echo $opt('border_radius', '15px'); ?>" placeholder="e.g., 10px or 50%"></td>
                </tr>
            </table>

            <h2>AI Content Synchronization</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Content Sources</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span>Content Sources</span></legend>
                            <?php
                            $post_types = get_post_types(['public' => true], 'objects');
                            $saved_sources = $options['content_sources'] ?? ['post', 'page'];
                            foreach ($post_types as $post_type) {
                                if ($post_type->name === 'attachment') continue;
                                $checked = in_array($post_type->name, $saved_sources) ? 'checked' : '';
                                echo '<label><input type="checkbox" name="innovopedia_brief_settings[content_sources][]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ' . esc_html($post_type->labels->singular_name) . '</label><br>';
                            }
                            ?>
                        </fieldset>
                        <p class="description">Select which content types the AI should be trained on.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
