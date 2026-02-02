<?php
/**
 * Universal Search API Endpoint + Shortcode
 *
 * Searches across songs, artists, albums, playlists, genres, and moods
 * in a single request. Uses WP_Query instead of Pods find() to avoid
 * Pods' SQL fragment security restrictions on public endpoints.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API route
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/search', [
        'methods' => 'GET',
        'callback' => 'fml_universal_search',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'q' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);
});

/**
 * Universal search callback
 */
function fml_universal_search(WP_REST_Request $request) {
    $query = $request->get_param('q');

    if (strlen($query) < 2) {
        return fml_api_error('Query must be at least 2 characters', 'invalid_query', 400);
    }

    $results = [
        'songs'     => fml_search_pod_type_universal('song', $query),
        'artists'   => fml_search_pod_type_universal('artist', $query),
        'albums'    => fml_search_pod_type_universal('album', $query),
        'playlists' => fml_search_pod_type_universal('playlist', $query),
        'genres'    => fml_search_taxonomy_universal('genre', $query),
        'moods'     => fml_search_taxonomy_universal('mood', $query),
    ];

    return fml_api_success([
        'data'  => $results,
        'query' => $query
    ]);
}

/**
 * Search a post type using WP_Query (avoids Pods WHERE restrictions)
 */
function fml_search_pod_type_universal($post_type, $query) {
    $wp_query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        's'              => $query,
        'posts_per_page' => 5,
        'orderby'        => 'relevance',
    ]);

    $results = [];

    if ($wp_query->have_posts()) {
        while ($wp_query->have_posts()) {
            $wp_query->the_post();
            $post_id = get_the_ID();

            switch ($post_type) {
                case 'song':
                    $results[] = fml_format_song_search_result($post_id);
                    break;
                case 'artist':
                    $results[] = fml_format_artist_search_result($post_id);
                    break;
                case 'album':
                    $results[] = fml_format_album_search_result($post_id);
                    break;
                case 'playlist':
                    $results[] = fml_format_playlist_search_result($post_id);
                    break;
            }
        }
    }

    wp_reset_postdata();
    return $results;
}

/**
 * Format a song result — includes playable data
 */
function fml_format_song_search_result($post_id) {
    $song_pod = pods('song', $post_id);

    $artist_name = 'Unknown Artist';
    $artist_permalink = '';
    $artist_data = $song_pod->field('artist');
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
            $artist_permalink = get_permalink($artist_id);
        }
    }

    $album_name = '';
    $album_permalink = '';
    $album_data = $song_pod->field('album');
    if (!empty($album_data)) {
        $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
        $album_pod = pods('album', $album_id);
        if ($album_pod && $album_pod->exists()) {
            $album_name = $album_pod->field('post_title');
            $album_permalink = get_permalink($album_id);
        }
    }

    $cover_art_url = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '';

    return [
        'id'               => $post_id,
        'name'             => get_the_title($post_id),
        'audio_url'        => $song_pod->field('audio_url'),
        'cover_art_url'    => $cover_art_url,
        'artist_name'      => $artist_name,
        'artist_permalink' => $artist_permalink,
        'album_name'       => $album_name,
        'album_permalink'  => $album_permalink,
        'permalink'        => get_permalink($post_id),
    ];
}

/**
 * Format an artist result
 */
function fml_format_artist_search_result($post_id) {
    return [
        'id'        => $post_id,
        'name'      => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
        'thumbnail' => get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '',
    ];
}

/**
 * Format an album result
 */
function fml_format_album_search_result($post_id) {
    $album_pod = pods('album', $post_id);

    $artist_name = 'Unknown Artist';
    $artist_data = $album_pod->field('artist');
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    return [
        'id'            => $post_id,
        'title'         => get_the_title($post_id),
        'artist_name'   => $artist_name,
        'cover_art_url' => get_the_post_thumbnail_url($post_id, 'thumbnail') ?: '',
        'permalink'     => get_permalink($post_id),
    ];
}

/**
 * Format a playlist result
 */
function fml_format_playlist_search_result($post_id) {
    return [
        'id'        => $post_id,
        'name'      => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
    ];
}

/**
 * Search taxonomy terms (genres, moods)
 */
function fml_search_taxonomy_universal($taxonomy, $query) {
    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'name__like' => $query,
        'number'     => 5,
        'hide_empty' => true,
    ]);

    if (is_wp_error($terms)) {
        return [];
    }

    $results = [];
    foreach ($terms as $term) {
        $results[] = [
            'id'   => intval($term->term_id),
            'name' => $term->name,
            'slug' => $term->slug,
            'link' => get_term_link($term),
        ];
    }

    return $results;
}

/**
 * Shortcode: [fml_search_icon]
 *
 * Renders a search icon that opens the search overlay.
 * Add this anywhere — header, menu, widget, etc.
 */
add_shortcode('fml_search_icon', function() {
    return '<div class="show-search" title="Search (/)" role="button" tabindex="0"><i class="fas fa-search"></i></div>';
});
