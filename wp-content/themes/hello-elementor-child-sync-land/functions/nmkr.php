<?php
/**
 * NMKR NFT Minting Integration for Sync.Land
 *
 * Handles NFT minting via NMKR API with support for:
 * - Preprod (test) and Mainnet (live) environments
 * - API keys managed in WordPress Admin > Settings > Sync.Land Licensing
 * - Backwards compatible with wp-config.php constants
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * NMKR API KEY MANAGEMENT
 * ============================================================================
 */

/**
 * Get current NMKR mode (preprod or mainnet)
 */
function fml_get_nmkr_mode() {
    return get_option('fml_nmkr_mode', 'preprod');
}

/**
 * Check if NMKR is in mainnet (live) mode
 */
function fml_nmkr_is_mainnet() {
    return fml_get_nmkr_mode() === 'mainnet';
}

/**
 * Get NMKR API URL based on current mode
 */
function fml_get_nmkr_api_url() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $url = get_option('fml_nmkr_mainnet_api_url', 'https://studio-api.nmkr.io');
    } else {
        $url = get_option('fml_nmkr_preprod_api_url', 'https://studio-api.preprod.nmkr.io');
    }

    // Fallback to constant if option is empty
    if (empty($url) && defined('FML_NMKR_API_URL')) {
        $url = FML_NMKR_API_URL;
    }

    return rtrim($url, '/');
}

/**
 * Get active NMKR API key based on current mode
 */
function fml_get_nmkr_api_key() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $key = get_option('fml_nmkr_mainnet_api_key', '');
    } else {
        $key = get_option('fml_nmkr_preprod_api_key', '');
    }

    // Fallback to constant if option is empty
    if (empty($key) && defined('FML_NMKR_API_KEY')) {
        $key = FML_NMKR_API_KEY;
    }

    return $key;
}

/**
 * Get active NMKR project UID based on current mode
 */
function fml_get_nmkr_project_uid() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $uid = get_option('fml_nmkr_mainnet_project_uid', '');
    } else {
        $uid = get_option('fml_nmkr_preprod_project_uid', '');
    }

    // Fallback to constant if option is empty
    if (empty($uid) && defined('FML_NMKR_PROJECT_UID')) {
        $uid = FML_NMKR_PROJECT_UID;
    }

    return $uid;
}

/**
 * Get active NMKR policy ID based on current mode
 */
function fml_get_nmkr_policy_id() {
    $mode = fml_get_nmkr_mode();

    if ($mode === 'mainnet') {
        $id = get_option('fml_nmkr_mainnet_policy_id', '');
    } else {
        $id = get_option('fml_nmkr_preprod_policy_id', '');
    }

    // Fallback to constant if option is empty
    if (empty($id) && defined('FML_NMKR_POLICY_ID')) {
        $id = FML_NMKR_POLICY_ID;
    }

    return $id;
}

/**
 * Check if NMKR is properly configured
 */
function fml_nmkr_is_configured() {
    $api_key = fml_get_nmkr_api_key();
    $project_uid = fml_get_nmkr_project_uid();
    return !empty($api_key) && !empty($project_uid);
}

/**
 * Get NMKR credentials safely (updated to use new helper functions)
 */
function fml_get_nmkr_credentials() {
    $missing = [];

    $api_key = fml_get_nmkr_api_key();
    $project_uid = fml_get_nmkr_project_uid();
    $policy_id = fml_get_nmkr_policy_id();
    $api_url = fml_get_nmkr_api_url();

    if (empty($api_key)) $missing[] = 'API Key';
    if (empty($project_uid)) $missing[] = 'Project UID';
    if (empty($policy_id)) $missing[] = 'Policy ID';
    if (empty($api_url)) $missing[] = 'API URL';

    if (!empty($missing)) {
        return [
            'success' => false,
            'error' => 'Missing NMKR configuration: ' . implode(', ', $missing),
            'mode' => fml_get_nmkr_mode()
        ];
    }

    return [
        'success' => true,
        'api_key' => $api_key,
        'project_uid' => $project_uid,
        'policy_id' => $policy_id,
        'api_url' => $api_url,
        'mode' => fml_get_nmkr_mode()
    ];
}

/**
 * Verify NMKR connection by making a test API call
 */
function fml_verify_nmkr_connection($api_key = null, $api_url = null) {
    if ($api_key === null) {
        $api_key = fml_get_nmkr_api_key();
    }
    if ($api_url === null) {
        $api_url = fml_get_nmkr_api_url();
    }

    if (empty($api_key)) {
        return [
            'success' => false,
            'error' => 'No API key provided'
        ];
    }

    // Make a simple API call to verify the key works (get account info)
    $response = wp_remote_get($api_url . '/v2/GetCounts', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => $response->get_error_message()
        ];
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code === 200) {
        // Determine if preprod or mainnet based on URL
        $is_preprod = strpos($api_url, 'preprod') !== false;

        return [
            'success' => true,
            'mode' => $is_preprod ? 'preprod' : 'mainnet',
            'message' => 'Connected successfully' . ($is_preprod ? ' (Preprod/Test)' : ' (Mainnet/Live)'),
            'data' => $body
        ];
    } else {
        $error_message = $body['message'] ?? $body['error'] ?? 'Invalid API key or connection failed';
        return [
            'success' => false,
            'error' => $error_message,
            'http_code' => $status_code
        ];
    }
}

/**
 * AJAX handler for NMKR connection verification
 */
add_action('wp_ajax_fml_verify_nmkr', 'fml_ajax_verify_nmkr_connection');

function fml_ajax_verify_nmkr_connection() {
    check_ajax_referer('fml_licensing_settings', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $key_type = sanitize_text_field($_POST['key_type'] ?? 'current');

    // Determine which credentials to test
    if ($key_type === 'preprod') {
        $api_key = sanitize_text_field($_POST['preprod_api_key'] ?? '');
        $api_url = 'https://studio-api.preprod.nmkr.io';
    } elseif ($key_type === 'mainnet') {
        $api_key = sanitize_text_field($_POST['mainnet_api_key'] ?? '');
        $api_url = 'https://studio-api.nmkr.io';
    } else {
        $api_key = fml_get_nmkr_api_key();
        $api_url = fml_get_nmkr_api_url();
    }

    if (empty($api_key)) {
        wp_send_json_error(['message' => 'No API key provided']);
    }

    $result = fml_verify_nmkr_connection($api_key, $api_url);

    if ($result['success']) {
        wp_send_json_success([
            'message' => $result['message'],
            'mode' => $result['mode']
        ]);
    } else {
        wp_send_json_error(['message' => $result['error']]);
    }
}

/**
 * ============================================================================
 * LEGACY AJAX HANDLERS
 * ============================================================================
 */

add_action('wp_ajax_mint_nft', 'mint_nft_callback');
add_action('wp_ajax_nopriv_mint_nft', 'mint_nft_callback'); // Allow non-logged-in users

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
 * This function now delegates to the enhanced IPFS-enabled minting function.
 *
 * @param int    $license_id     The license post ID
 * @param string $wallet_address The recipient's Cardano wallet address
 * @return array Result with success status and data/error
 */
function fml_mint_license_nft($license_id, $wallet_address = '') {
    // Delegate to the enhanced IPFS-enabled minting function
    return fml_mint_license_nft_with_ipfs($license_id, $wallet_address);
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
            'nft_ipfs_hash' => $license_pod->field('nft_ipfs_hash') ?: null,
            'nft_policy_id' => $license_pod->field('nft_policy_id') ?: null,
            'nft_asset_name' => $license_pod->field('nft_asset_name') ?: null,
            'wallet_address' => $license_pod->field('wallet_address') ?: null
        ]
    ], 200);
}


/**
 * ============================================================================
 * IPFS UPLOAD FOR LICENSE PDFs
 * ============================================================================
 */

/**
 * Upload a license PDF to IPFS via NMKR
 *
 * @param string $pdf_url The URL of the license PDF to upload
 * @param string $filename The filename to use on IPFS
 * @return array Result with IPFS hash or error
 */
function fml_upload_license_pdf_to_ipfs($pdf_url, $filename = '') {
    $creds = fml_get_nmkr_credentials();
    if (!$creds['success']) {
        return ['success' => false, 'error' => $creds['error']];
    }

    // Download PDF content
    $pdf_response = wp_remote_get($pdf_url, ['timeout' => 30]);
    if (is_wp_error($pdf_response)) {
        return ['success' => false, 'error' => 'Failed to download PDF: ' . $pdf_response->get_error_message()];
    }

    $pdf_content = wp_remote_retrieve_body($pdf_response);
    if (empty($pdf_content)) {
        return ['success' => false, 'error' => 'PDF content is empty'];
    }

    // Convert to base64
    $pdf_base64 = base64_encode($pdf_content);

    // Generate filename if not provided
    if (empty($filename)) {
        $filename = 'license_' . time() . '.pdf';
    }

    // Upload to IPFS via NMKR API
    $api_url = $creds['api_url'] . '/v2/UploadToIpfs';

    $data = [
        'fileFromBase64' => $pdf_base64,
        'fileName' => $filename,
        'mimeType' => 'application/pdf'
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($data),
        'timeout' => 60
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => 'IPFS upload failed: ' . $response->get_error_message()];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code == 200 || $http_code == 201) {
        return [
            'success' => true,
            'ipfs_hash' => $body['ipfsHash'] ?? $body['ipfs_hash'] ?? null,
            'ipfs_url' => 'ipfs://' . ($body['ipfsHash'] ?? $body['ipfs_hash'] ?? ''),
            'gateway_url' => 'https://ipfs.io/ipfs/' . ($body['ipfsHash'] ?? $body['ipfs_hash'] ?? '')
        ];
    } else {
        return [
            'success' => false,
            'error' => 'IPFS upload failed',
            'http_code' => $http_code,
            'response' => $body
        ];
    }
}


/**
 * ============================================================================
 * ENHANCED LICENSE NFT MINTING WITH IPFS
 * ============================================================================
 */

/**
 * Mint a license as an NFT with IPFS-hosted PDF
 *
 * @param int    $license_id     The license post ID
 * @param string $wallet_address The recipient's Cardano wallet address
 * @return array Result with success status and data/error
 */
function fml_mint_license_nft_with_ipfs($license_id, $wallet_address = '') {
    // Validate license exists
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return ['success' => false, 'error' => 'License not found'];
    }

    // Check NMKR credentials
    $creds = fml_get_nmkr_credentials();
    if (!$creds['success']) {
        return ['success' => false, 'error' => $creds['error']];
    }

    // Get license data
    $license_url = $license_pod->field('license_url');
    $licensor = $license_pod->field('licensor');
    $project = $license_pod->field('project');
    $datetime = $license_pod->field('datetime');
    $legal_name = $license_pod->field('legal_name');
    $license_type = $license_pod->field('license_type') ?: 'cc_by';

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

    // Upload license PDF to IPFS first
    $ipfs_filename = sanitize_file_name("SyncLicense_{$artist_name}_{$song_title}_{$license_id}.pdf");
    $ipfs_result = fml_upload_license_pdf_to_ipfs($license_url, $ipfs_filename);

    $ipfs_hash = null;
    $ipfs_url = null;

    if ($ipfs_result['success']) {
        $ipfs_hash = $ipfs_result['ipfs_hash'];
        $ipfs_url = $ipfs_result['ipfs_url'];
        error_log("License PDF uploaded to IPFS: {$ipfs_hash}");
    } else {
        error_log("IPFS upload failed for license {$license_id}: " . ($ipfs_result['error'] ?? 'Unknown error'));
        // Continue with minting using direct URL as fallback
    }

    // Get song image for NFT visual
    $album_data = $song_pod->field('album');
    $song_image = '';
    if (!empty($album_data)) {
        $album_id = is_array($album_data) ? $album_data['ID'] : $album_data;
        $song_image = get_the_post_thumbnail_url($album_id, 'full');
    }
    if (empty($song_image)) {
        $song_image = 'https://www.sync.land/wp-content/uploads/2024/06/cropped-SyncLand-Logo-optimized-150x150.png';
    }

    // Generate unique token name
    $token_name = 'SyncLicense_' . $license_id . '_' . time();

    // Format datetime for display
    $issue_date = !empty($datetime) ? date('Y-m-d', strtotime($datetime)) : date('Y-m-d');

    // Determine license type label
    $license_type_label = ($license_type === 'non_exclusive') ? 'Non-Exclusive Commercial' : 'CC-BY 4.0';

    // Build CIP-25 compliant metadata
    $metadata = [
        '721' => [
            $creds['policy_id'] => [
                $token_name => [
                    // Required CIP-25 fields
                    'name' => "Sync License: {$artist_name} - {$song_title}",
                    'image' => $song_image,
                    'mediaType' => 'image/png',

                    // Files array with license PDF
                    'files' => [
                        [
                            'name' => 'License PDF',
                            'src' => $ipfs_url ?: $license_url,
                            'mediaType' => 'application/pdf'
                        ]
                    ],

                    // License-specific metadata
                    'License Type' => $license_type_label,
                    'License URL' => $license_url,
                    'Issue Date' => $issue_date,

                    // Song/Artist info
                    'Song' => $song_title,
                    'Artist' => $artist_name,

                    // Parties
                    'Licensee' => $legal_name ?: $licensor,

                    // Terms
                    'Project' => $project ?: 'General Use',

                    // Marketplace info
                    'Marketplace' => 'Sync.Land',
                    'Blockchain Verified' => true,

                    // Description
                    'description' => [
                        "Music sync license NFT for '{$song_title}' by {$artist_name}.",
                        "License Type: {$license_type_label}",
                        "Verified on Cardano blockchain via Sync.Land"
                    ]
                ]
            ],
            'version' => '1.0'
        ]
    ];

    // Prepare NMKR MintAndSendSpecific request
    $mint_api_url = $creds['api_url'] . '/v2/MintAndSendSpecific/' . $creds['project_uid'];

    $mint_data = [
        'tokenname' => $token_name,
        'displayname' => "Sync License: {$artist_name} - {$song_title}",
        'previewImageNft' => [
            'mimetype' => 'image/png',
            'fileFromUrl' => $song_image
        ],
        'subfiles' => [
            [
                'subfile' => [
                    'mimetype' => 'application/pdf',
                    'fileFromUrl' => $license_url
                ]
            ]
        ],
        'metadataOverride' => $metadata,
        'receiveraddress' => $wallet_address,
        'count' => 1
    ];

    // Make NMKR API request
    $response = wp_remote_post($mint_api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $creds['api_key'],
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($mint_data),
        'timeout' => 120
    ]);

    if (is_wp_error($response)) {
        $license_pod->save(['nft_status' => 'failed']);
        return ['success' => false, 'error' => 'Minting request failed: ' . $response->get_error_message()];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code == 200 || $http_code == 201) {
        // Update license pod with NFT data
        $license_pod->save([
            'nft_status' => 'minted',
            'nft_asset_id' => $body['nftId'] ?? $token_name,
            'nft_transaction_hash' => $body['transactionId'] ?? '',
            'nft_minted_at' => current_time('mysql'),
            'nft_ipfs_hash' => $ipfs_hash,
            'nft_policy_id' => $creds['policy_id'],
            'nft_asset_name' => $token_name,
            'wallet_address' => $wallet_address
        ]);

        return [
            'success' => true,
            'message' => 'License NFT minted successfully',
            'data' => [
                'nft_id' => $body['nftId'] ?? $token_name,
                'transaction_id' => $body['transactionId'] ?? null,
                'token_name' => $token_name,
                'license_id' => $license_id,
                'ipfs_hash' => $ipfs_hash,
                'policy_id' => $creds['policy_id']
            ]
        ];
    } else {
        // Update license status to failed
        $license_pod->save(['nft_status' => 'failed']);

        error_log("NMKR License NFT mint failed: HTTP {$http_code} - " . json_encode($body));

        return [
            'success' => false,
            'error' => 'NFT minting failed',
            'http_code' => $http_code,
            'response' => $body
        ];
    }
}


/**
 * ============================================================================
 * LICENSE VERIFICATION ENDPOINT
 * ============================================================================
 */

add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/licenses/(?P<id>\d+)/verify', [
        'methods' => 'GET',
        'callback' => 'fml_verify_license_nft',
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

/**
 * Verify NFT status for a license
 */
function fml_verify_license_nft(WP_REST_Request $request) {
    $license_id = $request->get_param('id');

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return new WP_REST_Response(['success' => false, 'error' => 'License not found'], 404);
    }

    $nft_status = $license_pod->field('nft_status') ?: 'none';
    $nft_transaction_hash = $license_pod->field('nft_transaction_hash');
    $nft_ipfs_hash = $license_pod->field('nft_ipfs_hash');
    $nft_policy_id = $license_pod->field('nft_policy_id');
    $nft_asset_name = $license_pod->field('nft_asset_name');
    $license_type = $license_pod->field('license_type') ?: 'cc_by';

    // Determine verification status
    $is_verified = ($nft_status === 'minted' && !empty($nft_transaction_hash));

    // Build verification response
    $verification = [
        'license_id' => intval($license_id),
        'license_type' => $license_type,
        'license_type_label' => ($license_type === 'non_exclusive') ? 'Non-Exclusive Commercial' : 'CC-BY 4.0',
        'nft_verified' => $is_verified,
        'nft_status' => $nft_status,
        'verification_badge' => $is_verified ? 'NFT Verified' : 'Standard License'
    ];

    if ($is_verified) {
        $verification['blockchain'] = [
            'network' => 'Cardano',
            'transaction_hash' => $nft_transaction_hash,
            'policy_id' => $nft_policy_id,
            'asset_name' => $nft_asset_name,
            'explorer_url' => "https://cardanoscan.io/transaction/{$nft_transaction_hash}"
        ];

        if ($nft_ipfs_hash) {
            $verification['ipfs'] = [
                'hash' => $nft_ipfs_hash,
                'url' => "ipfs://{$nft_ipfs_hash}",
                'gateway_url' => "https://ipfs.io/ipfs/{$nft_ipfs_hash}"
            ];
        }
    }

    // Add license details
    $song_data = $license_pod->field('song');
    if (!empty($song_data)) {
        $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
        $song_pod = pods('song', $song_id);
        if ($song_pod && $song_pod->exists()) {
            $verification['song'] = [
                'id' => $song_id,
                'title' => $song_pod->field('post_title')
            ];

            $artist_data = $song_pod->field('artist');
            if (!empty($artist_data)) {
                $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
                $artist_pod = pods('artist', $artist_id);
                if ($artist_pod && $artist_pod->exists()) {
                    $verification['artist'] = [
                        'id' => $artist_id,
                        'name' => $artist_pod->field('post_title')
                    ];
                }
            }
        }
    }

    $verification['licensee'] = $license_pod->field('legal_name') ?: $license_pod->field('licensor');
    $verification['issue_date'] = $license_pod->field('datetime');
    $verification['license_url'] = $license_pod->field('license_url');

    return new WP_REST_Response([
        'success' => true,
        'data' => $verification
    ], 200);
}


/**
 * ============================================================================
 * NFT MINTING RETRY LOGIC
 * ============================================================================
 */

/**
 * Retry failed NFT minting
 */
function fml_retry_failed_nft_minting($license_id) {
    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return ['success' => false, 'error' => 'License not found'];
    }

    $nft_status = $license_pod->field('nft_status');
    if ($nft_status !== 'failed' && $nft_status !== 'pending') {
        return ['success' => false, 'error' => 'License NFT status is not failed or pending'];
    }

    $wallet_address = $license_pod->field('wallet_address');
    if (empty($wallet_address)) {
        return ['success' => false, 'error' => 'No wallet address on record'];
    }

    // Update status to pending
    $license_pod->save(['nft_status' => 'pending']);

    // Attempt minting with IPFS
    return fml_mint_license_nft_with_ipfs($license_id, $wallet_address);
}

/**
 * Admin action to retry NFT minting
 */
add_action('wp_ajax_fml_retry_nft_minting', 'fml_admin_retry_nft_minting');
function fml_admin_retry_nft_minting() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $license_id = intval($_POST['license_id'] ?? 0);
    if (!$license_id) {
        wp_send_json_error(['message' => 'License ID required']);
        return;
    }

    $result = fml_retry_failed_nft_minting($license_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}