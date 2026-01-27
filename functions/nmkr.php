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