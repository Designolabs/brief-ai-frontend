<?php
// Enqueue frontend JS/CSS only if shortcode is present
function innovopedia_brief_enqueue_assets() {
    if ( is_singular() && has_shortcode( get_post()->post_content, 'innovopedia-brief' ) ) {
        wp_enqueue_style( 'innovopedia-brief-style', INNOVOPEDIA_BRIEF_URL . 'assets/brief.css', [], INNOVOPEDIA_BRIEF_VERSION );
        wp_enqueue_script( 'innovopedia-brief-script', INNOVOPEDIA_BRIEF_URL . 'assets/brief.js', [ 'jquery' ], INNOVOPEDIA_BRIEF_VERSION, true );
        wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0' );
        // Pass settings (API base URL, etc.) to JS
        $options = get_option('innovopedia_brief_settings', []);
        wp_localize_script( 'innovopedia-brief-script', 'InnovopediaBrief', [
            'apiBaseUrl' => isset($options['api_base_url']) ? $options['api_base_url'] : '',
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ] );
    }
}
add_action( 'wp_enqueue_scripts', 'innovopedia_brief_enqueue_assets' );
