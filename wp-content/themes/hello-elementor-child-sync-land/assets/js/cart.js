/**
 * Sync.Land Shopping Cart JavaScript
 *
 * Handles AJAX cart operations, UI updates, and checkout flow.
 */

(function($) {
    'use strict';

    // Cart API endpoints
    const API = {
        base: '/wp-json/FML/v1',
        getCart: '/wp-json/FML/v1/cart',
        addToCart: '/wp-json/FML/v1/cart/add',
        updateCart: '/wp-json/FML/v1/cart/update',
        removeFromCart: '/wp-json/FML/v1/cart/remove/',
        clearCart: '/wp-json/FML/v1/cart/clear',
        checkout: '/wp-json/FML/v1/cart/checkout'
    };

    // Cart state
    let cartData = null;

    /**
     * Initialize cart functionality
     */
    function init() {
        // Bind event handlers
        bindCartEvents();
        bindAddToCartEvents();
        bindCheckoutEvents();
        bindLicenseOptionEvents();

        // Initialize tooltips
        initTooltips();

        // Update cart count on page load
        updateCartCount();
    }

    /**
     * Bind cart page events
     */
    function bindCartEvents() {
        // License type change
        $(document).on('change', '.fml-license-type-select', function() {
            const songId = $(this).data('song-id');
            const licenseType = $(this).val();
            updateCartItem(songId, { license_type: licenseType });
        });

        // NFT checkbox toggle
        $(document).on('change', '.fml-nft-checkbox', function() {
            const songId = $(this).data('song-id');
            const includeNft = $(this).is(':checked');
            const $walletInput = $(this).closest('.fml-cart-item').find('.fml-wallet-input');

            if (includeNft) {
                $walletInput.removeClass('hidden');
            } else {
                $walletInput.addClass('hidden');
            }

            updateCartItem(songId, { include_nft: includeNft });
        });

        // Wallet address change (debounced)
        let walletTimeout;
        $(document).on('input', '.fml-wallet-address', function() {
            const $input = $(this);
            const songId = $input.data('song-id');
            const walletAddress = $input.val().trim();

            clearTimeout(walletTimeout);
            walletTimeout = setTimeout(function() {
                if (validateCardanoAddress(walletAddress)) {
                    $input.removeClass('invalid').addClass('valid');
                    $input.siblings('.fml-wallet-validation').text('Valid address').removeClass('error').addClass('success');
                    updateCartItem(songId, { wallet_address: walletAddress });
                } else if (walletAddress.length > 0) {
                    $input.removeClass('valid').addClass('invalid');
                    $input.siblings('.fml-wallet-validation').text('Invalid Cardano address').removeClass('success').addClass('error');
                } else {
                    $input.removeClass('valid invalid');
                    $input.siblings('.fml-wallet-validation').text('');
                }
            }, 500);
        });

        // Remove item from cart
        $(document).on('click', '.fml-cart-remove', function(e) {
            e.preventDefault();
            const songId = $(this).data('song-id');

            if (confirm('Remove this item from your cart?')) {
                removeFromCart(songId);
            }
        });

        // Clear cart
        $(document).on('click', '#fml-clear-cart-btn', function(e) {
            e.preventDefault();

            if (confirm('Are you sure you want to clear your entire cart?')) {
                clearCart();
            }
        });
    }

    /**
     * Bind add to cart button events
     */
    function bindAddToCartEvents() {
        $(document).on('click', '.fml-add-to-cart-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const songId = $btn.data('song-id');
            const $wrapper = $btn.closest('.fml-add-to-cart-wrapper');

            // Check if already in cart
            if ($btn.hasClass('in-cart')) {
                window.location.href = '/cart/';
                return;
            }

            // Get license options
            const licenseType = $wrapper.find('input[name="fml_license_type_' + songId + '"]:checked').val() || 'cc_by';
            const includeNft = $wrapper.find('.fml-nft-add-checkbox').is(':checked');
            const walletAddress = $wrapper.find('.fml-wallet-input-field').val() || '';

            // Validate wallet if NFT selected
            if (includeNft && walletAddress && !validateCardanoAddress(walletAddress)) {
                alert('Please enter a valid Cardano wallet address for NFT delivery.');
                return;
            }

            addToCart(songId, licenseType, includeNft, walletAddress, $btn);
        });

        // Show/hide wallet input when NFT checkbox changes on add-to-cart forms
        $(document).on('change', '.fml-nft-add-checkbox', function() {
            const $walletInput = $(this).closest('.fml-nft-option').find('.fml-wallet-input-add');

            if ($(this).is(':checked')) {
                $walletInput.removeClass('hidden');
            } else {
                $walletInput.addClass('hidden');
            }
        });
    }

    /**
     * Bind checkout events
     */
    function bindCheckoutEvents() {
        // Checkout button
        $(document).on('click', '#fml-checkout-btn', function(e) {
            e.preventDefault();
            processCheckout();
        });

        // Standalone checkout form
        $(document).on('submit', '#fml-checkout-form', function(e) {
            e.preventDefault();
            processCheckout();
        });
    }

    /**
     * Bind license options selector events
     */
    function bindLicenseOptionEvents() {
        // License card selection
        $(document).on('change', '.fml-license-card input[type="radio"]', function() {
            const $card = $(this).closest('.fml-license-card');
            const $container = $(this).closest('.fml-license-options-standalone');

            $container.find('.fml-license-card').removeClass('selected');
            $card.addClass('selected');

            $container.find('.fml-selected-license').val($(this).val());
        });

        // NFT addon checkbox
        $(document).on('change', '.fml-nft-addon-checkbox', function() {
            const $body = $(this).closest('.fml-nft-addon').find('.fml-wallet-input-standalone');
            const $hidden = $(this).closest('.fml-license-options-standalone').find('.fml-include-nft-hidden');

            if ($(this).is(':checked')) {
                $body.removeClass('hidden');
                $hidden.val('1');
            } else {
                $body.addClass('hidden');
                $hidden.val('0');
            }
        });
    }

    /**
     * Add item to cart
     */
    function addToCart(songId, licenseType, includeNft, walletAddress, $btn) {
        const nonce = $btn.closest('[data-nonce]').data('nonce') || $('#fml-cart').data('nonce');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');

        $.ajax({
            url: API.addToCart,
            method: 'POST',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: {
                song_id: songId,
                license_type: licenseType,
                include_nft: includeNft,
                wallet_address: walletAddress
            },
            success: function(response) {
                if (response.success) {
                    $btn.removeClass('fml-btn-primary').addClass('fml-btn-secondary in-cart');
                    $btn.html('<i class="fas fa-check"></i> In Cart - <a href="/cart/">View Cart</a>');

                    updateCartCount(response.data.item_count);
                    showNotification('Added to cart!', 'success');
                } else {
                    $btn.prop('disabled', false).html('<i class="fas fa-cart-plus"></i> Add to Cart');
                    showNotification(response.error || 'Failed to add to cart', 'error');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-cart-plus"></i> Add to Cart');
                const errorMsg = xhr.responseJSON?.error || 'Failed to add to cart';
                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Update cart item
     */
    function updateCartItem(songId, data) {
        const nonce = $('#fml-cart').data('nonce');

        showCartLoading(true);

        data.song_id = songId;

        $.ajax({
            url: API.updateCart,
            method: 'POST',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: data,
            success: function(response) {
                showCartLoading(false);

                if (response.success) {
                    refreshCartDisplay(response.data);
                } else {
                    showNotification(response.error || 'Failed to update cart', 'error');
                }
            },
            error: function(xhr) {
                showCartLoading(false);
                const errorMsg = xhr.responseJSON?.error || 'Failed to update cart';
                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Remove item from cart
     */
    function removeFromCart(songId) {
        const nonce = $('#fml-cart').data('nonce');

        showCartLoading(true);

        $.ajax({
            url: API.removeFromCart + songId,
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function(response) {
                showCartLoading(false);

                if (response.success) {
                    // Remove item from DOM
                    $('.fml-cart-item[data-song-id="' + songId + '"]').fadeOut(300, function() {
                        $(this).remove();

                        // Check if cart is now empty
                        if ($('.fml-cart-item').length === 0) {
                            location.reload();
                        } else {
                            refreshCartDisplay(response.data);
                        }
                    });

                    updateCartCount(response.data.item_count);
                    showNotification('Item removed from cart', 'success');
                } else {
                    showNotification(response.error || 'Failed to remove item', 'error');
                }
            },
            error: function(xhr) {
                showCartLoading(false);
                const errorMsg = xhr.responseJSON?.error || 'Failed to remove item';
                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Clear entire cart
     */
    function clearCart() {
        const nonce = $('#fml-cart').data('nonce');

        showCartLoading(true);

        $.ajax({
            url: API.clearCart,
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function(response) {
                showCartLoading(false);

                if (response.success) {
                    updateCartCount(0);
                    location.reload();
                } else {
                    showNotification(response.error || 'Failed to clear cart', 'error');
                }
            },
            error: function(xhr) {
                showCartLoading(false);
                const errorMsg = xhr.responseJSON?.error || 'Failed to clear cart';
                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Process checkout
     */
    function processCheckout() {
        const $cart = $('#fml-cart');
        const nonce = $cart.data('nonce');

        // Validate form
        const licenseeName = $('#fml-licensee-name').val().trim();
        const projectName = $('#fml-project-name').val().trim();
        const usageDescription = $('#fml-usage-description').val().trim();

        if (!licenseeName) {
            showNotification('Please enter your name or company name', 'error');
            $('#fml-licensee-name').focus();
            return;
        }

        if (!projectName) {
            showNotification('Please enter a project name', 'error');
            $('#fml-project-name').focus();
            return;
        }

        // Validate wallet addresses for NFT items
        let walletError = false;
        $('.fml-cart-item').each(function() {
            const $item = $(this);
            const includeNft = $item.find('.fml-nft-checkbox').is(':checked');
            const walletAddress = $item.find('.fml-wallet-address').val().trim();

            if (includeNft && walletAddress && !validateCardanoAddress(walletAddress)) {
                walletError = true;
                $item.find('.fml-wallet-address').focus();
                return false;
            }
        });

        if (walletError) {
            showNotification('Please enter valid Cardano wallet addresses for NFT items', 'error');
            return;
        }

        // Show loading state
        const $btn = $('#fml-checkout-btn');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        showCartLoading(true);

        $.ajax({
            url: API.checkout,
            method: 'POST',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: {
                licensee_name: licenseeName,
                project_name: projectName,
                usage_description: usageDescription
            },
            success: function(response) {
                showCartLoading(false);

                if (response.success) {
                    if (response.checkout_url) {
                        // Redirect to Stripe checkout
                        window.location.href = response.checkout_url;
                    } else if (response.redirect_url) {
                        // Free checkout completed, redirect to success page
                        window.location.href = response.redirect_url;
                    }
                } else {
                    $btn.prop('disabled', false).html(originalText);
                    showNotification(response.error || 'Checkout failed', 'error');
                }
            },
            error: function(xhr) {
                showCartLoading(false);
                $btn.prop('disabled', false).html(originalText);

                let errorMsg = 'Checkout failed';
                if (xhr.status === 401) {
                    errorMsg = 'Please log in to complete checkout';
                    setTimeout(function() {
                        window.location.href = '/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href);
                    }, 1500);
                } else if (xhr.responseJSON?.error) {
                    errorMsg = xhr.responseJSON.error;
                }

                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Refresh cart display with new data
     */
    function refreshCartDisplay(data) {
        if (!data) return;

        cartData = data;

        // Update totals
        const $summary = $('.fml-cart-summary');

        if (data.subtotal > 0) {
            $summary.find('.fml-cart-row:contains("License Fees") span:last').text('$' + (data.subtotal / 100).toFixed(2));
        }

        if (data.nft_total > 0) {
            $summary.find('.fml-cart-row:contains("NFT Minting") span:last').text('$' + (data.nft_total / 100).toFixed(2));
        }

        // Update total
        const totalText = data.total > 0 ? '$' + (data.total / 100).toFixed(2) : 'Free';
        $('.fml-total-amount').text(totalText);

        // Update item prices
        data.items.forEach(function(item) {
            const $itemRow = $('.fml-cart-item[data-song-id="' + item.song_id + '"]');
            const priceText = item.item_total > 0 ? '$' + (item.item_total / 100).toFixed(2) : 'Free';
            $itemRow.find('.fml-item-price-amount').text(priceText);
        });

        // Update checkout button text
        const $checkoutBtn = $('#fml-checkout-btn');
        if (data.total > 0) {
            $checkoutBtn.html('<i class="fas fa-lock"></i> Proceed to Checkout');
        } else {
            const licenseText = data.item_count > 1 ? 'Licenses' : 'License';
            $checkoutBtn.html('<i class="fas fa-download"></i> Generate Free ' + licenseText);
        }

        // Update header count
        updateCartCount(data.item_count);
    }

    /**
     * Update cart count in header
     */
    function updateCartCount(count) {
        const $count = $('#fml-cart-count');

        if (typeof count !== 'undefined') {
            $count.text(count);

            if (count > 0) {
                $count.removeClass('hidden');
            } else {
                $count.addClass('hidden');
            }
        } else {
            // Fetch current count
            $.get(API.getCart, function(response) {
                if (response.success && response.data) {
                    const itemCount = response.data.item_count || 0;
                    $count.text(itemCount);

                    if (itemCount > 0) {
                        $count.removeClass('hidden');
                    } else {
                        $count.addClass('hidden');
                    }
                }
            });
        }
    }

    /**
     * Validate Cardano wallet address
     */
    function validateCardanoAddress(address) {
        if (!address) return false;

        // Mainnet address (addr1...)
        if (/^addr1[a-z0-9]{53,98}$/i.test(address)) {
            return true;
        }

        // Testnet address (addr_test1...)
        if (/^addr_test1[a-z0-9]{50,95}$/i.test(address)) {
            return true;
        }

        return false;
    }

    /**
     * Show/hide cart loading overlay
     */
    function showCartLoading(show) {
        const $loading = $('.fml-cart-loading');

        if (show) {
            $loading.removeClass('hidden');
        } else {
            $loading.addClass('hidden');
        }
    }

    /**
     * Show notification message (centered modal style)
     */
    function showNotification(message, type) {
        type = type || 'info';

        // Remove existing notifications and overlays
        $('.fml-notification, .fml-notification-overlay').remove();

        // Determine icon based on type
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        else if (type === 'error') icon = 'fa-exclamation-circle';
        else if (type === 'warning') icon = 'fa-exclamation-triangle';

        // Create overlay
        const $overlay = $('<div class="fml-notification-overlay"></div>');

        // Create notification element (centered modal style)
        const $notification = $('<div class="fml-notification fml-notification-' + type + '">' +
            '<div class="fml-notification-icon"><i class="fas ' + icon + '"></i></div>' +
            '<span class="fml-notification-message">' + message + '</span>' +
            '<button class="fml-notification-close">&times;</button>' +
            '</div>');

        // Add to page
        $('body').append($overlay).append($notification);

        // Animate in
        setTimeout(function() {
            $overlay.addClass('show');
            $notification.addClass('show');
        }, 10);

        // Auto-remove duration: errors stay longer (8s), others 5s
        const duration = (type === 'error') ? 8000 : 5000;

        const autoRemoveTimer = setTimeout(function() {
            hideNotification($overlay, $notification);
        }, duration);

        // Close button
        $notification.find('.fml-notification-close').on('click', function() {
            clearTimeout(autoRemoveTimer);
            hideNotification($overlay, $notification);
        });

        // Click overlay to close
        $overlay.on('click', function() {
            clearTimeout(autoRemoveTimer);
            hideNotification($overlay, $notification);
        });
    }

    /**
     * Hide notification with animation
     */
    function hideNotification($overlay, $notification) {
        $overlay.removeClass('show');
        $notification.removeClass('show');
        setTimeout(function() {
            $overlay.remove();
            $notification.remove();
        }, 300);
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('[title]').each(function() {
            const $el = $(this);
            const title = $el.attr('title');

            if (title && $el.hasClass('fml-tooltip')) {
                $el.removeAttr('title');
                $el.attr('data-tooltip', title);
            }
        });
    }

    // Initialize on DOM ready
    $(document).ready(init);

    // Expose public methods
    window.FMLCart = {
        addToCart: addToCart,
        updateCartItem: updateCartItem,
        removeFromCart: removeFromCart,
        clearCart: clearCart,
        updateCartCount: updateCartCount,
        validateCardanoAddress: validateCardanoAddress
    };

})(jQuery);
