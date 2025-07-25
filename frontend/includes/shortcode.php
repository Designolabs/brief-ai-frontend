<?php
// Register [innovopedia-brief] shortcode
function innovopedia_brief_shortcode( $atts ) {
    ob_start();
    ?>
    <div id="innovopedia-brief-shortcode-root"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'innovopedia-brief', 'innovopedia_brief_shortcode' );
