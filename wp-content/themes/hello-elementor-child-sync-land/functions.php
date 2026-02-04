<?php
/**
 * Theme functions and definitions.
 *
 * Sets up the theme and provides some helper functions
 *
 * When using a child theme (see http://codex.wordpress.org/Theme_Development
 * and http://codex.wordpress.org/Child_Themes), you can override certain
 * functions (those wrapped in a function_exists() call) by defining them first
 * in your child theme's functions.php file. The child theme's functions.php
 * file is included before the parent theme's file, so the child theme
 * functions would be used.
 *
 *
 * For more information on hooks, actions, and filters,
 * see http://codex.wordpress.org/Plugin_API
 *
 * @package Hello Elementor Theme
 */

/**
* Allow Pods Templates to use shortcodes
*
* NOTE: Will only work if the constant PODS_SHORTCODE_ALLOW_SUB_SHORTCODES is defined and set to  true, which by default it IS NOT
*/
add_filter( 'pods_shortcode', function( $tags )  {
  $tags[ 'shortcodes' ] = true;
  
  return $tags;
  
});

//ADD GOOGLE ANALYTICS

function add_analytics_head_js() {
    ?>
        <!-- Replace this with your Analytical HEAD code -->
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-RHX5TVXMCT"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', 'G-RHX5TVXMCT');
        </script>
    <?php
}
add_action( 'wp_head', 'add_analytics_head_js', 11 );


function blockusers_init() {
    if ( is_admin() && ! current_user_can( 'administrator' ) &&
    ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        wp_redirect( home_url() );
        exit;
    }
}
add_action( 'init', 'blockusers_init' );

//
//LOADING SCREEN FOR THE ENTIRE SITEs
//
function display_loading_screen() {
    ?>
    <div id="loading-screen">
        <div id="loader"></div>
    </div>
    <?php
}

function add_loading_screen() {
    display_loading_screen();
}
add_action('wp_body_open', 'add_loading_screen');

//
//ADD CSS
//
add_action('wp_enqueue_scripts', function() {
    
    $style_version = '1.333'; // Change this to your desired version number
    
    //
    //ADD CSS
    //
    wp_enqueue_style('custom-style', get_stylesheet_directory_uri() . '/assets/css/main.css', array(), $style_version);
    wp_enqueue_style('song-upload-style', get_stylesheet_directory_uri() . '/assets/css/song-upload-styles.css', array(), $style_version);

    // wp_enqueue_style('gravity-forms-style', get_stylesheet_directory_uri() . '/assets/css/gravityforms.css', array(), $style_version);
    wp_enqueue_style('my-account-style', get_stylesheet_directory_uri() . '/assets/css/my-account.css', array(), $style_version);
    // wp_enqueue_style('jquery-datatables-style', 'https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css', array(), $style_version);
    wp_enqueue_style('boostrap-badge-style',  get_stylesheet_directory_uri() . '/assets/css/bootstrap-badges.css', array(), $style_version);
    wp_enqueue_style('datatables-style', get_stylesheet_directory_uri() . '/assets/css/dataTables.css', array(), $style_version);
    wp_enqueue_style('magnific-popup-css', 'https://cdn.jsdelivr.net/npm/magnific-popup@1.1.0/dist/magnific-popup.css', array(), $style_version);
    wp_enqueue_style('fml-search-style', get_stylesheet_directory_uri() . '/assets/css/search.css', array(), $style_version);
    wp_enqueue_style('fml-cart-style', get_stylesheet_directory_uri() . '/assets/css/cart.css', array(), $style_version);

    //
    //ADD JS
    //
    wp_enqueue_script('custom-script', get_stylesheet_directory_uri() . '/assets/js/main.js', array('jquery'), $style_version);
    wp_enqueue_script('FML-script', get_stylesheet_directory_uri() . '/assets/js/FML.js', array('jquery'), $style_version);
    wp_enqueue_script('pjax-navigation', get_stylesheet_directory_uri() . '/assets/js/pjax-navigation.js', array('jquery'), $style_version, true);
    // wp_enqueue_script('song-upload-custom', get_stylesheet_directory_uri() . '/assets/js/song-upload.js', array('jquery', 'song-upload-script'), '1.0', true);
    wp_enqueue_script('jquery-sortable', get_stylesheet_directory_uri() . '/assets/js/jquery-sortable-min.js', array('jquery'), $style_version);
    wp_enqueue_script('tables', get_stylesheet_directory_uri() . '/assets/js/tables.js', array('jquery'), $style_version);
    wp_enqueue_script('jquery-ui', 'https://code.jquery.com/ui/1.13.0/jquery-ui.js', array('jquery'), $style_version);
    wp_enqueue_script('jquery-datatables', 'https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js', array('jquery'), $style_version);
    wp_enqueue_script('datatables-responsive-js', 'https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js', ['jquery-datatables'], null, $style_version);
    wp_enqueue_script('magnific-popup', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.2.0/jquery.magnific-popup.min.js', array('jquery'), $style_version);
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), $style_version);
    wp_enqueue_script('magnific-popup', 'https://cdn.jsdelivr.net/npm/magnific-popup@1.1.0/dist/jquery.magnific-popup.min.js', array('jquery'), $style_version, true);
    
     // Enqueue Three.js core
    wp_enqueue_script(
        'three-js',
        'https://cdn.jsdelivr.net/npm/three/build/three.min.js',
        array(),
        null,
        true
    );

    // Enqueue Stats
    // wp_enqueue_script(
    //     'three-stats',
    //     'https://cdn.jsdelivr.net/npm/three/examples/js/libs/stats.min.js',
    //     array('three-js'),
    //     null,
    //     true
    // );

    // Enqueue BufferGeometryUtils
    wp_enqueue_script('three-buffer-geometry-utils', 'https://cdn.jsdelivr.net/npm/three/examples/js/utils/BufferGeometryUtils.js', array('three-js'), null, true);
    wp_enqueue_script('threejs-background',get_stylesheet_directory_uri() . '/assets/js/background-particles.js', array('three-js'), $style_version, true);
    wp_enqueue_script('inner-planet', get_stylesheet_directory_uri() . '/assets/js/inner-planet.js', array('three-js'), $style_version, true); // Enqueue the new script
    wp_enqueue_script('fml-search', get_stylesheet_directory_uri() . '/assets/js/search.js', array(), $style_version, true);
    wp_enqueue_script('fml-cart', get_stylesheet_directory_uri() . '/assets/js/cart.js', array('jquery'), $style_version, true);


    // Add type="module" to the script tag
    add_filter('script_loader_tag', 'add_module_to_threejs_script', 10, 3);


    //NMKR

    wp_enqueue_script('custom-script-nmkr', get_stylesheet_directory_uri() . '/assets/js/nmkr.js', array('jquery'), $style_version);

     // Localize the script with AJAX URL and nonce
     wp_localize_script('custom-script-nmkr', 'nftAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mint_nft_nonce')
    ]);


    });

function add_module_to_threejs_script($tag, $handle, $src) {
    if ('threejs-background' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}    

//remove the user registration styles to only rely on Elementor
function remove_and_replace_user_registration_styles() {
    // Array of all handles for user-registration styles
    $handles = array(
        'user-registration-css',
        'user-registration-blocks-editor',
        'user-registration-general',
        'user-registration-block-editor'
    );

    foreach ($handles as $handle) {
        // Dequeue and Deregister each style
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
    }

    // Replace with an empty CSS for all handles
    $empty_css_url = get_stylesheet_directory_uri() . '/assets/css/empty.css';
    foreach ($handles as $handle) {
        wp_register_style($handle, $empty_css_url, array(), '1.0'); // Assuming '1.0' as version for all
        wp_enqueue_style($handle);
    }
}
add_action('wp_enqueue_scripts', 'remove_and_replace_user_registration_styles', 20);

require get_stylesheet_directory().'/functions/myaccount.php';
require get_stylesheet_directory().'/functions/forms.php';
require get_stylesheet_directory().'/functions/shortcodes.php';
require get_stylesheet_directory().'/functions/shortcodes/shortcodes_artists.php';
require get_stylesheet_directory().'/functions/shortcodes/songupload.php';
// require get_stylesheet_directory().'/functions/nav_menu.php';
// require get_stylesheet_directory().'/functions/analytics.php';
require get_stylesheet_directory().'/functions/elementor.php';
require get_stylesheet_directory().'/functions/nmkr.php';
require get_stylesheet_directory().'/functions/gravityforms/licensing-creativecommons.php';

require get_stylesheet_directory().'/functions/api/security.php';
require get_stylesheet_directory().'/functions/api/songs.php';
require get_stylesheet_directory().'/functions/api/playlists.php';
require get_stylesheet_directory().'/functions/api/stripe.php';
require get_stylesheet_directory().'/functions/api/external.php';
require get_stylesheet_directory().'/functions/api/search.php';

// Shopping Cart System
require get_stylesheet_directory().'/functions/cart.php';
require get_stylesheet_directory().'/functions/shortcodes/cart-shortcodes.php';

// Admin monitoring pages
require get_stylesheet_directory().'/functions/admin/nft-monitor.php';
