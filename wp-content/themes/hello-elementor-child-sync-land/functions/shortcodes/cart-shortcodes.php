<?php
/**
 * Shopping Cart Shortcodes for Sync.Land
 *
 * Provides shortcodes for displaying and interacting with the shopping cart.
 *
 * Shortcodes:
 * - [fml_cart] - Full cart display with items, totals, and checkout
 * - [fml_cart_icon] - Mini cart icon with item count (for header)
 * - [fml_add_to_cart] - Add to cart button for song pages
 * - [fml_license_options] - License type selector with NFT toggle
 * - [fml_license_verified] - Display NFT verification badge for a license
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================================
 * [fml_cart] - Full Cart Display
 * ============================================================================
 * Displays the complete shopping cart with items, pricing, and checkout form.
 *
 * Usage: [fml_cart]
 */
add_shortcode('fml_cart', 'fml_cart_shortcode');
function fml_cart_shortcode($atts) {
    $atts = shortcode_atts([
        'show_empty_message' => 'true'
    ], $atts);

    $summary = fml_cart_get_summary();
    $nft_fee = intval(get_option('fml_nft_minting_fee', 500));
    $license_price = intval(get_option('fml_non_exclusive_license_price', 4900));

    // Check test/preprod mode status
    $stripe_mode = function_exists('fml_get_stripe_mode') ? fml_get_stripe_mode() : 'test';
    $nmkr_mode = function_exists('fml_get_nmkr_mode') ? fml_get_nmkr_mode() : 'preprod';
    $is_test_mode = ($stripe_mode === 'test' || $nmkr_mode === 'preprod');

    ob_start();
    ?>
    <div id="fml-cart" class="fml-cart-container" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
        <?php if ($is_test_mode): ?>
        <div class="fml-test-mode-banner">
            <i class="fas fa-flask"></i>
            <strong>TEST MODE</strong> -
            <?php
            $test_parts = [];
            if ($stripe_mode === 'test') $test_parts[] = 'Stripe (Test)';
            if ($nmkr_mode === 'preprod') $test_parts[] = 'NMKR (Preprod)';
            echo implode(' &amp; ', $test_parts);
            ?>
            <span class="fml-test-mode-note">No real charges will be made. Use test cards and preprod wallet addresses.</span>
        </div>
        <?php endif; ?>
        <?php if (empty($summary['items'])): ?>
            <?php if ($atts['show_empty_message'] === 'true'): ?>
            <div class="fml-cart-empty">
                <i class="fas fa-shopping-cart fa-3x"></i>
                <h3>Your cart is empty</h3>
                <p>Browse our music library and add songs to license.</p>
                <a href="<?php echo home_url('/songs/'); ?>" class="fml-btn fml-btn-primary">Browse Songs</a>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Cart Items -->
            <div class="fml-cart-items">
                <h3>Your Cart (<?php echo $summary['item_count']; ?> item<?php echo $summary['item_count'] !== 1 ? 's' : ''; ?>)</h3>

                <?php foreach ($summary['items'] as $item): ?>
                <div class="fml-cart-item" data-song-id="<?php echo $item['song_id']; ?>">
                    <div class="fml-cart-item-image">
                        <?php if ($item['thumbnail']): ?>
                            <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['song_title']); ?>">
                        <?php else: ?>
                            <div class="fml-cart-item-placeholder"><i class="fas fa-music"></i></div>
                        <?php endif; ?>
                    </div>

                    <div class="fml-cart-item-details">
                        <h4 class="fml-cart-item-title">
                            <a href="<?php echo esc_url($item['permalink']); ?>"><?php echo esc_html($item['song_title']); ?></a>
                        </h4>
                        <p class="fml-cart-item-artist"><?php echo esc_html($item['artist_name']); ?></p>

                        <!-- License Type Selector -->
                        <div class="fml-cart-item-license">
                            <label>License Type:</label>
                            <select class="fml-license-type-select" data-song-id="<?php echo $item['song_id']; ?>">
                                <option value="cc_by" <?php selected($item['license_type'], 'cc_by'); ?>>
                                    CC-BY 4.0 (Free)
                                </option>
                                <option value="non_exclusive" <?php selected($item['license_type'], 'non_exclusive'); ?>>
                                    Commercial License ($<?php echo number_format($license_price / 100, 2); ?>)
                                </option>
                            </select>
                        </div>

                        <!-- NFT Toggle -->
                        <div class="fml-cart-item-nft">
                            <label class="fml-nft-toggle">
                                <input type="checkbox" class="fml-nft-checkbox" data-song-id="<?php echo $item['song_id']; ?>"
                                       <?php checked($item['include_nft']); ?>>
                                <span class="fml-nft-label">
                                    Add NFT Verification (+$<?php echo number_format($nft_fee / 100, 2); ?>)
                                    <i class="fas fa-info-circle" title="Mint your license as an NFT on Cardano blockchain for permanent verification"></i>
                                </span>
                            </label>

                            <!-- Wallet Address Input (shown when NFT selected) -->
                            <div class="fml-wallet-input <?php echo $item['include_nft'] ? '' : 'hidden'; ?>">
                                <input type="text" class="fml-wallet-address" data-song-id="<?php echo $item['song_id']; ?>"
                                       value="<?php echo esc_attr($item['wallet_address']); ?>"
                                       placeholder="<?php echo ($nmkr_mode === 'preprod') ? 'Enter Cardano preprod address (addr_test1...)' : 'Enter Cardano wallet address (addr1...)'; ?>">
                                <span class="fml-wallet-validation"></span>
                            </div>
                        </div>
                    </div>

                    <div class="fml-cart-item-price">
                        <span class="fml-item-price-amount">
                            <?php if ($item['item_total'] > 0): ?>
                                $<?php echo number_format($item['item_total'] / 100, 2); ?>
                            <?php else: ?>
                                Free
                            <?php endif; ?>
                        </span>
                        <button class="fml-cart-remove" data-song-id="<?php echo $item['song_id']; ?>" title="Remove from cart">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Cart Summary -->
            <div class="fml-cart-summary">
                <div class="fml-cart-totals">
                    <?php if ($summary['subtotal'] > 0): ?>
                    <div class="fml-cart-row">
                        <span>License Fees:</span>
                        <span>$<?php echo number_format($summary['subtotal'] / 100, 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($summary['nft_total'] > 0): ?>
                    <div class="fml-cart-row">
                        <span>NFT Minting Fees:</span>
                        <span>$<?php echo number_format($summary['nft_total'] / 100, 2); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="fml-cart-row fml-cart-total">
                        <span>Total:</span>
                        <span class="fml-total-amount">
                            <?php if ($summary['total'] > 0): ?>
                                $<?php echo number_format($summary['total'] / 100, 2); ?>
                            <?php else: ?>
                                Free
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Checkout Form -->
                <div class="fml-checkout-form">
                    <h4>License Details</h4>

                    <div class="fml-form-group">
                        <label for="fml-licensee-name">Your Name / Company *</label>
                        <input type="text" id="fml-licensee-name" name="licensee_name" required
                               value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
                    </div>

                    <div class="fml-form-group">
                        <label for="fml-project-name">Project Name *</label>
                        <input type="text" id="fml-project-name" name="project_name" required
                               placeholder="e.g., My YouTube Video">
                    </div>

                    <div class="fml-form-group">
                        <label for="fml-usage-description">Usage Description</label>
                        <textarea id="fml-usage-description" name="usage_description" rows="2"
                                  placeholder="Describe how you'll use this music (optional)"></textarea>
                    </div>

                    <?php if (!is_user_logged_in()): ?>
                    <div class="fml-login-notice">
                        <p><i class="fas fa-info-circle"></i> Please <a href="<?php echo wp_login_url(get_permalink()); ?>">log in</a> to complete your purchase.</p>
                    </div>
                    <?php else: ?>
                    <button type="button" id="fml-checkout-btn" class="fml-btn fml-btn-primary fml-btn-block">
                        <?php if ($summary['total'] > 0): ?>
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        <?php else: ?>
                            <i class="fas fa-download"></i> Generate Free License<?php echo $summary['item_count'] > 1 ? 's' : ''; ?>
                        <?php endif; ?>
                    </button>
                    <?php endif; ?>

                    <button type="button" id="fml-clear-cart-btn" class="fml-btn fml-btn-secondary fml-btn-block">
                        Clear Cart
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="fml-cart-loading hidden">
            <i class="fas fa-spinner fa-spin"></i> Processing...
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * ============================================================================
 * [fml_cart_icon] - Mini Cart Icon
 * ============================================================================
 * Displays a small cart icon with item count for the site header.
 *
 * Usage: [fml_cart_icon]
 * Attributes:
 * - show_total: 'true' or 'false' (default: false)
 */
add_shortcode('fml_cart_icon', 'fml_cart_icon_shortcode');
function fml_cart_icon_shortcode($atts) {
    $atts = shortcode_atts([
        'show_total' => 'false'
    ], $atts);

    $count = fml_cart_get_item_count();
    $total = fml_cart_get_total();

    ob_start();
    ?>
    <a href="<?php echo home_url('/cart/'); ?>" class="fml-mini-cart" id="fml-mini-cart">
        <i class="fas fa-shopping-cart"></i>
        <span class="fml-cart-count <?php echo $count === 0 ? 'hidden' : ''; ?>" id="fml-cart-count"><?php echo $count; ?></span>
        <?php if ($atts['show_total'] === 'true' && $total > 0): ?>
        <span class="fml-cart-total-mini">$<?php echo number_format($total / 100, 2); ?></span>
        <?php endif; ?>
    </a>
    <?php
    return ob_get_clean();
}


/**
 * ============================================================================
 * [fml_add_to_cart] - Add to Cart Button
 * ============================================================================
 * Displays an "Add to Cart" button for a specific song.
 *
 * Usage: [fml_add_to_cart song_id="123"]
 * Attributes:
 * - song_id: The song post ID (optional, defaults to current post)
 * - show_options: 'true' or 'false' (default: true) - Show license options inline
 * - button_text: Custom button text (default: 'Add to Cart')
 */
add_shortcode('fml_add_to_cart', 'fml_add_to_cart_shortcode');
function fml_add_to_cart_shortcode($atts) {
    $atts = shortcode_atts([
        'song_id' => get_the_ID(),
        'show_options' => 'true',
        'button_text' => 'Add to Cart'
    ], $atts);

    $song_id = intval($atts['song_id']);

    // Verify it's a valid song
    $song_pod = pods('song', $song_id);
    if (!$song_pod || !$song_pod->exists()) {
        return '';
    }

    $nft_fee = intval(get_option('fml_nft_minting_fee', 500));
    $license_price = intval(get_option('fml_non_exclusive_license_price', 4900));

    // Check if already in cart
    $cart = fml_cart_get_items();
    $in_cart = false;
    foreach ($cart as $item) {
        if ($item['song_id'] === $song_id) {
            $in_cart = true;
            break;
        }
    }

    ob_start();
    ?>
    <div class="fml-add-to-cart-wrapper" data-song-id="<?php echo $song_id; ?>" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
        <?php if ($atts['show_options'] === 'true'): ?>
        <div class="fml-license-options">
            <div class="fml-option-group">
                <label>License Type:</label>
                <div class="fml-radio-group">
                    <label class="fml-radio-option">
                        <input type="radio" name="fml_license_type_<?php echo $song_id; ?>" value="cc_by" checked>
                        <span class="fml-radio-label">
                            <strong>CC-BY 4.0</strong>
                            <span class="fml-price">Free</span>
                        </span>
                    </label>
                    <label class="fml-radio-option">
                        <input type="radio" name="fml_license_type_<?php echo $song_id; ?>" value="non_exclusive">
                        <span class="fml-radio-label">
                            <strong>Commercial License</strong>
                            <span class="fml-price">$<?php echo number_format($license_price / 100, 2); ?></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="fml-option-group fml-nft-option">
                <label class="fml-checkbox-option">
                    <input type="checkbox" name="fml_include_nft_<?php echo $song_id; ?>" class="fml-nft-add-checkbox">
                    <span class="fml-checkbox-label">
                        <strong>Add NFT Verification</strong>
                        <span class="fml-price">+$<?php echo number_format($nft_fee / 100, 2); ?></span>
                        <i class="fas fa-info-circle fml-tooltip" title="Mint your license as an NFT on the Cardano blockchain for permanent, verifiable proof of licensing"></i>
                    </span>
                </label>

                <div class="fml-wallet-input-add hidden">
                    <input type="text" name="fml_wallet_<?php echo $song_id; ?>" class="fml-wallet-input-field"
                           placeholder="Cardano wallet address (addr1...)">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <button type="button" class="fml-add-to-cart-btn fml-btn <?php echo $in_cart ? 'fml-btn-secondary in-cart' : 'fml-btn-primary'; ?>"
                data-song-id="<?php echo $song_id; ?>">
            <?php if ($in_cart): ?>
                <i class="fas fa-check"></i> In Cart - <a href="<?php echo home_url('/cart/'); ?>">View Cart</a>
            <?php else: ?>
                <i class="fas fa-cart-plus"></i> <?php echo esc_html($atts['button_text']); ?>
            <?php endif; ?>
        </button>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * ============================================================================
 * [fml_license_options] - License Options Selector
 * ============================================================================
 * Displays license options without the add to cart button.
 * Useful for integrating with existing forms like Gravity Forms.
 *
 * Usage: [fml_license_options song_id="123"]
 */
add_shortcode('fml_license_options', 'fml_license_options_shortcode');
function fml_license_options_shortcode($atts) {
    $atts = shortcode_atts([
        'song_id' => get_the_ID()
    ], $atts);

    $song_id = intval($atts['song_id']);
    $nft_fee = intval(get_option('fml_nft_minting_fee', 500));
    $license_price = intval(get_option('fml_non_exclusive_license_price', 4900));

    ob_start();
    ?>
    <div class="fml-license-options-standalone" data-song-id="<?php echo $song_id; ?>">
        <div class="fml-license-cards">
            <!-- CC-BY Option -->
            <div class="fml-license-card" data-license="cc_by">
                <div class="fml-license-card-header">
                    <h4>CC-BY 4.0</h4>
                    <span class="fml-license-price">Free</span>
                </div>
                <div class="fml-license-card-body">
                    <ul>
                        <li><i class="fas fa-check"></i> Personal & commercial use</li>
                        <li><i class="fas fa-check"></i> Attribution required</li>
                        <li><i class="fas fa-check"></i> Modifications allowed</li>
                        <li><i class="fas fa-check"></i> Perpetual license</li>
                    </ul>
                </div>
                <div class="fml-license-card-footer">
                    <label class="fml-radio-select">
                        <input type="radio" name="fml_license_select_<?php echo $song_id; ?>" value="cc_by" checked>
                        <span>Select</span>
                    </label>
                </div>
            </div>

            <!-- Commercial Option -->
            <div class="fml-license-card" data-license="non_exclusive">
                <div class="fml-license-card-header">
                    <h4>Commercial License</h4>
                    <span class="fml-license-price">$<?php echo number_format($license_price / 100, 2); ?></span>
                </div>
                <div class="fml-license-card-body">
                    <ul>
                        <li><i class="fas fa-check"></i> Full commercial rights</li>
                        <li><i class="fas fa-check"></i> No attribution required</li>
                        <li><i class="fas fa-check"></i> Sync licensing included</li>
                        <li><i class="fas fa-check"></i> Worldwide perpetual</li>
                    </ul>
                </div>
                <div class="fml-license-card-footer">
                    <label class="fml-radio-select">
                        <input type="radio" name="fml_license_select_<?php echo $song_id; ?>" value="non_exclusive">
                        <span>Select</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- NFT Add-on -->
        <div class="fml-nft-addon">
            <div class="fml-nft-addon-header">
                <label class="fml-checkbox-select">
                    <input type="checkbox" name="fml_nft_addon_<?php echo $song_id; ?>" class="fml-nft-addon-checkbox">
                    <span class="fml-nft-addon-title">
                        <i class="fas fa-certificate"></i>
                        Add NFT Verification
                        <span class="fml-addon-price">+$<?php echo number_format($nft_fee / 100, 2); ?></span>
                    </span>
                </label>
            </div>
            <div class="fml-nft-addon-body">
                <p>Mint your license as an NFT on the Cardano blockchain for permanent, verifiable proof of licensing.</p>
                <div class="fml-wallet-input-standalone hidden">
                    <label for="fml_wallet_standalone_<?php echo $song_id; ?>">Cardano Wallet Address:</label>
                    <input type="text" id="fml_wallet_standalone_<?php echo $song_id; ?>"
                           name="fml_wallet_standalone_<?php echo $song_id; ?>"
                           placeholder="addr1...">
                    <span class="fml-wallet-hint">Your NFT will be minted and sent to this address</span>
                </div>
            </div>
        </div>

        <input type="hidden" name="fml_selected_license_<?php echo $song_id; ?>" class="fml-selected-license" value="cc_by">
        <input type="hidden" name="fml_include_nft_<?php echo $song_id; ?>" class="fml-include-nft-hidden" value="0">
    </div>
    <?php
    return ob_get_clean();
}


/**
 * ============================================================================
 * [fml_license_verified] - License Verification Badge
 * ============================================================================
 * Displays NFT verification status badge for a license.
 *
 * Usage: [fml_license_verified license_id="123"]
 * Attributes:
 * - license_id: The license post ID (required)
 * - show_details: 'true' or 'false' (default: false)
 */
add_shortcode('fml_license_verified', 'fml_license_verified_shortcode');
function fml_license_verified_shortcode($atts) {
    $atts = shortcode_atts([
        'license_id' => 0,
        'show_details' => 'false'
    ], $atts);

    $license_id = intval($atts['license_id']);
    if (!$license_id) {
        return '';
    }

    $license_pod = pods('license', $license_id);
    if (!$license_pod || !$license_pod->exists()) {
        return '';
    }

    $nft_status = $license_pod->field('nft_status') ?: 'none';
    $nft_transaction_hash = $license_pod->field('nft_transaction_hash');
    $nft_asset_id = $license_pod->field('nft_asset_id');
    $nft_ipfs_hash = $license_pod->field('nft_ipfs_hash');

    $is_verified = ($nft_status === 'minted' && !empty($nft_transaction_hash));

    ob_start();
    ?>
    <div class="fml-verification-badge <?php echo $is_verified ? 'verified' : 'standard'; ?>">
        <?php if ($is_verified): ?>
            <span class="fml-badge fml-badge-verified">
                <i class="fas fa-certificate"></i> NFT Verified
            </span>
            <?php if ($atts['show_details'] === 'true'): ?>
            <div class="fml-verification-details">
                <?php if ($nft_transaction_hash): ?>
                <p>
                    <strong>Transaction:</strong>
                    <a href="https://cardanoscan.io/transaction/<?php echo esc_attr($nft_transaction_hash); ?>"
                       target="_blank" rel="noopener">
                        <?php echo esc_html(substr($nft_transaction_hash, 0, 16) . '...'); ?>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </p>
                <?php endif; ?>
                <?php if ($nft_ipfs_hash): ?>
                <p>
                    <strong>IPFS:</strong>
                    <a href="https://ipfs.io/ipfs/<?php echo esc_attr($nft_ipfs_hash); ?>"
                       target="_blank" rel="noopener">
                        <?php echo esc_html(substr($nft_ipfs_hash, 0, 16) . '...'); ?>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <span class="fml-badge fml-badge-standard">
                <i class="fas fa-file-contract"></i> Standard License
            </span>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * ============================================================================
 * [fml_checkout] - Standalone Checkout Form
 * ============================================================================
 * Displays just the checkout form without cart items.
 * Useful for embedding in custom pages.
 *
 * Usage: [fml_checkout]
 */
add_shortcode('fml_checkout', 'fml_checkout_shortcode');
function fml_checkout_shortcode($atts) {
    $summary = fml_cart_get_summary();

    if (empty($summary['items'])) {
        return '<p>Your cart is empty. <a href="' . home_url('/songs/') . '">Browse songs</a> to add licenses.</p>';
    }

    ob_start();
    ?>
    <div class="fml-checkout-standalone" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>">
        <div class="fml-checkout-summary">
            <h3>Order Summary</h3>
            <ul class="fml-order-items">
                <?php foreach ($summary['items'] as $item): ?>
                <li>
                    <span class="fml-order-item-name"><?php echo esc_html($item['song_title']); ?></span>
                    <span class="fml-order-item-details">
                        <?php echo esc_html($item['license_type_label']); ?>
                        <?php if ($item['include_nft']): ?><span class="fml-nft-badge">+NFT</span><?php endif; ?>
                    </span>
                    <span class="fml-order-item-price">
                        <?php echo $item['item_total'] > 0 ? '$' . number_format($item['item_total'] / 100, 2) : 'Free'; ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="fml-order-total">
                <strong>Total:</strong>
                <span><?php echo $summary['total'] > 0 ? '$' . number_format($summary['total'] / 100, 2) : 'Free'; ?></span>
            </div>
        </div>

        <form id="fml-checkout-form" class="fml-checkout-form-standalone">
            <div class="fml-form-group">
                <label for="fml-checkout-name">Your Name / Company *</label>
                <input type="text" id="fml-checkout-name" name="licensee_name" required
                       value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
            </div>

            <div class="fml-form-group">
                <label for="fml-checkout-project">Project Name *</label>
                <input type="text" id="fml-checkout-project" name="project_name" required>
            </div>

            <div class="fml-form-group">
                <label for="fml-checkout-usage">Usage Description</label>
                <textarea id="fml-checkout-usage" name="usage_description" rows="3"></textarea>
            </div>

            <?php if (!is_user_logged_in()): ?>
            <div class="fml-login-required">
                <p>Please <a href="<?php echo wp_login_url(get_permalink()); ?>">log in</a> to complete checkout.</p>
            </div>
            <?php else: ?>
            <button type="submit" class="fml-btn fml-btn-primary fml-btn-lg">
                <?php if ($summary['total'] > 0): ?>
                    <i class="fas fa-lock"></i> Pay $<?php echo number_format($summary['total'] / 100, 2); ?>
                <?php else: ?>
                    <i class="fas fa-download"></i> Get Free License<?php echo $summary['item_count'] > 1 ? 's' : ''; ?>
                <?php endif; ?>
            </button>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
