<?php
// Admin dashboard functions

function osk_wallet_admin_dashboard() {
    ?>
    <div class="wrap osk-wallet-dashboard">
        <h1><?php _e('Ordersoftwarekeys Wallet System', 'ordersoftwarekeys-wallet'); ?></h1>
        
        <?php
        // Handle bulk actions
        if (isset($_GET['bulk_action']) && isset($_GET['user_ids'])) {
            $user_ids = explode(',', $_GET['user_ids']);
            $action = $_GET['bulk_action'];
            ?>
            <div class="bulk-action-form">
                <h2><?php 
                    echo $action === 'add_wallet_funds' ? 
                    __('Add Funds to Selected Users', 'ordersoftwarekeys-wallet') : 
                    __('Deduct Funds from Selected Users', 'ordersoftwarekeys-wallet');
                ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('osk_wallet_bulk_action', 'osk_wallet_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
                    <input type="hidden" name="user_ids" value="<?php echo esc_attr($_GET['user_ids']); ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="amount"><?php _e('Amount', 'ordersoftwarekeys-wallet'); ?></label></th>
                            <td>
                                <input type="number" step="0.01" name="amount" id="amount" required style="width: 200px;">
                                <?php echo get_woocommerce_currency_symbol(); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="note"><?php _e('Note/Reason', 'ordersoftwarekeys-wallet'); ?></label></th>
                            <td>
                                <textarea name="note" id="note" rows="3" style="width: 100%;"></textarea>
                                <p class="description"><?php _e('This note will be recorded in transaction history.', 'ordersoftwarekeys-wallet'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Process Bulk Update', 'ordersoftwarekeys-wallet'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=ordersoftwarekeys-wallet'); ?>" class="button"><?php _e('Cancel', 'ordersoftwarekeys-wallet'); ?></a>
                    </p>
                </form>
            </div>
            <?php
        } else {
            // Show quick stats
            $total_users = count_users();
            $total_balance = osk_wallet_get_total_balance();
            ?>
            
            <div class="osk-wallet-stats">
                <div class="stat-box">
                    <h3><?php _e('Total Wallet Balance', 'ordersoftwarekeys-wallet'); ?></h3>
                    <p class="stat-number"><?php echo wc_price($total_balance); ?></p>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Users with Wallet', 'ordersoftwarekeys-wallet'); ?></h3>
                    <p class="stat-number"><?php echo $total_users['total_users']; ?></p>
                </div>
                <div class="stat-box">
                    <h3><?php _e('Today\'s Transactions', 'ordersoftwarekeys-wallet'); ?></h3>
                    <p class="stat-number"><?php echo osk_wallet_get_todays_transactions_count(); ?></p>
                </div>
            </div>
            
            <div class="osk-wallet-quick-actions">
                <h2><?php _e('Quick Actions', 'ordersoftwarekeys-wallet'); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('users.php'); ?>" class="button button-primary">
                        <?php _e('Manage User Wallets', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=ordersoftwarekeys-wallet-balances'); ?>" class="button">
                        <?php _e('View All Balances', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=ordersoftwarekeys-wallet-transactions'); ?>" class="button">
                        <?php _e('Transaction History', 'ordersoftwarekeys-wallet'); ?>
                    </a>
                </div>
            </div>
            
            <div class="osk-wallet-recent-transactions">
                <h2><?php _e('Recent Wallet Transactions', 'ordersoftwarekeys-wallet'); ?></h2>
                <?php osk_wallet_display_recent_transactions(); ?>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}

function osk_wallet_balances_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('All User Wallet Balances', 'ordersoftwarekeys-wallet'); ?></h1>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('users.php'); ?>" class="button">
                    <?php _e('Go to Users List', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('User ID', 'ordersoftwarekeys-wallet'); ?></th>
                    <th><?php _e('Username', 'ordersoftwarekeys-wallet'); ?></th>
                    <th><?php _e('Email', 'ordersoftwarekeys-wallet'); ?></th>
                    <th><?php _e('Wallet Balance', 'ordersoftwarekeys-wallet'); ?></th>
                    <th><?php _e('Last Transaction', 'ordersoftwarekeys-wallet'); ?></th>
                    <th><?php _e('Actions', 'ordersoftwarekeys-wallet'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $users = get_users(array('number' => 50));
                foreach ($users as $user) {
                    $balance = get_user_meta($user->ID, 'wallet_balance', true);
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
                    // Note: 0 balance keeps default color (grey) and normal weight
                    
                    // Get last transaction
                    $transactions = get_user_meta($user->ID, 'wallet_transactions', true);
                    $last_transaction = is_array($transactions) ? end($transactions) : null;
                    ?>
                    <tr>
                        <td><?php echo $user->ID; ?></td>
                        <td><?php echo $user->user_login; ?></td>
                        <td><?php echo $user->user_email; ?></td>
                        <td>
                            <span style="color: <?php echo $color; ?>; font-weight: <?php echo $font_weight; ?>;">
                                <?php echo wc_price($balance); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($last_transaction) {
                                echo date_i18n(get_option('date_format'), strtotime($last_transaction['time']));
                            } else {
                                _e('No transactions', 'ordersoftwarekeys-wallet');
                            } ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url("user-edit.php?user_id={$user->ID}#wallet_balance"); ?>" class="button button-small">
                                <?php _e('Edit', 'ordersoftwarekeys-wallet'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

function osk_wallet_transactions_page() {
    // This would show all wallet transactions across all users
    // Implementation depends on your specific needs
    ?>
    <div class="wrap">
        <h1><?php _e('Wallet Transactions', 'ordersoftwarekeys-wallet'); ?></h1>
        <p><?php _e('Transaction history page coming soon.', 'ordersoftwarekeys-wallet'); ?></p>
    </div>
    <?php
}

function osk_wallet_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Wallet System Settings', 'ordersoftwarekeys-wallet'); ?></h1>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('osk_wallet_settings');
            do_settings_sections('osk_wallet_settings');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="min_balance"><?php _e('Minimum Balance', 'ordersoftwarekeys-wallet'); ?></label>
                    </th>
                    <td>
                        <input type="number" step="0.01" name="osk_wallet_min_balance" id="min_balance" 
                               value="<?php echo get_option('osk_wallet_min_balance', 0); ?>" style="width: 200px;">
                        <?php echo get_woocommerce_currency_symbol(); ?>
                        <p class="description"><?php _e('Minimum allowed wallet balance (can go negative if set to negative value)', 'ordersoftwarekeys-wallet'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max_balance"><?php _e('Maximum Balance', 'ordersoftwarekeys-wallet'); ?></label>
                    </th>
                    <td>
                        <input type="number" step="0.01" name="osk_wallet_max_balance" id="max_balance" 
                               value="<?php echo get_option('osk_wallet_max_balance', 10000); ?>" style="width: 200px;">
                        <?php echo get_woocommerce_currency_symbol(); ?>
                        <p class="description"><?php _e('Maximum allowed wallet balance (0 for no limit)', 'ordersoftwarekeys-wallet'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="low_balance_threshold"><?php _e('Low Balance Threshold', 'ordersoftwarekeys-wallet'); ?></label>
                    </th>
                    <td>
                        <input type="number" step="0.01" name="osk_wallet_low_balance" id="low_balance_threshold" 
                               value="<?php echo get_option('osk_wallet_low_balance', 10); ?>" style="width: 200px;">
                        <?php echo get_woocommerce_currency_symbol(); ?>
                        <p class="description"><?php _e('Send low balance notification when below this amount', 'ordersoftwarekeys-wallet'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function osk_wallet_get_total_balance() {
    global $wpdb;
    $total = $wpdb->get_var(
        "SELECT SUM(meta_value) FROM $wpdb->usermeta WHERE meta_key = 'wallet_balance'"
    );
    return floatval($total);
}

function osk_wallet_get_todays_transactions_count() {
    // Simplified implementation
    return 0;
}

function osk_wallet_display_recent_transactions() {
    // Simplified implementation
    echo '<p>' . __('No recent transactions.', 'ordersoftwarekeys-wallet') . '</p>';
}
