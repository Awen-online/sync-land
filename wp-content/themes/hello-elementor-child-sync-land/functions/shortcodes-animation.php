<?php
/**
 * Animation Shortcodes
 *
 * Shortcodes for animated/interactive elements like the hero planet.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inner Planet Shortcode
 *
 * Displays a basic 3D planet animation.
 *
 * Usage: [inner_planet id="planet-container"]
 */
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

/**
 * Hero Planet Shortcode
 *
 * Displays an interactive 3D planet with random song metadata overlay.
 *
 * Usage: [hero_planet size="500" genre="" mood=""]
 *
 * Attributes:
 * - size: Planet container size in pixels (default: 500)
 * - genre: Optional filter for random song by genre
 * - mood: Optional filter for random song by mood
 */
function fml_hero_planet_shortcode($atts) {
    $atts = shortcode_atts([
        'size' => 500,
        'genre' => '',
        'mood' => ''
    ], $atts, 'hero_planet');

    $size = intval($atts['size']);
    $genre = sanitize_text_field($atts['genre']);
    $mood = sanitize_text_field($atts['mood']);

    // Generate unique ID for this instance
    $instance_id = 'hero-planet-' . uniqid();

    ob_start();
    ?>
    <div class="hero-planet-wrapper" id="<?php echo esc_attr($instance_id); ?>"
         style="width: <?php echo $size; ?>px; height: <?php echo $size; ?>px;"
         data-genre="<?php echo esc_attr($genre); ?>"
         data-mood="<?php echo esc_attr($mood); ?>">

        <div class="hero-planet-canvas"></div>

        <div class="hero-planet-overlay">
            <div class="hero-planet-loading">
                <div class="hero-planet-spinner"></div>
                <span>Loading...</span>
            </div>

            <div class="hero-planet-content" style="display: none;">
                <div class="hero-planet-song-info">
                    <img class="hero-planet-album-art" src="" alt="Album Art" style="display: none;">
                    <div class="hero-planet-metadata">
                        <h3 class="hero-planet-title"></h3>
                        <p class="hero-planet-artist"></p>
                        <div class="hero-planet-tags"></div>
                    </div>
                </div>

                <button class="hero-planet-play-btn" aria-label="Play song">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </button>

                <button class="hero-planet-refresh-btn" aria-label="Get new song">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hero_planet', 'fml_hero_planet_shortcode');