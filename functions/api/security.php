<?php
/**
 * API Security Module for Sync.Land
 *
 * Provides:
 * - API key authentication for external applications
 * - Rate limiting
 * - CORS configuration
 * - Input validation helpers
 * - Permission callback helpers
 *
 * Configuration in wp-config.php:
 * define('FML_API_RATE_LIMIT', 100);        // Requests per hour
 * define('FML_API_RATE_WINDOW', 3600);      // Window in seconds
 * define('FML_CORS_ALLOWED_ORIGINS', 'https://app.sync.land,https://example.com');
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * API KEY MANAGEMENT
 * ============================================================================
 */

/**
 * Register API key management endpoints (admin only)
 */
add_action('rest_api_init', function() {
    // Generate new API key
    register_rest_route('FML/v1', '/api-keys', [
        'methods' => 'POST',
        'callback' => 'fml_create_api_key',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    // List API keys
    register_rest_route('FML/v1', '/api-keys', [
        'methods' => 'GET',
        'callback' => 'fml_list_api_keys',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    // Revoke API key
    register_rest_route('FML/v1', '/api-keys/(?P<key_id>[a-zA-Z0-9_-]+)', [
        'methods' => 'DELETE',
        'callback' => 'fml_revoke_api_key',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});

/**
 * Create a new API key for an external application
 */
function fml_create_api_key(WP_REST_Request $request) {
    $app_name = sanitize_text_field($request->get_param('app_name'));

    if (empty($app_name)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'app_name is required'
        ], 400);
    }

    // Generate secure API key
    $api_key = 'fml_' . bin2hex(random_bytes(24));
    $key_id = 'key_' . bin2hex(random_bytes(8));

    // Store hashed key
    $api_keys = get_option('fml_api_keys', []);
    $api_keys[$key_id] = [
        'hash' => password_hash($api_key, PASSWORD_DEFAULT),
        'app_name' => $app_name,
        'created_at' => current_time('mysql'),
        'created_by' => get_current_user_id(),
        'last_used' => null,
        'request_count' => 0
    ];
    update_option('fml_api_keys', $api_keys);

    return new WP_REST_Response([
        'success' => true,
        'key_id' => $key_id,
        'api_key' => $api_key, // Only shown once
        'message' => 'Store this API key securely - it will not be shown again'
    ], 201);
}

/**
 * List all API keys (without the actual keys)
 */
function fml_list_api_keys(WP_REST_Request $request) {
    $api_keys = get_option('fml_api_keys', []);

    $result = [];
    foreach ($api_keys as $key_id => $data) {
        $result[] = [
            'key_id' => $key_id,
            'app_name' => $data['app_name'],
            'created_at' => $data['created_at'],
            'last_used' => $data['last_used'],
            'request_count' => $data['request_count']
        ];
    }

    return new WP_REST_Response([
        'success' => true,
        'keys' => $result
    ], 200);
}

/**
 * Revoke an API key
 */
function fml_revoke_api_key(WP_REST_Request $request) {
    $key_id = $request->get_param('key_id');

    $api_keys = get_option('fml_api_keys', []);

    if (!isset($api_keys[$key_id])) {
        return new WP_REST_Response([
            'success' => false,
            'error' => 'API key not found'
        ], 404);
    }

    unset($api_keys[$key_id]);
    update_option('fml_api_keys', $api_keys);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'API key revoked'
    ], 200);
}


/**
 * ============================================================================
 * API KEY AUTHENTICATION
 * ============================================================================
 */

/**
 * Validate API key from request header
 * Header: X-API-Key: fml_xxx...
 */
function fml_validate_api_key($request = null) {
    $api_key = null;

    // Check header first
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $api_key = sanitize_text_field($_SERVER['HTTP_X_API_KEY']);
    }

    // Fallback to query param (less secure, for debugging)
    if (!$api_key && isset($_GET['api_key'])) {
        $api_key = sanitize_text_field($_GET['api_key']);
    }

    if (empty($api_key)) {
        return false;
    }

    // Validate against stored keys
    $api_keys = get_option('fml_api_keys', []);

    foreach ($api_keys as $key_id => $data) {
        if (password_verify($api_key, $data['hash'])) {
            // Update usage stats
            $api_keys[$key_id]['last_used'] = current_time('mysql');
            $api_keys[$key_id]['request_count']++;
            update_option('fml_api_keys', $api_keys);

            return [
                'key_id' => $key_id,
                'app_name' => $data['app_name']
            ];
        }
    }

    return false;
}

/**
 * Check if request has valid API key
 */
function fml_has_valid_api_key() {
    return fml_validate_api_key() !== false;
}


/**
 * ============================================================================
 * RATE LIMITING
 * ============================================================================
 */

/**
 * Check rate limit for current client
 * Returns true if allowed, false if rate limited
 */
function fml_check_rate_limit($identifier = null) {
    $rate_limit = defined('FML_API_RATE_LIMIT') ? FML_API_RATE_LIMIT : 100;
    $rate_window = defined('FML_API_RATE_WINDOW') ? FML_API_RATE_WINDOW : 3600;

    // Identify client by API key or IP
    if (!$identifier) {
        $api_key_info = fml_validate_api_key();
        if ($api_key_info) {
            $identifier = 'apikey_' . $api_key_info['key_id'];
        } else {
            $identifier = 'ip_' . fml_get_client_ip();
        }
    }

    $transient_key = 'fml_rate_' . md5($identifier);
    $current_count = get_transient($transient_key);

    if ($current_count === false) {
        // First request in window
        set_transient($transient_key, 1, $rate_window);
        return true;
    }

    if ($current_count >= $rate_limit) {
        return false;
    }

    // Increment counter
    set_transient($transient_key, $current_count + 1, $rate_window);
    return true;
}

/**
 * Get client IP address
 */
function fml_get_client_ip() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated list (X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Get remaining rate limit info
 */
function fml_get_rate_limit_info($identifier = null) {
    $rate_limit = defined('FML_API_RATE_LIMIT') ? FML_API_RATE_LIMIT : 100;
    $rate_window = defined('FML_API_RATE_WINDOW') ? FML_API_RATE_WINDOW : 3600;

    if (!$identifier) {
        $api_key_info = fml_validate_api_key();
        if ($api_key_info) {
            $identifier = 'apikey_' . $api_key_info['key_id'];
        } else {
            $identifier = 'ip_' . fml_get_client_ip();
        }
    }

    $transient_key = 'fml_rate_' . md5($identifier);
    $current_count = get_transient($transient_key);

    return [
        'limit' => $rate_limit,
        'remaining' => max(0, $rate_limit - ($current_count ?: 0)),
        'window_seconds' => $rate_window
    ];
}


/**
 * ============================================================================
 * CORS HANDLING
 * ============================================================================
 */

/**
 * Handle CORS headers for API requests
 */
add_action('rest_api_init', function() {
    // Remove default CORS handling
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

    // Add custom CORS handling
    add_filter('rest_pre_serve_request', 'fml_handle_cors');
}, 15);

function fml_handle_cors($value) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    // Get allowed origins
    $allowed_origins = [];
    if (defined('FML_CORS_ALLOWED_ORIGINS') && !empty(FML_CORS_ALLOWED_ORIGINS)) {
        $allowed_origins = array_map('trim', explode(',', FML_CORS_ALLOWED_ORIGINS));
    }

    // Always allow same-origin
    $site_url = get_site_url();
    $allowed_origins[] = $site_url;

    // In development, allow localhost
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $allowed_origins[] = 'http://localhost:3000';
        $allowed_origins[] = 'http://localhost:5173';
        $allowed_origins[] = 'http://127.0.0.1:3000';
    }

    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key, X-WP-Nonce');
        header('Access-Control-Max-Age: 86400');
    }

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit();
    }

    return $value;
}


/**
 * ============================================================================
 * PERMISSION CALLBACKS
 * ============================================================================
 */

/**
 * Permission callback: Requires user to be logged in
 */
function fml_permission_logged_in() {
    return is_user_logged_in();
}

/**
 * Permission callback: Requires valid API key OR logged-in user
 */
function fml_permission_api_or_user() {
    return is_user_logged_in() || fml_has_valid_api_key();
}

/**
 * Permission callback: Requires valid API key only
 */
function fml_permission_api_key_required() {
    return fml_has_valid_api_key();
}

/**
 * Permission callback: Public endpoint with rate limiting
 */
function fml_permission_public_rate_limited() {
    if (!fml_check_rate_limit()) {
        // Return WP_Error for rate limit exceeded
        return new WP_Error(
            'rate_limit_exceeded',
            'Rate limit exceeded. Please try again later.',
            ['status' => 429]
        );
    }
    return true;
}

/**
 * Permission callback: API key or user with rate limiting
 */
function fml_permission_authenticated_rate_limited() {
    if (!fml_check_rate_limit()) {
        return new WP_Error(
            'rate_limit_exceeded',
            'Rate limit exceeded. Please try again later.',
            ['status' => 429]
        );
    }
    return is_user_logged_in() || fml_has_valid_api_key();
}


/**
 * ============================================================================
 * INPUT VALIDATION HELPERS
 * ============================================================================
 */

/**
 * Sanitize and validate integer ID
 */
function fml_validate_id($value) {
    $id = intval($value);
    return $id > 0 ? $id : false;
}

/**
 * Sanitize text input
 */
function fml_validate_text($value, $max_length = 255) {
    $text = sanitize_text_field($value);
    if (strlen($text) > $max_length) {
        return substr($text, 0, $max_length);
    }
    return $text;
}

/**
 * Validate email
 */
function fml_validate_email($value) {
    return is_email($value) ? sanitize_email($value) : false;
}

/**
 * Validate Cardano wallet address
 */
function fml_validate_wallet_address($address) {
    $address = sanitize_text_field($address);
    // Cardano mainnet addresses start with addr1
    // Testnet addresses start with addr_test1
    if (preg_match('/^addr(1|_test1)[a-z0-9]{50,120}$/', $address)) {
        return $address;
    }
    return false;
}

/**
 * Escape value for SQL LIKE queries (prevents SQL injection in LIKE clauses)
 */
function fml_escape_like($value) {
    global $wpdb;
    return $wpdb->esc_like(sanitize_text_field($value));
}


/**
 * ============================================================================
 * RESPONSE HELPERS
 * ============================================================================
 */

/**
 * Create standardized API response
 */
function fml_api_response($data, $status = 200) {
    $response = new WP_REST_Response($data, $status);

    // Add rate limit headers
    $rate_info = fml_get_rate_limit_info();
    $response->header('X-RateLimit-Limit', $rate_info['limit']);
    $response->header('X-RateLimit-Remaining', $rate_info['remaining']);

    return $response;
}

/**
 * Create error response
 */
function fml_api_error($message, $code = 'api_error', $status = 400) {
    return fml_api_response([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ], $status);
}

/**
 * Create success response
 */
function fml_api_success($data, $status = 200) {
    return fml_api_response(array_merge([
        'success' => true
    ], $data), $status);
}


/**
 * ============================================================================
 * ADMIN INTERFACE
 * ============================================================================
 */

add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'API Keys',
        'API Keys',
        'manage_options',
        'fml-api-keys',
        'fml_api_keys_admin_page'
    );
});

function fml_api_keys_admin_page() {
    // Handle form submissions
    if (isset($_POST['fml_create_api_key']) && check_admin_referer('fml_api_keys_action')) {
        $app_name = sanitize_text_field($_POST['app_name']);
        if (!empty($app_name)) {
            $api_key = 'fml_' . bin2hex(random_bytes(24));
            $key_id = 'key_' . bin2hex(random_bytes(8));

            $api_keys = get_option('fml_api_keys', []);
            $api_keys[$key_id] = [
                'hash' => password_hash($api_key, PASSWORD_DEFAULT),
                'app_name' => $app_name,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
                'last_used' => null,
                'request_count' => 0
            ];
            update_option('fml_api_keys', $api_keys);

            echo '<div class="notice notice-success"><p>API Key created: <code>' . esc_html($api_key) . '</code><br><strong>Copy this now - it will not be shown again!</strong></p></div>';
        }
    }

    if (isset($_POST['fml_revoke_api_key']) && check_admin_referer('fml_api_keys_action')) {
        $key_id = sanitize_text_field($_POST['key_id']);
        $api_keys = get_option('fml_api_keys', []);
        if (isset($api_keys[$key_id])) {
            unset($api_keys[$key_id]);
            update_option('fml_api_keys', $api_keys);
            echo '<div class="notice notice-success"><p>API key revoked.</p></div>';
        }
    }

    $api_keys = get_option('fml_api_keys', []);
    ?>
    <div class="wrap">
        <h1>Sync.Land API Keys</h1>

        <h2>Create New API Key</h2>
        <form method="post">
            <?php wp_nonce_field('fml_api_keys_action'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="app_name">Application Name</label></th>
                    <td>
                        <input type="text" name="app_name" id="app_name" class="regular-text" required>
                        <p class="description">Name of the application using this API key</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="fml_create_api_key" class="button-primary" value="Generate API Key">
            </p>
        </form>

        <h2>Existing API Keys</h2>
        <?php if (empty($api_keys)): ?>
            <p>No API keys have been created yet.</p>
        <?php else: ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Key ID</th>
                        <th>Application</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Requests</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_keys as $key_id => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($key_id); ?></code></td>
                            <td><?php echo esc_html($data['app_name']); ?></td>
                            <td><?php echo esc_html($data['created_at']); ?></td>
                            <td><?php echo $data['last_used'] ? esc_html($data['last_used']) : 'Never'; ?></td>
                            <td><?php echo intval($data['request_count']); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('fml_api_keys_action'); ?>
                                    <input type="hidden" name="key_id" value="<?php echo esc_attr($key_id); ?>">
                                    <input type="submit" name="fml_revoke_api_key" class="button button-secondary" value="Revoke" onclick="return confirm('Are you sure you want to revoke this API key?');">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>API Usage</h2>
        <p>To authenticate API requests, include the API key in the <code>X-API-Key</code> header:</p>
        <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">curl -H "X-API-Key: fml_your_api_key_here" \
     <?php echo esc_html(home_url('/wp-json/FML/v1/songs/123')); ?></pre>

        <h2>Rate Limiting</h2>
        <p>
            <strong>Current Limit:</strong> <?php echo defined('FML_API_RATE_LIMIT') ? FML_API_RATE_LIMIT : 100; ?> requests per hour<br>
            Configure in <code>wp-config.php</code>: <code>define('FML_API_RATE_LIMIT', 100);</code>
        </p>
    </div>
    <?php
}
