<?php
/**
 * Plugin Name:       DigiWoo Referral for WooCommerce
 * Plugin URI:        https://fundscap.com/
 * Description:       A simple referral system for WooCommerce.
 * Version:           1.0.1
 * Author:            Ardi JM Consulting
 * Author URI:        https://fundscap.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       digiwoo_referral
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {

    // Plugin activation
    register_activation_hook(__FILE__, 'digiwoo_referral_activate');
    function digiwoo_referral_activate() {
        update_option('digiwoo_referral_enabled', 'yes');
    }

    // Plugin deactivation
    register_deactivation_hook(__FILE__, 'digiwoo_referral_deactivate');
    function digiwoo_referral_deactivate() {
        delete_option('digiwoo_referral_enabled');
    }

    // Add settings link to plugins page
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'digiwoo_referral_settings_link');
    function digiwoo_referral_settings_link($links) {
        $settings_link = '<a href="admin.php?page=digiwoo_referral">Settings</a>';
        array_push($links, $settings_link);
        return $links;
    }

    // Add settings page
    add_action('admin_menu', 'digiwoo_referral_add_admin_menu');
    function digiwoo_referral_add_admin_menu() {
        add_menu_page('DigiWoo Referral', 'DigiWoo Referral', 'manage_options', 'digiwoo_referral', 'digiwoo_referral_settings_page');
    }

    function digiwoo_referral_settings_page() {
        if (isset($_POST['digiwoo_referral_status'])) {
            update_option('digiwoo_referral_enabled', sanitize_text_field($_POST['digiwoo_referral_status']));
            update_option('digiwoo_cookie_duration', intval($_POST['digiwoo_cookie_duration']));
        }

        $current_status = get_option('digiwoo_referral_enabled', 'no');
        $cookie_duration = get_option('digiwoo_cookie_duration', 365);  // Defaulting to 365 days if not set
        ?>
        <div class="wrap">
            <h2>DigiWoo Referral Settings</h2>
            <form method="post">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Referral System Status</th>
                        <td>
                            <select name="digiwoo_referral_status">
                                <option value="yes" <?php selected($current_status, 'yes'); ?>>Enable</option>
                                <option value="no" <?php selected($current_status, 'no'); ?>>Disable</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Cookie Duration (Days)</th>
                        <td>
                            <input type="number" name="digiwoo_cookie_duration" value="<?php echo esc_attr($cookie_duration); ?>" min="1">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    if (get_option('digiwoo_referral_enabled') === 'yes') {
        // 1. Capture the Referral ID from the URL
        function get_referral_id_from_url() {
            if( isset($_GET['_ref']) ) {
                return sanitize_text_field( $_GET['_ref'] );
            }
            return '';
        }

        // 2. Set the Referral ID in WooCommerce Session
        function set_ref_id_in_session() {
            if (isset($_GET['_ref'])) {
                WC()->session->set('ref_id', sanitize_text_field($_GET['_ref']));
                error_log("Session set: " . WC()->session->get('ref_id'));  // This logs the session value, you can check this in wp-content/debug.log
                // Set a cookie based on the duration set in the settings
                setcookie('used_ref_id', $_GET['_ref'], time() + 3600, "/", "", is_ssl(), true);
            }
        }
        add_action('init', 'set_ref_id_in_session', 10);


        // 3. Add Hidden Field to WooCommerce Checkout
        function add_hidden_referral_field_to_checkout( $checkout ) {
            $referral_id = get_referral_id_from_url();

            // Add a hidden field to the checkout
            woocommerce_form_field( '_ref', array(
                'type'          => 'hidden',
                'class'         => array('referral-id-hidden-field'),
                'label_class'   => array('hidden'),
                'input_class'   => array('hidden'),
            ), $referral_id );
        }
        add_action('woocommerce_after_checkout_billing_form', 'add_hidden_referral_field_to_checkout');

        // 4. Save Referral ID as Order Meta
        function save_referral_id_in_order_meta( $order_id ) {
            if( !empty($_POST['_ref']) ) {
                update_post_meta( $order_id, 'referral_id_order', sanitize_text_field($_POST['_ref']) );
            }
        }
        add_action('woocommerce_checkout_update_order_meta', 'save_referral_id_in_order_meta');

        // 5. Save the Referral ID to User Meta Upon Order Completion
        function save_ref_id_actions_after_completion( $order_id ) {
            // Save ref_id to user meta
            if ( WC()->session->__isset('ref_id') ) {
                $order = wc_get_order( $order_id );
                $user_id = $order->get_user_id();
                
                if ( $user_id ) {
                    $ref_id = WC()->session->get('ref_id');
                    update_user_meta($user_id, 'referral_id_completed', $ref_id);
                    update_post_meta( $order_id, 'referral_id_completed', $ref_id);
                }
            }

            // Set a cookie based on ref_id
            if ( WC()->session->__isset('ref_id') ) {
                $ref_id = WC()->session->get('ref_id');
                $cookie_duration = get_option('digiwoo_cookie_duration', 365); // Defaulting to 365 days if not set
                $cookie_expiry = time() + ($cookie_duration * 24 * 60 * 60); 

                // Set a cookie based on the duration set in the settings
                setcookie('used_ref_id', $ref_id, $cookie_expiry, "/", "", is_ssl(), true);
                error_log("Cookie set: used_ref_id with value " . $ref_id);  // This logs the cookie value, you can check this in wp-content/debug.log
            }
        }

        add_action('woocommerce_order_status_completed', 'save_ref_id_actions_after_completion');

 
        // 6. Checking the Cookie on Checkout
        function check_ref_cookie_on_checkout() {
            if (isset($_GET['_ref']) && isset($_COOKIE['used_ref_id'])) {
                $ref_id = sanitize_text_field($_GET['_ref']);
                if ($_COOKIE['used_ref_id'] == $ref_id) {                    
                    // Add a notice to inform the user why the checkout is disabled
                    wc_add_notice( __( 'Checkout is disabled because you have already used this referral ID.', 'woocommerce' ), 'error' );
                    inject_disable_checkout_script();

                }
            }
        }
        add_action('woocommerce_before_checkout_form', 'check_ref_cookie_on_checkout');

        function inject_disable_checkout_script() {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Hide the payment section
                    jQuery('.woocommerce-checkout-payment').attr('style', 'display: none !important;');
                    jQuery('.wc_payment_methods').attr('style', 'display: none !important;');

                    if (jQuery('.woocommerce-checkout').length) {
                        // Disable the form inputs, textareas, and buttons
                       jQuery('form.checkout.woocommerce-checkout.sellkit-checkout-virtual-session').find('input, textarea, button').prop('disabled', true);
                    }
                });
            </script>
            <?php
        }
        

    }

}
?>
