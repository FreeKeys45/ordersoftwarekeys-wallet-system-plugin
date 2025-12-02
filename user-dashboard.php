<?php
/**
 * User Dashboard Functions
 * File: user-dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add wallet endpoints to My Account
add_action('init', 'osk_wallet_add_myaccount_endpoints');

function osk_wallet_add_myaccount_endpoints() {
    add_rewrite_endpoint('wallet-topup', EP_PAGES);
    add_rewrite_endpoint('wallet-transactions', EP_PAGES);
}

// Add menu items to My Account
add_filter('woocommerce_account_menu_items', 'osk_wallet_myaccount_menu_items');

function osk_wallet_myaccount_menu_items($items) {
    $wallet_items = array(
        'wallet-topup' => __('Add Funds', 'ordersoftwarekeys-wallet'),
        'wallet-transactions' => __('Wallet History', 'ordersoftwarekeys-wallet')
    );
    
    // Insert after orders
    $position = array_search('orders', array_keys($items));
    $items = array_slice($items, 0, $position + 1, true) +
             $wallet_items +
             array_slice($items, $position + 1, count($items) - 1, true);
    
    return $items;
}

// Wallet top-up page
add_action('woocommerce_account_wallet-topup_endpoint', 'osk_wallet_topup_page');

function osk_wallet_topup_page() {
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
    
    // Get the default top-up product
    $topup_product = get_posts(array(
        'post_type' => 'product',
        'meta_key' => '_is_default_wallet_topup',
        'meta_value' => 'yes',
        'posts_per_page' => 1
    ));
    
    ?>
    <div class="osk-wallet-topup" style="max-width: 800px; margin: 0 auto;">
        <h2 style="color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px;">
            <?php _e('Add Funds to Your Wallet', 'ordersoftwarekeys-wallet'); ?>
        </h2>
        
        <div style="background: #e7f5ff; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <p style="margin: 0; font-size: 16px;">
                <?php _e('Current Balance:', 'ordersoftwarekeys-wallet'); ?> 
                <strong style="color: <?php echo $color; ?>; font-size: 24px;"><?php echo wc_price($balance); ?></strong>
            </p>
        </div>
        
        <?php if (!empty($topup_product)) : 
            $product_id = $topup_product[0]->ID;
            $product = wc_get_product($product_id);
            $min_amount = get_post_meta($product_id, '_simple_topup_min_amount', true) ?: 1;
            $max_amount = get_post_meta($product_id, '_simple_topup_max_amount', true) ?: 1000;
            $default_amount = get_post_meta($product_id, '_simple_topup_default_amount', true) ?: 10;
        ?>
        
        <div style="background: #f8f9fa; border-radius: 10px; padding: 30px; margin: 30px 0; border: 1px solid #dee2e6;">
            <h3 style="color: #333; text-align: center; margin-top: 0;">
                <?php _e('Add Funds to Wallet', 'ordersoftwarekeys-wallet'); ?>
            </h3>
            
            <div class="osk-wallet-simple-topup-form" style="margin-top: 20px;">
                <form class="cart" method="post" enctype="multipart/form-data" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>">
                    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 25px; margin: 20px 0;">
                        <h4 style="color: #333; margin-top: 0; text-align: center;">
                            <?php _e('Enter Amount to Add', 'ordersoftwarekeys-wallet'); ?>
                        </h4>
                        
                        <div style="text-align: center; margin: 20px 0;">
                            <div style="display: inline-block; position: relative;">
                                <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 20px; font-weight: bold; color: #666;">
                                    <?php echo get_woocommerce_currency_symbol(); ?>
                                </span>
                                <input type="number" 
                                       step="0.01" 
                                       min="<?php echo esc_attr($min_amount); ?>" 
                                       max="<?php echo esc_attr($max_amount); ?>" 
                                       name="topup_amount" 
                                       id="topup_amount" 
                                       value="<?php echo esc_attr($default_amount); ?>"
                                       style="width: 200px; padding: 15px 15px 15px 40px; font-size: 24px; text-align: center; border: 2px solid #007cba; border-radius: 5px;"
                                       required>
                            </div>
                        </div>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin: 20px 0;">
                            <?php
                            $quick_amounts = array(5, 10, 25, 50, 100, 250);
                            foreach ($quick_amounts as $amount) {
                                if ($amount >= $min_amount && $amount <= $max_amount) {
                                    ?>
                                    <button type="button" 
                                            class="quick-amount-btn" 
                                            data-amount="<?php echo $amount; ?>"
                                            style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; padding: 10px 15px; cursor: pointer; transition: all 0.3s;"
                                            onmouseover="this.style.background='#e7f5ff'; this.style.borderColor='#007cba'; this.style.transform='translateY(-2px)';"
                                            onmouseout="this.style.background='#f8f9fa'; this.style.borderColor='#ddd'; this.style.transform='translateY(0)';">
                                        <?php echo wc_price($amount); ?>
                                    </button>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        
                        <div style="color: #666; text-align: center; margin-top: 10px; font-size: 14px;">
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
                        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>">
                        <input type="hidden" name="topup_custom_amount" id="topup_custom_amount" value="<?php echo esc_attr($default_amount); ?>">
                        
                        <button type="submit" 
                                class="single_add_to_cart_button button alt"
                                style="background: #28a745; border-color: #28a745; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; border-radius: 5px; transition: all 0.3s;"
                                onmouseover="this.style.background='#218838'; this.style.transform='translateY(-2px)';"
                                onmouseout="this.style.background='#28a745'; this.style.transform='translateY(0)';">
                            <?php 
                            echo sprintf(
                                __('Add %s to Wallet', 'ordersoftwarekeys-wallet'),
                                '<span id="amount-display">' . wc_price($default_amount) . '</span>'
                            ); 
                            ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Update amount display
                function updateAmountDisplay() {
                    var amount = $('#topup_amount').val();
                    var formatted = new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: '<?php echo get_woocommerce_currency(); ?>'
                    }).format(amount);
                    
                    $('#amount-display').text(formatted);
                    $('#topup_custom_amount').val(amount);
                }
                
                // Quick amount buttons
                $('.quick-amount-btn').click(function() {
                    var amount = $(this).data('amount');
                    $('#topup_amount').val(amount).trigger('change');
                });
                
                // Update on input change
                $('#topup_amount').on('input change', updateAmountDisplay);
                
                // Initial update
                updateAmountDisplay();
                
                // Validate amount on form submission
                $('form.cart').on('submit', function(e) {
                    var amount = parseFloat($('#topup_amount').val());
                    var min = parseFloat(<?php echo $min_amount; ?>);
                    var max = parseFloat(<?php echo $max_amount; ?>);
                    
                    if (isNaN(amount) || amount < min || amount > max) {
                        e.preventDefault();
                        alert('<?php printf(esc_js(__('Please enter an amount between %s and %s.', 'ordersoftwarekeys-wallet')), wc_price($min_amount), wc_price($max_amount)); ?>');
                        return false;
                    }
                    
                    // Add the custom amount to the form data
                    $('#topup_custom_amount').val(amount);
                });
            });
            </script>
        </div>
        
        <?php else : ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 0 5px 5px 0; margin: 30px 0;">
                <h3 style="color: #856404; margin-top: 0;">
                    <?php _e('Top-up Product Not Found', 'ordersoftwarekeys-wallet'); ?>
                </h3>
                <p style="color: #856404; margin: 10px 0;">
                    <?php _e('The wallet top-up product has not been set up yet. Please contact the administrator.', 'ordersoftwarekeys-wallet'); ?>
                </p>
                <a href="mailto:<?php echo get_option('admin_email'); ?>" class="button" style="background: #ffc107; border-color: #ffc107;">
                    <?php _e('Contact Admin', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <!-- How It Works Section -->
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 25px; border-radius: 0 8px 8px 0; margin: 30px 0;">
            <h3 style="color: #856404; margin-top: 0; text-align: center;">
                <?php _e('How It Works', 'ordersoftwarekeys-wallet'); ?>
            </h3>
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
                <div style="flex: 1; min-width: 170px; text-align: center;">
                    <div style="background: #28a745; color: white; width: 50px; height: 50px; line-height: 50px; border-radius: 50%; text-align: center; font-weight: bold; margin: 0 auto 15px; font-size: 20px;">1</div>
                    <h4 style="color: #28a745; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Enter Amount', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.5;">
                        <?php _e('Enter the exact amount you want to add to your wallet.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                
                <div style="flex: 1; min-width: 170px; text-align: center;">
                    <div style="background: #007cba; color: white; width: 50px; height: 50px; line-height: 50px; border-radius: 50%; text-align: center; font-weight: bold; margin: 0 auto 15px; font-size: 20px;">2</div>
                    <h4 style="color: #007cba; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Proceed to Checkout', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.5;">
                        <?php _e('Go through the normal checkout process.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                
                <div style="flex: 1; min-width: 170px; text-align: center;">
                    <div style="background: #6f42c1; color: white; width: 50px; height: 50px; line-height: 50px; border-radius: 50%; text-align: center; font-weight: bold; margin: 0 auto 15px; font-size: 20px;">3</div>
                    <h4 style="color: #6f42c1; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Choose Payment Method', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.5;">
                        <?php _e('Pay with any available payment method (Credit Card, PayPal, etc.).', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                
                <div style="flex: 1; min-width: 170px; text-align: center;">
                    <div style="background: #28a745; color: white; width: 50px; height: 50px; line-height: 50px; border-radius: 50%; text-align: center; font-weight: bold; margin: 0 auto 15px; font-size: 20px;">4</div>
                    <h4 style="color: #28a745; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Funds Added Instantly', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.5;">
                        <?php _e('Once payment is confirmed, funds are added to your wallet.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Benefits Section -->
        <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 25px; margin: 30px 0;">
            <h3 style="color: #155724; margin-top: 0; text-align: center;">
                <?php _e('Benefits of Using Wallet', 'ordersoftwarekeys-wallet'); ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 10px;">⚡</div>
                    <h4 style="color: #155724; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Fast Checkout', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #155724; margin: 0; font-size: 14px;">
                        <?php _e('Complete purchases in seconds with one click.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 10px;">🛡️</div>
                    <h4 style="color: #155724; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Secure', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #155724; margin: 0; font-size: 14px;">
                        <?php _e('Your funds are safe and protected.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 10px;">💳</div>
                    <h4 style="color: #155724; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Flexible', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #155724; margin: 0; font-size: 14px;">
                        <?php _e('Use for partial or full payments.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 10px;">📊</div>
                    <h4 style="color: #155724; margin: 0 0 10px 0; font-size: 16px;"><?php _e('Track Spending', 'ordersoftwarekeys-wallet'); ?></h4>
                    <p style="color: #155724; margin: 0; font-size: 14px;">
                        <?php _e('Monitor all your transactions easily.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Contact Info -->
        <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 25px; margin-top: 30px; text-align: center;">
            <h4 style="color: #0c5460; margin-top: 0;">
                <?php _e('Need Help?', 'ordersoftwarekeys-wallet'); ?>
            </h4>
            <p style="color: #0c5460; margin: 15px 0;">
                <?php _e('Contact us for any wallet-related questions:', 'ordersoftwarekeys-wallet'); ?>
            </p>
            
            <div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; margin-top: 20px;">
                <a href="mailto:support@ordersoftwarekeys.com" 
                   class="button" 
                   style="background: #0c5460; border-color: #0c5460; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                    <?php _e('Email Support', 'ordersoftwarekeys-wallet'); ?>
                </a>
                
                <a href="<?php echo wc_get_page_permalink('shop'); ?>" 
                   class="button" 
                   style="background: #17a2b8; border-color: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                    <?php _e('Continue Shopping', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}

// Wallet transactions page
add_action('woocommerce_account_wallet-transactions_endpoint', 'osk_wallet_transactions_page_user');

function osk_wallet_transactions_page_user() {
    $user_id = get_current_user_id();
    $transactions = get_user_meta($user_id, 'wallet_transactions', true);
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
    
    ?>
    <div class="osk-wallet-transactions" style="max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <div>
                <h2 style="color: #333; margin: 0;">
                    <?php _e('Wallet Transactions', 'ordersoftwarekeys-wallet'); ?>
                </h2>
                <p style="color: #666; margin: 5px 0 0 0;">
                    <?php _e('View all your wallet transactions and balance history.', 'ordersoftwarekeys-wallet'); ?>
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px 25px; text-align: center; border: 1px solid #dee2e6; min-width: 150px;">
                    <div style="color: #666; font-size: 14px; margin-bottom: 5px;">
                        <?php _e('Current Balance', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                    <div style="font-size: 24px; font-weight: bold; color: <?php echo $color; ?>;">
                        <?php echo wc_price($balance); ?>
                    </div>
                </div>
                
                <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
                   class="button button-primary" 
                   style="background: #28a745; border-color: #28a745; padding: 10px 20px; height: fit-content;">
                    <?php _e('➕ Add Funds', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
        </div>
        
        <?php if (empty($transactions) || !is_array($transactions)) : ?>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 60px 40px; text-align: center; margin: 40px 0; border: 1px solid #dee2e6;">
                <div style="font-size: 64px; color: #ddd; margin-bottom: 20px;">📊</div>
                <h3 style="color: #666; margin: 0 0 15px 0;">
                    <?php _e('No Transactions Yet', 'ordersoftwarekeys-wallet'); ?>
                </h3>
                <p style="color: #999; margin: 0 0 30px 0; font-size: 16px;">
                    <?php _e('Your wallet transaction history will appear here once you make your first transaction.', 'ordersoftwarekeys-wallet'); ?>
                </p>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" class="button button-primary" style="padding: 12px 30px;">
                        <?php _e('Add Funds to Wallet', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button" style="padding: 12px 30px;">
                        <?php _e('Browse Products', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                </div>
            </div>
        <?php else : ?>
            <!-- Transaction Filters -->
            <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px; border: 1px solid #dee2e6;">
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <span style="font-weight: bold; color: #555; font-size: 15px;"><?php _e('Filter by:', 'ordersoftwarekeys-wallet'); ?></span>
                    <button type="button" onclick="filterTransactions('all')" class="button button-small filter-btn active" 
                            style="margin: 2px; padding: 8px 15px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <?php _e('All', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                    <button type="button" onclick="filterTransactions('add')" class="button button-small filter-btn" 
                            style="margin: 2px; padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <?php _e('Additions', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                    <button type="button" onclick="filterTransactions('deduct')" class="button button-small filter-btn" 
                            style="margin: 2px; padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <?php _e('Deductions', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                    <button type="button" onclick="filterTransactions('purchase')" class="button button-small filter-btn" 
                            style="margin: 2px; padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <?php _e('Purchases', 'ordersoftwarekeys-wallet'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div style="overflow-x: auto; border-radius: 8px; border: 1px solid #dee2e6; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #007cba 0%, #0056b3 100%);">
                            <th style="padding: 18px 20px; text-align: left; color: white; font-weight: bold; border: none; font-size: 15px;">
                                <?php _e('Date & Time', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 18px 20px; text-align: left; color: white; font-weight: bold; border: none; font-size: 15px;">
                                <?php _e('Transaction Type', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 18px 20px; text-align: left; color: white; font-weight: bold; border: none; font-size: 15px;">
                                <?php _e('Amount', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 18px 20px; text-align: left; color: white; font-weight: bold; border: none; font-size: 15px;">
                                <?php _e('Balance After', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                            <th style="padding: 18px 20px; text-align: left; color: white; font-weight: bold; border: none; font-size: 15px;">
                                <?php _e('Details', 'ordersoftwarekeys-wallet'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($transactions) as $index => $transaction) : 
                            $is_negative = ($transaction['type'] === 'purchase' || $transaction['type'] === 'subtract' || $transaction['type'] === 'admin_deduct');
                            $amount_color = $is_negative ? '#dc3545' : '#28a745';
                            $amount_sign = $is_negative ? '−' : '+';
                            $row_bg = $index % 2 === 0 ? '#fff' : '#f8f9fa';
                            
                            // Determine transaction class for filtering
                            $transaction_class = '';
                            if (in_array($transaction['type'], array('add', 'refund', 'topup', 'admin_add'))) {
                                $transaction_class = 'type-add';
                            } elseif (in_array($transaction['type'], array('subtract', 'admin_deduct'))) {
                                $transaction_class = 'type-deduct';
                            } elseif ($transaction['type'] === 'purchase') {
                                $transaction_class = 'type-purchase';
                            }
                        ?>
                            <tr class="transaction-row <?php echo $transaction_class; ?>" style="background: <?php echo $row_bg; ?>; border-bottom: 1px solid #eee;">
                                <td style="padding: 16px 20px; border: none;">
                                    <div style="font-weight: bold; color: #333; font-size: 15px;">
                                        <?php echo date_i18n('F j, Y', strtotime($transaction['time'])); ?>
                                    </div>
                                    <div style="font-size: 13px; color: #999;">
                                        <?php echo date_i18n('g:i A', strtotime($transaction['time'])); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; border: none;">
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
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span style="font-size: 22px;"><?php echo $type_icon; ?></span>
                                        <div>
                                            <div style="font-weight: bold; color: #333; font-size: 15px;"><?php echo $type_label; ?></div>
                                            <?php if ($transaction['type'] === 'topup') : ?>
                                            <div style="font-size: 12px; color: #28a745; background: #d4edda; padding: 2px 8px; border-radius: 10px; display: inline-block;">
                                                <?php _e('Wallet Top-up', 'ordersoftwarekeys-wallet'); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; border: none;">
                                    <div style="font-weight: bold; font-size: 18px; color: <?php echo $amount_color; ?>;">
                                        <?php echo $amount_sign . wc_price($transaction['amount']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; border: none;">
                                    <div style="font-weight: bold; font-size: 18px; color: #333;">
                                        <?php echo wc_price($transaction['new_balance']); ?>
                                    </div>
                                </td>
                                <td style="padding: 16px 20px; border: none;">
                                    <?php if ($transaction['order_id']) : ?>
                                        <div style="margin-bottom: 8px;">
                                            <a href="<?php echo wc_get_account_endpoint_url('view-order') . $transaction['order_id']; ?>" 
                                               style="display: inline-flex; align-items: center; gap: 6px; color: #007cba; text-decoration: none; font-weight: bold; font-size: 14px;"
                                               onmouseover="this.style.textDecoration='underline';"
                                               onmouseout="this.style.textDecoration='none';">
                                                <span>📦</span>
                                                <?php printf(__('Order #%s', 'ordersoftwarekeys-wallet'), $transaction['order_id']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($transaction['note']) : ?>
                                        <div style="background: #f8f9fa; padding: 10px 14px; border-radius: 6px; margin-top: 8px; border-left: 3px solid #007cba;">
                                            <div style="font-size: 12px; color: #666; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                                                <span>📝</span>
                                                <?php _e('Note:', 'ordersoftwarekeys-wallet'); ?>
                                            </div>
                                            <div style="color: #333; font-size: 14px; line-height: 1.4;">
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
            
            <!-- Summary Stats -->
            <?php
            $total_added = 0;
            $total_deducted = 0;
            $total_transactions = count($transactions);
            
            foreach ($transactions as $transaction) {
                if (in_array($transaction['type'], array('add', 'refund', 'topup', 'admin_add'))) {
                    $total_added += $transaction['amount'];
                } elseif (in_array($transaction['type'], array('purchase', 'subtract', 'admin_deduct'))) {
                    $total_deducted += $transaction['amount'];
                }
            }
            ?>
            
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 30px;">
                <div style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 10px; padding: 25px; text-align: center; border: 1px solid #c3e6cb;">
                    <div style="font-size: 16px; color: #155724; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span>📈</span>
                        <?php _e('Total Added', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                    <div style="font-size: 28px; font-weight: bold; color: #155724;">
                        <?php echo wc_price($total_added); ?>
                    </div>
                    <div style="font-size: 13px; color: #155724; margin-top: 5px; opacity: 0.8;">
                        <?php _e('All-time deposits', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-radius: 10px; padding: 25px; text-align: center; border: 1px solid #f5c6cb;">
                    <div style="font-size: 16px; color: #721c24; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span>📉</span>
                        <?php _e('Total Used', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                    <div style="font-size: 28px; font-weight: bold; color: #721c24;">
                        <?php echo wc_price($total_deducted); ?>
                    </div>
                    <div style="font-size: 13px; color: #721c24; margin-top: 5px; opacity: 0.8;">
                        <?php _e('All-time spending', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #cce5ff 0%, #b8daff 100%); border-radius: 10px; padding: 25px; text-align: center; border: 1px solid #b8daff;">
                    <div style="font-size: 16px; color: #004085; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span>📊</span>
                        <?php _e('Total Transactions', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                    <div style="font-size: 28px; font-weight: bold; color: #004085;">
                        <?php echo $total_transactions; ?>
                    </div>
                    <div style="font-size: 13px; color: #004085; margin-top: 5px; opacity: 0.8;">
                        <?php _e('All transactions', 'ordersoftwarekeys-wallet'); ?>
                    </div>
                </div>
            </div>
            
            <!-- JavaScript for filtering -->
            <script>
            function filterTransactions(type) {
                const rows = document.querySelectorAll('.transaction-row');
                
                rows.forEach(row => {
                    if (type === 'all') {
                        row.style.display = '';
                    } else if (row.classList.contains('type-' + type)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.style.background = '#6c757d';
                });
                
                event.target.style.background = '#007cba';
            }
            </script>
            
        <?php endif; ?>
        
        <!-- Help Section -->
        <div style="background: #f8f9fa; border-radius: 10px; padding: 30px; margin-top: 40px; border: 1px solid #dee2e6;">
            <div style="display: flex; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h4 style="color: #333; margin-top: 0;">
                        <?php _e('Need help with transactions?', 'ordersoftwarekeys-wallet'); ?>
                    </h4>
                    <p style="color: #666; margin: 15px 0; line-height: 1.6;">
                        <?php _e('If you notice any incorrect transactions or have questions about your wallet history, please contact our support team. We\'re here to help!', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="mailto:support@ordersoftwarekeys.com" class="button" style="background: #6c757d; border-color: #6c757d;">
                            <?php _e('Contact Support', 'ordersoftwarekeys-wallet'); ?>
                        </a>
                        <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" class="button button-primary">
                            <?php _e('Add More Funds', 'ordersoftwarekeys-wallet'); ?>
                        </a>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 300px;">
                    <div style="background: white; border-radius: 8px; padding: 20px; border: 1px solid #dee2e6;">
                        <h5 style="color: #333; margin-top: 0; margin-bottom: 15px;">
                            <?php _e('Transaction Types Explained', 'ordersoftwarekeys-wallet'); ?>
                        </h5>
                        <ul style="color: #666; margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><strong><?php _e('Top-up:', 'ordersoftwarekeys-wallet'); ?></strong> <?php _e('Funds added via payment', 'ordersoftwarekeys-wallet'); ?></li>
                            <li><strong><?php _e('Purchase:', 'ordersoftwarekeys-wallet'); ?></strong> <?php _e('Funds used for orders', 'ordersoftwarekeys-wallet'); ?></li>
                            <li><strong><?php _e('Refund:', 'ordersoftwarekeys-wallet'); ?></strong> <?php _e('Funds returned from orders', 'ordersoftwarekeys-wallet'); ?></li>
                            <li><strong><?php _e('Added/Deducted:', 'ordersoftwarekeys-wallet'); ?></strong> <?php _e('Manual adjustments by admin', 'ordersoftwarekeys-wallet'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Add wallet balance to My Account dashboard
add_action('woocommerce_account_dashboard', 'osk_wallet_show_dashboard_balance');

function osk_wallet_show_dashboard_balance() {
    if (!is_user_logged_in()) {
        return;
    }
    
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
    
    ?>
    <div class="osk-wallet-dashboard" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 30px; margin: 25px 0; border: 1px solid #dee2e6; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 25px;">
            <div>
                <h2 style="color: #333; margin-top: 0; border-bottom: 3px solid #007cba; padding-bottom: 12px; display: inline-block;">
                    <?php _e('Wallet Balance', 'ordersoftwarekeys-wallet'); ?>
                </h2>
                <p style="color: #666; margin: 8px 0 0 0; font-size: 15px;">
                    <?php _e('Use your wallet balance for faster checkout and easy payments.', 'ordersoftwarekeys-wallet'); ?>
                </p>
            </div>
            
            <div style="background: white; border-radius: 8px; padding: 15px 25px; text-align: center; border: 1px solid #dee2e6; min-width: 150px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <div style="color: #666; font-size: 14px; margin-bottom: 8px; font-weight: bold;">
                    <?php _e('Available Balance', 'ordersoftwarekeys-wallet'); ?>
                </div>
                <div style="font-size: 32px; font-weight: bold; color: <?php echo $color; ?>; line-height: 1;">
                    <?php echo wc_price($balance); ?>
                </div>
            </div>
        </div>
        
        <div class="wallet-actions" style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo wc_get_account_endpoint_url('wallet-topup'); ?>" 
               class="button button-primary" 
               style="padding: 14px 30px; background: #28a745; border-color: #28a745; font-size: 16px; font-weight: bold; border-radius: 6px; display: flex; align-items: center; gap: 10px; transition: all 0.3s;"
               onmouseover="this.style.background='#218838'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='#28a745'; this.style.transform='translateY(0)';">
                <span style="font-size: 20px;">➕</span>
                <?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?>
            </a>
            
            <a href="<?php echo wc_get_account_endpoint_url('wallet-transactions'); ?>" 
               class="button" 
               style="padding: 14px 30px; background: #007cba; border-color: #007cba; color: white; font-size: 16px; font-weight: bold; border-radius: 6px; display: flex; align-items: center; gap: 10px; transition: all 0.3s;"
               onmouseover="this.style.background='#0056b3'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='#007cba'; this.style.transform='translateY(0)';">
                <span style="font-size: 20px;">📊</span>
                <?php _e('View Transactions', 'ordersoftwarekeys-wallet'); ?>
            </a>
            
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" 
               class="button" 
               style="padding: 14px 30px; background: #6f42c1; border-color: #6f42c1; color: white; font-size: 16px; font-weight: bold; border-radius: 6px; display: flex; align-items: center; gap: 10px; transition: all 0.3s;"
               onmouseover="this.style.background='#5a32a3'; this.style.transform='translateY(-2px)';"
               onmouseout="this.style.background='#6f42c1'; this.style.transform='translateY(0)';">
                <span style="font-size: 20px;">🛒</span>
                <?php _e('Start Shopping', 'ordersoftwarekeys-wallet'); ?>
            </a>
        </div>
        
        <!-- Quick Tips -->
        <div style="margin-top: 30px; padding-top: 25px; border-top: 1px solid #dee2e6;">
            <h4 style="color: #333; margin-bottom: 15px; font-size: 16px;">
                <?php _e('💡 Quick Tips:', 'ordersoftwarekeys-wallet'); ?>
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #28a745;">
                    <strong style="color: #28a745;"><?php _e('Fast Checkout', 'ordersoftwarekeys-wallet'); ?></strong>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                        <?php _e('Use wallet for instant one-click payments.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #007cba;">
                    <strong style="color: #007cba;"><?php _e('Partial Payments', 'ordersoftwarekeys-wallet'); ?></strong>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                        <?php _e('Use wallet plus other payment methods.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
                <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #6f42c1;">
                    <strong style="color: #6f42c1;"><?php _e('Secure & Convenient', 'ordersoftwarekeys-wallet'); ?></strong>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                        <?php _e('No need to re-enter payment details.', 'ordersoftwarekeys-wallet'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
