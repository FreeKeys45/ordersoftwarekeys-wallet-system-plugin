<?php
/**
 * Plugin Name: Ordersoftwarekeys Wallet System
 * Plugin URI: https://ordersoftwarekeys.com/
 * Description: Complete digital wallet system for WooCommerce - top-ups, transactions, admin dashboard, partial payments, and more.
 * Version: 2.0.0
 * Author: Ordersoftwarekeys
 * License: GPL v2 or later
 * Text Domain: ordersoftwarekeys-wallet
 * WC requires at least: 5.0.0
 * WC tested up to: 8.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OSK_WALLET_VERSION', '2.0.0');
define('OSK_WALLET_PATH', plugin_dir_path(__FILE__));
define('OSK_WALLET_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
add_action('admin_init', 'osk_wallet_check_woocommerce');

function osk_wallet_check_woocommerce() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'osk_wallet_woocommerce_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

function osk_wallet_woocommerce_notice() {
    ?>
    <div class="error">
        <p><?php _e('Ordersoftwarekeys Wallet System requires WooCommerce to be installed and activated!', 'ordersoftwarekeys-wallet'); ?></p>
    </div>
    <?php
}

// Include necessary files
require_once OSK_WALLET_PATH . 'includes/admin-dashboard.php';
require_once OSK_WALLET_PATH . 'includes/user-dashboard.php';
require_once OSK_WALLET_PATH . 'includes/shortcodes.php';
require_once OSK_WALLET_PATH . 'includes/email-notifications.php';
require_once OSK_WALLET_PATH . 'includes/create-product.php';
require_once OSK_WALLET_PATH . 'includes/simple-gateway.php';

// Initialize plugin
add_action('plugins_loaded', 'osk_wallet_init');

function osk_wallet_init() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Add admin menu
    add_action('admin_menu', 'osk_wallet_admin_menu');
    
    // Add wallet column to users list
    add_filter('manage_users_columns', 'osk_wallet_add_user_column');
    add_filter('manage_users_custom_column', 'osk_wallet_show_user_wallet', 10, 3);
    
    // Add bulk actions for users
    add_filter('bulk_actions-users', 'osk_wallet_add_bulk_actions');
    add_filter('handle_bulk_actions-users', 'osk_wallet_handle_bulk_actions', 10, 3);
    
    // Add wallet top-up product type
    add_filter('product_type_selector', 'osk_wallet_add_topup_product');
    add_action('woocommerce_process_product_meta', 'osk_wallet_save_topup_product', 10, 2);
}

// ====================
// ADMIN MENU & DASHBOARD
// ====================

function osk_wallet_admin_menu() {
    add_menu_page(
        __('Wallet System', 'ordersoftwarekeys-wallet'),
        __('Wallet System', 'ordersoftwarekeys-wallet'),
        'manage_woocommerce',
        'ordersoftwarekeys-wallet',
        'osk_wallet_admin_dashboard',
        'dashicons-money-alt',
        56
    );
    
    add_submenu_page(
        'ordersoftwarekeys-wallet',
        __('All Balances', 'ordersoftwarekeys-wallet'),
        __('All Balances', 'ordersoftwarekeys-wallet'),
        'manage_woocommerce',
        'ordersoftwarekeys-wallet-balances',
        'osk_wallet_balances_page'
    );
    
    add_submenu_page(
        'ordersoftwarekeys-wallet',
        __('Transactions', 'ordersoftwarekeys-wallet'),
        __('Transactions', 'ordersoftwarekeys-wallet'),
        'manage_woocommerce',
        'ordersoftwarekeys-wallet-transactions',
        'osk_wallet_transactions_page'
    );
    
    add_submenu_page(
        'ordersoftwarekeys-wallet',
        __('Settings', 'ordersoftwarekeys-wallet'),
        __('Settings', 'ordersoftwarekeys-wallet'),
        'manage_woocommerce',
        'ordersoftwarekeys-wallet-settings',
        'osk_wallet_settings_page'
    );
}

// ====================
// USER COLUMNS & BULK ACTIONS
// ====================

function osk_wallet_add_user_column($columns) {
    $columns['wallet_balance'] = __('Wallet Balance', 'ordersoftwarekeys-wallet');
    return $columns;
}

function osk_wallet_show_user_wallet($value, $column_name, $user_id) {
    if ($column_name === 'wallet_balance') {
        $balance = get_user_meta($user_id, 'wallet_balance', true);
        $balance = $balance ? floatval($balance) : 0;
        
        // Set color based on balance amount
        $color = '#555'; // Default color (grey/normal) for 0 balance
        $font_weight = 'normal';
        
        if ($balance > 100) {
            $color = '#28a745'; // Green for > 100
            $font_weight = 'bold';
        } elseif ($balance > 0 && $balance <= 100) {
            $color = '#ffc107'; // Yellow for 1-100
            $font_weight = 'bold';
        } elseif ($balance < 0) {
            $color = '#dc3545'; // Red for negative
            $font_weight = 'bold';
        }
        
        $output = '<span style="color: ' . $color . '; font-weight: ' . $font_weight . ';">' . wc_price($balance) . '</span>';
        
        // Add quick edit links
        $output .= '<div class="row-actions">';
        $output .= '<span class="edit"><a href="' . admin_url('admin.php?page=ordersoftwarekeys-wallet&user_id=' . $user_id) . '">' . __('Manage', 'ordersoftwarekeys-wallet') . '</a></span>';
        $output .= '</div>';
        
        return $output;
    }
    return $value;
}

function osk_wallet_add_bulk_actions($bulk_actions) {
    $bulk_actions['add_wallet_funds'] = __('Add Wallet Funds', 'ordersoftwarekeys-wallet');
    $bulk_actions['deduct_wallet_funds'] = __('Deduct Wallet Funds', 'ordersoftwarekeys-wallet');
    return $bulk_actions;
}

function osk_wallet_handle_bulk_actions($redirect_to, $doaction, $user_ids) {
    if ($doaction === 'add_wallet_funds' || $doaction === 'deduct_wallet_funds') {
        $redirect_to = add_query_arg(array(
            'page' => 'ordersoftwarekeys-wallet',
            'bulk_action' => $doaction,
            'user_ids' => implode(',', $user_ids)
        ), admin_url('admin.php'));
    }
    return $redirect_to;
}

// ====================
// WALLET TOP-UP PRODUCT
// ====================

function osk_wallet_add_topup_product($types) {
    $types['wallet_topup'] = __('Wallet Top-up', 'ordersoftwarekeys-wallet');
    return $types;
}

function osk_wallet_save_topup_product($product_id, $post) {
    $product = wc_get_product($product_id);
    
    if ($product->get_type() === 'wallet_topup') {
        $product->set_virtual('yes');
        $product->set_downloadable('yes');
        $product->set_sold_individually('yes');
        
        // Remove shipping for top-up products
        $product->set_shipping_class_id(0);
        
        $product->save();
    }
}

// ====================
// PAYMENT GATEWAY CLASS
// ====================

add_filter('woocommerce_payment_gateways', 'osk_wallet_add_gateway_class');

function osk_wallet_add_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_Ordersoftwarekeys_Wallet';
    return $gateways;
}

add_action('plugins_loaded', 'osk_wallet_init_gateway_class');

function osk_wallet_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    
    class WC_Gateway_Ordersoftwarekeys_Wallet extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id = 'ordersoftwarekeys_wallet';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = __('Ordersoftwarekeys Wallet', 'ordersoftwarekeys-wallet');
            $this->method_description = __('Allow customers to pay using their wallet balance.', 'ordersoftwarekeys-wallet');
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->allow_partial = $this->get_option('allow_partial') === 'yes';
            
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_review_order_before_payment', array($this, 'show_wallet_balance_checkout'));
        }
        
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable', 'ordersoftwarekeys-wallet'),
                    'label'       => __('Enable Wallet Payment', 'ordersoftwarekeys-wallet'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'yes'
                ),
                'title' => array(
                    'title'       => __('Title', 'ordersoftwarekeys-wallet'),
                    'type'        => 'text',
                    'description' => __('Payment method title that customers see during checkout.', 'ordersoftwarekeys-wallet'),
                    'default'     => __('Ordersoftwarekeys Wallet', 'ordersoftwarekeys-wallet'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'ordersoftwarekeys-wallet'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that customers see during checkout.', 'ordersoftwarekeys-wallet'),
                    'default'     => __('Pay using your wallet balance.', 'ordersoftwarekeys-wallet'),
                    'desc_tip'    => true,
                ),
                'allow_partial' => array(
                    'title'       => __('Allow Partial Payments', 'ordersoftwarekeys-wallet'),
                    'label'       => __('Allow customers to use wallet for partial payment', 'ordersoftwarekeys-wallet'),
                    'type'        => 'checkbox',
                    'description' => __('If enabled, customers can use wallet balance plus another payment method.', 'ordersoftwarekeys-wallet'),
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'partial_label' => array(
                    'title'       => __('Partial Payment Label', 'ordersoftwarekeys-wallet'),
                    'type'        => 'text',
                    'description' => __('Label shown when using partial payment.', 'ordersoftwarekeys-wallet'),
                    'default'     => __('Use Wallet Balance', 'ordersoftwarekeys-wallet'),
                    'desc_tip'    => true,
                )
            );
        }
        
        public function show_wallet_balance_checkout() {
            if (!is_user_logged_in()) {
                return;
            }
            
            $user_id = get_current_user_id();
            $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
            $cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;
            
            if ($balance > 0) {
                echo '<div class="wallet-balance-checkout" style="background: #e7f5ff; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #007cba;">';
                echo '<p style="margin: 0 0 10px 0;"><strong>' . __('Your Wallet Balance:', 'ordersoftwarekeys-wallet') . '</strong> ' . wc_price($balance) . '</p>';
                
                if ($balance >= $cart_total) {
                    echo '<p style="color: #28a745; margin: 0;">' . __('✅ You have sufficient balance to pay with wallet.', 'ordersoftwarekeys-wallet') . '</p>';
                } elseif ($balance < $cart_total && $this->allow_partial) {
                    echo '<p style="color: #ffc107; margin: 0;">' . 
                         sprintf(
                             __('You can use %s from your wallet and pay the remaining %s with another method.', 'ordersoftwarekeys-wallet'),
                             wc_price($balance),
                             wc_price($cart_total - $balance)
                         ) . 
                         '</p>';
                } else {
                    echo '<p style="color: #dc3545; margin: 0;">' . 
                         sprintf(
                             __('Insufficient balance. Need %s more to use wallet.', 'ordersoftwarekeys-wallet'),
                             wc_price($cart_total - $balance)
                         ) . 
                         '</p>';
                }
                echo '</div>';
            }
        }
        
        public function is_available() {
            if (!is_user_logged_in()) {
                return false;
            }
            
            $user_id = get_current_user_id();
            $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
            
            if ($balance <= 0) {
                return false;
            }
            
            return parent::is_available();
        }
        
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            $order_total = $order->get_total();
            $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
            
            $wallet_used = min($balance, $order_total);
            $remaining = $order_total - $wallet_used;
            
            // If partial payments allowed and balance < order total
            if ($remaining > 0 && $this->allow_partial) {
                // Store wallet payment amount
                $order->update_meta_data('_wallet_payment', $wallet_used);
                $order->update_meta_data('_remaining_payment', $remaining);
                
                // Update order total to remaining amount
                $order->set_total($remaining);
                $order->save();
                
                // Return to payment methods for remaining amount
                return array(
                    'result' => 'success',
                    'redirect' => wc_get_checkout_url()
                );
            }
            
            // Check if user has sufficient balance for full payment
            if ($balance < $order_total) {
                wc_add_notice(__('Insufficient wallet balance to complete this order.', 'ordersoftwarekeys-wallet'), 'error');
                return false;
            }
            
            // Process full wallet payment
            return $this->process_wallet_payment($order, $user_id, $order_total, $balance);
        }
        
        private function process_wallet_payment($order, $user_id, $amount, $current_balance) {
            $new_balance = $current_balance - $amount;
            update_user_meta($user_id, 'wallet_balance', $new_balance);
            
            // Mark order as paid
            $order->payment_complete();
            
            // Add order note
            $order->add_order_note(sprintf(
                __('Payment completed via Ordersoftwarekeys Wallet. Balance deducted: %s. Remaining balance: %s', 'ordersoftwarekeys-wallet'),
                wc_price($amount),
                wc_price($new_balance)
            ));
            
            // Log transaction
            osk_wallet_log_transaction($user_id, array(
                'type' => 'purchase',
                'order_id' => $order->get_id(),
                'amount' => $amount,
                'old_balance' => $current_balance,
                'new_balance' => $new_balance,
                'note' => sprintf(__('Payment for order #%s', 'ordersoftwarekeys-wallet'), $order->get_id())
            ));
            
            // Trigger email action
            do_action('osk_wallet_after_balance_update', $user_id, -$amount, sprintf(__('Payment for order #%s', 'ordersoftwarekeys-wallet'), $order->get_id()));
            
            // Reduce stock levels
            wc_reduce_stock_levels($order->get_id());
            
            // Empty cart
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }
            
            // Send email notification
            if (function_exists('osk_wallet_send_email')) {
                osk_wallet_send_email($user_id, 'payment_made', array(
                    'amount' => $amount,
                    'order_id' => $order->get_id(),
                    'new_balance' => $new_balance
                ));
            }
            
            // Return thank you page redirect
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
            
            echo '<div class="osk-wallet-thankyou" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 20px; margin: 20px 0;">';
            echo '<h3 style="color: #155724; margin-top: 0;">' . __('Wallet Payment Summary', 'ordersoftwarekeys-wallet') . '</h3>';
            echo '<p style="margin: 10px 0;">' . sprintf(
                __('Your remaining wallet balance is: %s', 'ordersoftwarekeys-wallet'),
                '<strong style="color: #28a745; font-size: 18px;">' . wc_price($balance) . '</strong>'
            ) . '</p>';
            
            if ($order->get_meta('_wallet_payment')) {
                echo '<p style="margin: 10px 0;">' . sprintf(
                    __('Amount paid from wallet: %s', 'ordersoftwarekeys-wallet'),
                    '<strong>' . wc_price($order->get_meta('_wallet_payment')) . '</strong>'
                ) . '</p>';
            }
            echo '</div>';
        }
    }
}

// ====================
// TRANSACTION LOGGING
// ====================

function osk_wallet_log_transaction($user_id, $data) {
    $transaction = array(
        'id' => uniqid(),
        'time' => current_time('mysql'),
        'type' => $data['type'],
        'amount' => $data['amount'],
        'old_balance' => $data['old_balance'],
        'new_balance' => $data['new_balance'],
        'note' => isset($data['note']) ? $data['note'] : '',
        'order_id' => isset($data['order_id']) ? $data['order_id'] : '',
        'admin_id' => isset($data['admin_id']) ? $data['admin_id'] : get_current_user_id()
    );
    
    $transactions = get_user_meta($user_id, 'wallet_transactions', true);
    if (!is_array($transactions)) {
        $transactions = array();
    }
    $transactions[] = $transaction;
    update_user_meta($user_id, 'wallet_transactions', $transactions);
}

// ====================
// WALLET REFUNDS
// ====================

add_action('woocommerce_order_refunded', 'osk_wallet_process_refund', 10, 2);

function osk_wallet_process_refund($order_id, $refund_id) {
    $order = wc_get_order($order_id);
    $refund = wc_get_order($refund_id);
    
    // Check if original order was paid with wallet
    if ($order->get_payment_method() === 'ordersoftwarekeys_wallet') {
        $user_id = $order->get_user_id();
        $refund_amount = $refund->get_amount();
        $current_balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
        $new_balance = $current_balance + $refund_amount;
        
        update_user_meta($user_id, 'wallet_balance', $new_balance);
        
        // Log transaction
        osk_wallet_log_transaction($user_id, array(
            'type' => 'refund',
            'order_id' => $order_id,
            'refund_id' => $refund_id,
            'amount' => $refund_amount,
            'old_balance' => $current_balance,
            'new_balance' => $new_balance,
            'note' => sprintf(__('Refund for order #%s', 'ordersoftwarekeys-wallet'), $order_id)
        ));
        
        // Trigger email action
        do_action('osk_wallet_after_balance_update', $user_id, $refund_amount, sprintf(__('Refund for order #%s', 'ordersoftwarekeys-wallet'), $order_id));
        
        // Add order note
        $order->add_order_note(sprintf(
            __('Refunded %s to customer wallet. New balance: %s', 'ordersoftwarekeys-wallet'),
            wc_price($refund_amount),
            wc_price($new_balance)
        ));
        
        // Send email notification
        if (function_exists('osk_wallet_send_email')) {
            osk_wallet_send_email($user_id, 'refund_received', array(
                'amount' => $refund_amount,
                'order_id' => $order_id,
                'new_balance' => $new_balance
            ));
        }
    }
}

// ====================
// WALLET BALANCE FIELD FOR USER PROFILE
// ====================

// Add wallet field to user profile
add_action('show_user_profile', 'osk_wallet_user_profile_fields');
add_action('edit_user_profile', 'osk_wallet_user_profile_fields');

function osk_wallet_user_profile_fields($user) {
    // Only show to admins and shop managers
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    $balance = get_user_meta($user->ID, 'wallet_balance', true);
    $balance = $balance ? floatval($balance) : 0;
    
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
    <h3><?php _e('Wallet Balance', 'ordersoftwarekeys-wallet'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="wallet_balance"><?php _e('Current Balance', 'ordersoftwarekeys-wallet'); ?></label></th>
            <td>
                <p style="font-size: 18px; font-weight: bold; color: <?php echo $color; ?>;">
                    <?php echo get_woocommerce_currency_symbol() . ' ' . number_format($balance, 2); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th><label for="wallet_adjustment"><?php _e('Adjust Balance', 'ordersoftwarekeys-wallet'); ?></label></th>
            <td>
                <select name="wallet_adjustment_type" id="wallet_adjustment_type" style="vertical-align: middle;">
                    <option value="add"><?php _e('Add Funds', 'ordersoftwarekeys-wallet'); ?></option>
                    <option value="subtract"><?php _e('Subtract Funds', 'ordersoftwarekeys-wallet'); ?></option>
                    <option value="set"><?php _e('Set to Amount', 'ordersoftwarekeys-wallet'); ?></option>
                </select>
                <input type="number" step="0.01" name="wallet_adjustment_amount" id="wallet_adjustment_amount" 
                       placeholder="0.00" style="width: 120px; margin-left: 10px; padding: 5px;">
                <span style="margin-left: 5px; font-weight: bold;"><?php echo get_woocommerce_currency_symbol(); ?></span>
                <p class="description"><?php _e('Enter amount to adjust wallet balance', 'ordersoftwarekeys-wallet'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="wallet_note"><?php _e('Adjustment Note', 'ordersoftwarekeys-wallet'); ?></label></th>
            <td>
                <input type="text" name="wallet_note" id="wallet_note" class="regular-text" 
                       placeholder="<?php _e('Reason for adjustment', 'ordersoftwarekeys-wallet'); ?>" style="width: 100%; max-width: 400px;">
            </td>
        </tr>
    </table>
    <?php
}

// Save wallet balance adjustments
add_action('personal_options_update', 'osk_wallet_save_user_profile_fields');
add_action('edit_user_profile_update', 'osk_wallet_save_user_profile_fields');

function osk_wallet_save_user_profile_fields($user_id) {
    if (!current_user_can('manage_woocommerce')) {
        return false;
    }
    
    if (isset($_POST['wallet_adjustment_amount']) && $_POST['wallet_adjustment_amount'] !== '') {
        $amount = floatval($_POST['wallet_adjustment_amount']);
        $type = sanitize_text_field($_POST['wallet_adjustment_type']);
        $note = sanitize_text_field($_POST['wallet_note']);
        $current_balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
        
        if ($type === 'add') {
            $new_balance = $current_balance + $amount;
        } elseif ($type === 'subtract') {
            $new_balance = $current_balance - $amount;
        } elseif ($type === 'set') {
            $new_balance = $amount;
        }
        
        // Ensure balance doesn't go negative
        $new_balance = max(0, $new_balance);
        
        update_user_meta($user_id, 'wallet_balance', $new_balance);
        
        // Trigger email action (only for adding funds)
        if ($type === 'add') {
            do_action('osk_wallet_after_balance_update', $user_id, $amount, $note);
        }
        
        // Log the transaction
        $log = array(
            'time' => current_time('mysql'),
            'type' => $type,
            'amount' => $amount,
            'old_balance' => $current_balance,
            'new_balance' => $new_balance,
            'note' => $note,
            'admin_id' => get_current_user_id()
        );
        
        $transactions = get_user_meta($user_id, 'wallet_transactions', true);
        if (!is_array($transactions)) {
            $transactions = array();
        }
        $transactions[] = $log;
        update_user_meta($user_id, 'wallet_transactions', $transactions);
        
        // Show success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Wallet balance updated successfully!', 'ordersoftwarekeys-wallet') . 
                 '</p></div>';
        });
    }
}

// ====================
// FLUSH REWRITE RULES ON ACTIVATION
// ====================

register_activation_hook(__FILE__, 'osk_wallet_flush_rewrite_rules');
function osk_wallet_flush_rewrite_rules() {
    add_rewrite_endpoint('wallet-topup', EP_PAGES);
    add_rewrite_endpoint('wallet-transactions', EP_PAGES);
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'osk_wallet_flush_rewrite_rules_deactivate');
function osk_wallet_flush_rewrite_rules_deactivate() {
    flush_rewrite_rules();
}

// ====================
// INITIALIZE WALLET FOR ALL USERS
// ====================

// Initialize wallet for all users on plugin activation
register_activation_hook(__FILE__, 'osk_wallet_initialize_all_users');
function osk_wallet_initialize_all_users() {
    $users = get_users();
    foreach ($users as $user) {
        if (!get_user_meta($user->ID, 'wallet_balance', true)) {
            update_user_meta($user->ID, 'wallet_balance', 0);
        }
    }
}

// Initialize wallet for new users
add_action('user_register', 'osk_wallet_initialize_new_user');
function osk_wallet_initialize_new_user($user_id) {
    if (!get_user_meta($user_id, 'wallet_balance', true)) {
        update_user_meta($user_id, 'wallet_balance', 0);
    }
}

// ====================
// SIMPLE ADMIN CSS
// ====================

add_action('admin_head', 'osk_wallet_admin_css');
function osk_wallet_admin_css() {
    ?>
    <style>
        .column-wallet_balance {
            width: 150px;
        }
        .wallet-balance-display .balance-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        .osk-wallet-dashboard {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .wallet-checkout-info {
            background: #e7f5ff;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .osk-wallet-thankyou {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
    <?php
}

// ====================
// SESSION MANAGEMENT FOR WALLET (CRITICAL - THIS WAS MISSING!)
// ====================

// Ensure WooCommerce session is started
add_action('init', 'osk_wallet_start_session', 1);

function osk_wallet_start_session() {
    // Start session if not started
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    // Initialize WooCommerce session
    if (function_exists('WC') && class_exists('WooCommerce')) {
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }
}

// Clean up wallet session on logout
add_action('wp_logout', 'osk_wallet_clear_session');

function osk_wallet_clear_session() {
    if (isset(WC()->session)) {
        WC()->session->set('wallet_topup_amount', null);
    }
    
    // Clear any wallet-related session variables
    if (session_id()) {
        unset($_SESSION['wallet_topup_amount']);
    }
}

// ====================
// IMPORTANT: Create wallet top-up product on activation
// ====================

register_activation_hook(__FILE__, 'osk_wallet_create_topup_product');

function osk_wallet_create_topup_product() {
    // Include the product creation file
    if (file_exists(OSK_WALLET_PATH . 'includes/create-product.php')) {
        require_once OSK_WALLET_PATH . 'includes/create-product.php';
        
        // Call the function to create the product
        if (function_exists('osk_wallet_create_default_topup_product')) {
            osk_wallet_create_default_topup_product();
        }
    }
}
