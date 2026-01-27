<?php

function inner_planet_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 'planet-container',
    ), $atts);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($atts['id']); ?>" style="width: 100%; height: 400px; position: relative;"></div>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.innerPlanetModule !== 'undefined') {
                window.innerPlanetModule.createInnerPlanet('<?php echo esc_js($atts['id']); ?>');
            } else {
                console.error('innerPlanetModule is not defined. Ensure the script is enqueued.');
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('inner_planet', 'inner_planet_shortcode');