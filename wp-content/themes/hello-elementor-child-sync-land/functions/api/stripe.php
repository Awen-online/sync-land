<?php
/**
 * Stripe Integration for Sync.Land
 *
 * Handles paid Non-Exclusive Sync Licenses via Stripe Checkout.
 *
 * License Types:
 * - CC-BY (Free) - Can be minted as NFT for blockchain verification (free)
 * - Non-Exclusive (Paid) - Commercial sync license purchased via Stripe
 *
 * Stripe API keys are managed in WordPress Admin > Settings > Sync.Land Licensing
 * Supports both Test and Live modes with easy switching.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * FALLBACK: Process checkout on success redirect (for local dev without webhooks)
 * ============================================================================
 */
add_action('template_redirect', 'fml_process_checkout_on_success_redirect');

function fml_process_checkout_on_success_redirect() {
    // Only run on the licenses page with payment=success
    if (!isset($_GET['payment']) || $_GET['payment'] !== 'success') {
        return;
    }

    if (!isset($_GET['session_id']) || empty($_GET['session_id'])) {
        return;
    }

    $session_id = sanitize_text_field($_GET['session_id']);

    // Check if we've already processed this session
    $processed_key = 'fml_processed_' . $session_id;

    if (get_transient($processed_key)) {
        // Already fully processed
        error_log("Session {$session_id} already processed, skipping");
        return;
    }

    error_log("=== FALLBACK: Processing checkout on success redirect ===");
    error_log("Session ID: {$session_id}");

    // Fetch session from Stripe
    $stripe_secret_key = fml_get_stripe_secret_key();
    if (empty($stripe_secret_key)) {
        error_log("Stripe not configured");
        return;
    }

    $response = wp_remote_get("https://api.stripe.com/v1/checkout/sessions/{$session_id}", [
        'headers' => [
            'Authorization' => 'Bearer ' . $stripe_secret_key
        ],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log("Failed to fetch session from Stripe: " . $response->get_error_message());
        return;
    }

    $session = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($session['error'])) {
        error_log("Stripe API error: " . json_encode($session['error']));
        return;
    }

    // Check payment status
    if ($session['payment_status'] !== 'paid') {
        error_log("Session {$session_id} payment status is not 'paid': " . $session['payment_status']);
        return;
    }

    error_log("Session retrieved from Stripe: " . json_encode($session['metadata'] ?? []));

    // Mark as processed BEFORE processing to prevent duplicates
    set_transient($processed_key, true, DAY_IN_SECONDS);

    // Process the checkout synchronously
    // This runs when user lands on success page - they see a loading state briefly
    try {
        fml_handle_checkout_completed($session);
        error_log("=== FALLBACK: Checkout processed successfully ===");

        // Clear the cart after successful processing
        if (is_user_logged_in()) {
            fml_cart_clear();
            error_log("Cart cleared for user " . get_current_user_id());
        }
    } catch (Exception $e) {
        error_log("FALLBACK: Error processing checkout: " . $e->getMessage());
        // Remove the processed marker so it can be retried
        delete_transient($processed_key);
    }
}

/**
 * ============================================================================
 * STRIPE KEY MANAGEMENT
 * ============================================================================
 */

/**
 * Get current Stripe mode (test or live)
 */
function fml_get_stripe_mode() {
    return get_option('fml_stripe_mode', 'test');
}

/**
 * Check if Stripe is in live mode
 */
function fml_stripe_is_live() {
    return fml_get_stripe_mode() === 'live';
}

/**
 * Get active Stripe secret key based on current mode
 * Falls back to wp-config.php constants for backwards compatibility
 */
function fml_get_stripe_secret_key() {
    $mode = fml_get_stripe_mode();

    if ($mode === 'live') {
        $key = get_option('fml_stripe_live_secret_key', '');
    } else {
        $key = get_option('fml_stripe_test_secret_key', '');
    }

    // Fallback to constant if option is empty
    if (empty($key) && defined('FML_STRIPE_SECRET_KEY')) {
        $key = FML_STRIPE_SECRET_KEY;
    }

    return $key;
}

/**
 * Get active Stripe publishable key based on current mode
 */
function fml_get_stripe_publishable_key() {
    $mode = fml_get_stripe_mode();

    if ($mode === 'live') {
        $key = get_option('fml_stripe_live_publishable_key', '');
    } else {
        $key = get_option('fml_stripe_test_publishable_key', '');
    }

    // Fallback to constant if option is empty
    if (empty($key) && defined('FML_STRIPE_PUBLISHABLE_KEY')) {
        $key = FML_STRIPE_PUBLISHABLE_KEY;
    }

    return $key;
}

/**
 * Get Stripe webhook secret
 */
function fml_get_stripe_webhook_secret() {
    $key = get_option('fml_stripe_webhook_secret', '');

    // Fallback to constant if option is empty
    if (empty($key) && defined('FML_STRIPE_WEBHOOK_SECRET')) {
        $key = FML_STRIPE_WEBHOOK_SECRET;
    }

    return $key;
}

/**
 * Check if Stripe is properly configured
 */
function fml_stripe_is_configured() {
    $secret_key = fml_get_stripe_secret_key();
    return !empty($secret_key);
}

/**
 * Verify Stripe connection by making a test API call
 */
function fml_verify_stripe_connection($secret_key = null) {
    if ($secret_key === null) {
        $secret_key = fml_get_stripe_secret_key();
    }

    if (empty($secret_key)) {
        return [
            'success' => false,
            'error' => 'No API key provided'
        ];
    }

    // Make a simple API call to verify the key works
    $response = wp_remote_get('https://api.stripe.com/v1/balance', [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key
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
        // Determine if test or live mode based on key prefix
        $is_test = strpos($secret_key, 'sk_test_') === 0;

        return [
            'success' => true,
            'mode' => $is_test ? 'test' : 'live',
            'message' => 'Connected successfully' . ($is_test ? ' (Test Mode)' : ' (Live Mode)')
        ];
    } else {
        $error_message = $body['error']['message'] ?? 'Invalid API key';
        return [
            'success' => false,
            'error' => $error_message
        ];
    }
}

/**
 * ============================================================================
 * STRIPE WEBHOOK HANDLER
 * ============================================================================
 */

add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/stripe/webhook', [
        'methods' => 'POST',
        'callback' => 'fml_stripe_webhook_handler',
        'permission_callback' => '__return_true' // Webhooks need to be publicly accessible
    ]);

    // Debug endpoint to manually reprocess a checkout (admin only)
    register_rest_route('FML/v1', '/stripe/reprocess-checkout', [
        'methods' => 'POST',
        'callback' => 'fml_reprocess_checkout_debug',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

/**
 * Debug endpoint to manually reprocess a checkout session
 * Usage: POST /wp-json/FML/v1/stripe/reprocess-checkout
 * Body: { "checkout_key": "fml_cart_xxxxx" } or { "session_id": "cs_xxx" }
 */
function fml_reprocess_checkout_debug(WP_REST_Request $request) {
    $checkout_key = $request->get_param('checkout_key');
    $session_id = $request->get_param('session_id');

    error_log("=== DEBUG: Manual checkout reprocess ===");
    error_log("checkout_key: " . ($checkout_key ?: 'not provided'));
    error_log("session_id: " . ($session_id ?: 'not provided'));

    // If we have a checkout_key, try to get the transient data
    if (!empty($checkout_key)) {
        $transient_data = get_transient($checkout_key);
        error_log("Transient data for {$checkout_key}: " . ($transient_data ? json_encode($transient_data) : 'NOT FOUND'));

        if (!$transient_data) {
            return new WP_REST_Response([
                'success' => false,
                'error' => "Transient {$checkout_key} not found or expired"
            ], 404);
        }

        // Create a mock session object
        $mock_session = [
            'id' => 'debug_' . time(),
            'payment_intent' => 'debug_pi_' . time(),
            'metadata' => [
                'type' => 'cart_checkout',
                'checkout_key' => $checkout_key,
                'user_id' => $transient_data['user_id'] ?? get_current_user_id(),
                'licensee_name' => $transient_data['licensee_name'] ?? '',
                'project_name' => $transient_data['project_name'] ?? ''
            ]
        ];

        error_log("Mock session: " . json_encode($mock_session));

        // Process the checkout
        fml_handle_cart_checkout_completed($mock_session);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Checkout reprocessed - check debug.log for details',
            'checkout_key' => $checkout_key
        ], 200);
    }

    // If we have a session_id, try to fetch from Stripe
    if (!empty($session_id)) {
        $stripe_secret_key = fml_get_stripe_secret_key();
        if (empty($stripe_secret_key)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Stripe not configured'
            ], 500);
        }

        // Fetch session from Stripe
        $response = wp_remote_get("https://api.stripe.com/v1/checkout/sessions/{$session_id}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $stripe_secret_key
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $response->get_error_message()
            ], 500);
        }

        $session = json_decode(wp_remote_retrieve_body($response), true);
        error_log("Stripe session: " . json_encode($session));

        if (isset($session['error'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $session['error']['message'] ?? 'Unknown error'
            ], 400);
        }

        // Process the checkout
        fml_handle_cart_checkout_completed($session);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Checkout reprocessed - check debug.log for details',
            'session_id' => $session_id,
            'metadata' => $session['metadata'] ?? []
        ], 200);
    }

    return new WP_REST_Response([
        'success' => false,
        'error' => 'Either checkout_key or session_id is required'
    ], 400);
}

/**
 * Handle incoming Stripe webhooks
 *
 * IMPORTANT: This handler must respond QUICKLY (< 5 seconds) to avoid timeouts.
 * Heavy processing (PDF generation, license creation, NFT minting) is deferred
 * to background tasks via wp_schedule_single_event().
 */
function fml_stripe_webhook_handler(WP_REST_Request $request) {
    // Get the raw body for signature verification
    $payload = $request->get_body();
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $event_id = 'unknown';

    // Verify webhook secret is configured
    $webhook_secret = fml_get_stripe_webhook_secret();
    if (empty($webhook_secret)) {
        error_log('Stripe webhook error: Webhook secret not configured');
        return new WP_REST_Response(['error' => 'Webhook not configured'], 500);
    }

    // Verify webhook signature
    try {
        $event = fml_verify_stripe_webhook($payload, $sig_header, $webhook_secret);
        $event_id = $event['id'] ?? 'unknown';
    } catch (Exception $e) {
        error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
        // Track the failed webhook
        if (function_exists('fml_track_webhook_event')) {
            fml_track_webhook_event('signature_failed', $event_id, 'failed', ['error' => $e->getMessage()]);
        }
        return new WP_REST_Response(['error' => 'Invalid signature'], 400);
    }

    // Track the webhook event
    if (function_exists('fml_track_webhook_event')) {
        fml_track_webhook_event($event['type'], $event_id, 'received');
    }

    // Handle the event - DEFER heavy processing to background
    switch ($event['type']) {
        case 'checkout.session.completed':
            $session = $event['data']['object'];
            // Store session data and schedule background processing
            fml_queue_checkout_processing($session, $event_id);
            break;

        case 'payment_intent.succeeded':
            $payment_intent = $event['data']['object'];
            fml_handle_payment_succeeded($payment_intent);
            break;

        case 'payment_intent.payment_failed':
            $payment_intent = $event['data']['object'];
            fml_handle_payment_failed($payment_intent);
            break;

        default:
            error_log('Unhandled Stripe event type: ' . $event['type']);
    }

    // Return 200 immediately - processing happens in background
    return new WP_REST_Response(['received' => true], 200);
}

/**
 * Queue checkout session for background processing
 *
 * This ensures webhook responds quickly while heavy work happens async.
 * Heavy processing (PDF generation, S3 upload) is handled by:
 * 1. WP Cron background task (production)
 * 2. Success page redirect fallback (local dev / if cron fails)
 */
function fml_queue_checkout_processing($session, $event_id) {
    $session_id = $session['id'];

    error_log("=== STRIPE WEBHOOK: checkout.session.completed ===");
    error_log("Session ID: {$session_id}");
    error_log("Session metadata: " . json_encode($session['metadata'] ?? []));

    // Store the session data in a transient for background/fallback processing
    set_transient('fml_webhook_session_' . $session_id, [
        'session' => $session,
        'event_id' => $event_id,
        'received_at' => current_time('mysql')
    ], HOUR_IN_SECONDS);

    // Schedule background processing (1 second delay)
    wp_schedule_single_event(time() + 1, 'fml_process_checkout_session_async', [$session_id]);

    // Try to trigger cron immediately
    spawn_cron();

    error_log("Checkout session {$session_id} queued for background processing");
    error_log("Note: If cron doesn't run, the success page redirect will process the checkout");

    // Track webhook as processing
    if (function_exists('fml_track_webhook_event')) {
        fml_track_webhook_event('checkout.session.completed', $event_id, 'processing', [
            'session_id' => $session_id
        ]);
    }
}

// Register the async processing action
add_action('fml_process_checkout_session_async', 'fml_process_checkout_session_background');

/**
 * Process checkout session in background
 */
function fml_process_checkout_session_background($session_id) {
    error_log("Processing checkout session {$session_id} in background");

    // Retrieve stored session data
    $data = get_transient('fml_webhook_session_' . $session_id);
    if (!$data) {
        error_log("Checkout session {$session_id} data not found in transient");
        return;
    }

    $session = $data['session'];
    $event_id = $data['event_id'];

    try {
        // Process the checkout
        fml_handle_checkout_completed($session);

        // Track success
        if (function_exists('fml_track_webhook_event')) {
            fml_track_webhook_event('checkout.session.completed', $event_id, 'completed', [
                'session_id' => $session_id
            ]);
        }

        // Log success
        if (function_exists('fml_log_event')) {
            fml_log_event('webhook', "Checkout session {$session_id} processed successfully", [], 'success');
        }

    } catch (Exception $e) {
        error_log("Error processing checkout session {$session_id}: " . $e->getMessage());

        // Track failure
        if (function_exists('fml_track_webhook_event')) {
            fml_track_webhook_event('checkout.session.completed', $event_id, 'failed', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
        }

        // Log error
        if (function_exists('fml_log_event')) {
            fml_log_event('webhook', "Checkout session {$session_id} processing failed", [
                'error' => $e->getMessage()
            ], 'error');
        }
    }

    // Clean up transient
    delete_transient('fml_webhook_session_' . $session_id);
}

/**
 * Verify Stripe webhook signature
 */
function fml_verify_stripe_webhook($payload, $sig_header, $secret) {
    if (empty($sig_header)) {
        throw new Exception('No signature header');
    }

    // Parse signature header
    $sig_parts = [];
    foreach (explode(',', $sig_header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $sig_parts[$kv[0]] = $kv[1];
        }
    }

    if (!isset($sig_parts['t']) || !isset($sig_parts['v1'])) {
        throw new Exception('Invalid signature format');
    }

    $timestamp = $sig_parts['t'];
    $signature = $sig_parts['v1'];

    // Check timestamp tolerance (5 minute window)
    if (abs(time() - $timestamp) > 300) {
        throw new Exception('Timestamp outside tolerance');
    }

    // Compute expected signature
    $signed_payload = $timestamp . '.' . $payload;
    $expected_signature = hash_hmac('sha256', $signed_payload, $secret);

    if (!hash_equals($expected_signature, $signature)) {
        throw new Exception('Signature mismatch');
    }

    return json_decode($payload, true);
}

/**
 * Handle completed checkout session - Create the paid license
 */
function fml_handle_checkout_completed($session) {
    $metadata = $session['metadata'] ?? [];

    // Route to appropriate handler based on checkout type
    if (isset($metadata['type'])) {
        if ($metadata['type'] === 'cart_checkout') {
            fml_handle_cart_checkout_completed($session);
            return;
        }

        if ($metadata['type'] !== 'non_exclusive_license') {
            return;
        }
    } else {
        return;
    }

    $song_id = intval($metadata['song_id'] ?? 0);
    $user_id = intval($metadata['user_id'] ?? 0);
    $licensee_name = $metadata['licensee_name'] ?? '';
    $project_name = $metadata['project_name'] ?? '';
    $usage_description = $metadata['usage_description'] ?? '';

    if ($song_id <= 0 || $user_id <= 0) {
        error_log("Stripe checkout completed but missing song_id or user_id");
        return;
    }

    // Get song and artist info
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        error_log("Stripe checkout: Song {$song_id} not found");
        return;
    }

    $song_name = $song_pod->field('post_title');
    $artist_data = $song_pod->field('artist');
    $artist_name = 'Unknown Artist';
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    // Generate PDF license for non-exclusive license
    $license_result = fml_generate_non_exclusive_license_pdf(
        $song_id,
        $song_name,
        $artist_name,
        $licensee_name,
        $project_name,
        $usage_description,
        $session['amount_total'] / 100, // Convert cents to dollars
        $session['currency']
    );

    if (!$license_result['success']) {
        error_log("Failed to generate non-exclusive license PDF: " . $license_result['error']);
        return;
    }

    // Create license record in Pods
    $pod = pods('license');
    $data = [
        'user' => $user_id,
        'song' => $song_id,
        'datetime' => current_time('mysql'),
        'license_url' => $license_result['url'],
        'licensor' => $licensee_name,
        'project' => $project_name,
        'description_of_usage' => $usage_description,
        'legal_name' => $licensee_name,
        // Payment/license type fields
        'license_type' => 'non_exclusive',
        'stripe_payment_id' => $session['payment_intent'] ?? $session['id'],
        'stripe_payment_status' => 'completed',
        'payment_amount' => $session['amount_total'],
        'payment_currency' => $session['currency']
    ];

    $new_license_id = $pod->add($data);
    if ($new_license_id) {
        wp_update_post([
            'ID' => $new_license_id,
            'post_status' => 'publish'
        ]);

        error_log("Non-exclusive license created: {$new_license_id} for song {$song_id}");

        // Send email notification to user
        $user = get_user_by('id', $user_id);
        if ($user) {
            wp_mail(
                $user->user_email,
                "Your Sync.Land License for \"{$song_name}\"",
                "Your non-exclusive sync license has been processed.\n\n" .
                "Song: {$artist_name} - {$song_name}\n" .
                "Project: {$project_name}\n\n" .
                "Download your license: {$license_result['url']}\n\n" .
                "View all your licenses: " . home_url('/account/licenses/') . "\n\n" .
                "Thank you for using Sync.Land!"
            );
        }
    }
}

/**
 * Handle successful payment intent
 */
function fml_handle_payment_succeeded($payment_intent) {
    error_log("Payment succeeded: " . $payment_intent['id']);
}

/**
 * Handle failed payment
 */
function fml_handle_payment_failed($payment_intent) {
    error_log("Payment failed: " . $payment_intent['id']);
}


/**
 * ============================================================================
 * NON-EXCLUSIVE LICENSE PDF GENERATION
 * ============================================================================
 */

function fml_generate_non_exclusive_license_pdf($song_id, $song_name, $artist_name, $licensee_name, $project_name, $usage_description, $amount, $currency) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

    $currentDateTime = gmdate('Y-m-d\TH:i:s\Z');
    $sitelogo = "https://www.sync.land/wp-content/uploads/2024/06/SYNC.LAND_.jpg";

    $mpdf = new \Mpdf\Mpdf();

    $currency_symbol = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency) . ' ';

    $html = '
        <style>
            a { color: #277acc; text-decoration: none; }
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .center { text-align: center; }
            .container { width: 80%; margin: 0 auto; }
            ul { margin: 10px 0; padding-left: 20px; }
            .section { margin-top: 20px; }
            .highlight { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        </style>
        <body>
            <div class="center container"><img src="' . esc_url($sitelogo) . '" alt="Sync.Land" style="max-width: 60%;" /></div>

            <div class="center" style="margin-top:50px;">
                <h1>Non-Exclusive Sync License</h1>
            </div>

            <div class="container section highlight">
                <strong>License Details</strong>
                <ul>
                    <li><strong>Song:</strong> ' . esc_html($song_name) . '</li>
                    <li><strong>Artist:</strong> ' . esc_html($artist_name) . '</li>
                    <li><strong>Licensee:</strong> ' . esc_html($licensee_name) . '</li>
                    <li><strong>Project:</strong> ' . esc_html($project_name) . '</li>
                    <li><strong>License Fee:</strong> ' . esc_html($currency_symbol . number_format($amount, 2)) . '</li>
                    <li><strong>Issue Date:</strong> ' . esc_html($currentDateTime) . ' UTC</li>
                </ul>
            </div>

            <div class="container section">
                <h2>Grant of License</h2>
                <p>The Artist/Rights Holder ("<strong>' . esc_html($artist_name) . '</strong>") hereby grants to the Licensee ("<strong>' . esc_html($licensee_name) . '</strong>") a <strong>non-exclusive</strong> license to synchronize the musical composition and sound recording identified above (the "Work") with visual media for the following project:</p>
                <p><strong>Project:</strong> ' . esc_html($project_name) . '</p>
                <p><strong>Usage Description:</strong> ' . esc_html($usage_description ?: 'General commercial use') . '</p>
            </div>

            <div class="container section">
                <h2>Terms and Conditions</h2>

                <h3>1. Scope of License</h3>
                <ul>
                    <li>This is a <strong>non-exclusive</strong> license. The Artist retains all rights to license the Work to other parties.</li>
                    <li>The Licensee may synchronize the Work with visual media for the specified project.</li>
                    <li>The license is valid <strong>worldwide</strong> and in <strong>perpetuity</strong> unless otherwise specified.</li>
                </ul>

                <h3>2. Permitted Uses</h3>
                <ul>
                    <li>Film, television, video, and streaming content</li>
                    <li>Advertising and promotional materials</li>
                    <li>Social media content</li>
                    <li>Podcasts and audio-visual presentations</li>
                    <li>Video games and interactive media</li>
                </ul>

                <h3>3. Attribution</h3>
                <p>Where reasonably possible, the Licensee shall provide credit to the Artist in the following format:</p>
                <p class="highlight"><em>Music: ' . esc_html($artist_name) . ' - "' . esc_html($song_name) . '" licensed via Sync.Land</em></p>

                <h3>4. Restrictions</h3>
                <ul>
                    <li>The Work may not be re-sold, sub-licensed, or transferred to third parties.</li>
                    <li>The Work may not be used in content that is defamatory, obscene, or illegal.</li>
                    <li>This license does not grant ownership of the underlying copyright.</li>
                </ul>

                <h3>5. Warranty</h3>
                <p>The Artist warrants that they have the right to grant this license. The Work is provided "as is" without additional warranties.</p>
            </div>

            <div class="container section center">
                <p><em>This license was generated and verified via Sync.Land</em></p>
                <p><a href="https://sync.land">https://sync.land</a></p>
            </div>
        </body>';

    $mpdf->WriteHTML($html);

    // Generate filename and save
    $filename = sanitize_file_name("NonExclusive_{$artist_name}_{$song_name}_" . date('Ymd_His') . ".pdf");
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


/**
 * ============================================================================
 * STRIPE CHECKOUT SESSION CREATION
 * ============================================================================
 */

add_action('rest_api_init', function() {
    register_rest_route('FML/v1', '/stripe/create-checkout', [
        'methods' => 'POST',
        'callback' => 'fml_create_stripe_checkout',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
});

/**
 * Create Stripe Checkout session for purchasing a non-exclusive license
 */
function fml_create_stripe_checkout(WP_REST_Request $request) {
    // Verify Stripe is configured
    $stripe_secret_key = fml_get_stripe_secret_key();
    if (empty($stripe_secret_key)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Stripe not configured'
        ], 500);
    }

    $song_id = intval($request->get_param('song_id'));
    $licensee_name = sanitize_text_field($request->get_param('licensee_name') ?? '');
    $project_name = sanitize_text_field($request->get_param('project_name') ?? '');
    $usage_description = sanitize_text_field($request->get_param('usage_description') ?? '');

    if (!$song_id) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Song ID is required'
        ], 400);
    }

    // Get song info
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Song not found'
        ], 404);
    }

    $song_name = $song_pod->field('post_title');

    // Get artist info
    $artist_data = $song_pod->field('artist');
    $artist_name = 'Unknown Artist';
    if (!empty($artist_data)) {
        $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
        $artist_pod = pods('artist', $artist_id);
        if ($artist_pod && $artist_pod->exists()) {
            $artist_name = $artist_pod->field('post_title');
        }
    }

    // Get license price (could be per-song pricing in future)
    $license_price = get_option('fml_non_exclusive_license_price', 4900); // Default $49.00 in cents
    $current_user = wp_get_current_user();
    $description = "Sync.Land - Music license for {$artist_name} - {$song_name}";

    // Build checkout session
    $checkout_data = [
        'mode' => 'payment',
        'line_items' => [
            [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => "Sync License: {$artist_name} - {$song_name}",
                        'description' => 'Non-exclusive sync license for commercial use'
                    ],
                    'unit_amount' => intval($license_price)
                ],
                'quantity' => 1
            ]
        ],
        'success_url' => home_url("/account/licenses/?payment=success&song={$song_id}"),
        'cancel_url' => home_url("/song/{$song_id}/?payment=cancelled"),
        'payment_intent_data' => [
            'description' => $description,
            'statement_descriptor_suffix' => 'SYNC'
        ],
        'metadata' => [
            'type' => 'non_exclusive_license',
            'song_id' => $song_id,
            'user_id' => get_current_user_id(),
            'licensee_name' => $licensee_name ?: $current_user->display_name,
            'project_name' => $project_name,
            'usage_description' => $usage_description
        ],
        'customer_email' => $current_user->user_email
    ];

    // Make Stripe API request
    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $stripe_secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'body' => fml_build_stripe_body($checkout_data)
    ]);

    if (is_wp_error($response)) {
        error_log('Stripe API error: ' . $response->get_error_message());
        return new WP_REST_Response([
            'success' => false,
            'error' => 'Payment service unavailable'
        ], 500);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        error_log('Stripe error: ' . json_encode($body['error']));
        return new WP_REST_Response([
            'success' => false,
            'error' => $body['error']['message'] ?? 'Payment error'
        ], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'checkout_url' => $body['url'],
        'session_id' => $body['id']
    ], 200);
}

/**
 * Build URL-encoded body for Stripe API
 */
function fml_build_stripe_body($data, $prefix = '') {
    $result = [];

    foreach ($data as $key => $value) {
        $full_key = $prefix ? "{$prefix}[{$key}]" : $key;

        if (is_array($value)) {
            $result = array_merge($result, fml_build_stripe_body($value, $full_key));
        } else {
            $result[$full_key] = $value;
        }
    }

    return $result;
}


/**
 * ============================================================================
 * ADMIN SETTINGS FOR LICENSE PRICING
 * ============================================================================
 */

add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'Sync.Land Licensing',
        'Sync.Land Licensing',
        'manage_options',
        'fml-licensing',
        'fml_licensing_settings_page'
    );
});

/**
 * Register AJAX handler for Stripe connection verification
 */
add_action('wp_ajax_fml_verify_stripe', 'fml_ajax_verify_stripe_connection');

function fml_ajax_verify_stripe_connection() {
    check_ajax_referer('fml_licensing_settings', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $key_type = sanitize_text_field($_POST['key_type'] ?? 'current');

    // Determine which key to test
    if ($key_type === 'test') {
        $secret_key = sanitize_text_field($_POST['test_secret_key'] ?? '');
    } elseif ($key_type === 'live') {
        $secret_key = sanitize_text_field($_POST['live_secret_key'] ?? '');
    } else {
        $secret_key = fml_get_stripe_secret_key();
    }

    if (empty($secret_key)) {
        wp_send_json_error(['message' => 'No API key provided']);
    }

    $result = fml_verify_stripe_connection($secret_key);

    if ($result['success']) {
        wp_send_json_success([
            'message' => $result['message'],
            'mode' => $result['mode']
        ]);
    } else {
        wp_send_json_error(['message' => $result['error']]);
    }
}

function fml_licensing_settings_page() {
    // Handle form submission
    if (isset($_POST['fml_save_licensing_settings']) && check_admin_referer('fml_licensing_settings')) {
        // Save pricing settings
        update_option('fml_non_exclusive_license_price', intval($_POST['fml_non_exclusive_license_price']));
        update_option('fml_nft_minting_fee', intval($_POST['fml_nft_minting_fee']));

        // Save Stripe mode
        $mode = sanitize_text_field($_POST['fml_stripe_mode'] ?? 'test');
        update_option('fml_stripe_mode', in_array($mode, ['test', 'live']) ? $mode : 'test');

        // Save Stripe API keys (encrypted storage would be better for production)
        if (!empty($_POST['fml_stripe_test_secret_key'])) {
            update_option('fml_stripe_test_secret_key', sanitize_text_field($_POST['fml_stripe_test_secret_key']));
        }
        if (!empty($_POST['fml_stripe_test_publishable_key'])) {
            update_option('fml_stripe_test_publishable_key', sanitize_text_field($_POST['fml_stripe_test_publishable_key']));
        }
        if (!empty($_POST['fml_stripe_live_secret_key'])) {
            update_option('fml_stripe_live_secret_key', sanitize_text_field($_POST['fml_stripe_live_secret_key']));
        }
        if (!empty($_POST['fml_stripe_live_publishable_key'])) {
            update_option('fml_stripe_live_publishable_key', sanitize_text_field($_POST['fml_stripe_live_publishable_key']));
        }
        if (!empty($_POST['fml_stripe_webhook_secret'])) {
            update_option('fml_stripe_webhook_secret', sanitize_text_field($_POST['fml_stripe_webhook_secret']));
        }

        // Save NMKR mode
        $nmkr_mode = sanitize_text_field($_POST['fml_nmkr_mode'] ?? 'preprod');
        update_option('fml_nmkr_mode', in_array($nmkr_mode, ['preprod', 'mainnet']) ? $nmkr_mode : 'preprod');

        // Save NMKR Preprod keys
        if (!empty($_POST['fml_nmkr_preprod_api_key'])) {
            update_option('fml_nmkr_preprod_api_key', sanitize_text_field($_POST['fml_nmkr_preprod_api_key']));
        }
        if (!empty($_POST['fml_nmkr_preprod_project_uid'])) {
            update_option('fml_nmkr_preprod_project_uid', sanitize_text_field($_POST['fml_nmkr_preprod_project_uid']));
        }
        if (!empty($_POST['fml_nmkr_preprod_policy_id'])) {
            update_option('fml_nmkr_preprod_policy_id', sanitize_text_field($_POST['fml_nmkr_preprod_policy_id']));
        }

        // Save NMKR Mainnet keys
        if (!empty($_POST['fml_nmkr_mainnet_api_key'])) {
            update_option('fml_nmkr_mainnet_api_key', sanitize_text_field($_POST['fml_nmkr_mainnet_api_key']));
        }
        if (!empty($_POST['fml_nmkr_mainnet_project_uid'])) {
            update_option('fml_nmkr_mainnet_project_uid', sanitize_text_field($_POST['fml_nmkr_mainnet_project_uid']));
        }
        if (!empty($_POST['fml_nmkr_mainnet_policy_id'])) {
            update_option('fml_nmkr_mainnet_policy_id', sanitize_text_field($_POST['fml_nmkr_mainnet_policy_id']));
        }

        // Save IPFS settings (Pinata)
        if (isset($_POST['fml_pinata_jwt'])) {
            update_option('fml_pinata_jwt', sanitize_text_field($_POST['fml_pinata_jwt']));
        }

        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    // Get current values
    $license_price = get_option('fml_non_exclusive_license_price', 4900);
    $nft_fee = get_option('fml_nft_minting_fee', 500);
    $stripe_mode = get_option('fml_stripe_mode', 'test');

    // Get Stripe keys (masked for display)
    $test_secret = get_option('fml_stripe_test_secret_key', '');
    $test_publishable = get_option('fml_stripe_test_publishable_key', '');
    $live_secret = get_option('fml_stripe_live_secret_key', '');
    $live_publishable = get_option('fml_stripe_live_publishable_key', '');
    $webhook_secret = get_option('fml_stripe_webhook_secret', '');

    // Get NMKR settings
    $nmkr_mode = get_option('fml_nmkr_mode', 'preprod');
    $nmkr_preprod_api_key = get_option('fml_nmkr_preprod_api_key', '');
    $nmkr_preprod_project_uid = get_option('fml_nmkr_preprod_project_uid', '');
    $nmkr_preprod_policy_id = get_option('fml_nmkr_preprod_policy_id', '');
    $nmkr_mainnet_api_key = get_option('fml_nmkr_mainnet_api_key', '');
    $nmkr_mainnet_project_uid = get_option('fml_nmkr_mainnet_project_uid', '');
    $nmkr_mainnet_policy_id = get_option('fml_nmkr_mainnet_policy_id', '');

    // Get IPFS settings
    $pinata_jwt = get_option('fml_pinata_jwt', '');

    ?>
    <div class="wrap">
        <h1>Sync.Land Licensing Settings</h1>

        <form method="post" id="fml-licensing-form">
            <?php wp_nonce_field('fml_licensing_settings'); ?>

            <!-- Stripe API Configuration -->
            <h2>Stripe API Configuration</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">Stripe Mode</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="fml_stripe_mode" value="test" <?php checked($stripe_mode, 'test'); ?>>
                                <span style="color: #f0ad4e; font-weight: bold;">Test Mode</span>
                                <span class="description"> - Use test API keys (no real charges)</span>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="fml_stripe_mode" value="live" <?php checked($stripe_mode, 'live'); ?>>
                                <span style="color: #5cb85c; font-weight: bold;">Live Mode</span>
                                <span class="description"> - Use live API keys (real charges)</span>
                            </label>
                        </fieldset>
                        <p class="description" style="margin-top: 10px;">
                            <strong>Current Mode:</strong>
                            <?php if ($stripe_mode === 'live'): ?>
                                <span style="background: #5cb85c; color: white; padding: 2px 8px; border-radius: 3px;">LIVE</span>
                            <?php else: ?>
                                <span style="background: #f0ad4e; color: white; padding: 2px 8px; border-radius: 3px;">TEST</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3 style="border-bottom: 1px solid #f0ad4e; padding-bottom: 5px; color: #f0ad4e;">Test Mode Keys</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_stripe_test_secret_key">Test Secret Key</label>
                    </th>
                    <td>
                        <input type="password" name="fml_stripe_test_secret_key" id="fml_stripe_test_secret_key"
                               class="regular-text" placeholder="sk_test_..."
                               value="<?php echo esc_attr($test_secret); ?>" autocomplete="off">
                        <button type="button" class="button fml-toggle-visibility" data-target="fml_stripe_test_secret_key">Show</button>
                        <button type="button" class="button fml-verify-key" data-key-type="test">Verify</button>
                        <span class="fml-verify-status" id="fml-test-status"></span>
                        <?php if (!empty($test_secret)): ?>
                            <p class="description" style="color: green;">&#10003; Key saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_stripe_test_publishable_key">Test Publishable Key</label>
                    </th>
                    <td>
                        <input type="text" name="fml_stripe_test_publishable_key" id="fml_stripe_test_publishable_key"
                               class="regular-text" placeholder="pk_test_..."
                               value="<?php echo esc_attr($test_publishable); ?>">
                        <?php if (!empty($test_publishable)): ?>
                            <p class="description" style="color: green;">&#10003; Key saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h3 style="border-bottom: 1px solid #5cb85c; padding-bottom: 5px; color: #5cb85c;">Live Mode Keys</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_stripe_live_secret_key">Live Secret Key</label>
                    </th>
                    <td>
                        <input type="password" name="fml_stripe_live_secret_key" id="fml_stripe_live_secret_key"
                               class="regular-text" placeholder="sk_live_..."
                               value="<?php echo esc_attr($live_secret); ?>" autocomplete="off">
                        <button type="button" class="button fml-toggle-visibility" data-target="fml_stripe_live_secret_key">Show</button>
                        <button type="button" class="button fml-verify-key" data-key-type="live">Verify</button>
                        <span class="fml-verify-status" id="fml-live-status"></span>
                        <?php if (!empty($live_secret)): ?>
                            <p class="description" style="color: green;">&#10003; Key saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_stripe_live_publishable_key">Live Publishable Key</label>
                    </th>
                    <td>
                        <input type="text" name="fml_stripe_live_publishable_key" id="fml_stripe_live_publishable_key"
                               class="regular-text" placeholder="pk_live_..."
                               value="<?php echo esc_attr($live_publishable); ?>">
                        <?php if (!empty($live_publishable)): ?>
                            <p class="description" style="color: green;">&#10003; Key saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h3 style="border-bottom: 1px solid #ccc; padding-bottom: 5px;">Webhook Configuration</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_stripe_webhook_secret">Webhook Secret</label>
                    </th>
                    <td>
                        <input type="password" name="fml_stripe_webhook_secret" id="fml_stripe_webhook_secret"
                               class="regular-text" placeholder="whsec_..."
                               value="<?php echo esc_attr($webhook_secret); ?>" autocomplete="off">
                        <button type="button" class="button fml-toggle-visibility" data-target="fml_stripe_webhook_secret">Show</button>
                        <?php if (!empty($webhook_secret)): ?>
                            <p class="description" style="color: green;">&#10003; Secret saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Webhook URL</th>
                    <td>
                        <code id="fml-webhook-url"><?php echo esc_html(home_url('/wp-json/FML/v1/stripe/webhook')); ?></code>
                        <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('fml-webhook-url').textContent); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);">Copy</button>
                        <p class="description">Add this URL in <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard > Developers > Webhooks</a></p>
                    </td>
                </tr>
            </table>

            <!-- Stripe Connection Status -->
            <h3>Stripe Connection Status</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Current Connection</th>
                    <td>
                        <button type="button" class="button button-secondary" id="fml-verify-current">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            Verify Stripe Connection
                        </button>
                        <span id="fml-current-status" style="margin-left: 10px;"></span>
                    </td>
                </tr>
            </table>

            <hr style="margin: 30px 0;">

            <!-- NMKR API Configuration -->
            <h2>NMKR API Configuration (NFT Minting)</h2>
            <p class="description">NMKR is used to mint licenses as NFTs on the Cardano blockchain.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">NMKR Mode</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="fml_nmkr_mode" value="preprod" <?php checked($nmkr_mode, 'preprod'); ?>>
                                <span style="color: #f0ad4e; font-weight: bold;">Preprod (Test)</span>
                                <span class="description"> - Use preprod network (test ADA, no real value)</span>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="fml_nmkr_mode" value="mainnet" <?php checked($nmkr_mode, 'mainnet'); ?>>
                                <span style="color: #5cb85c; font-weight: bold;">Mainnet (Live)</span>
                                <span class="description"> - Use mainnet network (real ADA, real NFTs)</span>
                            </label>
                        </fieldset>
                        <p class="description" style="margin-top: 10px;">
                            <strong>Current Mode:</strong>
                            <?php if ($nmkr_mode === 'mainnet'): ?>
                                <span style="background: #5cb85c; color: white; padding: 2px 8px; border-radius: 3px;">MAINNET</span>
                            <?php else: ?>
                                <span style="background: #f0ad4e; color: white; padding: 2px 8px; border-radius: 3px;">PREPROD</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3 style="border-bottom: 1px solid #f0ad4e; padding-bottom: 5px; color: #f0ad4e;">Preprod (Test) Configuration</h3>
            <p class="description">Get your preprod API key from <a href="https://studio.preprod.nmkr.io" target="_blank">studio.preprod.nmkr.io</a></p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_nmkr_preprod_api_key">Preprod API Key</label>
                    </th>
                    <td>
                        <input type="password" name="fml_nmkr_preprod_api_key" id="fml_nmkr_preprod_api_key"
                               class="regular-text" placeholder="Your preprod API key"
                               value="<?php echo esc_attr($nmkr_preprod_api_key); ?>" autocomplete="off">
                        <button type="button" class="button fml-toggle-visibility" data-target="fml_nmkr_preprod_api_key">Show</button>
                        <button type="button" class="button fml-verify-nmkr" data-key-type="preprod">Verify</button>
                        <span class="fml-verify-status" id="fml-nmkr-preprod-status"></span>
                        <?php if (!empty($nmkr_preprod_api_key)): ?>
                            <p class="description" style="color: green;">&#10003; Key saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_nmkr_preprod_project_uid">Preprod Project UID</label>
                    </th>
                    <td>
                        <input type="text" name="fml_nmkr_preprod_project_uid" id="fml_nmkr_preprod_project_uid"
                               class="regular-text" placeholder="Project UID from NMKR Studio"
                               value="<?php echo esc_attr($nmkr_preprod_project_uid); ?>">
                        <button type="button" class="button fml-load-projects" data-env="preprod">Load Projects</button>
                        <div id="fml-preprod-projects-list" style="margin-top: 10px; display: none;"></div>
                        <?php if (!empty($nmkr_preprod_project_uid)): ?>
                            <p class="description" style="color: green;">&#10003; Saved</p>
                        <?php endif; ?>
                        <p class="description">Click "Load Projects" to see available projects in your NMKR account</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_nmkr_preprod_policy_id">Preprod Policy ID</label>
                    </th>
                    <td>
                        <input type="text" name="fml_nmkr_preprod_policy_id" id="fml_nmkr_preprod_policy_id"
                               class="regular-text" placeholder="Policy ID from your NMKR project"
                               value="<?php echo esc_attr($nmkr_preprod_policy_id); ?>">
                        <?php if (!empty($nmkr_preprod_policy_id)): ?>
                            <p class="description" style="color: green;">&#10003; Saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h3 style="border-bottom: 1px solid #5cb85c; padding-bottom: 5px; color: #5cb85c;">Mainnet (Live) Configuration</h3>
            <p class="description">Get your mainnet API key from <a href="https://studio.nmkr.io" target="_blank">studio.nmkr.io</a></p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_nmkr_mainnet_api_key">Mainnet API Key</label>
                    </th>
                    <td>
                        <input type="password" name="fml_nmkr_mainnet_api_key" id="fml_nmkr_mainnet_api_key"
                               class="regular-text" placeholder="Your mainnet API key"
                               value="<?php echo esc_attr($nmkr_mainnet_api_key); ?>" autocomplete="off">
                        <button type="button" class="button fml-toggle-visibility" data-target="fml_nmkr_mainnet_api_key">Show</button>
                        <button type="button" class="button fml-verify-nmkr" data-key-type="mainnet">Verify</button>
                        <span class="fml-verify-status" id="fml-nmkr-mainnet-status"></span>
                        <?php if (!empty($nmkr_mainnet_api_key)): ?>
                            <p class="description" style="color: green;">&#10003; Key saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_nmkr_mainnet_project_uid">Mainnet Project UID</label>
                    </th>
                    <td>
                        <input type="text" name="fml_nmkr_mainnet_project_uid" id="fml_nmkr_mainnet_project_uid"
                               class="regular-text" placeholder="Project UID from NMKR Studio"
                               value="<?php echo esc_attr($nmkr_mainnet_project_uid); ?>">
                        <button type="button" class="button fml-load-projects" data-env="mainnet">Load Projects</button>
                        <div id="fml-mainnet-projects-list" style="margin-top: 10px; display: none;"></div>
                        <?php if (!empty($nmkr_mainnet_project_uid)): ?>
                            <p class="description" style="color: green;">&#10003; Saved</p>
                        <?php endif; ?>
                        <p class="description">Click "Load Projects" to see available projects in your NMKR account</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_nmkr_mainnet_policy_id">Mainnet Policy ID</label>
                    </th>
                    <td>
                        <input type="text" name="fml_nmkr_mainnet_policy_id" id="fml_nmkr_mainnet_policy_id"
                               class="regular-text" placeholder="Policy ID from your NMKR project"
                               value="<?php echo esc_attr($nmkr_mainnet_policy_id); ?>">
                        <?php if (!empty($nmkr_mainnet_policy_id)): ?>
                            <p class="description" style="color: green;">&#10003; Saved</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <!-- NMKR Connection Status -->
            <h3>NMKR Connection Status</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Current Connection</th>
                    <td>
                        <button type="button" class="button button-secondary" id="fml-verify-nmkr-current">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            Verify NMKR Connection
                        </button>
                        <span id="fml-nmkr-current-status" style="margin-left: 10px;"></span>
                    </td>
                </tr>
            </table>

            <hr style="margin: 30px 0;">

            <!-- IPFS Configuration (Pinata) -->
            <h2>IPFS Configuration</h2>
            <p class="description">IPFS is used to store license PDFs on decentralized storage for NFT metadata. <a href="https://pinata.cloud" target="_blank">Get a free Pinata account</a> to enable this.</p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_pinata_jwt">Pinata JWT Token</label>
                    </th>
                    <td>
                        <input type="password" name="fml_pinata_jwt" id="fml_pinata_jwt"
                               class="regular-text" placeholder="Your Pinata JWT token"
                               value="<?php echo esc_attr($pinata_jwt); ?>" autocomplete="off">
                        <button type="button" class="button fml-toggle-visibility" data-target="fml_pinata_jwt">Show</button>
                        <?php if (!empty($pinata_jwt)): ?>
                            <p class="description" style="color: green;">&#10003; JWT Token saved - IPFS uploads enabled</p>
                        <?php else: ?>
                            <p class="description" style="color: #f0ad4e;">&#9888; No JWT token - IPFS uploads disabled</p>
                        <?php endif; ?>
                        <p class="description">
                            To get your JWT: Login to <a href="https://app.pinata.cloud/developers/api-keys" target="_blank">Pinata</a> →
                            API Keys → New Key → Enable "pinFileToIPFS" → Copy the JWT
                        </p>
                    </td>
                </tr>
            </table>

            <hr style="margin: 30px 0;">

            <!-- License Pricing -->
            <h2>License Pricing</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_non_exclusive_license_price">Commercial License Price (cents USD)</label>
                    </th>
                    <td>
                        <input type="number" name="fml_non_exclusive_license_price" id="fml_non_exclusive_license_price"
                               value="<?php echo esc_attr($license_price); ?>" min="0" step="1" style="width: 100px;">
                        <span class="description">= $<?php echo number_format($license_price / 100, 2); ?> USD</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fml_nft_minting_fee">NFT Minting Fee (cents USD)</label>
                    </th>
                    <td>
                        <input type="number" name="fml_nft_minting_fee" id="fml_nft_minting_fee"
                               value="<?php echo esc_attr($nft_fee); ?>" min="0" step="1" style="width: 100px;">
                        <span class="description">= $<?php echo number_format($nft_fee / 100, 2); ?> USD</span>
                    </td>
                </tr>
            </table>

            <!-- Pricing Summary -->
            <h3>Pricing Summary</h3>
            <table class="widefat" style="max-width: 700px;">
                <thead>
                    <tr>
                        <th>License Option</th>
                        <th>License Fee</th>
                        <th>NFT Fee</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>CC-BY 4.0</strong><br><small>Creative Commons Attribution</small></td>
                        <td>Free</td>
                        <td>-</td>
                        <td><strong>Free</strong></td>
                    </tr>
                    <tr>
                        <td><strong>CC-BY 4.0 + NFT</strong><br><small>CC-BY with blockchain verification</small></td>
                        <td>Free</td>
                        <td>$<?php echo number_format($nft_fee / 100, 2); ?></td>
                        <td><strong>$<?php echo number_format($nft_fee / 100, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Commercial License</strong><br><small>Non-exclusive sync license</small></td>
                        <td>$<?php echo number_format($license_price / 100, 2); ?></td>
                        <td>-</td>
                        <td><strong>$<?php echo number_format($license_price / 100, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Commercial + NFT</strong><br><small>Commercial with blockchain verification</small></td>
                        <td>$<?php echo number_format($license_price / 100, 2); ?></td>
                        <td>$<?php echo number_format($nft_fee / 100, 2); ?></td>
                        <td><strong>$<?php echo number_format(($license_price + $nft_fee) / 100, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="fml_save_licensing_settings" class="button-primary" value="Save All Settings" />
            </p>
        </form>
    </div>

    <style>
        .fml-verify-status {
            margin-left: 10px;
            font-weight: bold;
        }
        .fml-verify-status.success { color: #5cb85c; }
        .fml-verify-status.error { color: #d9534f; }
        .fml-verify-status.loading { color: #666; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Toggle password visibility
        $('.fml-toggle-visibility').on('click', function() {
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var $btn = $(this);

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.text('Hide');
            } else {
                $input.attr('type', 'password');
                $btn.text('Show');
            }
        });

        // Verify individual keys
        $('.fml-verify-key').on('click', function() {
            var keyType = $(this).data('key-type');
            var $status = $('#fml-' + keyType + '-status');
            var secretKey = keyType === 'test'
                ? $('#fml_stripe_test_secret_key').val()
                : $('#fml_stripe_live_secret_key').val();

            if (!secretKey) {
                $status.removeClass('success loading').addClass('error').text('No key entered');
                return;
            }

            $status.removeClass('success error').addClass('loading').text('Verifying...');

            $.post(ajaxurl, {
                action: 'fml_verify_stripe',
                nonce: '<?php echo wp_create_nonce('fml_licensing_settings'); ?>',
                key_type: keyType,
                test_secret_key: keyType === 'test' ? secretKey : '',
                live_secret_key: keyType === 'live' ? secretKey : ''
            }, function(response) {
                if (response.success) {
                    $status.removeClass('error loading').addClass('success').text('✓ ' + response.data.message);
                } else {
                    $status.removeClass('success loading').addClass('error').text('✗ ' + response.data.message);
                }
            }).fail(function() {
                $status.removeClass('success loading').addClass('error').text('✗ Connection failed');
            });
        });

        // Verify current Stripe connection
        $('#fml-verify-current').on('click', function() {
            var $btn = $(this);
            var $status = $('#fml-current-status');

            $btn.prop('disabled', true);
            $status.removeClass('success error').addClass('loading').text('Verifying...');

            $.post(ajaxurl, {
                action: 'fml_verify_stripe',
                nonce: '<?php echo wp_create_nonce('fml_licensing_settings'); ?>',
                key_type: 'current'
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.removeClass('error loading').css('color', '#5cb85c').text('✓ ' + response.data.message);
                } else {
                    $status.removeClass('success loading').css('color', '#d9534f').text('✗ ' + response.data.message);
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $status.removeClass('success loading').css('color', '#d9534f').text('✗ Connection failed');
            });
        });

        // Verify individual NMKR keys
        $('.fml-verify-nmkr').on('click', function() {
            var keyType = $(this).data('key-type');
            var $status = $('#fml-nmkr-' + keyType + '-status');
            var apiKey, projectUid;

            if (keyType === 'preprod') {
                apiKey = $('#fml_nmkr_preprod_api_key').val();
                projectUid = $('#fml_nmkr_preprod_project_uid').val();
            } else {
                apiKey = $('#fml_nmkr_mainnet_api_key').val();
                projectUid = $('#fml_nmkr_mainnet_project_uid').val();
            }

            if (!apiKey) {
                $status.removeClass('success loading').addClass('error').text('No key entered');
                return;
            }

            $status.removeClass('success error').addClass('loading').text('Verifying...');

            $.post(ajaxurl, {
                action: 'fml_verify_nmkr',
                nonce: '<?php echo wp_create_nonce('fml_licensing_settings'); ?>',
                key_type: keyType,
                preprod_api_key: keyType === 'preprod' ? apiKey : '',
                mainnet_api_key: keyType === 'mainnet' ? apiKey : '',
                preprod_project_uid: keyType === 'preprod' ? projectUid : '',
                mainnet_project_uid: keyType === 'mainnet' ? projectUid : ''
            }, function(response) {
                if (response.success) {
                    $status.removeClass('error loading').addClass('success').text('✓ ' + response.data.message);
                } else {
                    $status.removeClass('success loading').addClass('error').text('✗ ' + response.data.message);
                }
            }).fail(function() {
                $status.removeClass('success loading').addClass('error').text('✗ Connection failed');
            });
        });

        // Verify current NMKR connection
        $('#fml-verify-nmkr-current').on('click', function() {
            var $btn = $(this);
            var $status = $('#fml-nmkr-current-status');

            $btn.prop('disabled', true);
            $status.removeClass('success error').addClass('loading').text('Verifying...');

            $.post(ajaxurl, {
                action: 'fml_verify_nmkr',
                nonce: '<?php echo wp_create_nonce('fml_licensing_settings'); ?>',
                key_type: 'current'
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.removeClass('error loading').css('color', '#5cb85c').text('✓ ' + response.data.message);
                } else {
                    $status.removeClass('success loading').css('color', '#d9534f').text('✗ ' + response.data.message);
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $status.removeClass('success loading').css('color', '#d9534f').text('✗ Connection failed');
            });
        });

        // Load NMKR projects
        $('.fml-load-projects').on('click', function() {
            var env = $(this).data('env');
            var $btn = $(this);
            var $list = $('#fml-' + env + '-projects-list');
            var apiKey = $('#fml_nmkr_' + env + '_api_key').val();

            if (!apiKey) {
                alert('Please enter the ' + env + ' API key first');
                return;
            }

            $btn.prop('disabled', true).text('Loading...');
            $list.html('<p>Loading projects...</p>').show();

            $.post(ajaxurl, {
                action: 'fml_load_nmkr_projects',
                nonce: '<?php echo wp_create_nonce('fml_licensing_settings'); ?>',
                env: env,
                api_key: apiKey
            }, function(response) {
                $btn.prop('disabled', false).text('Load Projects');
                if (response.success && response.data.projects) {
                    var html = '<p><strong>Select a project:</strong></p>';
                    html += '<select id="fml-' + env + '-project-select" style="min-width: 300px;">';
                    html += '<option value="">-- Select a project --</option>';
                    response.data.projects.forEach(function(proj) {
                        var label = proj.projectname || 'Unnamed';
                        var uid = proj.uid || '';
                        var policyId = proj.policyId || '';
                        html += '<option value="' + uid + '" data-policy="' + policyId + '">' + label + ' (' + uid.substring(0, 8) + '...)</option>';
                    });
                    html += '</select>';
                    html += ' <button type="button" class="button fml-use-project" data-env="' + env + '">Use Selected</button>';
                    $list.html(html);
                } else {
                    $list.html('<p style="color: #d9534f;">Error: ' + (response.data ? response.data.error : 'Unknown error') + '</p>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Load Projects');
                $list.html('<p style="color: #d9534f;">Connection failed</p>');
            });
        });

        // Use selected project
        $(document).on('click', '.fml-use-project', function() {
            var env = $(this).data('env');
            var $select = $('#fml-' + env + '-project-select');
            var uid = $select.val();
            var policyId = $select.find(':selected').data('policy');

            if (!uid) {
                alert('Please select a project first');
                return;
            }

            $('#fml_nmkr_' + env + '_project_uid').val(uid);
            if (policyId) {
                $('#fml_nmkr_' + env + '_policy_id').val(policyId);
            }
            $('#fml-' + env + '-projects-list').html('<p style="color: #5cb85c;">✓ Project UID and Policy ID filled in. Don\'t forget to save!</p>');
        });
    });
    </script>
    <?php
}


/**
 * ============================================================================
 * CART STRIPE CHECKOUT - MULTI-ITEM SUPPORT
 * ============================================================================
 */

/**
 * Create Stripe Checkout session for cart with multiple items
 *
 * @param array  $summary          Cart summary from fml_cart_get_summary()
 * @param string $licensee_name    Licensee name
 * @param string $project_name     Project name
 * @param string $usage_description Usage description
 * @return array Result with checkout URL or error
 */
function fml_create_cart_stripe_checkout($summary, $licensee_name, $project_name, $usage_description) {
    // Verify Stripe is configured
    $stripe_secret_key = fml_get_stripe_secret_key();
    if (empty($stripe_secret_key)) {
        return [
            'success' => false,
            'error' => 'Stripe not configured'
        ];
    }

    if (empty($summary['items'])) {
        return [
            'success' => false,
            'error' => 'Cart is empty'
        ];
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $license_price = intval(get_option('fml_non_exclusive_license_price', 4900));
    $nft_fee = intval(get_option('fml_nft_minting_fee', 500));

    // Build line items for Stripe
    $line_items = [];
    $cart_items_meta = []; // Store cart item details for webhook

    foreach ($summary['items'] as $index => $item) {
        $item_name = "{$item['artist_name']} - {$item['song_title']}";

        // Add license fee if commercial
        if ($item['license_type'] === 'non_exclusive') {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => "Commercial License: {$item_name}",
                        'description' => 'Non-exclusive sync license for commercial use'
                    ],
                    'unit_amount' => $license_price
                ],
                'quantity' => 1
            ];
        }

        // Add NFT fee if selected
        if (!empty($item['include_nft'])) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => "NFT Verification: {$item_name}",
                        'description' => 'Blockchain verification on Cardano'
                    ],
                    'unit_amount' => $nft_fee
                ],
                'quantity' => 1
            ];
        }

        // Store item metadata for webhook processing
        $cart_items_meta[] = [
            'song_id' => $item['song_id'],
            'license_type' => $item['license_type'],
            'include_nft' => $item['include_nft'] ? '1' : '0',
            'wallet_address' => $item['wallet_address'] ?? ''
        ];
    }

    // If no line items (all free CC-BY without NFT), this shouldn't reach here
    if (empty($line_items)) {
        return [
            'success' => false,
            'error' => 'No payable items in cart'
        ];
    }

    // Build description for Stripe dashboard
    $item_count = count($summary['items']);
    $description = "Sync.Land - {$item_count} music license" . ($item_count > 1 ? 's' : '');
    if (!empty($licensee_name)) {
        $description .= " for {$licensee_name}";
    }

    // Generate a unique checkout key for transient storage
    // Stripe metadata has a 500 char limit per value, so we store cart data in a transient
    $checkout_key = 'fml_cart_' . wp_generate_password(16, false);

    // Store cart items in transient BEFORE creating checkout session
    set_transient($checkout_key, [
        'cart_items' => $cart_items_meta,
        'licensee_name' => $licensee_name ?: $user->display_name,
        'project_name' => $project_name,
        'usage_description' => $usage_description,
        'user_id' => $user_id
    ], HOUR_IN_SECONDS);

    // Build checkout session - only pass reference key, not full cart data
    $checkout_data = [
        'mode' => 'payment',
        'line_items' => $line_items,
        'success_url' => home_url("/account/licenses/?payment=success&session_id={CHECKOUT_SESSION_ID}"),
        'cancel_url' => home_url("/cart/?payment=cancelled"),
        'payment_intent_data' => [
            'description' => $description,
            'statement_descriptor_suffix' => 'SYNC'
        ],
        'metadata' => [
            'type' => 'cart_checkout',
            'user_id' => $user_id,
            'checkout_key' => $checkout_key,
            'licensee_name' => substr($licensee_name ?: $user->display_name, 0, 500),
            'project_name' => substr($project_name, 0, 500)
        ],
        'customer_email' => $user->user_email
    ];

    // Make Stripe API request
    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $stripe_secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'body' => fml_build_stripe_body($checkout_data),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log('Stripe API error: ' . $response->get_error_message());
        delete_transient($checkout_key);
        return [
            'success' => false,
            'error' => 'Payment service unavailable'
        ];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        error_log('Stripe error: ' . json_encode($body['error']));
        delete_transient($checkout_key);
        return [
            'success' => false,
            'error' => $body['error']['message'] ?? 'Payment error'
        ];
    }

    return [
        'success' => true,
        'checkout_url' => $body['url'],
        'session_id' => $body['id']
    ];
}


/**
 * Handle cart checkout completion - Process multiple licenses
 */
function fml_handle_cart_checkout_completed($session) {
    error_log("=== fml_handle_cart_checkout_completed START ===");
    error_log("Session ID: " . ($session['id'] ?? 'unknown'));

    $metadata = $session['metadata'] ?? [];
    error_log("Metadata received: " . json_encode($metadata));

    // Check if this is a cart checkout
    if (!isset($metadata['type']) || $metadata['type'] !== 'cart_checkout') {
        error_log("Not a cart checkout (type: " . ($metadata['type'] ?? 'not set') . "), skipping");
        return;
    }

    $user_id = intval($metadata['user_id'] ?? 0);
    $licensee_name = $metadata['licensee_name'] ?? '';
    $project_name = $metadata['project_name'] ?? '';
    $usage_description = '';

    // Get cart items from transient using checkout_key (primary method)
    $cart_items = [];
    $checkout_key = $metadata['checkout_key'] ?? '';
    error_log("Looking for checkout_key: '{$checkout_key}'");

    if (!empty($checkout_key)) {
        $transient_data = get_transient($checkout_key);
        error_log("Transient data for {$checkout_key}: " . ($transient_data ? json_encode($transient_data) : 'NULL/FALSE'));

        if ($transient_data) {
            $cart_items = $transient_data['cart_items'] ?? [];
            $licensee_name = $licensee_name ?: ($transient_data['licensee_name'] ?? '');
            $project_name = $project_name ?: ($transient_data['project_name'] ?? '');
            $usage_description = $transient_data['usage_description'] ?? '';
            $user_id = $user_id ?: ($transient_data['user_id'] ?? 0);

            error_log("Cart checkout: Retrieved " . count($cart_items) . " items from transient {$checkout_key}");
        } else {
            error_log("Cart checkout: Transient {$checkout_key} not found or expired");
        }
    } else {
        error_log("No checkout_key in metadata");
    }

    // Fallback to legacy methods if checkout_key method fails
    if (empty($cart_items)) {
        error_log("Trying legacy methods to find cart_items...");

        // Try cart_items from metadata (legacy)
        if (!empty($metadata['cart_items'])) {
            $cart_items = json_decode($metadata['cart_items'], true);
            error_log("Found cart_items in metadata: " . json_encode($cart_items));
        }

        // Fallback to old transient format
        if (empty($cart_items)) {
            $legacy_key = 'fml_checkout_' . $session['id'];
            $transient_data = get_transient($legacy_key);
            error_log("Checking legacy transient {$legacy_key}: " . ($transient_data ? 'found' : 'not found'));

            if ($transient_data) {
                $cart_items = $transient_data['cart_items'] ?? [];
                $licensee_name = $licensee_name ?: ($transient_data['licensee_name'] ?? '');
                $project_name = $project_name ?: ($transient_data['project_name'] ?? '');
                $usage_description = $usage_description ?: ($transient_data['usage_description'] ?? '');
                $user_id = $user_id ?: ($transient_data['user_id'] ?? 0);
            }
        }
    }

    error_log("Final cart_items count: " . count($cart_items) . ", user_id: {$user_id}");

    if (empty($cart_items) || $user_id <= 0) {
        error_log("FAILED: Cart checkout completed but missing cart_items or user_id. Session: " . $session['id'] . ", checkout_key: " . $checkout_key);
        return;
    }

    error_log("Processing " . count($cart_items) . " cart items for user {$user_id}");

    $licenses_created = [];

    foreach ($cart_items as $index => $item) {
        error_log("--- Processing cart item {$index} ---");
        error_log("Item data: " . json_encode($item));

        try {
            $song_id = intval($item['song_id'] ?? 0);
            $license_type = $item['license_type'] ?? 'cc_by';
            $include_nft = !empty($item['include_nft']) && $item['include_nft'] !== '0';
            $wallet_address = $item['wallet_address'] ?? '';

            error_log("Song ID: {$song_id}, License Type: {$license_type}, Include NFT: " . ($include_nft ? 'yes' : 'no'));

            if ($song_id <= 0) {
                error_log("Skipping item - invalid song_id");
                continue;
            }

            // Get song and artist info
            $song_pod = pods('song', $song_id);
            if (!$song_pod || !$song_pod->exists()) {
                error_log("Cart checkout: Song {$song_id} not found in Pods");
                continue;
            }

            $song_name = $song_pod->field('post_title');
            $artist_data = $song_pod->field('artist');
            $artist_name = 'Unknown Artist';
            if (!empty($artist_data)) {
                $artist_id = is_array($artist_data) ? $artist_data['ID'] : $artist_data;
                $artist_pod = pods('artist', $artist_id);
                if ($artist_pod && $artist_pod->exists()) {
                    $artist_name = $artist_pod->field('post_title');
                }
            }

            error_log("Song: {$song_name}, Artist: {$artist_name}");

            // Generate appropriate license PDF
            error_log("Generating {$license_type} license PDF...");

            if ($license_type === 'non_exclusive') {
                // Commercial license
                $license_price = intval(get_option('fml_non_exclusive_license_price', 4900));
                $license_result = fml_generate_non_exclusive_license_pdf(
                    $song_id,
                    $song_name,
                    $artist_name,
                    $licensee_name,
                    $project_name,
                    $usage_description,
                    $license_price / 100,
                    'usd'
                );
            } else {
                // CC-BY license
                if (!function_exists('fml_generate_ccby_license_pdf')) {
                    error_log("FATAL: fml_generate_ccby_license_pdf function not found!");
                    continue;
                }
                $license_result = fml_generate_ccby_license_pdf(
                    $song_id,
                    $song_name,
                    $artist_name,
                    $licensee_name,
                    $project_name
                );
            }

            error_log("PDF generation result: " . json_encode($license_result));

            if (!$license_result['success']) {
                error_log("Failed to generate license PDF for song {$song_id}: " . ($license_result['error'] ?? 'Unknown error'));
                continue;
            }

            // Create license record
            error_log("Creating license record in Pods...");
            $pod = pods('license');
            $license_data = [
                'user' => $user_id,
                'song' => $song_id,
                'datetime' => current_time('mysql'),
                'license_url' => $license_result['url'],
                'licensor' => $licensee_name,
                'project' => $project_name,
                'description_of_usage' => $usage_description,
                'legal_name' => $licensee_name,
                'license_type' => $license_type,
                'stripe_payment_id' => $session['payment_intent'] ?? $session['id'],
                'stripe_payment_status' => 'completed'
            ];

            // Add payment amount for commercial licenses
            if ($license_type === 'non_exclusive') {
                $license_data['payment_amount'] = intval(get_option('fml_non_exclusive_license_price', 4900));
                $license_data['payment_currency'] = 'usd';
            }

            // Add NFT fields if NFT was selected
            if ($include_nft) {
                $license_data['nft_status'] = 'pending';
                $license_data['wallet_address'] = $wallet_address;
            }

            error_log("License data: " . json_encode($license_data));
            $new_license_id = $pod->add($license_data);
            error_log("Pods add() returned: " . ($new_license_id ? $new_license_id : 'FALSE/0'));

            if ($new_license_id) {
                wp_update_post([
                    'ID' => $new_license_id,
                    'post_status' => 'publish'
            ]);

            $licenses_created[] = [
                'license_id' => $new_license_id,
                'song_id' => $song_id,
                'include_nft' => $include_nft,
                'wallet_address' => $wallet_address
            ];

            error_log("License created: {$new_license_id} for song {$song_id} (type: {$license_type}, nft: " . ($include_nft ? 'yes' : 'no') . ")");

            // Log license creation
            if (function_exists('fml_log_event')) {
                fml_log_event('license', "License #{$new_license_id} created for song #{$song_id}", [
                    'license_type' => $license_type,
                    'song_name' => $song_name,
                    'include_nft' => $include_nft
                ], 'success');
            }

            // Queue NFT minting if selected
            if ($include_nft && !empty($wallet_address)) {
                fml_queue_license_nft_minting($new_license_id, $wallet_address);
            }
        } else {
            error_log("Failed to create license record in Pods for song {$song_id}");
        }

        } catch (Exception $e) {
            error_log("EXCEPTION processing cart item {$index}: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
        } catch (Error $e) {
            error_log("ERROR processing cart item {$index}: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
        }
    }

    error_log("=== Cart checkout complete. Licenses created: " . count($licenses_created) . " ===");

    // Clear user's cart after successful checkout
    if ($user_id) {
        delete_user_meta($user_id, 'fml_cart');
    }

    // Clean up transients
    if (!empty($checkout_key)) {
        delete_transient($checkout_key);
    }
    delete_transient('fml_checkout_' . $session['id']); // Legacy cleanup

    // Send email notification
    $user = get_user_by('id', $user_id);
    if ($user && !empty($licenses_created)) {
        $license_count = count($licenses_created);
        $nft_count = count(array_filter($licenses_created, function($l) { return $l['include_nft']; }));

        $email_body = "Your Sync.Land license purchase has been processed.\n\n";
        $email_body .= "Licenses Created: {$license_count}\n";
        if ($nft_count > 0) {
            $email_body .= "NFT Verifications: {$nft_count} (minting in progress)\n";
        }
        $email_body .= "Project: {$project_name}\n\n";
        $email_body .= "View all your licenses: " . home_url('/account/licenses/') . "\n\n";
        $email_body .= "Thank you for using Sync.Land!";

        wp_mail(
            $user->user_email,
            "Your Sync.Land Licenses ({$license_count} " . ($license_count === 1 ? 'license' : 'licenses') . ")",
            $email_body
        );
    }
}

/**
 * Queue NFT minting for a license (async processing)
 */
function fml_queue_license_nft_minting($license_id, $wallet_address) {
    // Add to NFT queue for tracking
    if (function_exists('fml_add_to_nft_queue')) {
        fml_add_to_nft_queue($license_id, $wallet_address);
    }

    // Schedule NFT minting as a background task (5 second delay to allow license to be fully saved)
    wp_schedule_single_event(time() + 5, 'fml_mint_license_nft_async', [$license_id, $wallet_address]);

    // Trigger cron immediately
    spawn_cron();

    error_log("Queued NFT minting for license {$license_id} to wallet {$wallet_address}");
}

// Register the async minting action
add_action('fml_mint_license_nft_async', 'fml_process_queued_nft_minting', 10, 2);

function fml_process_queued_nft_minting($license_id, $wallet_address) {
    error_log("Processing queued NFT minting for license {$license_id}");

    // Update queue status to processing
    if (function_exists('fml_update_nft_queue_item')) {
        fml_update_nft_queue_item($license_id, 'processing');
    }

    // Call the minting function from nmkr.php
    if (function_exists('fml_mint_license_nft')) {
        $result = fml_mint_license_nft($license_id, $wallet_address);

        if ($result['success']) {
            error_log("NFT minted successfully for license {$license_id}");

            // Update queue status to completed
            if (function_exists('fml_update_nft_queue_item')) {
                fml_update_nft_queue_item($license_id, 'completed');
            }

            // Log success
            if (function_exists('fml_log_event')) {
                fml_log_event('nft', "NFT minted for license #{$license_id}", [
                    'transaction_id' => $result['data']['transaction_id'] ?? null
                ], 'success');
            }
        } else {
            $error_msg = $result['error'] ?? 'Unknown error';
            $http_code = $result['http_code'] ?? null;
            $response_body = $result['response'] ?? null;

            error_log("NFT minting failed for license {$license_id}: " . $error_msg);
            if ($http_code) {
                error_log("HTTP Code: {$http_code}");
            }
            if ($response_body) {
                error_log("Response: " . json_encode($response_body));
            }

            // Update queue status to failed with full error details
            $full_error = $error_msg;
            if ($response_body && is_array($response_body)) {
                $full_error .= ' | Response: ' . json_encode($response_body);
            }

            if (function_exists('fml_update_nft_queue_item')) {
                fml_update_nft_queue_item($license_id, 'failed', $full_error);
            }

            // Update license status to reflect failure
            $license_pod = pods('license', $license_id);
            if ($license_pod && $license_pod->exists()) {
                $license_pod->save(['nft_status' => 'failed']);
            }

            // Log failure
            if (function_exists('fml_log_event')) {
                fml_log_event('nft', "NFT minting failed for license #{$license_id}", [
                    'error' => $error_msg
                ], 'error');
            }
        }
    } else {
        error_log("fml_mint_license_nft function not available");

        // Update queue status
        if (function_exists('fml_update_nft_queue_item')) {
            fml_update_nft_queue_item($license_id, 'failed', 'Minting function not available');
        }
    }
}
