<?php

/**
 * NMKR NFT Minting Integration
 *
 * Credentials are loaded from wp-config.php constants:
 * - FML_NMKR_API_KEY
 * - FML_NMKR_PROJECT_UID
 * - FML_NMKR_POLICY_ID
 * - FML_NMKR_API_URL
 */

add_action('wp_ajax_mint_nft', 'mint_nft_callback');
add_action('wp_ajax_nopriv_mint_nft', 'mint_nft_callback'); // Allow non-logged-in users

function mint_nft_callback() {
    check_ajax_referer('mint_nft_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(['message' => 'No post ID provided']);
        return;
    }

    $title = sanitize_text_field(get_the_title($post_id));
    $description = sanitize_text_field(get_post_field('post_content', $post_id));
    $image_url = esc_url(get_the_post_thumbnail_url($post_id, 'full'));

    error_log("Post ID: $post_id, Title: '$title', Description: '$description', Image: '$image_url'");

    if (empty($title)) {
        wp_send_json_error(['message' => 'Missing post title']);
        return;
    }

    if (empty($image_url)) {
        $image_url = 'https://www.sync.land/wp-content/uploads/2024/06/cropped-SyncLand-Logo-optimized-150x150.png';
        error_log("No featured image found, using default: $image_url");
    }

    // Load credentials from wp-config.php constants
    $api_key = FML_NMKR_API_KEY;
    $project_uid = FML_NMKR_PROJECT_UID;
    $policy_id = FML_NMKR_POLICY_ID;
    $url = FML_NMKR_API_URL . "/v2/UploadNft/{$project_uid}";
    $token_name = uniqid('sync-land');

    $metadata = [
        '721' => [
            $policy_id => [
                $token_name => [
                    'name' => 'falling_sync',
                    'image' => $image_url, // Single reference for CIP-25
                    'mediaType' => 'image/png',
                    'description' => [$description],
                    'Title' => $title,
                    'Usage Duration' => 'Full Duration',
                    'Marketplace Owner' => 'Awen LLC',
                    'License PDF' => 'https://link.to.pdf',
                    'Publisher' => 'Cullah',
                    'Marketplace' => 'Sync.Land',
                    'Territory' => 'Worldwide',
                    'Composer' => 'Cullah',
                    'Licensor' => 'Cullah',
                    'Artist' => 'Cullah',
                    'Website' => 'https://awen.online/',
                    'Media Types' => 'All Media',
                    'Fee' => '1000 ADA',
                    'Licensee' => 'Nike Hoskinson',
                    'Term' => 'Perpetual',
                    'Description' => 'Master Use and Sync NFT for Cullah - Falling',
                    'Marketplace URL' => 'https://sync.land',
                    'Composition/Recording' => 'Falling',
                    'License Description' => 'The License Description goes here'
                ]
            ],
            'version' => '1.0'
        ]
    ];

    $data = [
        'nftName' => $token_name,
        'contentType' => 'image/png',
        'fileAsUrl' => $image_url, // Primary file reference
        'metadata' => $metadata
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);

    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        wp_send_json_success(['message' => 'NFT uploaded successfully!', 'data' => $result]);
    } else {
        rewind($verbose);
        $verbose_log = stream_get_contents($verbose);
        fclose($verbose);

        wp_send_json_error([
            'message' => 'Upload failed',
            'error' => $response,
            'http_code' => $http_code,
            'curl_error' => $curl_error,
            'curl_verbose' => $verbose_log
        ]);
    }
}


add_shortcode('nmkr_mint', function(){
    return '<form id="nft-form">
        <input type="hidden" name="post_id" value="'.get_the_ID().'">
        <button type="button" id="mint-nft-btn">Mint NFT</button>
    </form>';
});


add_shortcode('nmkr_pay', function() {
    ob_start();
    ?>
    <img src="https://studio.nmkr.io/images/buttons/paybutton_1_1.svg" onclick="javascript:openPaymentWindow()">

    <script type="text/javascript">
        function openPaymentWindow() {
            const paymentUrl = "https://pay.preprod.nmkr.io/?p=ba6643e3b89a49859d837366969a524d&c=1";

            // Specify the popup width and height
            const popupWidth = 500;
            const popupHeight = 700;

            // Calculate the center of the screen
            const left = window.top.outerWidth / 2 + window.top.screenX - ( popupWidth / 2);
            const top = window.top.outerHeight / 2 + window.top.screenY - ( popupHeight / 2);

            const popup =  window.open(paymentUrl, "NFT-MAKER PRO Payment Gateway",  `popup=1, location=1, width=${popupWidth}, height=${popupHeight}, left=${left}, top=${top}`);

            // Show dim background
            document.body.style = "background: rgba(0, 0, 0, 0.5)";

            // Continuously check whether the popup has been closed
            const backgroundCheck = setInterval(function () {
                if(popup.closed) {
                    clearInterval(backgroundCheck);

                    console.log("Popup closed");

                    // Remove dim background
                    document.body.style = "";
                }
            }, 1000);
        }
    </script>


    <?php
    return ob_get_clean();
});


/**
 * ============================================================================
 * LICENSE NFT MINTING
 * ============================================================================
 * Mint a license as an NFT with CIP-25 compliant metadata
 */

/**
 * Mint a license as an NFT
 *
 * @param int    $license_id     The license post ID
 * @param string $wallet_address The recipient's Cardano wallet address
 * @return array Result with success status and data/error
 */
function fml_mint_license_nft($license_id, $wallet_address = '') {
    // Validate license exists
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return ['success' => false, 'error' => 'License not found'];
    }

    // Get license data
    $license_url = $license_pod->field('license_url');
    $licensor = $license_pod->field('licensor');
    $project = $license_pod->field('project');
    $datetime = $license_pod->field('datetime');
    $legal_name = $license_pod->field('legal_name');
    $description_of_usage = $license_pod->field('description_of_usage');

    // Get related song data
    $song_data = $license_pod->field('song');
    if (empty($song_data)) {
        return ['success' => false, 'error' => 'No song associated with license'];
    }

    $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
    $song_pod = pods('song', $song_id);

    if (!$song_pod || !$song_pod->exists()) {
        return ['success' => false, 'error' => 'Associated song not found'];
    }

    $song_title = $song_pod->field('post_title');

    // Get artist from song
    $artist_data = $song_pod->field('artist');
    $artist_name = 'Unknown Artist';
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    // Get song image for NFT visual
    $song_image = get_the_post_thumbnail_url($song_id, 'full');
    if (empty($song_image)) {
        // Fallback to Sync.Land logo
        $song_image = 'https://www.sync.land/wp-content/uploads/2024/06/cropped-SyncLand-Logo-optimized-150x150.png';
    }

    // Load NMKR credentials from wp-config.php
    $api_key = FML_NMKR_API_KEY;
    $project_uid = FML_NMKR_PROJECT_UID;
    $policy_id = FML_NMKR_POLICY_ID;
    $api_url = FML_NMKR_API_URL . "/v2/UploadNft/{$project_uid}";

    // Generate unique token name
    $token_name = 'SyncLicense_' . $license_id . '_' . time();

    // Format datetime for display
    $issue_date = !empty($datetime) ? date('Y-m-d', strtotime($datetime)) : date('Y-m-d');

    // Build CIP-25 compliant metadata
    $metadata = [
        '721' => [
            $policy_id => [
                $token_name => [
                    // Required CIP-25 fields
                    'name' => "Sync License: {$artist_name} - {$song_title}",
                    'image' => $song_image,
                    'mediaType' => 'image/png',

                    // License-specific metadata
                    'License PDF' => $license_url,
                    'License Type' => 'CC-BY 4.0',
                    'Issue Date' => $issue_date,

                    // Song/Artist info
                    'Song' => $song_title,
                    'Artist' => $artist_name,
                    'Composer' => $artist_name,

                    // Parties
                    'Licensor' => $licensor,
                    'Licensee' => $legal_name ?: $licensor,

                    // Terms
                    'Project' => $project,
                    'Usage Description' => $description_of_usage ?: 'General use',
                    'Territory' => 'Worldwide',
                    'Term' => 'Perpetual',
                    'Media Types' => 'All Media',

                    // Marketplace info
                    'Marketplace' => 'Sync.Land',
                    'Marketplace URL' => 'https://sync.land',
                    'Marketplace Owner' => 'Awen LLC',

                    // Description array for longer text
                    'description' => [
                        "Music sync license NFT for '{$song_title}' by {$artist_name}.",
                        "Licensed under Creative Commons Attribution 4.0 International.",
                        "Project: {$project}"
                    ]
                ]
            ],
            'version' => '1.0'
        ]
    ];

    // Prepare NMKR request
    $data = [
        'nftName' => $token_name,
        'contentType' => 'image/png',
        'fileAsUrl' => $song_image,
        'metadata' => $metadata
    ];

    // Add receiver address if provided
    if (!empty($wallet_address)) {
        $data['receiverAddress'] = $wallet_address;
    }

    // Make API request
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 201) {
        $result = json_decode($response, true);

        // Update license pod with NFT data
        $license_pod->save([
            'nft_status' => 'minted',
            'nft_asset_id' => $result['nftId'] ?? $token_name,
            'nft_transaction_hash' => $result['transactionId'] ?? '',
            'nft_minted_at' => current_time('mysql'),
            'wallet_address' => $wallet_address
        ]);

        return [
            'success' => true,
            'message' => 'License NFT minted successfully',
            'data' => [
                'nft_id' => $result['nftId'] ?? $token_name,
                'transaction_id' => $result['transactionId'] ?? null,
                'token_name' => $token_name,
                'license_id' => $license_id
            ]
        ];
    } else {
        // Update license status to failed
        $license_pod->save([
            'nft_status' => 'failed'
        ]);

        error_log("NMKR License NFT mint failed: HTTP {$http_code} - {$response}");

        return [
            'success' => false,
            'error' => 'NFT minting failed',
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => $curl_error
        ];
    }
}

/**
 * AJAX handler for minting license NFT
 */
add_action('wp_ajax_mint_license_nft', 'fml_mint_license_nft_ajax');

function fml_mint_license_nft_ajax() {
    check_ajax_referer('mint_license_nft_nonce', 'nonce');

    $license_id = isset($_POST['license_id']) ? intval($_POST['license_id']) : 0;
    $wallet_address = isset($_POST['wallet_address']) ? sanitize_text_field($_POST['wallet_address']) : '';

    if (!$license_id) {
        wp_send_json_error(['message' => 'No license ID provided']);
        return;
    }

    // Validate wallet address format (basic Cardano address check)
    if (!empty($wallet_address) && !preg_match('/^addr[a-z0-9_]+$/i', $wallet_address)) {
        wp_send_json_error(['message' => 'Invalid Cardano wallet address format']);
        return;
    }

    $result = fml_mint_license_nft($license_id, $wallet_address);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * REST API endpoint for minting license NFT
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/mint-nft', [
        'methods' => 'POST',
        'callback' => 'fml_mint_license_nft_rest',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'wallet_address' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);
});

function fml_mint_license_nft_rest(WP_REST_Request $request) {
    $license_id = $request->get_param('id');
    $wallet_address = $request->get_param('wallet_address') ?? '';

    // Validate user owns this license
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return new WP_REST_Response(['success' => false, 'error' => 'License not found'], 404);
    }

    $license_user = $license_pod->field('user');
    $license_user_id = is_array($license_user) ? $license_user['ID'] : $license_user;

    if ($license_user_id != get_current_user_id() && !current_user_can('manage_options')) {
        return new WP_REST_Response(['success' => false, 'error' => 'Unauthorized'], 403);
    }

    $result = fml_mint_license_nft($license_id, $wallet_address);

    $status = $result['success'] ? 200 : 500;
    return new WP_REST_Response($result, $status);
}

/**
 * Get NFT status for a license
 */
add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/nft-status', [
        'methods' => 'GET',
        'callback' => 'fml_get_license_nft_status',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
});

function fml_get_license_nft_status(WP_REST_Request $request) {
    $license_id = $request->get_param('id');

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return new WP_REST_Response(['success' => false, 'error' => 'License not found'], 404);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'license_id' => $license_id,
            'nft_status' => $license_pod->field('nft_status') ?: 'none',
            'nft_asset_id' => $license_pod->field('nft_asset_id') ?: null,
            'nft_transaction_hash' => $license_pod->field('nft_transaction_hash') ?: null,
            'nft_minted_at' => $license_pod->field('nft_minted_at') ?: null,
            'wallet_address' => $license_pod->field('wallet_address') ?: null
        ]
    ], 200);
}