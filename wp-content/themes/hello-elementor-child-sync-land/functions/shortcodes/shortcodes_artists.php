<?php
function display_pods_datatable() {
    $output = '<table id="pods_table" class="display"><thead><tr>';
    $output .= '<th>Artist</th><th>Image</th><th>Albums</th><th>Date Signed Up</th>'; // Updated column name
    $output .= '</tr></thead><tbody>';

    $pods = pods('artist', ['limit' => -1]); // Fetch all artists
    while ($pods->fetch()) {
        $title = $pods->field('post_title');
        $permalink = $pods->field('permalink'); // Pods permalink field
        $image = $pods->field('profile_image') ? wp_get_attachment_image($pods->field('profile_image')['ID'], 'thumbnail') : 'No image'; // Custom image field
        $albums = $pods->field('albums'); // Relationship field to 'album' CPT
        $album_count = is_array($albums) ? count($albums) : 0; // Count related albums
        $date = $pods->field('post_date');
        $formatted_date = date('F j, Y', strtotime($date)); // Readable format: "Month Day, Year"

        $output .= '<tr>';
        $output .= '<td><a href="' . esc_url($permalink) . '" style="color: #0073aa;"><h3>' . esc_html($title) . '</h3></a></td>'; // Artist name as h3 with color
        $output .= '<td><a href="' . esc_url($permalink) . '">' . $image . '</a></td>'; // Image linked to permalink
        $output .= '<td>' . esc_html($album_count) . '</td>'; // Number of albums
        $output .= '<td>' . esc_html($formatted_date) . '</td>'; // Readable date
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';
    return $output;
}
add_shortcode('pods_datatable', 'display_pods_datatable');