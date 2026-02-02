<?php


//logged in / logged out logic for My Account Menu items
add_filter( 'wp_nav_menu_items', 'ur_add_loginout_link', 10, 2 );
function ur_add_loginout_link( $items, $args ) {
    
//    print_r($args);
    
    //menu to add code to
    $menuname = "logged-in";
    $theme_location = "main_menu";
    
    
    
    if (is_user_logged_in() && $args->menu == $menuname) {
        
    
        $links = array("Profile" => array("/account/edit-profile","icon-user"), 
            "Music" => array("/account/my-artists","icon-music-tone"),
            "Licenses"=> array("/account/my-licenses","icon-paper-plane"), 
            "Playlists"=> array("/account/my-playlists","icon-playlist"));

        $links["Log Out"] = array("/account/user-logout/","icon-logout");
    
    
        $string = "<li class='menu-item menu-item-type-post_type menu-item-object-page menu-item-has-children'>"
                . "<a href='/account' class='menu-link has-submenu elementor-item'>Dashboard"
//                . "<i class=\"nav-arrow fa fa-angle-down\" aria-hidden=\"true\" role=\"img\"></i>"
                . "</a>"
                . "<ul class='sub-menu'>";
        
        foreach($links as $name => $dets){
            $string .= '<li class=\'menu-item menu-item-type-post_type menu-item-object-page \' style=\'background: white;\' >'
                  . '<a href="'.$dets[0].'" class="menu-link">'
                    . '<span class="text-wrap">'
                    . '<i class="icon before line-icon '.$dets[1].'" aria-hidden="true"></i>'
                    .$name
                    .'</span>'
                    . '</a>'
                  . '</li>';
        }
            
        $string .= "</ul></li>";
               
        $items = $string.$items;
     } elseif ( ! is_user_logged_in() && $args->theme_location == 'primary' ) {
//          $items .= '<li><a href="' . get_permalink( ur_get_page_id( 'my-account' ) ) . '">Log In der</a></li>';
     }
      return $items;
}


//wp_update_nav_menu_item($menu_id, 0, array(
//    'menu-item-title' =>  __('Home'),
//    'menu-item-classes' => 'home',
//    'menu-item-url' => home_url( '/' ), 
//    'menu-item-status' => 'publish'));


//
// Conditional Nav Menu
//
function wpc_wp_nav_menu_args( $args = '' ) {
if( is_user_logged_in() ) { 
    $args['menu'] = 'logged-in';
} 
    return $args;
}
add_filter( 'wp_nav_menu_args', 'wpc_wp_nav_menu_args' );


//stop the weird database activity from this code for this post type
// generate a random, but not pretty, slug for a post type
//add_filter( 'pre_wp_unique_post_slug', 'vip_set_nav_slug', 10, 6 );
//
//function vip_set_nav_slug( $override, $slug, $post_ID, $post_status, $post_type, $post_parent ) {
//    if ( 'nav_menu_item' === $post_type ) {
//        if ( $post_ID ) {
//            return $post_ID;
//        }
//        return uniqid( $post_type . '-' );
//    }
//    return $override;
//}

//Disable Admin Bar for All Users Except Administrators
add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar() {
    if (!current_user_can('administrator') && !is_admin()) {
      show_admin_bar(false);
    }
}