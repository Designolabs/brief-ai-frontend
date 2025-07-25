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
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Base URL</th>
                    <td><input type="text" name="innovopedia_brief_settings[api_base_url]" value="<?php echo esc_attr( $options['api_base_url'] ?? '' ); ?>" style="width: 400px;" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
