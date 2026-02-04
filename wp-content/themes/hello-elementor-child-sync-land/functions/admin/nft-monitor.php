<?php
/**
 * NFT Minting & Webhook Monitor Admin Page
 *
 * Provides monitoring dashboard for:
 * - NFT minting queue and status
 * - Stripe webhook events
 * - Cron job status
 * - License processing logs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * LOGGING SYSTEM
 * ============================================================================
 */

/**
 * Log an event to the FML log
 */
function fml_log_event($type, $message, $data = [], $status = 'info') {
    $logs = get_option('fml_event_logs', []);

    // Keep only last 500 logs
    if (count($logs) > 500) {
        $logs = array_slice($logs, -400);
    }

    $logs[] = [
        'id' => uniqid(),
        'timestamp' => current_time('mysql'),
        'type' => $type,
        'message' => $message,
        'data' => $data,
        'status' => $status // info, success, warning, error
    ];

    update_option('fml_event_logs', $logs);
}

/**
 * Get recent logs
 */
function fml_get_logs($type = null, $limit = 100) {
    $logs = get_option('fml_event_logs', []);
    $logs = array_reverse($logs); // Most recent first

    if ($type) {
        $logs = array_filter($logs, function($log) use ($type) {
            return $log['type'] === $type;
        });
    }

    return array_slice($logs, 0, $limit);
}

/**
 * Clear logs
 */
function fml_clear_logs($type = null) {
    if ($type) {
        $logs = get_option('fml_event_logs', []);
        $logs = array_filter($logs, function($log) use ($type) {
            return $log['type'] !== $type;
        });
        update_option('fml_event_logs', array_values($logs));
    } else {
        update_option('fml_event_logs', []);
    }
}

/**
 * ============================================================================
 * WEBHOOK EVENT TRACKING
 * ============================================================================
 */

/**
 * Track a webhook event
 */
function fml_track_webhook_event($event_type, $event_id, $status, $data = []) {
    $webhooks = get_option('fml_webhook_events', []);

    // Keep only last 200 webhook events
    if (count($webhooks) > 200) {
        $webhooks = array_slice($webhooks, -150);
    }

    $webhooks[] = [
        'id' => $event_id,
        'type' => $event_type,
        'status' => $status, // received, processing, completed, failed
        'timestamp' => current_time('mysql'),
        'data' => $data
    ];

    update_option('fml_webhook_events', $webhooks);

    // Also log it
    fml_log_event('webhook', "Webhook: {$event_type}", [
        'event_id' => $event_id,
        'status' => $status
    ], $status === 'failed' ? 'error' : 'info');
}

/**
 * ============================================================================
 * NFT QUEUE MANAGEMENT
 * ============================================================================
 */

/**
 * Add item to NFT minting queue
 */
function fml_add_to_nft_queue($license_id, $wallet_address, $priority = 'normal') {
    $queue = get_option('fml_nft_queue', []);

    // Check if already in queue
    foreach ($queue as $item) {
        if ($item['license_id'] == $license_id && $item['status'] !== 'completed' && $item['status'] !== 'failed') {
            return false; // Already queued
        }
    }

    $queue[] = [
        'id' => uniqid('nft_'),
        'license_id' => $license_id,
        'wallet_address' => $wallet_address,
        'priority' => $priority,
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'error' => null
    ];

    update_option('fml_nft_queue', $queue);

    fml_log_event('nft', "Added license #{$license_id} to NFT queue", [
        'wallet' => substr($wallet_address, 0, 20) . '...'
    ], 'info');

    return true;
}

/**
 * Update NFT queue item status
 */
function fml_update_nft_queue_item($license_id, $status, $error = null) {
    $queue = get_option('fml_nft_queue', []);

    foreach ($queue as $key => $item) {
        if ($item['license_id'] == $license_id) {
            $queue[$key]['status'] = $status;
            $queue[$key]['updated_at'] = current_time('mysql');
            if ($error) {
                $queue[$key]['error'] = $error;
            }
            if ($status === 'processing') {
                $queue[$key]['attempts'] = ($queue[$key]['attempts'] ?? 0) + 1;
            }
            break;
        }
    }

    update_option('fml_nft_queue', $queue);

    fml_log_event('nft', "NFT queue item #{$license_id} status: {$status}", [
        'error' => $error
    ], $status === 'failed' ? 'error' : ($status === 'completed' ? 'success' : 'info'));
}

/**
 * Get NFT queue
 */
function fml_get_nft_queue($status = null) {
    $queue = get_option('fml_nft_queue', []);

    if ($status) {
        $queue = array_filter($queue, function($item) use ($status) {
            return $item['status'] === $status;
        });
    }

    return array_reverse($queue); // Most recent first
}

/**
 * Clean up old completed/failed items from queue
 */
function fml_cleanup_nft_queue() {
    $queue = get_option('fml_nft_queue', []);
    $cutoff = strtotime('-7 days');

    $queue = array_filter($queue, function($item) use ($cutoff) {
        if (in_array($item['status'], ['completed', 'failed'])) {
            return strtotime($item['updated_at']) > $cutoff;
        }
        return true;
    });

    update_option('fml_nft_queue', array_values($queue));
}

/**
 * ============================================================================
 * ADMIN MENU & PAGE
 * ============================================================================
 */

add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'NFT & Webhook Monitor',
        'NFT Monitor',
        'manage_options',
        'fml-nft-monitor',
        'fml_nft_monitor_page'
    );
});

/**
 * Render the NFT Monitor admin page
 */
function fml_nft_monitor_page() {
    // Handle actions
    if (isset($_POST['fml_action']) && check_admin_referer('fml_monitor_action')) {
        $action = sanitize_text_field($_POST['fml_action']);

        switch ($action) {
            case 'clear_logs':
                $log_type = sanitize_text_field($_POST['log_type'] ?? '');
                fml_clear_logs($log_type ?: null);
                echo '<div class="notice notice-success"><p>Logs cleared.</p></div>';
                break;

            case 'retry_nft':
                $license_id = intval($_POST['license_id'] ?? 0);
                $force = isset($_POST['force_retry']) && $_POST['force_retry'] === '1';
                if ($license_id && function_exists('fml_retry_failed_nft_minting')) {
                    $result = fml_retry_failed_nft_minting($license_id, $force);
                    if ($result['success']) {
                        echo '<div class="notice notice-success"><p>NFT minting succeeded! TX: ' . esc_html($result['data']['transaction_hash'] ?? 'pending') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Retry failed: ' . esc_html($result['error'] ?? 'Unknown error') . '</p></div>';
                    }
                }
                break;

            case 'check_license_status':
                $license_id = intval($_POST['license_id'] ?? 0);
                if ($license_id) {
                    $license_pod = pods('license', $license_id);
                    if ($license_pod && $license_pod->exists()) {
                        $status = $license_pod->field('nft_status') ?: '(empty)';
                        $wallet = $license_pod->field('wallet_address') ?: '(none)';
                        $license_url = $license_pod->field('license_url') ?: '(none)';
                        echo '<div class="notice notice-info"><p><strong>License #' . $license_id . ' Status:</strong><br>';
                        echo 'nft_status: <code>' . esc_html($status) . '</code><br>';
                        echo 'wallet_address: <code>' . esc_html($wallet) . '</code><br>';
                        echo 'license_url: <code>' . esc_html($license_url) . '</code></p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>License not found</p></div>';
                    }
                }
                break;

            case 'cleanup_queue':
                fml_cleanup_nft_queue();
                echo '<div class="notice notice-success"><p>Queue cleaned up.</p></div>';
                break;

            case 'process_queue':
                fml_process_nft_queue_manually();
                echo '<div class="notice notice-success"><p>Queue processing triggered.</p></div>';
                break;

            case 'run_cron':
                $ran = fml_run_pending_cron_jobs();
                echo '<div class="notice notice-success"><p>Ran ' . $ran . ' cron job(s).</p></div>';
                break;

            case 'run_single_cron':
                $hook = sanitize_text_field($_POST['cron_hook'] ?? '');
                $args = isset($_POST['cron_args']) ? json_decode(stripslashes($_POST['cron_args']), true) : [];
                if ($hook) {
                    do_action_ref_array($hook, $args ?: []);
                    echo '<div class="notice notice-success"><p>Executed: ' . esc_html($hook) . '</p></div>';
                }
                break;
        }
    }

    // Get data for display
    $nft_queue = fml_get_nft_queue();
    $webhook_events = array_slice(get_option('fml_webhook_events', []), -50);
    $webhook_events = array_reverse($webhook_events);
    $logs = fml_get_logs(null, 100);

    // Get cron jobs
    $cron_jobs = _get_cron_array();
    $fml_crons = [];
    if ($cron_jobs) {
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $data) {
                if (strpos($hook, 'fml_') === 0) {
                    foreach ($data as $key => $item) {
                        $fml_crons[] = [
                            'hook' => $hook,
                            'timestamp' => $timestamp,
                            'schedule' => $item['schedule'] ?? 'single',
                            'args' => $item['args'] ?? []
                        ];
                    }
                }
            }
        }
    }

    // Get license stats
    $license_stats = fml_get_license_stats();

    ?>
    <div class="wrap">
        <h1>NFT & Webhook Monitor</h1>

        <style>
            .fml-monitor-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .fml-stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            .fml-stat-card h3 {
                margin: 0 0 10px;
                font-size: 14px;
                color: #666;
            }
            .fml-stat-card .number {
                font-size: 36px;
                font-weight: bold;
                color: #333;
            }
            .fml-stat-card .number.success { color: #46b450; }
            .fml-stat-card .number.warning { color: #ffb900; }
            .fml-stat-card .number.error { color: #dc3232; }
            .fml-stat-card .number.info { color: #0073aa; }

            .fml-tabs {
                margin-top: 20px;
            }
            .fml-tabs .nav-tab-wrapper {
                border-bottom: 1px solid #ccd0d4;
            }
            .fml-tab-content {
                display: none;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-top: none;
            }
            .fml-tab-content.active {
                display: block;
            }

            .fml-log-table {
                width: 100%;
                border-collapse: collapse;
            }
            .fml-log-table th,
            .fml-log-table td {
                padding: 8px 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .fml-log-table tr:hover {
                background: #f9f9f9;
            }

            .fml-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .fml-status-badge.pending { background: #f0f0f1; color: #666; }
            .fml-status-badge.processing { background: #fff3cd; color: #856404; }
            .fml-status-badge.completed, .fml-status-badge.success { background: #d4edda; color: #155724; }
            .fml-status-badge.failed, .fml-status-badge.error { background: #f8d7da; color: #721c24; }
            .fml-status-badge.minted { background: #d4edda; color: #155724; }
            .fml-status-badge.info { background: #d1ecf1; color: #0c5460; }
            .fml-status-badge.warning { background: #fff3cd; color: #856404; }

            .fml-actions {
                margin-top: 15px;
            }
            .fml-code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
                font-size: 12px;
            }

            .fml-mode-indicator {
                padding: 5px 10px;
                border-radius: 4px;
                font-weight: bold;
                display: inline-block;
                margin-bottom: 10px;
            }
            .fml-mode-indicator.test {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffc107;
            }
            .fml-mode-indicator.live {
                background: #d4edda;
                color: #155724;
                border: 1px solid #28a745;
            }
        </style>

        <!-- Current Mode Indicators -->
        <div style="margin-bottom: 20px;">
            <?php
            $stripe_mode = function_exists('fml_get_stripe_mode') ? fml_get_stripe_mode() : 'test';
            $nmkr_mode = function_exists('fml_get_nmkr_mode') ? fml_get_nmkr_mode() : 'preprod';
            ?>
            <span class="fml-mode-indicator <?php echo $stripe_mode === 'live' ? 'live' : 'test'; ?>">
                Stripe: <?php echo strtoupper($stripe_mode); ?>
            </span>
            <span class="fml-mode-indicator <?php echo $nmkr_mode === 'mainnet' ? 'live' : 'test'; ?>">
                NMKR: <?php echo strtoupper($nmkr_mode); ?>
            </span>
        </div>

        <!-- Stats Cards -->
        <div class="fml-monitor-grid">
            <div class="fml-stat-card">
                <h3>Total Licenses</h3>
                <div class="number info"><?php echo esc_html($license_stats['total']); ?></div>
            </div>
            <div class="fml-stat-card">
                <h3>NFT Verified</h3>
                <div class="number success"><?php echo esc_html($license_stats['nft_minted']); ?></div>
            </div>
            <div class="fml-stat-card">
                <h3>NFT Pending</h3>
                <div class="number warning"><?php echo esc_html($license_stats['nft_pending']); ?></div>
            </div>
            <div class="fml-stat-card">
                <h3>NFT Failed</h3>
                <div class="number error"><?php echo esc_html($license_stats['nft_failed']); ?></div>
            </div>
            <div class="fml-stat-card">
                <h3>Queue Size</h3>
                <div class="number"><?php echo count(fml_get_nft_queue('pending')); ?></div>
            </div>
            <div class="fml-stat-card">
                <h3>Scheduled Crons</h3>
                <div class="number"><?php echo count($fml_crons); ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="fml-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#nft-queue" class="nav-tab nav-tab-active" data-tab="nft-queue">NFT Queue</a>
                <a href="#webhooks" class="nav-tab" data-tab="webhooks">Webhook Events</a>
                <a href="#cron-jobs" class="nav-tab" data-tab="cron-jobs">Cron Jobs</a>
                <a href="#logs" class="nav-tab" data-tab="logs">Activity Logs</a>
                <a href="#licenses" class="nav-tab" data-tab="licenses">License Status</a>
            </h2>

            <!-- NFT Queue Tab -->
            <div id="nft-queue" class="fml-tab-content active">
                <h3>NFT Minting Queue</h3>

                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('fml_monitor_action'); ?>
                    <input type="hidden" name="fml_action" value="process_queue">
                    <button type="submit" class="button button-primary">Process Queue Now</button>
                </form>

                <form method="post" style="display: inline; margin-left: 10px;">
                    <?php wp_nonce_field('fml_monitor_action'); ?>
                    <input type="hidden" name="fml_action" value="cleanup_queue">
                    <button type="submit" class="button">Clean Up Old Items</button>
                </form>

                <?php if (empty($nft_queue)): ?>
                    <p style="margin-top: 20px;"><em>No items in queue.</em></p>
                <?php else: ?>
                    <table class="fml-log-table" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th>License ID</th>
                                <th>Wallet</th>
                                <th>Queue Status</th>
                                <th>License Status</th>
                                <th>Attempts</th>
                                <th>Error</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($nft_queue, 0, 50) as $item):
                                // Get actual license status from database
                                $license_pod = pods('license', $item['license_id']);
                                $db_nft_status = ($license_pod && $license_pod->exists()) ? ($license_pod->field('nft_status') ?: 'none') : 'not found';
                                $db_wallet = ($license_pod && $license_pod->exists()) ? $license_pod->field('wallet_address') : '';
                            ?>
                                <tr>
                                    <td><a href="<?php echo admin_url('post.php?post=' . $item['license_id'] . '&action=edit'); ?>">#<?php echo esc_html($item['license_id']); ?></a></td>
                                    <td>
                                        <code class="fml-code" title="<?php echo esc_attr($item['wallet_address']); ?>"><?php echo esc_html(substr($item['wallet_address'], 0, 15) . '...'); ?></code>
                                        <?php if ($db_wallet !== $item['wallet_address']): ?>
                                            <br><small style="color: #f6e05e;">DB: <?php echo esc_html(substr($db_wallet, 0, 10) . '...'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fml-status-badge <?php echo esc_attr($item['status']); ?>"><?php echo esc_html($item['status']); ?></span></td>
                                    <td><span class="fml-status-badge <?php echo esc_attr($db_nft_status); ?>"><?php echo esc_html($db_nft_status); ?></span></td>
                                    <td><?php echo esc_html($item['attempts'] ?? 0); ?></td>
                                    <td style="max-width: 300px; word-break: break-word; font-size: 11px;"><?php echo esc_html($item['error'] ?? '-'); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('fml_monitor_action'); ?>
                                            <input type="hidden" name="fml_action" value="check_license_status">
                                            <input type="hidden" name="license_id" value="<?php echo esc_attr($item['license_id']); ?>">
                                            <button type="submit" class="button button-small">Check</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('fml_monitor_action'); ?>
                                            <input type="hidden" name="fml_action" value="retry_nft">
                                            <input type="hidden" name="license_id" value="<?php echo esc_attr($item['license_id']); ?>">
                                            <input type="hidden" name="force_retry" value="1">
                                            <button type="submit" class="button button-small button-primary">Force Retry</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Webhooks Tab -->
            <div id="webhooks" class="fml-tab-content">
                <h3>Recent Webhook Events</h3>

                <?php if (empty($webhook_events)): ?>
                    <p><em>No webhook events recorded yet.</em></p>
                <?php else: ?>
                    <table class="fml-log-table">
                        <thead>
                            <tr>
                                <th>Event ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webhook_events as $event): ?>
                                <tr>
                                    <td><code class="fml-code"><?php echo esc_html(substr($event['id'], 0, 20)); ?>...</code></td>
                                    <td><?php echo esc_html($event['type']); ?></td>
                                    <td><span class="fml-status-badge <?php echo esc_attr($event['status']); ?>"><?php echo esc_html($event['status']); ?></span></td>
                                    <td><?php echo esc_html($event['timestamp']); ?></td>
                                    <td>
                                        <?php if (!empty($event['data'])): ?>
                                            <details>
                                                <summary>View</summary>
                                                <pre style="font-size: 11px; max-width: 400px; overflow: auto;"><?php echo esc_html(json_encode($event['data'], JSON_PRETTY_PRINT)); ?></pre>
                                            </details>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Cron Jobs Tab -->
            <div id="cron-jobs" class="fml-tab-content">
                <h3>Scheduled Cron Jobs</h3>

                <?php
                // Check cron health
                $cron_issues = fml_check_cron_health();
                if (!empty($cron_issues)):
                ?>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                    <strong style="color: #856404;"><span class="dashicons dashicons-warning"></span> Cron Health Issues Detected:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <?php foreach ($cron_issues as $issue): ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin: 10px 0 0; color: #856404;"><strong>Solution:</strong> Use the "Run All Due Cron Jobs" button below to manually process pending jobs.</p>
                </div>
                <?php endif; ?>

                <p style="margin-bottom: 15px;">
                    <strong>Current Server Time:</strong>
                    <code class="fml-code"><?php echo date('Y-m-d H:i:s'); ?></code>
                    (<?php echo date('T'); ?>)
                </p>

                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('fml_monitor_action'); ?>
                    <input type="hidden" name="fml_action" value="run_cron">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
                        Run All Due Cron Jobs
                    </button>
                    <span class="description" style="margin-left: 10px;">Manually execute all FML cron jobs that are ready to run</span>
                </form>

                <?php if (empty($fml_crons)): ?>
                    <p><em>No FML cron jobs scheduled.</em></p>
                <?php else: ?>
                    <table class="fml-log-table">
                        <thead>
                            <tr>
                                <th>Hook</th>
                                <th>Schedule</th>
                                <th>Scheduled For</th>
                                <th>Time Until Run</th>
                                <th>Arguments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_time = time();
                            foreach ($fml_crons as $cron):
                                $time_diff = $cron['timestamp'] - $current_time;
                                if ($time_diff <= 0) {
                                    $time_diff_display = '<span style="color: #48bb78; font-weight: bold;">Ready to run</span>';
                                } elseif ($time_diff < 60) {
                                    $time_diff_display = '<span style="color: #f6e05e;">' . $time_diff . ' seconds</span>';
                                } elseif ($time_diff < 3600) {
                                    $mins = floor($time_diff / 60);
                                    $secs = $time_diff % 60;
                                    $time_diff_display = '<span style="color: #f6e05e;">' . $mins . 'm ' . $secs . 's</span>';
                                } else {
                                    $hours = floor($time_diff / 3600);
                                    $mins = floor(($time_diff % 3600) / 60);
                                    $time_diff_display = '<span style="color: #fc8181;">' . $hours . 'h ' . $mins . 'm</span>';
                                }
                            ?>
                                <tr>
                                    <td><code class="fml-code"><?php echo esc_html($cron['hook']); ?></code></td>
                                    <td><?php echo esc_html($cron['schedule'] ?: 'single'); ?></td>
                                    <td><?php echo esc_html(date('Y-m-d H:i:s', $cron['timestamp'])); ?></td>
                                    <td><?php echo $time_diff_display; ?></td>
                                    <td>
                                        <?php if (!empty($cron['args'])): ?>
                                            <code class="fml-code"><?php echo esc_html(json_encode($cron['args'])); ?></code>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('fml_monitor_action'); ?>
                                            <input type="hidden" name="fml_action" value="run_single_cron">
                                            <input type="hidden" name="cron_hook" value="<?php echo esc_attr($cron['hook']); ?>">
                                            <input type="hidden" name="cron_args" value="<?php echo esc_attr(json_encode($cron['args'])); ?>">
                                            <button type="submit" class="button button-small">Run Now</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="fml-actions">
                    <h4>WP-Cron Status</h4>
                    <p>
                        <strong>DISABLE_WP_CRON:</strong>
                        <span class="fml-status-badge <?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'warning' : 'success'; ?>">
                            <?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Disabled (using system cron)' : 'Enabled'; ?>
                        </span>
                    </p>
                    <p>
                        <strong>ALTERNATE_WP_CRON:</strong>
                        <span class="fml-status-badge info">
                            <?php echo defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="fml-tab-content">
                <h3>Activity Logs</h3>

                <form method="post" style="margin-bottom: 15px;">
                    <?php wp_nonce_field('fml_monitor_action'); ?>
                    <input type="hidden" name="fml_action" value="clear_logs">
                    <select name="log_type">
                        <option value="">All Logs</option>
                        <option value="webhook">Webhook Logs</option>
                        <option value="nft">NFT Logs</option>
                        <option value="license">License Logs</option>
                    </select>
                    <button type="submit" class="button">Clear Logs</button>
                </form>

                <?php if (empty($logs)): ?>
                    <p><em>No logs recorded yet.</em></p>
                <?php else: ?>
                    <table class="fml-log-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Message</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo esc_html($log['timestamp']); ?></td>
                                    <td><span class="fml-status-badge info"><?php echo esc_html($log['type']); ?></span></td>
                                    <td><span class="fml-status-badge <?php echo esc_attr($log['status']); ?>"><?php echo esc_html($log['status']); ?></span></td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                    <td>
                                        <?php if (!empty($log['data'])): ?>
                                            <details>
                                                <summary>View</summary>
                                                <pre style="font-size: 11px; max-width: 300px; overflow: auto;"><?php echo esc_html(json_encode($log['data'], JSON_PRETTY_PRINT)); ?></pre>
                                            </details>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Licenses Tab -->
            <div id="licenses" class="fml-tab-content">
                <h3>Recent License NFT Status</h3>

                <?php
                $licenses = fml_get_recent_licenses_with_nft_status(25);
                if (empty($licenses)):
                ?>
                    <p><em>No licenses found.</em></p>
                <?php else: ?>
                    <table class="fml-log-table">
                        <thead>
                            <tr>
                                <th>License ID</th>
                                <th>Song</th>
                                <th>License Type</th>
                                <th>NFT Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($licenses as $license): ?>
                                <tr>
                                    <td><a href="<?php echo admin_url('post.php?post=' . $license['id'] . '&action=edit'); ?>">#<?php echo esc_html($license['id']); ?></a></td>
                                    <td><?php echo esc_html($license['song_title'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="fml-status-badge <?php echo $license['license_type'] === 'non_exclusive' ? 'info' : 'success'; ?>">
                                            <?php echo $license['license_type'] === 'non_exclusive' ? 'Commercial' : 'CC-BY'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fml-status-badge <?php echo esc_attr($license['nft_status'] ?? 'none'); ?>">
                                            <?php echo esc_html($license['nft_status'] ?? 'none'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($license['datetime']); ?></td>
                                    <td>
                                        <?php if ($license['nft_status'] === 'failed'): ?>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('fml_monitor_action'); ?>
                                                <input type="hidden" name="fml_action" value="retry_nft">
                                                <input type="hidden" name="license_id" value="<?php echo esc_attr($license['id']); ?>">
                                                <button type="submit" class="button button-small">Retry NFT</button>
                                            </form>
                                        <?php elseif ($license['nft_status'] === 'minted' && !empty($license['nft_transaction_hash'])): ?>
                                            <a href="https://cardanoscan.io/transaction/<?php echo esc_attr($license['nft_transaction_hash']); ?>" target="_blank" class="button button-small">View on Chain</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');

                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $('.fml-tab-content').removeClass('active');
                $('#' + tab).addClass('active');

                // Update URL hash
                window.location.hash = tab;
            });

            // Load tab from hash
            if (window.location.hash) {
                var tab = window.location.hash.substring(1);
                $('[data-tab="' + tab + '"]').click();
            }
        });
        </script>
    </div>
    <?php
}

/**
 * Get license statistics
 */
function fml_get_license_stats() {
    global $wpdb;

    $stats = [
        'total' => 0,
        'nft_minted' => 0,
        'nft_pending' => 0,
        'nft_failed' => 0,
        'cc_by' => 0,
        'non_exclusive' => 0
    ];

    // Get total licenses
    $license_post_type = 'license';
    $stats['total'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
        $license_post_type
    ));

    // Get NFT status counts
    $nft_statuses = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.meta_value as status, COUNT(*) as count
         FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = 'nft_status' AND p.post_type = %s AND p.post_status = 'publish'
         GROUP BY pm.meta_value",
        $license_post_type
    ));

    foreach ($nft_statuses as $row) {
        if ($row->status === 'minted') $stats['nft_minted'] = intval($row->count);
        if ($row->status === 'pending') $stats['nft_pending'] = intval($row->count);
        if ($row->status === 'failed') $stats['nft_failed'] = intval($row->count);
    }

    return $stats;
}

/**
 * Get recent licenses with NFT status
 */
function fml_get_recent_licenses_with_nft_status($limit = 25) {
    $args = [
        'post_type' => 'license',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    $query = new WP_Query($args);
    $licenses = [];

    foreach ($query->posts as $post) {
        $license_pod = pods('license', $post->ID);
        if (!$license_pod || !$license_pod->exists()) continue;

        $song_data = $license_pod->field('song');
        $song_title = 'Unknown';
        if (!empty($song_data)) {
            $song_id = is_array($song_data) ? $song_data['ID'] : $song_data;
            $song_title = get_the_title($song_id);
        }

        $licenses[] = [
            'id' => $post->ID,
            'song_title' => $song_title,
            'license_type' => $license_pod->field('license_type') ?: 'cc_by',
            'nft_status' => $license_pod->field('nft_status') ?: 'none',
            'nft_transaction_hash' => $license_pod->field('nft_transaction_hash'),
            'datetime' => $license_pod->field('datetime'),
            'wallet_address' => $license_pod->field('wallet_address')
        ];
    }

    return $licenses;
}

/**
 * Manually process NFT queue
 */
function fml_process_nft_queue_manually() {
    $pending = fml_get_nft_queue('pending');

    foreach (array_slice($pending, 0, 5) as $item) { // Process up to 5 at a time
        $license_id = $item['license_id'];
        $wallet_address = $item['wallet_address'];

        fml_update_nft_queue_item($license_id, 'processing');

        if (function_exists('fml_mint_license_nft')) {
            $result = fml_mint_license_nft($license_id, $wallet_address);

            if ($result['success']) {
                fml_update_nft_queue_item($license_id, 'completed');
            } else {
                fml_update_nft_queue_item($license_id, 'failed', $result['error'] ?? 'Unknown error');
            }
        } else {
            fml_update_nft_queue_item($license_id, 'failed', 'Minting function not available');
        }
    }
}

/**
 * Run all pending FML cron jobs manually
 * This bypasses the WordPress cron system which can fail on local dev
 */
function fml_run_pending_cron_jobs() {
    $cron_jobs = _get_cron_array();
    $current_time = time();
    $ran = 0;

    if (!$cron_jobs) {
        return 0;
    }

    foreach ($cron_jobs as $timestamp => $cron) {
        // Only run jobs that are due
        if ($timestamp > $current_time) {
            continue;
        }

        foreach ($cron as $hook => $data) {
            // Only process FML hooks
            if (strpos($hook, 'fml_') !== 0) {
                continue;
            }

            foreach ($data as $key => $item) {
                $args = $item['args'] ?? [];

                // Log what we're running
                error_log("Manually running cron: {$hook} with args: " . json_encode($args));

                // Execute the hook
                do_action_ref_array($hook, $args);

                // Unschedule this specific event
                wp_unschedule_event($timestamp, $hook, $args);

                $ran++;

                // Log to our system
                if (function_exists('fml_log_event')) {
                    fml_log_event('cron', "Manually executed: {$hook}", ['args' => $args], 'success');
                }
            }
        }
    }

    return $ran;
}

/**
 * Check if WordPress cron system is healthy
 */
function fml_check_cron_health() {
    $issues = [];

    // Check if cron is disabled
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        $issues[] = 'DISABLE_WP_CRON is set to true - WordPress cron will not run automatically';
    }

    // Check if we can reach ourselves (loopback test)
    $loopback_url = site_url('wp-cron.php');
    $response = wp_remote_get($loopback_url, [
        'timeout' => 5,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        $issues[] = 'Loopback request failed: ' . $response->get_error_message() . ' - Cron jobs may not fire automatically';
    }

    // Check for stuck cron
    $doing_cron = get_transient('doing_cron');
    if ($doing_cron && ($doing_cron > (time() - 60))) {
        $issues[] = 'A cron process appears to be running (or stuck)';
    }

    return $issues;
}
