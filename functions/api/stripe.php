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
 * Required wp-config.php constants:
 * - FML_STRIPE_SECRET_KEY (sk_live_... or sk_test_...)
 * - FML_STRIPE_WEBHOOK_SECRET (whsec_...)
 * - FML_STRIPE_PUBLISHABLE_KEY (pk_live_... or pk_test_...)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
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
});

/**
 * Handle incoming Stripe webhooks
 */
function fml_stripe_webhook_handler(WP_REST_Request $request) {
    // Get the raw body for signature verification
    $payload = $request->get_body();
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    // Verify webhook secret is configured
    if (!defined('FML_STRIPE_WEBHOOK_SECRET') || empty(FML_STRIPE_WEBHOOK_SECRET)) {
        error_log('Stripe webhook error: FML_STRIPE_WEBHOOK_SECRET not configured');
        return new WP_REST_Response(['error' => 'Webhook not configured'], 500);
    }

    // Verify webhook signature
    try {
        $event = fml_verify_stripe_webhook($payload, $sig_header, FML_STRIPE_WEBHOOK_SECRET);
    } catch (Exception $e) {
        error_log('Stripe webhook signature verification failed: ' . $e->getMessage());
        return new WP_REST_Response(['error' => 'Invalid signature'], 400);
    }

    // Handle the event
    switch ($event['type']) {
        case 'checkout.session.completed':
            $session = $event['data']['object'];
            fml_handle_checkout_completed($session);
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

    return new WP_REST_Response(['received' => true], 200);
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

    // Check if this is a non-exclusive license purchase
    if (!isset($metadata['type']) || $metadata['type'] !== 'non_exclusive_license') {
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
                "View all your licenses: " . home_url('/account/my-licenses/') . "\n\n" .
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
    require get_stylesheet_directory() . "/php/aws/aws-autoloader.php";
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
    if (!defined('FML_STRIPE_SECRET_KEY') || empty(FML_STRIPE_SECRET_KEY)) {
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
        'success_url' => home_url("/account/my-licenses/?payment=success&song={$song_id}"),
        'cancel_url' => home_url("/song/{$song_id}/?payment=cancelled"),
        'metadata' => [
            'type' => 'non_exclusive_license',
            'song_id' => $song_id,
            'user_id' => get_current_user_id(),
            'licensee_name' => $licensee_name ?: wp_get_current_user()->display_name,
            'project_name' => $project_name,
            'usage_description' => $usage_description
        ],
        'customer_email' => wp_get_current_user()->user_email
    ];

    // Make Stripe API request
    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . FML_STRIPE_SECRET_KEY,
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

function fml_licensing_settings_page() {
    if (isset($_POST['fml_save_licensing_settings']) && check_admin_referer('fml_licensing_settings')) {
        update_option('fml_non_exclusive_license_price', intval($_POST['fml_non_exclusive_license_price']));
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $license_price = get_option('fml_non_exclusive_license_price', 4900);

    ?>
    <div class="wrap">
        <h1>Sync.Land Licensing Settings</h1>

        <form method="post">
            <?php wp_nonce_field('fml_licensing_settings'); ?>

            <h2>License Pricing</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fml_non_exclusive_license_price">Non-Exclusive License Price (cents USD)</label>
                    </th>
                    <td>
                        <input type="number" name="fml_non_exclusive_license_price" id="fml_non_exclusive_license_price"
                               value="<?php echo esc_attr($license_price); ?>" min="0" step="1" />
                        <p class="description">
                            Price in cents. 4900 = $49.00 USD<br>
                            This is the default price for non-exclusive sync licenses.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>License Types</h2>
            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th>License Type</th>
                        <th>Price</th>
                        <th>NFT Available</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>CC-BY 4.0</strong><br><small>Creative Commons Attribution</small></td>
                        <td>Free</td>
                        <td>Yes (free mint)</td>
                    </tr>
                    <tr>
                        <td><strong>Non-Exclusive Sync</strong><br><small>Commercial sync license</small></td>
                        <td>$<?php echo number_format($license_price / 100, 2); ?></td>
                        <td>No</td>
                    </tr>
                </tbody>
            </table>

            <h2>Stripe Configuration Status</h2>
            <table class="form-table">
                <tr>
                    <th>Stripe Secret Key</th>
                    <td>
                        <?php if (defined('FML_STRIPE_SECRET_KEY') && !empty(FML_STRIPE_SECRET_KEY)): ?>
                            <span style="color: green;">&#10003; Configured</span>
                            (<?php echo strpos(FML_STRIPE_SECRET_KEY, 'sk_test_') === 0 ? 'Test Mode' : 'Live Mode'; ?>)
                        <?php else: ?>
                            <span style="color: red;">&#10007; Not configured</span>
                            <br><code>define('FML_STRIPE_SECRET_KEY', 'sk_...');</code> in wp-config.php
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Stripe Webhook Secret</th>
                    <td>
                        <?php if (defined('FML_STRIPE_WEBHOOK_SECRET') && !empty(FML_STRIPE_WEBHOOK_SECRET)): ?>
                            <span style="color: green;">&#10003; Configured</span>
                        <?php else: ?>
                            <span style="color: red;">&#10007; Not configured</span>
                            <br><code>define('FML_STRIPE_WEBHOOK_SECRET', 'whsec_...');</code> in wp-config.php
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Webhook URL</th>
                    <td>
                        <code><?php echo esc_html(home_url('/wp-json/FML/v1/stripe/webhook')); ?></code>
                        <p class="description">Add this URL in Stripe Dashboard > Developers > Webhooks</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="fml_save_licensing_settings" class="button-primary"
                       value="Save Settings" />
            </p>
        </form>
    </div>
    <?php
}
