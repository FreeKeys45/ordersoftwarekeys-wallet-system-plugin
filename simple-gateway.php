<?php
/**
 * Simple Payment Gateway for Wallet Top-up
 * File: includes/simple-gateway.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register the custom top-up product type
add_filter('product_type_selector', 'osk_wallet_add_simple_topup_product');
add_action('woocommerce_process_product_meta', 'osk_wallet_save_simple_topup_product', 10, 2);

function osk_wallet_add_simple_topup_product($types) {
    $types['simple_wallet_topup'] = __('Simple Wallet Top-up', 'ordersoftwarekeys-wallet');
    return $types;
}

function osk_wallet_save_simple_topup_product($product_id, $post) {
    $product = wc_get_product($product_id);
    
    if ($product->get_type() === 'simple_wallet_topup') {
        // Make it virtual and downloadable
        $product->set_virtual('yes');
        $product->set_downloadable('yes');
        $product->set_sold_individually('yes');
        
        // Remove shipping
        $product->set_shipping_class_id(0);
        
        $product->save();
    }
}

// Add custom fields to simple top-up product
add_action('woocommerce_product_options_general_product_data', 'osk_wallet_simple_topup_product_fields');

function osk_wallet_simple_topup_product_fields() {
    global $product_object;
    
    if (!$product_object || $product_object->get_type() !== 'simple_wallet_topup') {
        return;
    }
    
    echo '<div class="options_group">';
    
    woocommerce_wp_text_input(array(
        'id' => '_simple_topup_min_amount',
        'label' => __('Minimum Top-up Amount', 'ordersoftwarekeys-wallet'),
        'placeholder' => '1',
        'desc_tip' => 'true',
        'description' => __('Minimum amount user can add to wallet', 'ordersoftwarekeys-wallet'),
        'type' => 'number',
        'custom_attributes' => array(
            'step' => '0.01',
            'min' => '0'
        )
    ));
    
    woocommerce_wp_text_input(array(
        'id' => '_simple_topup_max_amount',
        'label' => __('Maximum Top-up Amount', 'ordersoftwarekeys-wallet'),
        'placeholder' => '1000',
        'desc_tip' => 'true',
        'description' => __('Maximum amount user can add to wallet', 'ordersoftwarekeys-wallet'),
        'type' => 'number',
        'custom_attributes' => array(
            'step' => '0.01',
            'min' => '0'
        )
    ));
    
    woocommerce_wp_text_input(array(
        'id' => '_simple_topup_default_amount',
        'label' => __('Default Amount', 'ordersoftwarekeys-wallet'),
        'placeholder' => '10',
        'desc_tip' => 'true',
        'description' => __('Default amount shown on top-up form', 'ordersoftwarekeys-wallet'),
        'type' => 'number',
        'custom_attributes' => array(
            'step' => '0.01',
            'min' => '0'
        )
    ));
    
    echo '</div>';
    
    // Hide regular price field
    echo '<style>
        .show_if_simple { display: none !important; }
        .show_if_simple_wallet_topup { display: block !important; }
    </style>';
}

// Save simple top-up product custom fields
add_action('woocommerce_process_product_meta_simple_wallet_topup', 'osk_wallet_save_simple_topup_custom_fields');

function osk_wallet_save_simple_topup_custom_fields($product_id) {
    $min_amount = isset($_POST['_simple_topup_min_amount']) ? wc_clean($_POST['_simple_topup_min_amount']) : '1';
    $max_amount = isset($_POST['_simple_topup_max_amount']) ? wc_clean($_POST['_simple_topup_max_amount']) : '1000';
    $default_amount = isset($_POST['_simple_topup_default_amount']) ? wc_clean($_POST['_simple_topup_default_amount']) : '10';
    
    update_post_meta($product_id, '_simple_topup_min_amount', $min_amount);
    update_post_meta($product_id, '_simple_topup_max_amount', $max_amount);
    update_post_meta($product_id, '_simple_topup_default_amount', $default_amount);
    
    // Set price to 0 - we'll handle it dynamically
    update_post_meta($product_id, '_price', '0');
    update_post_meta($product_id, '_regular_price', '0');
}

// Customize the add to cart form for simple top-up products
add_action('woocommerce_before_add_to_cart_form', 'osk_wallet_simple_topup_before_add_to_cart');

function osk_wallet_simple_topup_before_add_to_cart() {
    global $product;
    
    if (!$product || $product->get_type() !== 'simple_wallet_topup') {
        return;
    }
    
    // Remove default quantity input
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    
    // Add our custom form
    add_action('woocommerce_single_product_summary', 'osk_wallet_simple_topup_add_to_cart_form', 30);
}

function osk_wallet_simple_topup_add_to_cart_form() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    $min_amount = get_post_meta($product_id, '_simple_topup_min_amount', true) ?: 1;
    $max_amount = get_post_meta($product_id, '_simple_topup_max_amount', true) ?: 1000;
    $default_amount = get_post_meta($product_id, '_simple_topup_default_amount', true) ?: 10;
    
    // Get current user balance
    $current_balance = 0;
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $current_balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
    }
    ?>
    
    <div class="osk-wallet-simple-topup-form" style="max-width: 500px; margin: 0 auto;">
        <div style="background: #e7f5ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
            <?php if (is_user_logged_in()) : ?>
                <p style="margin: 0; font-size: 16px;">
                    <?php _e('Your Current Balance:', 'ordersoftwarekeys-wallet'); ?> 
                    <strong style="color: #28a745; font-size: 24px;"><?php echo wc_price($current_balance); ?></strong>
                </p>
            <?php else : ?>
                <p style="margin: 0; font-size: 16px; color: #ffc107;">
                    <?php _e('Please log in to add funds to your wallet.', 'ordersoftwarekeys-wallet'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <?php if (is_user_logged_in()) : ?>
            <form class="cart" method="post" enctype="multipart/form-data">
                <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 25px; margin: 20px 0;">
                    <h3 style="color: #333; margin-top: 0; text-align: center;">
                        <?php _e('Enter Amount to Add', 'ordersoftwarekeys-wallet'); ?>
                    </h3>
                    
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
                                        style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; padding: 10px 15px; cursor: pointer; transition: all 0.3s;">
                                    <?php echo wc_price($amount); ?>
                                </button>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    
                    <div style="color: #666; text-align: center; margin-top: 10px;">
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
                    <button type="submit" 
                            name="add-to-cart" 
                            value="<?php echo esc_attr($product_id); ?>" 
                            class="single_add_to_cart_button button alt"
                            style="background: #28a745; border-color: #28a745; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; border-radius: 5px;">
                        <?php 
                        echo sprintf(
                            __('Add %s to Wallet', 'ordersoftwarekeys-wallet'),
                            '<span id="amount-display">' . wc_price($default_amount) . '</span>'
                        ); 
                        ?>
                    </button>
                </div>
                
                <!-- FIXED: Changed to hidden input with correct name -->
                <input type="hidden" name="topup_custom_amount" id="topup_custom_amount" value="<?php echo esc_attr($default_amount); ?>">
            </form>
            
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
                    
                    // Ensure the hidden field has the correct value
                    $('#topup_custom_amount').val(amount);
                });
            });
            </script>
            
        <?php else : ?>
            <div style="text-align: center; margin: 30px 0;">
                <a href="<?php echo wc_get_page_permalink('myaccount'); ?>" class="button button-primary" style="padding: 15px 30px;">
                    <?php _e('Login to Add Funds', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
}

// Process the custom amount when added to cart - FIXED VERSION
add_filter('woocommerce_add_cart_item_data', 'osk_wallet_add_topup_amount_to_cart', 10, 3);

function osk_wallet_add_topup_amount_to_cart($cart_item_data, $product_id, $variation_id) {
    $product = wc_get_product($product_id);
    
    if ($product && $product->get_type() === 'simple_wallet_topup' && isset($_POST['topup_custom_amount'])) {
        $amount = floatval($_POST['topup_custom_amount']);
        
        // Validate amount
        $min_amount = get_post_meta($product_id, '_simple_topup_min_amount', true) ?: 1;
        $max_amount = get_post_meta($product_id, '_simple_topup_max_amount', true) ?: 1000;
        
        if ($amount >= $min_amount && $amount <= $max_amount) {
            $cart_item_data['topup_amount'] = $amount;
            $cart_item_data['unique_key'] = md5(microtime().rand()); // Make item unique
        }
    }
    
    return $cart_item_data;
}

// Set the product price based on custom amount - FIXED VERSION
add_action('woocommerce_before_calculate_totals', 'osk_wallet_set_topup_price', 9999, 1);

function osk_wallet_set_topup_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['topup_amount']) && $cart_item['topup_amount'] > 0) {
            // Set the price to the custom amount
            $cart_item['data']->set_price($cart_item['topup_amount']);
        }
    }
}

// Display custom amount in cart - FIXED VERSION
add_filter('woocommerce_get_item_data', 'osk_wallet_display_topup_amount_in_cart', 10, 2);

function osk_wallet_display_topup_amount_in_cart($item_data, $cart_item) {
    if (isset($cart_item['topup_amount'])) {
        $item_data[] = array(
            'name' => __('Top-up Amount', 'ordersoftwarekeys-wallet'),
            'value' => wc_price($cart_item['topup_amount']),
            'display' => wc_price($cart_item['topup_amount'])
        );
    }
    
    return $item_data;
}

// Update order item meta - FIXED VERSION
add_action('woocommerce_checkout_create_order_line_item', 'osk_wallet_add_topup_amount_to_order_item', 10, 4);

function osk_wallet_add_topup_amount_to_order_item($item, $cart_item_key, $values, $order) {
    if (isset($values['topup_amount'])) {
        $item->add_meta_data('_topup_amount', $values['topup_amount']);
        $item->add_meta_data('_is_wallet_topup', 'yes');
        // Also set the line item total
        $item->set_total($values['topup_amount']);
        $item->set_subtotal($values['topup_amount']);
    }
}

// Add funds to wallet when order is completed
add_action('woocommerce_order_status_completed', 'osk_wallet_add_funds_on_order_completion', 10, 1);

function osk_wallet_add_funds_on_order_completion($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    
    if (!$user_id) {
        return;
    }
    
    // Check if this is a wallet top-up order
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('_is_wallet_topup') === 'yes') {
            $topup_amount = $item->get_meta('_topup_amount');
            
            if ($topup_amount) {
                $current_balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
                $new_balance = $current_balance + floatval($topup_amount);
                
                // Update wallet balance
                update_user_meta($user_id, 'wallet_balance', $new_balance);
                
                // Log transaction
                if (function_exists('osk_wallet_log_transaction')) {
                    osk_wallet_log_transaction($user_id, array(
                        'type' => 'topup',
                        'order_id' => $order_id,
                        'amount' => $topup_amount,
                        'old_balance' => $current_balance,
                        'new_balance' => $new_balance,
                        'note' => sprintf(__('Wallet top-up via order #%s', 'ordersoftwarekeys-wallet'), $order_id)
                    ));
                }
                
                // Send email notification
                if (function_exists('osk_wallet_send_email')) {
                    osk_wallet_send_email($user_id, 'funds_added', array(
                        'amount' => $topup_amount,
                        'order_id' => $order_id,
                        'new_balance' => $new_balance
                    ));
                }
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Wallet top-up completed. Added %s to user wallet. New balance: %s', 'ordersoftwarekeys-wallet'),
                    wc_price($topup_amount),
                    wc_price($new_balance)
                ));
                
                break; // Only process first top-up item
            }
        }
    }
}

// Also process on "processing" status for certain payment methods
add_action('woocommerce_order_status_processing', 'osk_wallet_add_funds_on_order_processing', 10, 1);

function osk_wallet_add_funds_on_order_processing($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    // Only process for certain payment methods that are immediate
    $immediate_methods = array('cod', 'bacs'); // Cash on delivery, Bank transfer
    
    if (in_array($order->get_payment_method(), $immediate_methods)) {
        osk_wallet_add_funds_on_order_completion($order_id);
    }
}
