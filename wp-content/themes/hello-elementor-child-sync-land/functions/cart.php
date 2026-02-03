<?php
/**
 * Shopping Cart System for Sync.Land
 *
 * Handles cart management for music license purchases with optional NFT verification.
 *
 * License Options:
 * - CC-BY (Free): Creative Commons Attribution 4.0 - no cost
 * - CC-BY + NFT: CC-BY with blockchain verification ($5 NFT fee)
 * - Commercial: Non-exclusive sync license ($49 default)
 * - Commercial + NFT: Commercial license with NFT verification ($49 + $5)
 *
 * Cart Storage:
 * - Logged-in users: wp_usermeta with key 'fml_cart'
 * - Guests: WordPress transients with session-based key
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * CART STORAGE FUNCTIONS
 * ============================================================================
 */

/**
 * Get the cart storage key for the current user/session
 */
function fml_get_cart_key() {
    if (is_user_logged_in()) {
        return 'fml_cart_' . get_current_user_id();
    }

    // For guests, use a session-based transient key
    if (!isset($_COOKIE['fml_cart_session'])) {
        $session_id = wp_generate_uuid4();
        setcookie('fml_cart_session', $session_id, time() + (7 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['fml_cart_session'] = $session_id;
    }

    return 'fml_cart_guest_' . sanitize_key($_COOKIE['fml_cart_session']);
}

/**
 * Get cart items from storage
 */
function fml_cart_get_items() {
    $cart_key = fml_get_cart_key();

    if (is_user_logged_in()) {
        $cart = get_user_meta(get_current_user_id(), 'fml_cart', true);
    } else {
        $cart = get_transient($cart_key);
    }

    return is_array($cart) ? $cart : [];
}

/**
 * Save cart items to storage
 */
function fml_cart_save($items) {
    $cart_key = fml_get_cart_key();

    if (is_user_logged_in()) {
        update_user_meta(get_current_user_id(), 'fml_cart', $items);
    } else {
        set_transient($cart_key, $items, 7 * DAY_IN_SECONDS);
    }

    return true;
}

/**
 * ============================================================================
 * CART MANAGEMENT FUNCTIONS
 * ============================================================================
 */

/**
 * Add item to cart
 *
 * @param int    $song_id        The song post ID
 * @param string $license_type   'cc_by' or 'non_exclusive'
 * @param bool   $include_nft    Whether to include NFT verification
 * @param string $wallet_address Cardano wallet address (required if include_nft)
 * @return array Result with success status
 */
function fml_cart_add_item($song_id, $license_type = 'cc_by', $include_nft = false, $wallet_address = '') {
    $song_id = intval($song_id);

    // Validate song exists
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return ['success' => false, 'error' => 'Song not found'];
    }

    // Validate license type
    $valid_types = ['cc_by', 'non_exclusive'];
    if (!in_array($license_type, $valid_types)) {
        return ['success' => false, 'error' => 'Invalid license type'];
    }

    // Validate wallet address if NFT is selected
    if ($include_nft && !empty($wallet_address)) {
        if (!fml_validate_cardano_address($wallet_address)) {
            return ['success' => false, 'error' => 'Invalid Cardano wallet address'];
        }
    }

    $cart = fml_cart_get_items();

    // Check if song already in cart
    foreach ($cart as $key => $item) {
        if ($item['song_id'] === $song_id) {
            // Update existing item
            $cart[$key] = [
                'song_id' => $song_id,
                'license_type' => $license_type,
                'include_nft' => (bool) $include_nft,
                'wallet_address' => sanitize_text_field($wallet_address),
                'added_at' => $cart[$key]['added_at']
            ];
            fml_cart_save($cart);
            return ['success' => true, 'message' => 'Cart item updated', 'cart_count' => count($cart)];
        }
    }

    // Add new item
    $cart[] = [
        'song_id' => $song_id,
        'license_type' => $license_type,
        'include_nft' => (bool) $include_nft,
        'wallet_address' => sanitize_text_field($wallet_address),
        'added_at' => current_time('mysql')
    ];

    fml_cart_save($cart);

    return ['success' => true, 'message' => 'Item added to cart', 'cart_count' => count($cart)];
}

/**
 * Remove item from cart
 *
 * @param int $song_id The song post ID to remove
 * @return array Result with success status
 */
function fml_cart_remove_item($song_id) {
    $song_id = intval($song_id);
    $cart = fml_cart_get_items();

    $found = false;
    foreach ($cart as $key => $item) {
        if ($item['song_id'] === $song_id) {
            unset($cart[$key]);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'error' => 'Item not found in cart'];
    }

    // Re-index array
    $cart = array_values($cart);
    fml_cart_save($cart);

    return ['success' => true, 'message' => 'Item removed from cart', 'cart_count' => count($cart)];
}

/**
 * Update cart item
 *
 * @param int    $song_id        The song post ID
 * @param string $license_type   'cc_by' or 'non_exclusive'
 * @param bool   $include_nft    Whether to include NFT verification
 * @param string $wallet_address Cardano wallet address
 * @return array Result with success status
 */
function fml_cart_update_item($song_id, $license_type = null, $include_nft = null, $wallet_address = null) {
    $song_id = intval($song_id);
    $cart = fml_cart_get_items();

    foreach ($cart as $key => $item) {
        if ($item['song_id'] === $song_id) {
            if ($license_type !== null) {
                $valid_types = ['cc_by', 'non_exclusive'];
                if (!in_array($license_type, $valid_types)) {
                    return ['success' => false, 'error' => 'Invalid license type'];
                }
                $cart[$key]['license_type'] = $license_type;
            }

            if ($include_nft !== null) {
                $cart[$key]['include_nft'] = (bool) $include_nft;
            }

            if ($wallet_address !== null) {
                if (!empty($wallet_address) && !fml_validate_cardano_address($wallet_address)) {
                    return ['success' => false, 'error' => 'Invalid Cardano wallet address'];
                }
                $cart[$key]['wallet_address'] = sanitize_text_field($wallet_address);
            }

            fml_cart_save($cart);
            return ['success' => true, 'message' => 'Cart item updated', 'cart_count' => count($cart)];
        }
    }

    return ['success' => false, 'error' => 'Item not found in cart'];
}

/**
 * Clear all items from cart
 */
function fml_cart_clear() {
    fml_cart_save([]);
    return ['success' => true, 'message' => 'Cart cleared', 'cart_count' => 0];
}

/**
 * Get cart item count
 */
function fml_cart_get_item_count() {
    return count(fml_cart_get_items());
}

/**
 * Get cart total price in cents
 */
function fml_cart_get_total() {
    $cart = fml_cart_get_items();
    $total = 0;

    $license_price = intval(get_option('fml_non_exclusive_license_price', 4900)); // $49.00
    $nft_fee = intval(get_option('fml_nft_minting_fee', 500)); // $5.00

    foreach ($cart as $item) {
        // Add license fee
        if ($item['license_type'] === 'non_exclusive') {
            $total += $license_price;
        }

        // Add NFT fee if selected
        if (!empty($item['include_nft'])) {
            $total += $nft_fee;
        }
    }

    return $total;
}

/**
 * Get detailed cart summary with pricing
 */
function fml_cart_get_summary() {
    $cart = fml_cart_get_items();
    $license_price = intval(get_option('fml_non_exclusive_license_price', 4900));
    $nft_fee = intval(get_option('fml_nft_minting_fee', 500));

    $items = [];
    $subtotal = 0;
    $nft_total = 0;
    $requires_payment = false;

    foreach ($cart as $item) {
        $song_id = $item['song_id'];
        $song_pod = pods('song', $song_id);

        if (!$song_pod || !$song_pod->exists()) {
            continue;
        }

        $song_title = $song_pod->field('post_title');

        // Get artist
        $artist_data = $song_pod->field('artist');
        $artist_name = 'Unknown Artist';
        if (!empty($artist_data)) {
            $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
            $artist_pod = pods('artist', $artist_id);
            if ($artist_pod && $artist_pod->exists()) {
                $artist_name = $artist_pod->field('post_title');
            }
        }

        // Get album thumbnail
        $album_data = $song_pod->field('album');
        $thumbnail = '';
        if (!empty($album_data)) {
            $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
            $thumbnail = get_the_post_thumbnail_url($album_id, 'thumbnail');
        }

        // Calculate item price
        $item_license_price = ($item['license_type'] === 'non_exclusive') ? $license_price : 0;
        $item_nft_price = !empty($item['include_nft']) ? $nft_fee : 0;
        $item_total = $item_license_price + $item_nft_price;

        if ($item_total > 0) {
            $requires_payment = true;
        }

        $subtotal += $item_license_price;
        $nft_total += $item_nft_price;

        $items[] = [
            'song_id' => $song_id,
            'song_title' => $song_title,
            'artist_name' => $artist_name,
            'thumbnail' => $thumbnail,
            'license_type' => $item['license_type'],
            'license_type_label' => ($item['license_type'] === 'cc_by') ? 'CC-BY 4.0 (Free)' : 'Commercial License',
            'include_nft' => !empty($item['include_nft']),
            'wallet_address' => $item['wallet_address'] ?? '',
            'license_price' => $item_license_price,
            'nft_price' => $item_nft_price,
            'item_total' => $item_total,
            'permalink' => get_permalink($song_id)
        ];
    }

    return [
        'items' => $items,
        'item_count' => count($items),
        'subtotal' => $subtotal,
        'nft_total' => $nft_total,
        'total' => $subtotal + $nft_total,
        'requires_payment' => $requires_payment,
        'currency' => 'USD'
    ];
}

/**
 * Validate Cardano wallet address format
 */
function fml_validate_cardano_address($address) {
    // Basic validation: Cardano addresses start with addr1 (mainnet) or addr_test1 (testnet)
    // and are typically 58-103 characters
    if (empty($address)) {
        return false;
    }

    // Mainnet address (addr1...)
    if (preg_match('/^addr1[a-z0-9]{53,98}$/i', $address)) {
        return true;
    }

    // Testnet address (addr_test1...)
    if (preg_match('/^addr_test1[a-z0-9]{50,95}$/i', $address)) {
        return true;
    }

    return false;
}

/**
 * Migrate guest cart to user cart after login
 */
function fml_migrate_guest_cart_to_user($user_login, $user) {
    if (!isset($_COOKIE['fml_cart_session'])) {
        return;
    }

    $guest_key = 'fml_cart_guest_' . sanitize_key($_COOKIE['fml_cart_session']);
    $guest_cart = get_transient($guest_key);

    if (!empty($guest_cart) && is_array($guest_cart)) {
        $user_cart = get_user_meta($user->ID, 'fml_cart', true);
        $user_cart = is_array($user_cart) ? $user_cart : [];

        // Merge carts, avoiding duplicates
        foreach ($guest_cart as $guest_item) {
            $found = false;
            foreach ($user_cart as $user_item) {
                if ($user_item['song_id'] === $guest_item['song_id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $user_cart[] = $guest_item;
            }
        }

        update_user_meta($user->ID, 'fml_cart', $user_cart);
        delete_transient($guest_key);
    }
}
add_action('wp_login', 'fml_migrate_guest_cart_to_user', 10, 2);


/**
 * ============================================================================
 * REST API ENDPOINTS
 * ============================================================================
 */

add_action('rest_api_init', function() {
    // Get cart contents
    register_rest_route('FML/v1', '/cart', [
        'methods' => 'GET',
        'callback' => 'fml_rest_get_cart',
        'permission_callback' => '__return_true'
    ]);

    // Add item to cart
    register_rest_route('FML/v1', '/cart/add', [
        'methods' => 'POST',
        'callback' => 'fml_rest_add_to_cart',
        'permission_callback' => '__return_true'
    ]);

    // Update cart item
    register_rest_route('FML/v1', '/cart/update', [
        'methods' => 'POST',
        'callback' => 'fml_rest_update_cart_item',
        'permission_callback' => '__return_true'
    ]);

    // Remove item from cart
    register_rest_route('FML/v1', '/cart/remove/(?P<song_id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'fml_rest_remove_from_cart',
        'permission_callback' => '__return_true',
        'args' => [
            'song_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Clear cart
    register_rest_route('FML/v1', '/cart/clear', [
        'methods' => 'DELETE',
        'callback' => 'fml_rest_clear_cart',
        'permission_callback' => '__return_true'
    ]);

    // Create checkout session for cart
    register_rest_route('FML/v1', '/cart/checkout', [
        'methods' => 'POST',
        'callback' => 'fml_rest_cart_checkout',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
});

/**
 * REST: Get cart contents
 */
function fml_rest_get_cart(WP_REST_Request $request) {
    $summary = fml_cart_get_summary();

    return new WP_REST_Response([
        'success' => true,
        'data' => $summary
    ], 200);
}

/**
 * REST: Add item to cart
 */
function fml_rest_add_to_cart(WP_REST_Request $request) {
    $song_id = intval($request->get_param('song_id'));
    $license_type = sanitize_text_field($request->get_param('license_type') ?? 'cc_by');
    $include_nft = (bool) $request->get_param('include_nft');
    $wallet_address = sanitize_text_field($request->get_param('wallet_address') ?? '');

    if (!$song_id) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Song ID is required'
        ], 400);
    }

    $result = fml_cart_add_item($song_id, $license_type, $include_nft, $wallet_address);
    $status = $result['success'] ? 200 : 400;

    if ($result['success']) {
        $result['data'] = fml_cart_get_summary();
    }

    return new WP_REST_Response($result, $status);
}

/**
 * REST: Update cart item
 */
function fml_rest_update_cart_item(WP_REST_Request $request) {
    $song_id = intval($request->get_param('song_id'));
    $license_type = $request->get_param('license_type');
    $include_nft = $request->get_param('include_nft');
    $wallet_address = $request->get_param('wallet_address');

    if (!$song_id) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Song ID is required'
        ], 400);
    }

    // Only pass non-null values
    $result = fml_cart_update_item(
        $song_id,
        $license_type !== null ? sanitize_text_field($license_type) : null,
        $include_nft !== null ? (bool) $include_nft : null,
        $wallet_address !== null ? sanitize_text_field($wallet_address) : null
    );

    $status = $result['success'] ? 200 : 400;

    if ($result['success']) {
        $result['data'] = fml_cart_get_summary();
    }

    return new WP_REST_Response($result, $status);
}

/**
 * REST: Remove item from cart
 */
function fml_rest_remove_from_cart(WP_REST_Request $request) {
    $song_id = intval($request->get_param('song_id'));

    $result = fml_cart_remove_item($song_id);
    $status = $result['success'] ? 200 : 400;

    if ($result['success']) {
        $result['data'] = fml_cart_get_summary();
    }

    return new WP_REST_Response($result, $status);
}

/**
 * REST: Clear cart
 */
function fml_rest_clear_cart(WP_REST_Request $request) {
    $result = fml_cart_clear();

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Cart cleared',
        'data' => fml_cart_get_summary()
    ], 200);
}

/**
 * REST: Create Stripe checkout session for cart
 */
function fml_rest_cart_checkout(WP_REST_Request $request) {
    $summary = fml_cart_get_summary();

    if (empty($summary['items'])) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Cart is empty'
        ], 400);
    }

    // Get checkout form data
    $licensee_name = sanitize_text_field($request->get_param('licensee_name') ?? '');
    $project_name = sanitize_text_field($request->get_param('project_name') ?? '');
    $usage_description = sanitize_text_field($request->get_param('usage_description') ?? '');

    // If no payment required (all CC-BY without NFT), redirect to free checkout
    if (!$summary['requires_payment']) {
        // Process free CC-BY licenses directly
        $result = fml_process_free_cart_checkout($summary, $licensee_name, $project_name, $usage_description);
        return new WP_REST_Response($result, $result['success'] ? 200 : 500);
    }

    // Create Stripe checkout session for cart
    $result = fml_create_cart_stripe_checkout($summary, $licensee_name, $project_name, $usage_description);

    $status = $result['success'] ? 200 : 500;
    return new WP_REST_Response($result, $status);
}

/**
 * Process free cart checkout (CC-BY licenses without NFT)
 */
function fml_process_free_cart_checkout($summary, $licensee_name, $project_name, $usage_description) {
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $licenses_created = [];

    foreach ($summary['items'] as $item) {
        // Only process CC-BY licenses without NFT (truly free)
        if ($item['license_type'] !== 'cc_by' || $item['include_nft']) {
            continue;
        }

        $song_id = $item['song_id'];

        // Generate CC-BY license PDF
        $license_result = fml_generate_ccby_license_pdf(
            $song_id,
            $item['song_title'],
            $item['artist_name'],
            $licensee_name ?: $user->display_name,
            $project_name
        );

        if (!$license_result['success']) {
            error_log("Failed to generate CC-BY license for song {$song_id}: " . ($license_result['error'] ?? 'Unknown error'));
            continue;
        }

        // Create license record
        $pod = pods('license');
        $license_data = [
            'user' => $user_id,
            'song' => $song_id,
            'datetime' => current_time('mysql'),
            'license_url' => $license_result['url'],
            'licensor' => $licensee_name ?: $user->display_name,
            'project' => $project_name,
            'description_of_usage' => $usage_description,
            'legal_name' => $licensee_name ?: $user->display_name,
            'license_type' => 'cc_by'
        ];

        $new_license_id = $pod->add($license_data);
        if ($new_license_id) {
            wp_update_post([
                'ID' => $new_license_id,
                'post_status' => 'publish'
            ]);
            $licenses_created[] = $new_license_id;
        }
    }

    // Clear cart
    fml_cart_clear();

    return [
        'success' => true,
        'message' => 'Free licenses generated successfully',
        'licenses_created' => count($licenses_created),
        'redirect_url' => home_url('/account/my-licenses/?checkout=success')
    ];
}

/**
 * Generate CC-BY license PDF (wrapper for existing function in licensing.php)
 */
function fml_generate_ccby_license_pdf($song_id, $song_name, $artist_name, $licensee_name, $project_name) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

    $currentDateTime = gmdate('Y-m-d\TH:i:s\Z');
    $custom_logo_id = get_theme_mod('custom_logo');
    $sitelogo = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';

    $mpdf = new \Mpdf\Mpdf();
    $mpdf->AddPage();
    $mpdf->WriteHTML(''
        . '<style> a {color: #277acc; text-decoration: none;}</style>'
        . '<body style="font-family: sans-serif"><div style="width: 75%; text-align: center; margin: auto;"><img src="' . $sitelogo . '" /></div>'
        . '<div style="width: 100%; text-align: center;"><h2>Statement of Licensure</h2></div>'
        . '<div>I, '
        . '<strong><em>' . esc_html($licensee_name) . '</em></strong> '
        . 'hereby agree to the license terms herein, as of ' . $currentDateTime . ' UTC, '
        . 'for the song <strong><em>' . esc_html($song_name) . '</em></strong> '
        . 'created by <strong><em>' . esc_html($artist_name) . '</em></strong> for use in the project <strong><em>' . esc_html($project_name) . '</em></strong>.'
        . '</div><br />'
        . '<div style="width: 100%; text-align: center;"><img alt="" src="/wp-content/uploads/2020/05/cc.svg"><img alt="" src="/wp-content/uploads/2020/04/by.svg"></div>'
        . '<div style="width: 100%; text-align: center;"><h1>Attribution 4.0 International</h1></div>'
        . '<br>'
        . '<div style="width: 100%; text-align: center;"><em>This is a human-readable summary of the license that follows:</em></div>'
        . '<br>'
        . '<div style="width: 100%; text-align: center;"><h3>You are free to:</h3></div>'
        . '<ul><li><strong>Share</strong> - Copy and redistribute the material in any medium or format</li>'
        . '<li><strong>Adapt</strong> - Remix, transform, and build upon the material for any purpose, even commercially.</li></ul>'
        . '<div style="width: 100%; text-align: center;">The licensor cannot revoke these freedoms as long as you follow the license terms.</div>'
        . '<br>'
        . '<div style="width: 100%; text-align: center;"><h3>When doing so, you must comply with these terms:</h3></div>'
        . '<ul><li><strong>Attribution</strong> - You must give appropriate credit, provide a link to the license, and indicate if changes were made.</li>'
        . '<li><strong>No Additional Restrictions</strong> - You may not apply legal terms or technological measures that legally restrict others from doing anything the license permits.</li></ul>'
        . '</body>');

    // Add CC-BY legal text pages
    $cc_pdf_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/2020/04/Creative-Commons-—-Attribution-4.0-International-—-CC-BY-4.0.pdf';
    if (file_exists($cc_pdf_path)) {
        $pagecount = $mpdf->SetSourceFile($cc_pdf_path);
        for ($i = 1; $i <= $pagecount; $i++) {
            $mpdf->AddPage();
            $import_page = $mpdf->ImportPage($i);
            $mpdf->UseTemplate($import_page);
        }
    }

    $filename = sanitize_file_name("{$artist_name}_{$song_name}_CCBY40_{$currentDateTime}.pdf");
    $tmpPath = tempnam(sys_get_temp_dir(), 'pdf_');
    $mpdf->Output($tmpPath, 'F');

    // Upload to AWS
    require_once get_stylesheet_directory() . "/php/aws/aws-autoloader.php";

    $client = new Aws\S3\S3Client([
        'version' => '2006-03-01',
        'region' => FML_AWS_REGION,
        'endpoint' => FML_AWS_HOST,
        'credentials' => [
            'key' => FML_AWS_KEY,
            'secret' => FML_AWS_SECRET_KEY,
        ]
    ]);

    $bucket = 'fml-licenses';
    try {
        $result = $client->putObject([
            'Bucket' => $bucket,
            'Key' => $filename,
            'SourceFile' => $tmpPath,
            'ACL' => 'public-read',
        ]);
        $url = $result['ObjectURL'];
        unlink($tmpPath);
        return ['success' => true, 'url' => $url, 'filename' => $filename];
    } catch (Exception $e) {
        unlink($tmpPath);
        return ['success' => false, 'error' => 'AWS upload failed: ' . $e->getMessage()];
    }
}
