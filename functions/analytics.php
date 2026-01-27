<?php

//
//COUNT VIEWS
//


function gt_get_post_view() {
    $count = get_post_meta( get_the_ID(), 'post_views_count', true );
    return "👁️ $count views";
}
function gt_set_post_view() {
    $key = 'post_views_count';
    $post_id = get_the_ID();
    $count = (int) get_post_meta( $post_id, $key, true );
    $count++;
    update_post_meta( $post_id, $key, $count );
}
function gt_posts_column_views( $columns ) {
    $columns['post_views'] = 'Views';
    return $columns;
}
function gt_posts_custom_column_views( $column ) {
    if ( $column === 'post_views') {
        echo gt_get_post_view();
    }
}
add_filter( 'manage_posts_columns', 'gt_posts_column_views' );
add_action( 'manage_posts_custom_column', 'gt_posts_custom_column_views' );


// from https://gretathemes.com/count-post-views/
//2. Then, copy the code below and paste it into single.php file in the while loop.
//
//gt_set_post_view(); 
//3. Next, copy the following code and paste it where you want to show the number of views
//<?= gt_get_post_view();

//
//END COUNT VIEWS
//
