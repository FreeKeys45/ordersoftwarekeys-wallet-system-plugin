<?php
/**
 * Wallet System Shortcodes
 * File: shortcodes.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// [wallet_balance] - Show current user's wallet balance
add_shortcode('wallet_balance', 'osk_wallet_balance_shortcode');

function osk_wallet_balance_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="osk-wallet-notice"><p>' . __('Please log in to view your wallet balance.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $atts = shortcode_atts(array(
        'show_label' => 'yes',
        'show_actions' => 'no',
        'style' => 'default' // default, simple, box
    ), $atts, 'wallet_balance');
    
    $user_id = get_current_user_id();
    $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    
    // Set color based on balance
    $color = '#555'; // Default
    if ($balance > 100) {
        $color = '#28a745'; // Green for > 100
    } elseif ($balance > 0 && $balance <= 100) {
        $color = '#ffc107'; // Yellow for 1-100
    } elseif ($balance < 0) {
        $color = '#dc3545'; // Red for negative
    }
    
    ob_start();
    
    if ($atts['style'] === 'simple') {
        // Simple style - just the amount
        ?>
        <div class="osk-wallet-balance-simple">
            <span style="color: <?php echo $color; ?>; font-weight: bold;">
                <?php echo wc_price($balance); ?>
            </span>
        </div>
        <?php
    } elseif ($atts['style'] === 'box') {
        // Box style - fancy display
        ?>
        <div class="osk-wallet-balance-box" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; text-align: center; margin: 10px 0;">
            <?php if ($atts['show_label'] === 'yes') : ?>
                <div style="color: #666; font-size: 14px; margin-bottom: 5px;"><?php _e('Wallet Balance:', 'ordersoftwarekeys-wallet'); ?></div>
            <?php endif; ?>
            
            <div style="font-size: 28px; font-weight: bold; color: <?php echo $color; ?>; margin: 10px 0;">
                <?php echo wc_price($balance); ?>
            </div>
            
            <?php if ($atts['show_actions'] === 'yes') : ?>
                <div style="margin-top: 15px;">
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" class="button" style="margin: 0 5px;">
                        <?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" class="button" style="margin: 0 5px;">
                        <?php _e('History', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    } else {
        // Default style
        ?>
        <div class="osk-wallet-balance-default">
            <?php if ($atts['show_label'] === 'yes') : ?>
                <div style="display: inline-block; margin-right: 10px; font-weight: bold;">
                    <?php _e('Wallet Balance:', 'ordersoftwarekeys-wallet'); ?>
                </div>
            <?php endif; ?>
            
            <div style="display: inline-block; color: <?php echo $color; ?>; font-weight: bold; font-size: 18px;">
                <?php echo wc_price($balance); ?>
            </div>
            
            <?php if ($atts['show_actions'] === 'yes') : ?>
                <div style="margin-top: 10px;">
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" class="button button-small" style="margin-right: 5px;">
                        <?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" class="button button-small">
                        <?php _e('History', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    return ob_get_clean();
}

// [add_to_wallet] - FIXED: Show wallet top-up form (THIS IS THE MAIN FIX)
add_shortcode('add_to_wallet', 'add_to_wallet_shortcode');

function add_to_wallet_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please log in to add funds to your wallet.</p>';
    }
    
    $atts = shortcode_atts(array(
        'min' => '1',
        'max' => '1000',
        'step' => '1'
    ), $atts, 'add_to_wallet');
    
    ob_start();
    ?>
    <div class="wallet-add-funds">
        <h3>Add Funds to Wallet</h3>
        <form id="wallet-topup-form" method="post" action="">
            <?php wp_nonce_field('wallet_topup_action', 'wallet_topup_nonce'); ?>
            <input type="hidden" name="action" value="add_to_wallet">
            <div class="form-group">
                <label for="amount">Amount ($):</label>
                <input type="number" name="amount" id="amount" 
                       min="<?php echo esc_attr($atts['min']); ?>" 
                       max="<?php echo esc_attr($atts['max']); ?>" 
                       step="<?php echo esc_attr($atts['step']); ?>" 
                       value="<?php echo esc_attr($atts['min']); ?>"
                       required>
            </div>
            <button type="submit" name="add_to_wallet_submit" class="button">Add Funds</button>
        </form>
        
        <?php
        if (isset($_POST['add_to_wallet_submit']) && wp_verify_nonce($_POST['wallet_topup_nonce'], 'wallet_topup_action')) {
            $amount = floatval($_POST['amount']);
            
            if ($amount >= floatval($atts['min']) && $amount <= floatval($atts['max'])) {
                $wallet_product_id = get_option('wallet_topup_product_id');
                
                if ($wallet_product_id) {
                    // Clear any existing cart items
                    WC()->cart->empty_cart();
                    
                    // Store amount in session
                    if (isset(WC()->session)) {
                        WC()->session->set('wallet_topup_amount', $amount);
                    }
                    
                    // Add to cart with custom data
                    $cart_item_data = array(
                        'wallet_topup_amount' => $amount,
                        'wallet_topup' => true
                    );
                    
                    // Add product to cart
                    $added = WC()->cart->add_to_cart($wallet_product_id, 1, 0, array(), $cart_item_data);
                    
                    if ($added) {
                        // Redirect to checkout
                        wp_safe_redirect(wc_get_checkout_url());
                        exit;
                    } else {
                        echo '<p class="error">Failed to add to cart. Please try again.</p>';
                    }
                } else {
                    echo '<p class="error">Wallet top-up product not found. Please contact admin.</p>';
                }
            } else {
                echo '<p class="error">Please enter an amount between $' . esc_html($atts['min']) . ' and $' . esc_html($atts['max']) . '.</p>';
            }
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

// [wallet_topup_form] - Alternative top-up form
add_shortcode('wallet_topup_form', 'osk_wallet_topup_form_shortcode');

function osk_wallet_topup_form_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="osk-wallet-notice"><p>' . __('Please log in to add funds to your wallet.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $atts = shortcode_atts(array(
        'style' => 'default', // default, compact, minimal
        'show_balance' => 'yes',
        'title' => __('Add Funds to Wallet', 'ordersoftwarekeys-wallet'),
    ), $atts, 'wallet_topup_form');
    
    $user_id = get_current_user_id();
    $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    
    // Get the wallet top-up product
    $wallet_product_id = get_option('wallet_topup_product_id');
    
    if (empty($wallet_product_id)) {
        return '<div class="osk-wallet-notice"><p>' . __('Wallet top-up is not available at the moment.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $min_amount = 1;
    $max_amount = 1000;
    $default_amount = 10;
    
    ob_start();
    
    if ($atts['style'] === 'compact') {
        ?>
        <div class="osk-wallet-topup-form-compact" style="max-width: 400px; margin: 0 auto;">
            <?php if ($atts['show_balance'] === 'yes') : ?>
            <div style="background: #e7f5ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
                <div style="font-size: 12px; color: #666;"><?php _e('Current Balance', 'ordersoftwarekeys-wallet'); ?></div>
                <div style="font-size: 18px; font-weight: bold; color: #28a745;"><?php echo wc_price($balance); ?></div>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('wallet_topup_action', 'wallet_topup_nonce'); ?>
                <input type="hidden" name="action" value="add_to_wallet">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                        <?php _e('Amount to Add:', 'ordersoftwarekeys-wallet'); ?>
                    </label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-weight: bold; color: #666;">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                        </span>
                        <input type="number" 
                               step="0.01" 
                               min="<?php echo esc_attr($min_amount); ?>" 
                               max="<?php echo esc_attr($max_amount); ?>" 
                               name="amount" 
                               value="<?php echo esc_attr($default_amount); ?>"
                               style="width: 100%; padding: 10px 10px 10px 30px; border: 1px solid #ddd; border-radius: 4px;"
                               required>
                    </div>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        <?php printf(__('Min: %s, Max: %s', 'ordersoftwarekeys-wallet'), wc_price($min_amount), wc_price($max_amount)); ?>
                    </div>
                </div>
                
                <button type="submit" name="add_to_wallet_submit"
                        style="width: 100%; background: #28a745; color: white; border: none; padding: 12px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                    <?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?>
                </button>
            </form>
            
            <?php
            if (isset($_POST['add_to_wallet_submit']) && wp_verify_nonce($_POST['wallet_topup_nonce'], 'wallet_topup_action')) {
                $amount = floatval($_POST['amount']);
                
                if ($amount >= $min_amount && $amount <= $max_amount) {
                    if ($wallet_product_id) {
                        // Clear any existing cart items
                        WC()->cart->empty_cart();
                        
                        // Store amount in session
                        if (isset(WC()->session)) {
                            WC()->session->set('wallet_topup_amount', $amount);
                        }
                        
                        // Add to cart with custom data
                        $cart_item_data = array(
                            'wallet_topup_amount' => $amount,
                            'wallet_topup' => true
                        );
                        
                        // Add product to cart
                        $added = WC()->cart->add_to_cart($wallet_product_id, 1, 0, array(), $cart_item_data);
                        
                        if ($added) {
                            // Redirect to checkout
                            wp_safe_redirect(wc_get_checkout_url());
                            exit;
                        }
                    }
                }
            }
            ?>
        </div>
        <?php
    } elseif ($atts['style'] === 'minimal') {
        ?>
        <div class="osk-wallet-topup-form-minimal">
            <form method="post" action="">
                <?php wp_nonce_field('wallet_topup_action', 'wallet_topup_nonce'); ?>
                <input type="hidden" name="action" value="add_to_wallet">
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1; position: relative;">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-weight: bold; color: #666;">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                        </span>
                        <input type="number" 
                               step="0.01" 
                               min="<?php echo esc_attr($min_amount); ?>" 
                               max="<?php echo esc_attr($max_amount); ?>" 
                               name="amount" 
                               value="<?php echo esc_attr($default_amount); ?>"
                               style="width: 100%; padding: 8px 8px 8px 25px; border: 1px solid #ddd; border-radius: 4px;"
                               required>
                    </div>
                    <button type="submit" name="add_to_wallet_submit"
                            style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                        <?php _e('Add', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    } else {
        // Default style
        ?>
        <div class="osk-wallet-topup-form-default" style="max-width: 500px; margin: 0 auto;">
            <?php if ($atts['title']) : ?>
            <h3 style="color: #333; text-align: center; margin-top: 0; margin-bottom: 20px;">
                <?php echo esc_html($atts['title']); ?>
            </h3>
            <?php endif; ?>
            
            <?php if ($atts['show_balance'] === 'yes') : ?>
            <div style="background: #e7f5ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <div style="font-size: 14px; color: #666; margin-bottom: 5px;">
                    <?php _e('Current Wallet Balance', 'ordersoftwarekeys-wallet'); ?>
                </div>
                <div style="font-size: 28px; font-weight: bold; color: #28a745;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="background: #f8f9fa; border-radius: 8px; padding: 25px; border: 1px solid #dee2e6;">
                <form method="post" action="">
                    <?php wp_nonce_field('wallet_topup_action', 'wallet_topup_nonce'); ?>
                    <input type="hidden" name="action" value="add_to_wallet">
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: bold; color: #333; text-align: center;">
                            <?php _e('Enter Amount to Add', 'ordersoftwarekeys-wallet'); ?>
                        </label>
                        
                        <div style="text-align: center; margin-bottom: 15px;">
                            <div style="display: inline-block; position: relative;">
                                <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 18px; font-weight: bold; color: #666;">
                                    <?php echo get_woocommerce_currency_symbol(); ?>
                                </span>
                                <input type="number" 
                                       step="0.01" 
                                       min="<?php echo esc_attr($min_amount); ?>" 
                                       max="<?php echo esc_attr($max_amount); ?>" 
                                       name="amount" 
                                       id="topup_amount_<?php echo uniqid(); ?>" 
                                       value="<?php echo esc_attr($default_amount); ?>"
                                       style="width: 180px; padding: 12px 12px 12px 35px; font-size: 18px; text-align: center; border: 2px solid #007cba; border-radius: 5px;"
                                       required>
                            </div>
                        </div>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 15px 0;">
                            <?php
                            $quick_amounts = array(5, 10, 25, 50, 100, 250);
                            foreach ($quick_amounts as $amount) {
                                if ($amount >= $min_amount && $amount <= $max_amount) {
                                    ?>
                                    <button type="button" 
                                            class="quick-amount-btn" 
                                            data-amount="<?php echo $amount; ?>"
                                            data-target="topup_amount_<?php echo uniqid(); ?>"
                                            style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 8px 12px; cursor: pointer; transition: all 0.3s;"
                                            onmouseover="this.style.background='#e7f5ff'; this.style.borderColor='#007cba';"
                                            onmouseout="this.style.background='white'; this.style.borderColor='#ddd';">
                                        <?php echo wc_price($amount); ?>
                                    </button>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        
                        <div style="color: #666; text-align: center; font-size: 13px; margin-top: 10px;">
                            <?php 
                            printf(
                                __('Minimum: %s | Maximum: %s', 'ordersoftwarekeys-wallet'),
                                wc_price($min_amount),
                                wc_price($max_amount)
                            );
                            ?>
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" name="add_to_wallet_submit"
                                style="background: #28a745; color: white; border: none; padding: 15px 30px; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s;"
                                onmouseover="this.style.background='#218838';"
                                onmouseout="this.style.background='#28a745';">
                            <?php _e('Add Funds to Wallet', 'ordersoftwarekeys-wallet'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Quick info -->
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 0 5px 5px 0;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <div style="font-size: 20px;">💡</div>
                    <div>
                        <strong style="color: #856404;"><?php _e('How it works:', 'ordersoftwarekeys-wallet'); ?></strong>
                        <p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">
                            <?php _e('1. Enter amount → 2. Go to cart → 3. Checkout with any payment method → 4. Funds added to wallet instantly!', 'ordersoftwarekeys-wallet'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Quick amount buttons
                $('.quick-amount-btn').click(function() {
                    var amount = $(this).data('amount');
                    var targetId = $(this).data('target');
                    $('#' + targetId).val(amount).trigger('change');
                });
                
                // Validate amount on form submission
                $('form').on('submit', function(e) {
                    var amountInput = $(this).find('input[name="amount"]');
                    var amount = parseFloat(amountInput.val());
                    var min = parseFloat(<?php echo $min_amount; ?>);
                    var max = parseFloat(<?php echo $max_amount; ?>);
                    
                    if (isNaN(amount) || amount < min || amount > max) {
                        e.preventDefault();
                        alert('<?php printf(esc_js(__('Please enter an amount between %s and %s.', 'ordersoftwarekeys-wallet')), wc_price($min_amount), wc_price($max_amount)); ?>');
                        amountInput.focus();
                        return false;
                    }
                });
            });
            </script>
        </div>
        
        <?php
        // Handle form submission
        if (isset($_POST['add_to_wallet_submit']) && wp_verify_nonce($_POST['wallet_topup_nonce'], 'wallet_topup_action')) {
            $amount = floatval($_POST['amount']);
            
            if ($amount >= $min_amount && $amount <= $max_amount) {
                if ($wallet_product_id) {
                    // Clear any existing cart items
                    WC()->cart->empty_cart();
                    
                    // Store amount in session
                    if (isset(WC()->session)) {
                        WC()->session->set('wallet_topup_amount', $amount);
                    }
                    
                    // Add to cart with custom data
                    $cart_item_data = array(
                        'wallet_topup_amount' => $amount,
                        'wallet_topup' => true
                    );
                    
                    // Add product to cart
                    $added = WC()->cart->add_to_cart($wallet_product_id, 1, 0, array(), $cart_item_data);
                    
                    if ($added) {
                        // Redirect to checkout
                        wp_safe_redirect(wc_get_checkout_url());
                        exit;
                    }
                }
            }
        }
        ?>
        
        <?php
    }
    
    return ob_get_clean();
}

// [wallet_transactions] - Show transaction history
add_shortcode('wallet_transactions', 'osk_wallet_transactions_shortcode');

function osk_wallet_transactions_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="osk-wallet-notice"><p>' . __('Please log in to view your transaction history.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 10,
        'show_balance' => 'yes',
        'show_filters' => 'yes'
    ), $atts, 'wallet_transactions');
    
    $user_id = get_current_user_id();
    $transactions = get_user_meta($user_id, 'wallet_transactions', true);
    $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    
    ob_start();
    ?>
    <div class="osk-wallet-transactions-shortcode">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <h3 style="color: #333; margin: 0;">
                <?php _e('Wallet Transactions', 'ordersoftwarekeys-wallet'); ?>
            </h3>
            
            <?php if ($atts['show_balance'] === 'yes') : ?>
            <div style="background: #f8f9fa; border-radius: 5px; padding: 10px 20px; text-align: center; border: 1px solid #dee2e6;">
                <div style="font-size: 12px; color: #666; margin-bottom: 3px;">
                    <?php _e('Current Balance', 'ordersoftwarekeys-wallet'); ?>
                </div>
                <div style="font-size: 20px; font-weight: bold; color: #28a745;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($transactions) || !is_array($transactions)) : ?>
            <div style="background: #f8f9fa; padding: 30px; border-radius: 5px; text-align: center; margin: 20px 0;">
                <div style="font-size: 48px; color: #ddd; margin-bottom: 15px;">📊</div>
                <p style="margin: 0; color: #666; font-size: 16px;"><?php _e('No transactions yet.', 'ordersoftwarekeys-wallet'); ?></p>
                <p style="margin: 10px 0 0 0; color: #999;"><?php _e('Your wallet transaction history will appear here.', 'ordersoftwarekeys-wallet'); ?></p>
            </div>
        <?php else : 
            $recent_transactions = array_reverse($transactions);
            $limit = intval($atts['limit']);
            if ($limit > 0) {
                $recent_transactions = array_slice($recent_transactions, 0, $limit);
            }
            
            if ($atts['show_filters'] === 'yes') : ?>
            <div style="background: #f8f9fa; border-radius: 5px; padding: 10px; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <span style="font-weight: bold; color: #555; font-size: 13px;"><?php _e('Filter:', 'ordersoftwarekeys-wallet'); ?></span>
                    <button type="button" onclick="filterShortcodeTransactions('all')" class="button button-small" style="margin: 2px; padding: 5px 10px; font-size: 12px;">
                        <?php _e('All', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                    <button type="button" onclick="filterShortcodeTransactions('add')" class="button button-small" style="margin: 2px; padding: 5px 10px; font-size: 12px;">
                        <?php _e('Added', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                    <button type="button" onclick="filterShortcodeTransactions('deduct')" class="button button-small" style="margin: 2px; padding: 5px 10px; font-size: 12px;">
                        <?php _e('Used', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <thead>
                        <tr style="background: #007cba;">
                            <th style="padding: 12px 15px; text-align: left; color: white; font-weight: bold; border: none; font-size: 14px;">
                                <?php _e('Date', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 12px 15px; text-align: left; color: white; font-weight: bold; border: none; font-size: 14px;">
                                <?php _e('Type', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 12px 15px; text-align: left; color: white; font-weight: bold; border: none; font-size: 14px;">
                                <?php _e('Amount', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 12px 15px; text-align: left; color: white; font-weight: bold; border: none; font-size: 14px;">
                                <?php _e('Balance', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 12px 15px; text-align: left; color: white; font-weight: bold; border: none; font-size: 14px;">
                                <?php _e('Details', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $index => $transaction) : 
                            $is_negative = ($transaction['type'] === 'purchase' || $transaction['type'] === 'subtract' || $transaction['type'] === 'admin_deduct');
                            $amount_color = $is_negative ? '#dc3545' : '#28a745';
                            $amount_sign = $is_negative ? '−' : '+';
                            $row_bg = $index % 2 === 0 ? '#fff' : '#f8f9fa';
                            
                            // Determine transaction class for filtering
                            $transaction_class = '';
                            if (in_array($transaction['type'], array('add', 'refund', 'topup', 'admin_add'))) {
                                $transaction_class = 'shortcode-type-add';
                            } elseif (in_array($transaction['type'], array('subtract', 'admin_deduct'))) {
                                $transaction_class = 'shortcode-type-deduct';
                            } elseif ($transaction['type'] === 'purchase') {
                                $transaction_class = 'shortcode-type-deduct';
                            }
                        ?>
                            <tr class="shortcode-transaction-row <?php echo $transaction_class; ?>" style="background: <?php echo $row_bg; ?>; border-bottom: 1px solid #eee;">
                                <td style="padding: 10px 15px; border: none;">
                                    <div style="font-weight: bold; color: #333; font-size: 14px;">
                                        <?php echo date_i18n('M j, Y', strtotime($transaction['time'])); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #999;">
                                        <?php echo date_i18n('g:i A', strtotime($transaction['time'])); ?>
                                    </div>
                                </td>
                                <td style="padding: 10px 15px; border: none;">
                                    <?php 
                                    $type_labels = array(
                                        'purchase' => __('Purchase', 'ordersoftwarekeys-wallet'),
                                        'refund' => __('Refund', 'ordersoftwarekeys-wallet'),
                                        'topup' => __('Top-up', 'ordersoftwarekeys-wallet'),
                                        'add' => __('Added', 'ordersoftwarekeys-wallet'),
                                        'subtract' => __('Deducted', 'ordersoftwarekeys-wallet'),
                                        'set' => __('Balance Set', 'ordersoftwarekeys-wallet'),
                                        'admin_add' => __('Admin Added', 'ordersoftwarekeys-wallet'),
                                        'admin_deduct' => __('Admin Deducted', 'ordersoftwarekeys-wallet')
                                    );
                                    $type_label = isset($type_labels[$transaction['type']]) ? $type_labels[$transaction['type']] : $transaction['type'];
                                    $type_icon = $is_negative ? '📉' : '📈';
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 16px;"><?php echo $type_icon; ?></span>
                                        <span style="font-weight: bold; color: #333; font-size: 14px;"><?php echo $type_label; ?></span>
                                    </div>
                                </td>
                                <td style="padding: 10px 15px; border: none;">
                                    <div style="font-weight: bold; font-size: 15px; color: <?php echo $amount_color; ?>;">
                                        <?php echo $amount_sign . wc_price($transaction['amount']); ?>
                                    </div>
                                </td>
                                <td style="padding: 10px 15px; border: none;">
                                    <div style="font-weight: bold; font-size: 15px; color: #333;">
                                        <?php echo wc_price($transaction['new_balance']); ?>
                                    </div>
                                </td>
                                <td style="padding: 10px 15px; border: none;">
                                    <?php if ($transaction['order_id']) : ?>
                                        <div style="margin-bottom: 5px;">
                                            <a href="<?php echo wc_get_account_endpoint_url('view-order') . $transaction['order_id']; ?>" 
                                               style="color: #007cba; text-decoration: none; font-weight: bold; font-size: 13px;">
                                                <?php printf(__('Order #%s', 'ordersoftwarekeys-wallet'), $transaction['order_id']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($transaction['note']) : ?>
                                        <div style="background: #f8f9fa; padding: 6px 10px; border-radius: 4px; margin-top: 5px;">
                                            <div style="font-size: 11px; color: #666; margin-bottom: 2px;">
                                                <?php _e('Note:', 'ordersoftwarekeys-wallet'); ?>
                                            </div>
                                            <div style="color: #333; font-size: 13px; line-height: 1.3;">
                                                <?php echo esc_html($transaction['note']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($transactions) > $limit && $limit > 0) : ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
                   style="color: #007cba; text-decoration: none; font-weight: bold; font-size: 14px;">
                    <?php _e('View All Transactions →', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_filters'] === 'yes') : ?>
            <script>
            function filterShortcodeTransactions(type) {
                const rows = document.querySelectorAll('.shortcode-transaction-row');
                
                rows.forEach(row => {
                    if (type === 'all') {
                        row.style.display = '';
                    } else if (row.classList.contains('shortcode-type-' + type)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Update active button
                const buttons = document.querySelectorAll('.shortcode-transaction-row').closest('.osk-wallet-transactions-shortcode').querySelectorAll('.button-small');
                buttons.forEach(btn => {
                    btn.style.background = '';
                    btn.style.color = '';
                });
                
                if (event && event.target) {
                    event.target.style.background = '#007cba';
                    event.target.style.color = 'white';
                }
            }
            </script>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

// [wallet_mini_dashboard] - Show mini dashboard
add_shortcode('wallet_mini_dashboard', 'osk_wallet_mini_dashboard_shortcode');

function osk_wallet_mini_dashboard_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="osk-wallet-notice"><p>' . __('Please log in to view your wallet.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $atts = shortcode_atts(array(
        'show_topup_button' => 'yes',
        'show_history_button' => 'yes',
        'size' => 'normal' // normal, small, large
    ), $atts, 'wallet_mini_dashboard');
    
    $user_id = get_current_user_id();
    $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    
    // Set color based on balance
    $color = '#555'; // Default
    if ($balance > 100) {
        $color = '#28a745'; // Green for > 100
    } elseif ($balance > 0 && $balance <= 100) {
        $color = '#ffc107'; // Yellow for 1-100
    } elseif ($balance < 0) {
        $color = '#dc3545'; // Red for negative
    }
    
    ob_start();
    
    if ($atts['size'] === 'small') {
        ?>
        <div class="osk-wallet-mini-dashboard-small" style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="wallet-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="margin: 0; color: #333; font-size: 14px;"><?php _e('Wallet', 'ordersoftwarekeys-wallet'); ?></h4>
                <div style="font-weight: bold; color: <?php echo $color; ?>; font-size: 16px;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
            
            <div class="wallet-links" style="display: flex; gap: 10px;">
                <?php if ($atts['show_topup_button'] === 'yes') : ?>
                <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
                   style="flex: 1; background: #28a745; color: white; text-align: center; padding: 6px; border-radius: 3px; text-decoration: none; font-size: 12px;">
                    <?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?>
                </a>
                <?php endif; ?>
                
                <?php if ($atts['show_history_button'] === 'yes') : ?>
                <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
                   style="flex: 1; background: #007cba; color: white; text-align: center; padding: 6px; border-radius: 3px; text-decoration: none; font-size: 12px;">
                    <?php _e('History', 'ordersoftwarekeys-wallet'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    } elseif ($atts['size'] === 'large') {
        ?>
        <div class="osk-wallet-mini-dashboard-large" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #ddd; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div class="wallet-header" style="margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333; font-size: 20px; text-align: center;"><?php _e('My Wallet', 'ordersoftwarekeys-wallet'); ?></h3>
            </div>
            
            <div class="wallet-balance" style="text-align: center; margin-bottom: 25px;">
                <div style="color: #666; font-size: 16px; margin-bottom: 8px;"><?php _e('Available Balance', 'ordersoftwarekeys-wallet'); ?></div>
                <div style="font-size: 36px; font-weight: bold; color: <?php echo $color; ?>;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
            
            <div class="wallet-links" style="display: flex; gap: 15px; justify-content: center;">
                <?php if ($atts['show_topup_button'] === 'yes') : ?>
                <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
                   style="background: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; transition: all 0.3s;"
                   onmouseover="this.style.background='#218838'; this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.background='#28a745'; this.style.transform='translateY(0)';">
                    <?php _e('➕ Add Funds', 'ordersoftwarekeys-wallet'); ?>
                </a>
                <?php endif; ?>
                
                <?php if ($atts['show_history_button'] === 'yes') : ?>
                <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
                   style="background: #007cba; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; transition: all 0.3s;"
                   onmouseover="this.style.background='#0056b3'; this.style.transform='translateY(-2px)';"
                   onmouseout="this.style.background='#007cba'; this.style.transform='translateY(0)';">
                    <?php _e('📊 History', 'ordersoftwarekeys-wallet'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    } else {
        // Normal size (default)
        ?>
        <div class="osk-wallet-mini-dashboard-normal" style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div class="wallet-header" style="margin-bottom: 15px;">
                <h3 style="margin: 0; color: #333; font-size: 18px;"><?php _e('Your Wallet', 'ordersoftwarekeys-wallet'); ?></h3>
            </div>
            
            <div class="wallet-balance" style="margin-bottom: 20px;">
                <div style="color: #666; font-size: 14px; margin-bottom: 5px;"><?php _e('Current Balance', 'ordersoftwarekeys-wallet'); ?></div>
                <div style="font-size: 28px; font-weight: bold; color: <?php echo $color; ?>;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
            
            <div class="wallet-links" style="border-top: 1px solid #eee; padding-top: 15px;">
                <div style="display: flex; gap: 10px;">
                    <?php if ($atts['show_topup_button'] === 'yes') : ?>
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
                       style="flex: 1; background: #28a745; color: white; text-align: center; padding: 10px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 14px;">
                        <?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_history_button'] === 'yes') : ?>
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
                       style="flex: 1; background: #007cba; color: white; text-align: center; padding: 10px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 14px;">
                        <?php _e('View History', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    return ob_get_clean();
}

// [wallet_info] - Show all wallet information
add_shortcode('wallet_info', 'osk_wallet_info_shortcode');

function osk_wallet_info_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="osk-wallet-notice"><p>' . __('Please log in to view wallet information.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $atts = shortcode_atts(array(
        'show_balance' => 'yes',
        'show_actions' => 'yes',
        'show_history' => 'no',
        'history_limit' => 5,
        'layout' => 'vertical' // vertical, horizontal
    ), $atts, 'wallet_info');
    
    $user_id = get_current_user_id();
    $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    $transactions = get_user_meta($user_id, 'wallet_transactions', true);
    
    // Set color based on balance
    $color = '#555';
    if ($balance > 100) {
        $color = '#28a745';
    } elseif ($balance > 0 && $balance <= 100) {
        $color = '#ffc107';
    } elseif ($balance < 0) {
        $color = '#dc3545';
    }
    
    ob_start();
    
    if ($atts['layout'] === 'horizontal') {
        ?>
        <div class="osk-wallet-info-horizontal" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center; background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0;">
            
            <?php if ($atts['show_balance'] === 'yes') : ?>
            <div style="flex: 1; min-width: 200px;">
                <div style="color: #666; font-size: 14px; margin-bottom: 5px;"><?php _e('Wallet Balance', 'ordersoftwarekeys-wallet'); ?></div>
                <div style="font-size: 28px; font-weight: bold; color: <?php echo $color; ?>;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_actions'] === 'yes') : ?>
            <div style="flex: 2; min-width: 300px;">
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
                       style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                        <?php _e('➕ Add Funds', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
                       style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                        <?php _e('📊 View History', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
    } else {
        // Vertical layout (default)
        ?>
        <div class="osk-wallet-info-vertical" style="background: #f8f9fa; border-radius: 10px; padding: 25px; margin: 20px 0;">
            
            <?php if ($atts['show_balance'] === 'yes') : ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="color: #666; font-size: 16px; margin-bottom: 10px;"><?php _e('Your Wallet Balance', 'ordersoftwarekeys-wallet'); ?></div>
                <div style="font-size: 36px; font-weight: bold; color: <?php echo $color; ?>; margin: 10px 0;">
                    <?php echo wc_price($balance); ?>
                </div>
                <div style="color: #999; font-size: 14px;">
                    <?php 
                    if ($balance > 100) {
                        _e('Excellent balance!', 'ordersoftwarekeys-wallet');
                    } elseif ($balance > 0) {
                        _e('Good balance', 'ordersoftwarekeys-wallet');
                    } else {
                        _e('No balance', 'ordersoftwarekeys-wallet');
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_actions'] === 'yes') : ?>
            <div style="text-align: center; margin: 25px 0;">
                <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
                   class="button button-primary" 
                   style="margin: 0 10px; padding: 12px 25px; background: #28a745; border-color: #28a745;">
                    <?php _e('➕ Add Funds', 'ordersoftwarekeys-wallet'); ?>
                </a>
                <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
                   class="button" 
                   style="margin: 0 10px; padding: 12px 25px;">
                    <?php _e('📊 View History', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_history'] === 'yes' && !empty($transactions) && is_array($transactions)) : 
                $recent_transactions = array_slice(array_reverse($transactions), 0, intval($atts['history_limit']));
            ?>
            <div style="background: white; border-radius: 8px; padding: 20px; margin-top: 20px;">
                <h4 style="margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    <?php _e('Recent Transactions', 'ordersoftwarekeys-wallet'); ?>
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($recent_transactions as $transaction) : 
                        $is_negative = ($transaction['type'] === 'purchase' || $transaction['type'] === 'subtract');
                        $amount_color = $is_negative ? '#dc3545' : '#28a745';
                        $amount_sign = $is_negative ? '-' : '+';
                    ?>
                    <li style="padding: 10px 0; border-bottom: 1px solid #f8f9fa;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: bold; color: #333;">
                                    <?php 
                                    $type_labels = array(
                                        'purchase' => __('Purchase', 'ordersoftwarekeys-wallet'),
                                        'refund' => __('Refund', 'ordersoftwarekeys-wallet'),
                                        'topup' => __('Top-up', 'ordersoftwarekeys-wallet'),
                                        'add' => __('Added', 'ordersoftwarekeys-wallet'),
                                        'subtract' => __('Deducted', 'ordersoftwarekeys-wallet'),
                                        'set' => __('Set', 'ordersoftwarekeys-wallet')
                                    );
                                    echo isset($type_labels[$transaction['type']]) ? $type_labels[$transaction['type']] : $transaction['type'];
                                    ?>
                                </div>
                                <div style="font-size: 12px; color: #999;">
                                    <?php echo date_i18n(get_option('date_format'), strtotime($transaction['time'])); ?>
                                    <?php if (!empty($transaction['note'])) : ?>
                                        <br><?php echo esc_html($transaction['note']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-weight: bold; color: <?php echo $amount_color; ?>;">
                                <?php echo $amount_sign . wc_price($transaction['amount']); ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($transactions) > intval($atts['history_limit'])) : ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" style="color: #007cba; text-decoration: none;">
                        <?php _e('View all transactions →', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    return ob_get_clean();
}

// [wallet_quick_topup] - Simple quick top-up form
add_shortcode('wallet_quick_topup', 'osk_wallet_quick_topup_shortcode');

function osk_wallet_quick_topup_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="osk-wallet-notice"><p>' . __('Please log in to add funds.', 'ordersoftwarekeys-wallet') . '</p></div>';
    }
    
    $atts = shortcode_atts(array(
        'button_text' => __('Quick Add Funds', 'ordersoftwarekeys-wallet'),
        'button_color' => '#28a745',
        'size' => 'medium' // small, medium, large
    ), $atts, 'wallet_quick_topup');
    
    // Get the wallet top-up product
    $wallet_product_id = get_option('wallet_topup_product_id');
    
    if (empty($wallet_product_id)) {
        return '';
    }
    
    $default_amount = 10;
    
    // Set button size
    $padding = '10px 20px';
    $font_size = '14px';
    
    if ($atts['size'] === 'small') {
        $padding = '6px 12px';
        $font_size = '12px';
    } elseif ($atts['size'] === 'large') {
        $padding = '15px 30px';
        $font_size = '16px';
    }
    
    ob_start();
    ?>
    <div class="osk-wallet-quick-topup">
        <form method="post" action="">
            <?php wp_nonce_field('wallet_topup_action', 'wallet_topup_nonce'); ?>
            <input type="hidden" name="action" value="add_to_wallet">
            <input type="hidden" name="amount" value="<?php echo esc_attr($default_amount); ?>">
            
            <button type="submit" name="add_to_wallet_submit"
                    style="background: <?php echo esc_attr($atts['button_color']); ?>; color: white; border: none; padding: <?php echo $padding; ?>; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: <?php echo $font_size; ?>; transition: all 0.3s;"
                    onmouseover="this.style.opacity='0.9'; this.style.transform='translateY(-1px)';"
                    onmouseout="this.style.opacity='1'; this.style.transform='translateY(0)';">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </form>
        
        <?php
        if (isset($_POST['add_to_wallet_submit']) && wp_verify_nonce($_POST['wallet_topup_nonce'], 'wallet_topup_action')) {
            $amount = floatval($_POST['amount']);
            
            if ($wallet_product_id) {
                // Clear any existing cart items
                WC()->cart->empty_cart();
                
                // Store amount in session
                if (isset(WC()->session)) {
                    WC()->session->set('wallet_topup_amount', $amount);
                }
                
                // Add to cart with custom data
                $cart_item_data = array(
                    'wallet_topup_amount' => $amount,
                    'wallet_topup' => true
                );
                
                // Add product to cart
                $added = WC()->cart->add_to_cart($wallet_product_id, 1, 0, array(), $cart_item_data);
                
                if ($added) {
                    // Redirect to checkout
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }
            }
        }
        ?>
    </div>
    <?php
    
    return ob_get_clean();
}

// [wallet_status] - Show wallet status with icon
add_shortcode('wallet_status', 'osk_wallet_status_shortcode');

function osk_wallet_status_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<span class="wallet-status" style="color: #999;">' . __('Login to view', 'ordersoftwarekeys-wallet') . '</span>';
    }
    
    $atts = shortcode_atts(array(
        'show_icon' => 'yes',
        'icon_size' => '16px',
        'text_size' => '14px'
    ), $atts, 'wallet_status');
    
    $user_id = get_current_user_id();
    $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    
    // Set color and icon based on balance
    if ($balance > 100) {
        $color = '#28a745';
        $icon = '💰'; // Money bag
    } elseif ($balance > 0) {
        $color = '#ffc107';
        $icon = '💵'; // Money
    } else {
        $color = '#dc3545';
        $icon = '💳'; // Credit card
    }
    
    ob_start();
    ?>
    <span class="wallet-status" style="display: inline-flex; align-items: center; gap: 5px;">
        <?php if ($atts['show_icon'] === 'yes') : ?>
            <span style="font-size: <?php echo esc_attr($atts['icon_size']); ?>;"><?php echo $icon; ?></span>
        <?php endif; ?>
        <span style="color: <?php echo $color; ?>; font-size: <?php echo esc_attr($atts['text_size']); ?>; font-weight: bold;">
            <?php echo wc_price($balance); ?>
        </span>
    </span>
    <?php
    
    return ob_get_clean();
}
