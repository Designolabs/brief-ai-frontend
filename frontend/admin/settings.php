<?php
// Register plugin settings page
function innovopedia_brief_register_settings() {
    register_setting( 'innovopedia_brief_settings_group', 'innovopedia_brief_settings' );
    add_options_page(
        'Innovopedia Brief Settings',
        'Innovopedia Brief',
        'manage_options',
        'innovopedia-brief-settings',
        'innovopedia_brief_settings_page'
    );
}
add_action( 'admin_menu', 'innovopedia_brief_register_settings' );

function innovopedia_brief_settings_page() {
    ?>
    <div class="wrap">
        <h1>Innovopedia Brief Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'innovopedia_brief_settings_group' );
            $options = get_option('innovopedia_brief_settings');
            
            // Define default values for new settings if they don't exist
            $api_base_url = esc_attr( $options['api_base_url'] ?? '' );
            $api_key = esc_attr( $options['api_key'] ?? '' );
            $primary_color = esc_attr( $options['primary_color'] ?? '#00bcd4' );
            $secondary_color = esc_attr( $options['secondary_color'] ?? '#4CAF50' );
            $border_radius = esc_attr( $options['border_radius'] ?? '15px' );
            
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="innovopedia_brief_api_base_url">API Base URL</label></th>
                    <td>
                        <input type="url" id="innovopedia_brief_api_base_url" name="innovopedia_brief_settings[api_base_url]" value="<?php echo $api_base_url; ?>" style="width: 400px;" placeholder="e.g., https://api.innovopedia.com" />
                        <p class="description">The base URL for your Innovopedia backend API.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="innovopedia_brief_api_key">API Key</label></th>
                    <td>
                        <input type="text" id="innovopedia_brief_api_key" name="innovopedia_brief_settings[api_key]" value="<?php echo $api_key; ?>" style="width: 400px;" placeholder="Enter your API key" />
                        <p class="description">API key required for authenticating requests to your backend.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="innovopedia_brief_primary_color">Primary Color</label></th>
                    <td>
                        <input type="color" id="innovopedia_brief_primary_color" name="innovopedia_brief_settings[primary_color]" value="<?php echo $primary_color; ?>" />
                        <p class="description">Sets the main accent color for buttons and highlights.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="innovopedia_brief_secondary_color">Secondary Color</label></th>
                    <td>
                        <input type="color" id="innovopedia_brief_secondary_color" name="innovopedia_brief_settings[secondary_color]" value="<?php echo $secondary_color; ?>" />
                        <p class="description">Sets the secondary color, e.g., for feedback submit button.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="innovopedia_brief_border_radius">Border Radius (e.g., 15px)</label></th>
                    <td>
                        <input type="text" id="innovopedia_brief_border_radius" name="innovopedia_brief_settings[border_radius]" value="<?php echo $border_radius; ?>" placeholder="e.g., 10px or 50%" />
                        <p class="description">Adjusts the border-radius of popup elements. Use CSS units (e.g., 15px, 0.5em, 50%).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
