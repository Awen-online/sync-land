<?php
//USER REGISTRATION PLUGIN
//MY ACCOUNT
//ADDITIONAL TABS
// add_filter( 'user_registration_account_menu_items', 'ur_custom_menu_items', 10, 1 );
// function ur_custom_menu_items ($items ) {
//     $new_items = array(
//         'edit-profile'	=> __( 'User Profile', 'user-registration' ),
//         'my-licenses'	=> __( 'Licenses', 'user-registration' ),
//         'my-playlists'	=> __( 'Playlists', 'user-registration' ),
//         'my-artists'	=> __( 'Music', 'user-music-tone' ),
//     );
    
//     $newItemsArray = array_slice($items, 0, 1, true) +
//         $new_items +
//         array_slice($items, 1, count($items) - 1, true) ;
    
//     return $newItemsArray;
// }

// add_action( 'init', 'user_registration_add_new_my_account_endpoint' );
// function user_registration_add_new_my_account_endpoint() {
//     add_rewrite_endpoint( 'my-licenses', EP_PAGES );
//     add_rewrite_endpoint( 'my-playlists', EP_PAGES );
//     add_rewrite_endpoint( 'my-artists', EP_PAGES );
//    add_rewrite_endpoint( 'my-orders', EP_PAGES );
// }

// Shortcode for My Licenses
// Shortcode for My Licenses

add_shortcode( 'my_dashboard', 'user_registration_dashboard_shortcode' );
function user_registration_dashboard_shortcode() {
    ob_start();
    $template_path = get_stylesheet_directory() . '/user-registration/myaccount/dashboard.php';
    if ( file_exists( $template_path ) ) {
        include( $template_path );
    } else {
        echo 'Template "'.$template_path.'" not found!';
    }
    return ob_get_clean();
}

add_shortcode( 'my_licenses', 'user_registration_my_licenses_shortcode' );
function user_registration_my_licenses_shortcode() {
    ob_start();
    $template_path = get_stylesheet_directory() . '/user-registration/myaccount/my-licenses.php';
    if ( file_exists( $template_path ) ) {
        include( $template_path );
    } else {
        echo 'Template "'.$template_path.'" not found!';
    }
    return ob_get_clean();
}

// Shortcode for My Playlists
add_shortcode( 'my_playlists', 'user_registration_my_playlists_shortcode' );
function user_registration_my_playlists_shortcode() {
    ob_start();
    $template_path = get_stylesheet_directory() . '/user-registration/myaccount/my-playlists.php';
    if ( file_exists( $template_path ) ) {
        include( $template_path );
    } else {
        echo 'Template "'.$template_path.'" not found!';
    }
    return ob_get_clean();
}

// Shortcode for My Artists
add_shortcode( 'my_artists', 'user_registration_my_artists_shortcode' );
function user_registration_my_artists_shortcode() {
    ob_start();
    $template_path = get_stylesheet_directory() . '/user-registration/myaccount/artists.php';
    if ( file_exists( $template_path ) ) {
        include( $template_path );
    } else {
        echo 'Template "'.$template_path.'" not found!';
    }
    return ob_get_clean();
}

// Shortcode to display User Registration breadcrumbs
add_shortcode( 'user_registration_breadcrumbs', 'user_registration_breadcrumbs_shortcode' );
function user_registration_breadcrumbs_shortcode() {
    ob_start();
    if ( function_exists( 'ur_breadcrumb' ) ) {
        ur_breadcrumb();
    }
    return ob_get_clean();
}

add_shortcode('ur_logout', function() {

    return ur_logout_url();

    // ob_start();
    // ur_logout_url();
    // return ob_get_clean();
});

//REMOVE UNNECCESSARY TABS
// add_filter( 'user_registration_account_menu_items', 'ur_remove_dashboard', 10 );
// function ur_remove_dashboard( $items )  {
// 	unset( $items['payment'] );
//         unset( $items['downloads'] );
//         unset( $items['orders'] );
//         unset( $items['edit-address'] );
// 	return $items;
// }
?>
