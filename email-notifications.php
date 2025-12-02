<?php
// Email notification functions

function osk_wallet_send_email($user_id, $type, $data = array()) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $to = $user->user_email;
    $subject = '';
    $message = '';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    switch ($type) {
        case 'payment_made':
            $subject = sprintf(__('Wallet Payment for Order #%s', 'ordersoftwarekeys-wallet'), $data['order_id']);
            $message = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #28a745;">' . __('Wallet Payment Confirmation', 'ordersoftwarekeys-wallet') . '</h2>
                    <p>' . sprintf(__('Hello %s,', 'ordersoftwarekeys-wallet'), $user->display_name) . '</p>
                    <p>' . sprintf(__('A payment of <strong>%s</strong> has been made from your wallet for order #%s.', 'ordersoftwarekeys-wallet'), wc_price($data['amount']), $data['order_id']) . '</p>
                    <p>' . sprintf(__('Your new wallet balance is: <strong>%s</strong>', 'ordersoftwarekeys-wallet'), wc_price($data['new_balance'])) . '</p>
                    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h3 style="margin-top: 0;">' . __('Transaction Details', 'ordersoftwarekeys-wallet') . '</h3>
                        <p><strong>' . __('Order ID:', 'ordersoftwarekeys-wallet') . '</strong> ' . $data['order_id'] . '</p>
                        <p><strong>' . __('Amount Deducted:', 'ordersoftwarekeys-wallet') . '</strong> ' . wc_price($data['amount']) . '</p>
                        <p><strong>' . __('Remaining Balance:', 'ordersoftwarekeys-wallet') . '</strong> ' . wc_price($data['new_balance']) . '</p>
                    </div>
                    <p>' . __('Thank you for your purchase!', 'ordersoftwarekeys-wallet') . '</p>
                    <p>' . get_bloginfo('name') . '</p>
                </div>
            </body>
            </html>';
            break;
            
        case 'refund_received':
            $subject = sprintf(__('Refund to Your Wallet for Order #%s', 'ordersoftwarekeys-wallet'), $data['order_id']);
            $message = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #17a2b8;">' . __('Refund Received', 'ordersoftwarekeys-wallet') . '</h2>
                    <p>' . sprintf(__('Hello %s,', 'ordersoftwarekeys-wallet'), $user->display_name) . '</p>
                    <p>' . sprintf(__('A refund of <strong>%s</strong> has been added to your wallet for order #%s.', 'ordersoftwarekeys-wallet'), wc_price($data['amount']), $data['order_id']) . '</p>
                    <p>' . sprintf(__('Your new wallet balance is: <strong>%s</strong>', 'ordersoftwarekeys-wallet'), wc_price($data['new_balance'])) . '</p>
                    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h3 style="margin-top: 0;">' . __('Refund Details', 'ordersoftwarekeys-wallet') . '</h3>
                        <p><strong>' . __('Order ID:', 'ordersoftwarekeys-wallet') . '</strong> ' . $data['order_id'] . '</p>
                        <p><strong>' . __('Refund Amount:', 'ordersoftwarekeys-wallet') . '</strong> ' . wc_price($data['amount']) . '</p>
                        <p><strong>' . __('New Balance:', 'ordersoftwarekeys-wallet') . '</strong> ' . wc_price($data['new_balance']) . '</p>
                    </div>
                    <p>' . __('Thank you for your business!', 'ordersoftwarekeys-wallet') . '</p>
                    <p>' . get_bloginfo('name') . '</p>
                </div>
            </body>
            </html>';
            break;
            
        case 'low_balance':
            $subject = __('Low Wallet Balance Alert', 'ordersoftwarekeys-wallet');
            $message = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #ffc107;">' . __('Low Balance Alert', 'ordersoftwarekeys-wallet') . '</h2>
                    <p>' . sprintf(__('Hello %s,', 'ordersoftwarekeys-wallet'), $user->display_name) . '</p>
                    <p>' . sprintf(__('Your wallet balance is running low. Current balance: <strong>%s</strong>', 'ordersoftwarekeys-wallet'), wc_price($data['balance'])) . '</p>
                    <p>' . __('We recommend adding more funds to your wallet for future purchases.', 'ordersoftwarekeys-wallet') . '</p>
                    <div style="text-align: center; margin: 25px 0;">
                        <a href="' . wc_get_account_endpoint_url('wallet-topup') . '" style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                            ' . __('Add Funds Now', 'ordersoftwarekeys-wallet') . '
                        </a>
                    </div>
                    <p>' . get_bloginfo('name') . '</p>
                </div>
            </body>
            </html>';
            break;
            
        case 'funds_added':
            $subject = __('Funds Added to Your Wallet', 'ordersoftwarekeys-wallet');
            $message = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h2 style="color: #28a745;">' . __('Funds Added to Wallet', 'ordersoftwarekeys-wallet') . '</h2>
                    <p>' . sprintf(__('Hello %s,', 'ordersoftwarekeys-wallet'), $user->display_name) . '</p>
                    <p>' . sprintf(__('<strong>%s</strong> has been added to your wallet.', 'ordersoftwarekeys-wallet'), wc_price($data['amount'])) . '</p>';
    
            if (!empty($data['note'])) {
                $message .= '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p><strong>' . __('Note:', 'ordersoftwarekeys-wallet') . '</strong> ' . esc_html($data['note']) . '</p>
                            </div>';
            }
    
            $message .= '<p>' . __('Thank you!', 'ordersoftwarekeys-wallet') . '</p>
                    <p>' . get_bloginfo('name') . '</p>
                </div>
            </body>
            </html>';
            break;
    }
    
    if ($subject && $message) {
        // Use WooCommerce email system if available
        if (function_exists('wc_mail')) {
            return wc_mail($to, $subject, $message, $headers);
        } else {
            return wp_mail($to, $subject, $message, $headers);
        }
    }
    
    return false;
}

// Send email when funds are added via admin
add_action('osk_wallet_after_balance_update', 'osk_wallet_send_funds_added_email', 10, 3);

function osk_wallet_send_funds_added_email($user_id, $amount, $note = '') {
    // Just show the amount added, no balance calculation
    osk_wallet_send_email($user_id, 'funds_added', array(
        'amount' => $amount,
        'note' => $note
    ));
}

// Check for low balance on order completion
add_action('woocommerce_order_status_completed', 'osk_wallet_check_low_balance');

function osk_wallet_check_low_balance($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    
    if ($user_id && $order->get_payment_method() === 'ordersoftwarekeys_wallet') {
        $balance = floatval(get_user_meta($user_id, 'wallet_balance', true));
        $threshold = floatval(get_option('osk_wallet_low_balance', 10));
        
        if ($balance < $threshold) {
            osk_wallet_send_email($user_id, 'low_balance', array(
                'balance' => $balance
            ));
        }
    }
}

// Send email when admin adds funds from user profile
add_action('osk_wallet_save_user_profile_fields', 'osk_wallet_send_admin_funds_email', 10, 4);

function osk_wallet_send_admin_funds_email($user_id, $amount, $type, $note) {
    if ($type === 'add') {
        // Just send the amount added, no balance calculation
        osk_wallet_send_email($user_id, 'funds_added', array(
            'amount' => $amount,
            'note' => $note
        ));
    }
}
