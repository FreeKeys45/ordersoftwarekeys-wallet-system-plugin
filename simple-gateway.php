<?php
/**
 * Simple Wallet Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_wallet_gateway');

function init_wallet_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Wallet_Topup extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id = 'wallet_topup';
            $this->method_title = 'Wallet Top-up';
            $this->method_description = 'Gateway for adding funds to wallet';
            $this->has_fields = false;
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wallet_topup', array($this, 'handle_callback'));
        }
        
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Wallet Top-up Gateway',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Wallet Top-up',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Add funds to your wallet for future purchases.'
                )
            );
        }
        
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            // Mark as processing
            $order->update_status('processing', __('Payment received, wallet topped up.', 'woocommerce'));
            
            // Add funds to user's wallet
            $user_id = $order->get_user_id();
            $order_total = $order->get_total();
            
            if ($user_id > 0) {
                $current_balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
                $new_balance = $current_balance + $order_total;
                update_user_meta($user_id, 'wallet_balance', $new_balance);
                
                // Add transaction record
                $transactions = get_user_meta($user_id, 'wallet_transactions', true);
                if (!is_array($transactions)) {
                    $transactions = array();
                }
                
                $transactions[] = array(
                    'type' => 'credit',
                    'amount' => $order_total,
                    'date' => current_time('mysql'),
                    'description' => 'Wallet top-up via order #' . $order_id,
                    'order_id' => $order_id
                );
                
                update_user_meta($user_id, 'wallet_transactions', $transactions);
                
                // Send notification email
                do_action('wallet_topup_completed', $user_id, $order_total, $order_id);
            }
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Remove cart
            WC()->cart->empty_cart();
            
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        public function handle_callback() {
            // Handle any callback logic if needed
            wp_die('Wallet Top-up Gateway Callback');
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_wallet_gateway');

function add_wallet_gateway($gateways) {
    $gateways[] = 'WC_Gateway_Wallet_Topup';
    return $gateways;
}

/**
 * Add custom price to wallet top-up product
 */
add_action('woocommerce_before_calculate_totals', 'set_wallet_topup_price', 10, 1);

function set_wallet_topup_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    // Get the wallet top-up product ID
    $wallet_product_id = get_option('wallet_topup_product_id');
    
    if (empty($wallet_product_id)) {
        return;
    }
    
    // Check if we have a custom amount in session
    $wallet_topup_amount = WC()->session->get('wallet_topup_amount');
    
    if (empty($wallet_topup_amount)) {
        // Try to get from URL parameter
        if (isset($_GET['topup_amount']) && is_numeric($_GET['topup_amount'])) {
            $wallet_topup_amount = floatval($_GET['topup_amount']);
            WC()->session->set('wallet_topup_amount', $wallet_topup_amount);
        } elseif (isset($_POST['topup_amount']) && is_numeric($_POST['topup_amount'])) {
            $wallet_topup_amount = floatval($_POST['topup_amount']);
            WC()->session->set('wallet_topup_amount', $wallet_topup_amount);
        }
    }
    
    if (!empty($wallet_topup_amount) && $wallet_topup_amount > 0) {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['product_id']) && $cart_item['product_id'] == $wallet_product_id) {
                // Set the custom price
                $cart_item['data']->set_price($wallet_topup_amount);
                
                // Store the amount in cart item data for reference
                WC()->cart->cart_contents[$cart_item_key]['wallet_topup_amount'] = $wallet_topup_amount;
            }
        }
    }
}

/**
 * Display custom amount in cart
 */
add_filter('woocommerce_cart_item_price', 'display_wallet_topup_amount_in_cart', 10, 3);

function display_wallet_topup_amount_in_cart($price, $cart_item, $cart_item_key) {
    $wallet_product_id = get_option('wallet_topup_product_id');
    
    if (isset($cart_item['product_id']) && $cart_item['product_id'] == $wallet_product_id) {
        if (isset($cart_item['wallet_topup_amount']) && $cart_item['wallet_topup_amount'] > 0) {
            return wc_price($cart_item['wallet_topup_amount']);
        }
    }
    
    return $price;
}

/**
 * Clear session after order completion
 */
add_action('woocommerce_checkout_order_processed', 'clear_wallet_topup_session');

function clear_wallet_topup_session() {
    WC()->session->__unset('wallet_topup_amount');
}
