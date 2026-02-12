<?php
/**
 * External API Endpoints for Sync.Land
 *
 * Hardened, publicly accessible endpoints for external applications.
 * All endpoints use proper authentication, rate limiting, and input validation.
 *
 * Authentication: X-API-Key header or WordPress session
 * Rate Limiting: 100 requests/hour (configurable via FML_API_RATE_LIMIT)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * SONGS API
 * ============================================================================
 */

add_action('rest_api_init', function() {
    // Get random song for hero planet
    register_rest_route('FML/v1', '/songs/random', [
        'methods' => 'GET',
        'callback' => 'fml_get_random_song',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'genre' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'mood' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);

    // Get single song
    register_rest_route('FML/v1', '/songs/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_get_song',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);

    // Search songs (hardened version)
    register_rest_route('FML/v1', '/songs', [
        'methods' => 'GET',
        'callback' => 'fml_search_songs',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'q' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'genre' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'mood' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'bpm_min' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'bpm_max' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'page' => [
                'required' => false,
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ],
            'per_page' => [
                'required' => false,
                'default' => 20,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0 && intval($param) <= 100;
                }
            ]
        ]
    ]);

    // Get licenses for a song
    register_rest_route('FML/v1', '/songs/(?P<id>\d+)/licenses', [
        'methods' => 'GET',
        'callback' => 'fml_get_song_licenses',
        'permission_callback' => 'fml_permission_authenticated_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);

    // Get license modal content (add-to-cart HTML) for a song
    register_rest_route('FML/v1', '/license-modal/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_get_license_modal_content',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);
});

/**
 * Get single song by ID
 */
function fml_get_song(WP_REST_Request $request) {
    $song_id = intval($request->get_param('id'));

    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return fml_api_error('Song not found', 'not_found', 404);
    }

    $song_data = fml_format_song($song_pod);

    return fml_api_success(['song' => $song_data]);
}

/**
 * Get a random published song
 * Uses WP_Query instead of Pods find() to avoid SQL restrictions
 */
function fml_get_random_song(WP_REST_Request $request) {
    // Get a random published song using WP_Query
    $query = new WP_Query([
        'post_type'      => 'song',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'rand',
    ]);

    if (!$query->have_posts()) {
        wp_reset_postdata();
        return fml_api_error('No songs found', 'not_found', 404);
    }

    $query->the_post();
    $song_id = get_the_ID();
    wp_reset_postdata();

    // Use Pods to get full song data with relationships
    $song_pod = pods('song', $song_id);

    if (!$song_pod || !$song_pod->exists()) {
        return fml_api_error('Song not found', 'not_found', 404);
    }

    $song_data = fml_format_song_full($song_pod);

    return fml_api_success(['song' => $song_data]);
}

/**
 * Format a related item (genre/mood CPT) with name and permalink
 * Handles both Pods CPT relationships and WordPress taxonomy terms
 */
function fml_format_taxonomy_term($term, $taxonomy) {
    $name = '';
    $post_id = null;
    $term_id = null;

    // Handle Pods relationship data (returns post object or array)
    if (is_object($term)) {
        // Pods relationship - post object
        if (isset($term->ID)) {
            $post_id = $term->ID;
            $name = isset($term->post_title) ? $term->post_title : '';
        }
        // WordPress term object
        elseif (isset($term->term_id)) {
            $term_id = $term->term_id;
            $name = isset($term->name) ? $term->name : '';
        }
        // Fallback to name property
        elseif (isset($term->name)) {
            $name = $term->name;
        }
    } elseif (is_array($term)) {
        // Pods relationship - post array
        if (isset($term['ID'])) {
            $post_id = $term['ID'];
            $name = isset($term['post_title']) ? $term['post_title'] : '';
        }
        // WordPress term array
        elseif (isset($term['term_id'])) {
            $term_id = $term['term_id'];
            $name = isset($term['name']) ? $term['name'] : '';
        }
        // Fallback to name key
        elseif (isset($term['name'])) {
            $name = $term['name'];
        }
    } elseif (is_string($term) && !empty($term)) {
        $name = $term;
    }

    if (empty($name)) {
        return null;
    }

    // Get the permalink
    $permalink = '';

    // If we have a post ID (Pods CPT relationship), use get_permalink
    if ($post_id) {
        $permalink = get_permalink($post_id);
    }
    // If we have a term ID (WordPress taxonomy), use get_term_link
    elseif ($term_id) {
        $permalink = get_term_link($term_id, $taxonomy);
        if (is_wp_error($permalink)) {
            $permalink = '';
        }
    }
    // Fallback: try to find the post by title in the CPT
    else {
        // First try as a Pods CPT
        $found_post = get_page_by_title($name, OBJECT, $taxonomy);
        if ($found_post) {
            $permalink = get_permalink($found_post->ID);
        } else {
            // Fallback to taxonomy term lookup
            $found_term = get_term_by('name', $name, $taxonomy);
            if ($found_term && !is_wp_error($found_term)) {
                $permalink = get_term_link($found_term, $taxonomy);
                if (is_wp_error($permalink)) {
                    $permalink = '';
                }
            }
        }
    }

    return [
        'name' => $name,
        'permalink' => $permalink
    ];
}

/**
 * Format song data with full metadata for hero planet
 */
function fml_format_song_full($song_pod) {
    $song_id = intval($song_pod->id());

    // Get artist data
    $artist_data = $song_pod->field('artist');
    $artist_name = 'Unknown Artist';
    $artist_id = null;
    $artist_permalink = '';

    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
            $artist_permalink = get_permalink($artist_id);
        }
    }

    // Get album data and cover art from album
    $album_data = $song_pod->field('album');
    $album_name = '';
    $album_id = null;
    $album_permalink = '';
    $cover_art_url = '';

    if (!empty($album_data)) {
        $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
        $album_pod = pods('album', $album_id);
        if ($album_pod && $album_pod->exists()) {
            $album_name = $album_pod->field('post_title');
            $album_permalink = get_permalink($album_id);
            // Get cover art from album's featured image
            $cover_art_url = get_the_post_thumbnail_url($album_id, 'medium') ?: '';
        }
    }

    // Fallback to song's featured image if no album cover
    if (empty($cover_art_url)) {
        $cover_art_url = get_the_post_thumbnail_url($song_id, 'medium') ?: '';
    }

    // Get audio URL
    $audio_url = $song_pod->field('audio_url') ?: '';

    // Format genre - return array of objects with name and permalink
    $genre_raw = $song_pod->field('genre');
    $genres = [];
    if (!empty($genre_raw)) {
        if (is_array($genre_raw)) {
            foreach ($genre_raw as $g) {
                $genre_item = fml_format_taxonomy_term($g, 'genre');
                if ($genre_item) {
                    $genres[] = $genre_item;
                }
            }
        } else {
            $genre_item = fml_format_taxonomy_term($genre_raw, 'genre');
            if ($genre_item) {
                $genres[] = $genre_item;
            }
        }
    }

    // Format mood - return array of objects with name and permalink
    $mood_raw = $song_pod->field('mood');
    $moods = [];
    if (!empty($mood_raw)) {
        if (is_array($mood_raw)) {
            foreach ($mood_raw as $m) {
                $mood_item = fml_format_taxonomy_term($m, 'mood');
                if ($mood_item) {
                    $moods[] = $mood_item;
                }
            }
        } else {
            $mood_item = fml_format_taxonomy_term($mood_raw, 'mood');
            if ($mood_item) {
                $moods[] = $mood_item;
            }
        }
    }

    // Get BPM as simple value
    $bpm = $song_pod->field('bpm');
    if (is_array($bpm)) {
        $bpm = isset($bpm['value']) ? $bpm['value'] : (isset($bpm[0]) ? $bpm[0] : '');
    }

    return [
        'id' => $song_id,
        'name' => $song_pod->field('post_title'),
        'audio_url' => $audio_url,
        'artist_name' => $artist_name,
        'artist_id' => $artist_id,
        'artist_permalink' => $artist_permalink,
        'album_name' => $album_name,
        'album_id' => $album_id,
        'album_permalink' => $album_permalink,
        'cover_art_url' => $cover_art_url,
        'genres' => $genres,
        'moods' => $moods,
        'bpm' => $bpm,
        'permalink' => get_permalink($song_id)
    ];
}

/**
 * Search songs with proper SQL escaping
 */
function fml_search_songs(WP_REST_Request $request) {
    global $wpdb;

    $query = $request->get_param('q');
    $genre = $request->get_param('genre');
    $mood = $request->get_param('mood');
    $bpm_min = $request->get_param('bpm_min');
    $bpm_max = $request->get_param('bpm_max');
    $page = intval($request->get_param('page'));
    $per_page = intval($request->get_param('per_page'));

    // Build WHERE conditions with proper escaping
    $where_conditions = ["t.post_status = 'publish'"];

    if (!empty($query)) {
        $escaped_query = fml_escape_like($query);
        $where_conditions[] = $wpdb->prepare(
            "t.post_title LIKE %s",
            '%' . $escaped_query . '%'
        );
    }

    // Build params for Pods
    $params = [
        'limit' => $per_page,
        'page' => $page,
        'orderby' => 't.post_date DESC',
        'where' => implode(' AND ', $where_conditions)
    ];

    $songs = pods('song', $params);

    $results = [];
    if ($songs->total() > 0) {
        while ($songs->fetch()) {
            $results[] = fml_format_song($songs);
        }
    }

    return fml_api_success([
        'songs' => $results,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $songs->total_found(),
            'total_pages' => ceil($songs->total_found() / $per_page)
        ]
    ]);
}

/**
 * Get license modal content (add-to-cart HTML) for a song
 * Returns the add-to-cart shortcode content for use in the player modal
 */
function fml_get_license_modal_content(WP_REST_Request $request) {
    $song_id = intval($request->get_param('id'));

    // Verify song exists
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Song not found'
        ], 404);
    }

    // Generate the add-to-cart shortcode content
    $html = '';

    // Check if the shortcode function exists
    if (function_exists('fml_add_to_cart_shortcode')) {
        $html = fml_add_to_cart_shortcode([
            'song_id' => $song_id,
            'show_options' => 'true',
            'button_text' => 'Add to Cart'
        ]);

        // Remove the data-nonce attribute from the shortcode output
        // The nonce from the REST API context won't be valid for the user's session
        // The modal has its own valid nonce that will be used instead
        $html = preg_replace('/\s*data-nonce="[^"]*"/', '', $html);

        // Add a link to view the full song page
        $permalink = get_permalink($song_id);
        if ($permalink) {
            $html .= '<div class="fml-license-modal-link" style="margin-top: 16px; text-align: center;">';
            $html .= '<a href="' . esc_url($permalink) . '" style="color: #a0aec0; font-size: 0.9rem; text-decoration: none;">';
            $html .= '<i class="fas fa-external-link-alt" style="margin-right: 6px;"></i>View full song page';
            $html .= '</a>';
            $html .= '</div>';
        }
    } else {
        $html = '<p style="color: #fc8181;">Licensing options unavailable.</p>';
    }

    return new WP_REST_Response([
        'success' => true,
        'html' => $html
    ], 200);
}

/**
 * Get licenses for a song
 */
function fml_get_song_licenses(WP_REST_Request $request) {
    $song_id = intval($request->get_param('id'));

    // Verify song exists
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return fml_api_error('Song not found', 'not_found', 404);
    }

    // Get licenses for this song
    $params = [
        'where' => "song.ID = {$song_id}",
        'orderby' => 't.post_date DESC',
        'limit' => 50
    ];

    $licenses = pods('license', $params);
    $results = [];

    if ($licenses->total() > 0) {
        while ($licenses->fetch()) {
            $results[] = fml_format_license($licenses);
        }
    }

    return fml_api_success([
        'song_id' => $song_id,
        'licenses' => $results
    ]);
}

/**
 * Format song data for API response
 */
function fml_format_song($song_pod) {
    $artist_data = $song_pod->field('artist');
    $artist_name = 'Unknown Artist';
    $artist_id = null;

    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    $album_data = $song_pod->field('album');
    $album_name = null;
    $album_id = null;

    if (!empty($album_data)) {
        $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
        $album_pod = pods('album', $album_id);
        if ($album_pod && $album_pod->exists()) {
            $album_name = $album_pod->field('post_title');
        }
    }

    return [
        'id' => intval($song_pod->id()),
        'title' => $song_pod->field('post_title'),
        'artist' => [
            'id' => $artist_id,
            'name' => $artist_name
        ],
        'album' => $album_id ? [
            'id' => $album_id,
            'name' => $album_name
        ] : null,
        'audio_url' => $song_pod->field('audio_file'),
        'duration' => $song_pod->field('duration'),
        'bpm' => $song_pod->field('bpm'),
        'key' => $song_pod->field('key'),
        'genre' => $song_pod->field('genre'),
        'mood' => $song_pod->field('mood'),
        'permalink' => get_permalink($song_pod->id()),
        'created_at' => get_the_date('c', $song_pod->id())
    ];
}


/**
 * ============================================================================
 * ARTISTS API
 * ============================================================================
 */

add_action('rest_api_init', function() {
    // Get single artist
    register_rest_route('FML/v1', '/artists/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_get_artist',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);

    // Get artist's songs
    register_rest_route('FML/v1', '/artists/(?P<id>\d+)/songs', [
        'methods' => 'GET',
        'callback' => 'fml_get_artist_songs',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);
});

/**
 * Get single artist by ID
 */
function fml_get_artist(WP_REST_Request $request) {
    $artist_id = intval($request->get_param('id'));

    $artist_pod = pods('artist', $artist_id);
    if (!$artist_pod || !$artist_pod->exists()) {
        return fml_api_error('Artist not found', 'not_found', 404);
    }

    return fml_api_success([
        'artist' => fml_format_artist($artist_pod)
    ]);
}

/**
 * Get songs by artist
 */
function fml_get_artist_songs(WP_REST_Request $request) {
    $artist_id = intval($request->get_param('id'));

    // Verify artist exists
    $artist_pod = pods('artist', $artist_id);
    if (!$artist_pod || !$artist_pod->exists()) {
        return fml_api_error('Artist not found', 'not_found', 404);
    }

    // Get songs by this artist
    $params = [
        'where' => "artist.ID = {$artist_id} AND t.post_status = 'publish'",
        'orderby' => 't.post_date DESC',
        'limit' => 100
    ];

    $songs = pods('song', $params);
    $results = [];

    if ($songs->total() > 0) {
        while ($songs->fetch()) {
            $results[] = fml_format_song($songs);
        }
    }

    return fml_api_success([
        'artist' => fml_format_artist($artist_pod),
        'songs' => $results
    ]);
}

/**
 * Format artist data for API response
 */
function fml_format_artist($artist_pod) {
    return [
        'id' => intval($artist_pod->id()),
        'name' => $artist_pod->field('post_title'),
        'bio' => $artist_pod->field('post_content'),
        'website' => $artist_pod->field('website'),
        'permalink' => get_permalink($artist_pod->id()),
        'created_at' => get_the_date('c', $artist_pod->id())
    ];
}


/**
 * ============================================================================
 * ALBUMS API
 * ============================================================================
 */

add_action('rest_api_init', function() {
    // Get single album
    register_rest_route('FML/v1', '/albums/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_get_album',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);

    // Get album's songs
    register_rest_route('FML/v1', '/albums/(?P<id>\d+)/songs', [
        'methods' => 'GET',
        'callback' => 'fml_get_album_songs',
        'permission_callback' => 'fml_permission_public_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);
});

/**
 * Get single album by ID
 */
function fml_get_album(WP_REST_Request $request) {
    $album_id = intval($request->get_param('id'));

    $album_pod = pods('album', $album_id);
    if (!$album_pod || !$album_pod->exists()) {
        return fml_api_error('Album not found', 'not_found', 404);
    }

    return fml_api_success([
        'album' => fml_format_album($album_pod)
    ]);
}

/**
 * Get songs in album
 */
function fml_get_album_songs(WP_REST_Request $request) {
    $album_id = intval($request->get_param('id'));

    // Verify album exists
    $album_pod = pods('album', $album_id);
    if (!$album_pod || !$album_pod->exists()) {
        return fml_api_error('Album not found', 'not_found', 404);
    }

    // Get songs in this album
    $params = [
        'where' => "album.ID = {$album_id} AND t.post_status = 'publish'",
        'orderby' => 't.menu_order ASC, t.post_date DESC',
        'limit' => 100
    ];

    $songs = pods('song', $params);
    $results = [];

    if ($songs->total() > 0) {
        while ($songs->fetch()) {
            $results[] = fml_format_song($songs);
        }
    }

    return fml_api_success([
        'album' => fml_format_album($album_pod),
        'songs' => $results
    ]);
}

/**
 * Format album data for API response
 */
function fml_format_album($album_pod) {
    $artist_data = $album_pod->field('artist');
    $artist_name = 'Unknown Artist';
    $artist_id = null;

    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    return [
        'id' => intval($album_pod->id()),
        'title' => $album_pod->field('post_title'),
        'artist' => [
            'id' => $artist_id,
            'name' => $artist_name
        ],
        'release_date' => $album_pod->field('release_date'),
        'cover_art' => $album_pod->field('cover_art'),
        'permalink' => get_permalink($album_pod->id()),
        'created_at' => get_the_date('c', $album_pod->id())
    ];
}


/**
 * ============================================================================
 * LICENSES API
 * ============================================================================
 */

add_action('rest_api_init', function() {
    // Get single license
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'fml_get_license',
        'permission_callback' => 'fml_permission_authenticated_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);

    // Request a license (CC-BY)
    register_rest_route('FML/v1', '/licenses/request', [
        'methods' => 'POST',
        'callback' => 'fml_request_license',
        'permission_callback' => 'fml_permission_authenticated_rate_limited',
        'args' => [
            'song_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ],
            'licensee_name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'project_name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'mint_nft' => [
                'required' => false,
                'default' => false
            ],
            'wallet_address' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);

    // Get user's licenses
    register_rest_route('FML/v1', '/licenses/my', [
        'methods' => 'GET',
        'callback' => 'fml_get_my_licenses',
        'permission_callback' => 'fml_permission_logged_in'
    ]);

    // Get license payment status
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/payment-status', [
        'methods' => 'GET',
        'callback' => 'fml_get_license_payment_status',
        'permission_callback' => 'fml_permission_authenticated_rate_limited',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ]
        ]
    ]);
});

/**
 * Get single license by ID
 */
function fml_get_license(WP_REST_Request $request) {
    $license_id = intval($request->get_param('id'));

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return fml_api_error('License not found', 'not_found', 404);
    }

    // Check if user has permission to view this license
    $user_data = $license_pod->field('user');
    $license_user_id = is_array($user_data) ? $user_data['ID'] : $user_data;

    // Allow access if: admin, license owner, or API key
    if (!current_user_can('manage_options') &&
        get_current_user_id() != $license_user_id &&
        !fml_has_valid_api_key()) {
        return fml_api_error('Not authorized to view this license', 'forbidden', 403);
    }

    return fml_api_success([
        'license' => fml_format_license($license_pod)
    ]);
}

/**
 * Request a new CC-BY license
 */
function fml_request_license(WP_REST_Request $request) {
    $song_id = intval($request->get_param('song_id'));
    $licensee_name = $request->get_param('licensee_name');
    $project_name = $request->get_param('project_name');
    $mint_nft = filter_var($request->get_param('mint_nft'), FILTER_VALIDATE_BOOLEAN);
    $wallet_address = $request->get_param('wallet_address');

    // Validate song exists
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return fml_api_error('Song not found', 'not_found', 404);
    }

    // Validate wallet address if NFT requested
    if ($mint_nft && empty($wallet_address)) {
        return fml_api_error('Wallet address required for NFT minting', 'validation_error', 400);
    }

    if ($mint_nft && !fml_validate_wallet_address($wallet_address)) {
        return fml_api_error('Invalid Cardano wallet address', 'validation_error', 400);
    }

    // Call the existing license generator
    // This mimics the Gravity Form submission
    $_POST['songID'] = $song_id;
    $_POST['licensor'] = $licensee_name;
    $_POST['projectname'] = $project_name;

    // Generate the license using existing function (from licensing-creativecommons.php)
    if (function_exists('PDF_license_generator_internal')) {
        $result = PDF_license_generator_internal($song_id, $licensee_name, $project_name, $mint_nft, $wallet_address);
    } else {
        // Fallback to direct generation
        $result = fml_create_license_record($song_id, $licensee_name, $project_name);
    }

    if (!$result['success']) {
        return fml_api_error($result['error'] ?? 'Failed to create license', 'server_error', 500);
    }

    $response_data = [
        'license_id' => $result['license_id'] ?? null,
        'license_url' => $result['url'] ?? null,
        'message' => 'CC-BY 4.0 license created successfully'
    ];

    // Handle NFT minting
    if ($mint_nft && function_exists('fml_mint_license_nft')) {
        $nft_result = fml_mint_license_nft($result['license_id'], $wallet_address);
        $response_data['nft'] = [
            'status' => $nft_result['success'] ? 'pending' : 'failed',
            'message' => $nft_result['message'] ?? null
        ];
    }

    return fml_api_success($response_data, 201);
}

/**
 * Get current user's licenses
 */
function fml_get_my_licenses(WP_REST_Request $request) {
    $user_id = get_current_user_id();

    $params = [
        'where' => "user.ID = {$user_id}",
        'orderby' => 't.post_date DESC',
        'limit' => 100
    ];

    $licenses = pods('license', $params);
    $results = [];

    if ($licenses->total() > 0) {
        while ($licenses->fetch()) {
            $results[] = fml_format_license($licenses);
        }
    }

    return fml_api_success([
        'licenses' => $results
    ]);
}

/**
 * Get license payment status
 */
function fml_get_license_payment_status(WP_REST_Request $request) {
    $license_id = intval($request->get_param('id'));

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return fml_api_error('License not found', 'not_found', 404);
    }

    return fml_api_success([
        'license_id' => $license_id,
        'license_type' => $license_pod->field('license_type') ?: 'cc_by',
        'payment_status' => $license_pod->field('stripe_payment_status') ?: 'not_applicable',
        'payment_amount' => $license_pod->field('payment_amount'),
        'payment_currency' => $license_pod->field('payment_currency')
    ]);
}

/**
 * Format license data for API response
 */
function fml_format_license($license_pod) {
    $song_data = $license_pod->field('song');
    $song_id = null;
    $song_title = null;

    if (!empty($song_data)) {
        $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
        $song_pod = pods('song', $song_id);
        if ($song_pod && $song_pod->exists()) {
            $song_title = $song_pod->field('post_title');
        }
    }

    return [
        'id' => intval($license_pod->id()),
        'song' => [
            'id' => $song_id,
            'title' => $song_title
        ],
        'license_type' => $license_pod->field('license_type') ?: 'cc_by',
        'licensee' => $license_pod->field('licensor'),
        'project' => $license_pod->field('project'),
        'license_url' => $license_pod->field('license_url'),
        'nft' => [
            'status' => $license_pod->field('nft_status'),
            'asset_id' => $license_pod->field('nft_asset_id'),
            'transaction_hash' => $license_pod->field('nft_transaction_hash')
        ],
        'payment' => [
            'status' => $license_pod->field('stripe_payment_status'),
            'amount' => $license_pod->field('payment_amount'),
            'currency' => $license_pod->field('payment_currency')
        ],
        'created_at' => $license_pod->field('datetime') ?: get_the_date('c', $license_pod->id())
    ];
}

/**
 * Create license record (fallback if PDF generator not available)
 */
function fml_create_license_record($song_id, $licensee_name, $project_name) {
    $user_id = get_current_user_id();
    $current_time = current_time('mysql');

    $pod = pods('license');
    $data = [
        'user' => $user_id,
        'song' => $song_id,
        'datetime' => $current_time,
        'licensor' => $licensee_name,
        'project' => $project_name,
        'license_type' => 'cc_by'
    ];

    $new_license_id = $pod->add($data);

    if ($new_license_id) {
        wp_update_post([
            'ID' => $new_license_id,
            'post_status' => 'publish'
        ]);

        return [
            'success' => true,
            'license_id' => $new_license_id
        ];
    }

    return [
        'success' => false,
        'error' => 'Failed to create license record'
    ];
}


/**
 * ============================================================================
 * HEALTH CHECK / STATUS ENDPOINT
 * ============================================================================
 */

add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/status', [
        'methods' => 'GET',
        'callback' => 'fml_api_status',
        'permission_callback' => '__return_true'
    ]);
});

/**
 * API status/health check endpoint
 */
function fml_api_status(WP_REST_Request $request) {
    $status = [
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => current_time('c'),
        'endpoints' => [
            'songs' => '/FML/v1/songs',
            'artists' => '/FML/v1/artists/{id}',
            'albums' => '/FML/v1/albums/{id}',
            'licenses' => '/FML/v1/licenses/{id}',
            'stripe_checkout' => '/FML/v1/stripe/create-checkout',
            'nft_mint' => '/FML/v1/licenses/{id}/mint-nft'
        ]
    ];

    // Add auth status if authenticated
    if (is_user_logged_in()) {
        $status['auth'] = [
            'type' => 'wordpress_session',
            'user_id' => get_current_user_id()
        ];
    } elseif (fml_has_valid_api_key()) {
        $key_info = fml_validate_api_key();
        $status['auth'] = [
            'type' => 'api_key',
            'app_name' => $key_info['app_name']
        ];
    } else {
        $status['auth'] = [
            'type' => 'none'
        ];
    }

    // Add rate limit info
    $status['rate_limit'] = fml_get_rate_limit_info();

    return fml_api_success($status);
}
