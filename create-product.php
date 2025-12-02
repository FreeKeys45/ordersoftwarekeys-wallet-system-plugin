<?php
/**
 * Create Default Top-up Product
 * File: includes/create-product.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Function to create the default top-up product
function osk_wallet_create_topup_product_now() {
    // Check if we're in admin and have permission
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if default product already exists
    $existing_product = get_posts(array(
        'post_type' => 'product',
        'meta_key' => '_is_default_wallet_topup',
        'meta_value' => 'yes',
        'posts_per_page' => 1
    ));
    
    if (!empty($existing_product)) {
        return; // Product already exists
    }
    
    // Create the product
    $product = new WC_Product();
    
    // Basic product details
    $product->set_name(__('Wallet Top-up', 'ordersoftwarekeys-wallet'));
    $product->set_slug('wallet-top-up-' . time()); // Unique slug
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_description(__('Add funds to your wallet to make purchases faster and easier.', 'ordersoftwarekeys-wallet'));
    $product->set_short_description(__('Add funds to your wallet.', 'ordersoftwarekeys-wallet'));
    $product->set_regular_price('0');
    $product->set_price('0');
    $product->set_sold_individually(true);
    $product->set_virtual(true);
    $product->set_downloadable(true);
    $product->set_tax_status('none');
    $product->set_manage_stock(false);
    
    // Set as simple_wallet_topup type
    wp_set_object_terms($product->get_id(), 'simple_wallet_topup', 'product_type');
    
    // Save product
    $product_id = $product->save();
    
    if ($product_id && !is_wp_error($product_id)) {
        // Set default meta values
        update_post_meta($product_id, '_simple_topup_min_amount', 1);
        update_post_meta($product_id, '_simple_topup_max_amount', 1000);
        update_post_meta($product_id, '_simple_topup_default_amount', 10);
        update_post_meta($product_id, '_is_default_wallet_topup', 'yes');
        
        // Set additional product settings
        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_downloadable', 'yes');
        update_post_meta($product_id, '_sold_individually', 'yes');
        update_post_meta($product_id, '_backorders', 'no');
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_tax_status', 'none');
        update_post_meta($product_id, '_tax_class', '');
        
        return $product_id;
    }
    
    return false;
}

// Add admin notice to create product
add_action('admin_notices', 'osk_wallet_admin_notice_create_product');

function osk_wallet_admin_notice_create_product() {
    // Check if default product exists
    $existing_product = get_posts(array(
        'post_type' => 'product',
        'meta_key' => '_is_default_wallet_topup',
        'meta_value' => 'yes',
        'posts_per_page' => 1
    ));
    
    if (empty($existing_product)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php _e('Ordersoftwarekeys Wallet:', 'ordersoftwarekeys-wallet'); ?></strong>
                <?php _e('The wallet top-up product needs to be created.', 'ordersoftwarekeys-wallet'); ?>
                <a href="<?php echo admin_url('admin.php?page=ordersoftwarekeys-wallet&create_topup_product=1'); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php _e('Create Top-up Product Now', 'ordersoftwarekeys-wallet'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}

// Handle product creation from admin
add_action('admin_init', 'osk_wallet_handle_product_creation');

function osk_wallet_handle_product_creation() {
    if (isset($_GET['create_topup_product']) && $_GET['create_topup_product'] == '1' && current_user_can('manage_options')) {
        $product_id = osk_wallet_create_topup_product_now();
        
        if ($product_id) {
            // Redirect with success message
            wp_redirect(admin_url('admin.php?page=ordersoftwarekeys-wallet&product_created=1'));
            exit;
        } else {
            // Redirect with error message
            wp_redirect(admin_url('admin.php?page=ordersoftwarekeys-wallet&product_error=1'));
            exit;
        }
    }
    
    // Show success/error messages
    if (isset($_GET['product_created']) && $_GET['product_created'] == '1') {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('✅ Wallet top-up product created successfully!', 'ordersoftwarekeys-wallet'); ?></p>
            </div>
            <?php
        });
    }
    
    if (isset($_GET['product_error']) && $_GET['product_error'] == '1') {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('❌ Error creating wallet top-up product. Please try again.', 'ordersoftwarekeys-wallet'); ?></p>
            </div>
            <?php
        });
    }
}

// Force create product on plugin activation
register_activation_hook(__FILE__, 'osk_wallet_force_create_product');

function osk_wallet_force_create_product() {
    // Schedule product creation for next page load
    update_option('osk_wallet_create_product_on_next_load', 'yes');
}

// Check and create product on admin load
add_action('admin_init', 'osk_wallet_check_and_create_product');

function osk_wallet_check_and_create_product() {
    if (get_option('osk_wallet_create_product_on_next_load') === 'yes') {
        osk_wallet_create_topup_product_now();
        delete_option('osk_wallet_create_product_on_next_load');
    }
}

// Create product via AJAX
add_action('wp_ajax_osk_wallet_create_product', 'osk_wallet_ajax_create_product');

function osk_wallet_ajax_create_product() {
    // Check nonce for security
    if (!check_ajax_referer('osk_wallet_nonce', 'security', false)) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $product_id = osk_wallet_create_topup_product_now();
    
    if ($product_id) {
        wp_send_json_success(array(
            'message' => __('Product created successfully!', 'ordersoftwarekeys-wallet'),
            'product_id' => $product_id,
            'edit_url' => admin_url('post.php?post=' . $product_id . '&action=edit')
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Failed to create product.', 'ordersoftwarekeys-wallet')
        ));
    }
}

// Add quick create button to admin dashboard
add_action('osk_wallet_admin_dashboard_top', 'osk_wallet_add_product_create_button');

function osk_wallet_add_product_create_button() {
    // Check if default product exists
    $existing_product = get_posts(array(
        'post_type' => 'product',
        'meta_key' => '_is_default_wallet_topup',
        'meta_value' => 'yes',
        'posts_per_page' => 1
    ));
    
    if (empty($existing_product)) {
        ?>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 0 4px 4px 0;">
            <h3 style="margin-top: 0; color: #856404;"><?php _e('Action Required', 'ordersoftwarekeys-wallet'); ?></h3>
            <p style="color: #856404; margin-bottom: 15px;">
                <?php _e('The wallet top-up product needs to be created before customers can add funds.', 'ordersoftwarekeys-wallet'); ?>
            </p>
            <button id="create-topup-product" class="button button-primary" style="background: #28a745; border-color: #28a745;">
                <?php _e('🚀 Create Top-up Product Now', 'ordersoftwarekeys-wallet'); ?>
            </button>
            <span id="creating-product" style="display: none; margin-left: 10px; color: #28a745;">
                <?php _e('Creating product...', 'ordersoftwarekeys-wallet'); ?>
            </span>
            <span id="product-created" style="display: none; margin-left: 10px; color: #28a745;">
                <?php _e('✅ Product created!', 'ordersoftwarekeys-wallet'); ?>
            </span>
            
            <script>
            jQuery(document).ready(function($) {
                $('#create-topup-product').click(function() {
                    $(this).prop('disabled', true);
                    $('#creating-product').show();
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'osk_wallet_create_product',
                            security: '<?php echo wp_create_nonce('osk_wallet_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#creating-product').hide();
                            if (response.success) {
                                $('#product-created').show();
                                $('#create-topup-product').hide();
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                alert('Error: ' + response.data.message);
                                $('#create-topup-product').prop('disabled', false);
                            }
                        },
                        error: function() {
                            $('#creating-product').hide();
                            alert('<?php _e('An error occurred. Please try again.', 'ordersoftwarekeys-wallet'); ?>');
                            $('#create-topup-product').prop('disabled', false);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
}

// Manual product creation via direct call
function osk_wallet_manual_create_product() {
    if (isset($_GET['manual_create_product']) && $_GET['manual_create_product'] == '1' && current_user_can('manage_options')) {
        $product_id = osk_wallet_create_topup_product_now();
        
        if ($product_id) {
            echo '<div class="notice notice-success"><p>';
            printf(__('✅ Product created successfully! Product ID: %s', 'ordersoftwarekeys-wallet'), $product_id);
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>';
            _e('❌ Failed to create product.', 'ordersoftwarekeys-wallet');
            echo '</p></div>';
        }
    }
}

// Add direct creation link for testing
add_action('admin_notices', 'osk_wallet_manual_create_product');
