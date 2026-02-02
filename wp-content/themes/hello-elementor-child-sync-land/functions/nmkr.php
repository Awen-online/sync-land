<?php


add_action('wp_ajax_mint_nft', 'mint_nft_callback');
add_action('wp_ajax_nopriv_mint_nft', 'mint_nft_callback'); // Allow non-logged-in users

/**
 * NMKR NFT Minting Integration (Safe Version)
 * Uses constants from wp-config.php: FML_NMKR_API_KEY, FML_NMKR_PROJECT_UID, FML_NMKR_POLICY_ID, FML_NMKR_API_URL
 * Fails gracefully if constants are missing
 */

/**
 * Get NMKR credentials safely
 */
function fml_get_nmkr_credentials() {
    $missing = [];

    $api_key = defined('FML_NMKR_API_KEY') ? FML_NMKR_API_KEY : null;
    $project_uid = defined('FML_NMKR_PROJECT_UID') ? FML_NMKR_PROJECT_UID : null;
    $policy_id = defined('FML_NMKR_POLICY_ID') ? FML_NMKR_POLICY_ID : null;
    $api_url = defined('FML_NMKR_API_URL') ? FML_NMKR_API_URL : null;

    if (!$api_key) $missing[] = 'FML_NMKR_API_KEY';
    if (!$project_uid) $missing[] = 'FML_NMKR_PROJECT_UID';
    if (!$policy_id) $missing[] = 'FML_NMKR_POLICY_ID';
    if (!$api_url) $missing[] = 'FML_NMKR_API_URL';

    if (!empty($missing)) {
        // Return error array instead of fatal error
        return [
            'success' => false,
            'error' => 'Missing NMKR constants: ' . implode(', ', $missing)
        ];
    }

    return [
        'success' => true,
        'api_key' => $api_key,
        'project_uid' => $project_uid,
        'policy_id' => $policy_id,
        'api_url' => rtrim($api_url, '/')
    ];
}

add_action('wp_ajax_mint_nft', 'mint_nft_callback');
add_action('wp_ajax_nopriv_mint_nft', 'mint_nft_callback');

function mint_nft_callback() {
    check_ajax_referer('mint_nft_nonce', 'nonce');

    // Step 1: Check NMKR credentials
    $creds = fml_get_nmkr_credentials();
    if (!$creds['success']) {
        wp_send_json_error(['message' => $creds['error']]);
        return;
    }

    // Step 2: Get post info
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(['message' => 'No post ID provided']);
        return;
    }

    $title = sanitize_text_field(get_the_title($post_id));
    $description = sanitize_text_field(get_post_field('post_content', $post_id));

    // Step 3: Determine image URL
    $image_url = get_the_post_thumbnail_url($post_id, 'full');

    // Fallback if missing or not publicly accessible
    if (empty($image_url) || !fml_url_is_accessible($image_url)) {
        $image_url = 'https://www.sync.land/wp-content/uploads/2024/06/cropped-SyncLand-Logo-optimized-150x150.png';
        error_log("Post $post_id missing or inaccessible thumbnail. Using default image: $image_url");
    } else {
        error_log("Post $post_id thumbnail URL: $image_url");
    }

    // Step 4: Build metadata (simplified example)
    $token_name = uniqid('sync-');

    $metadata = [
        '721' => [
            $creds['policy_id'] => [
                $token_name => [
                    'name' => $title,
                    'image' => $image_url,
                    'mediaType' => 'image/png',
                    'description' => [$description],
                ]
            ],
            'version' => '1.0'
        ]
    ];

    // Step 5: Prepare NMKR payload
    $data = [
        'nftName' => $token_name,
        'contentType' => 'image/png',
        'fileAsUrl' => $image_url,
        'previewFileAsUrl' => $image_url, // REQUIRED by NMKR
        'metadata' => $metadata
    ];

    error_log("Sending NFT payload to NMKR: " . json_encode($data));

    // Step 6: Call NMKR API
    $ch = curl_init($creds['api_url'] . "/v2/UploadNft/{$creds['project_uid']}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $creds['api_key'],
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 201) {
        wp_send_json_success(['message' => 'NFT uploaded successfully', 'data' => json_decode($response, true)]);
    } else {
        wp_send_json_error([
            'message' => 'Upload failed',
            'http_code' => $http_code,
            'response' => $response,
            'curl_error' => $curl_error
        ]);
    }
}

/**
 * Helper function to check if a URL is publicly accessible
 */
function fml_url_is_accessible($url) {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200') !== false;
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
        'previewFileAsUrl' => $image_url, // Add this
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