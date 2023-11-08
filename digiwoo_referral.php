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
            update_option('digiwoo_cookie_enable', sanitize_text_field($_POST['digiwoo_cookie_enable']));
            update_option('digiwoo_cookie_duration', intval($_POST['digiwoo_cookie_duration']));
        }

        $current_status = get_option('digiwoo_referral_enabled', 'no');
        $cookie_enable = get_option('digiwoo_cookie_enable', 'no');
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
                        <th scope="row">Enable Cookies</th>
                        <td>
                            <select name="digiwoo_cookie_enable">
                                <option value="yes" <?php selected($cookie_enable, 'yes'); ?>>Enable</option>
                                <option value="no" <?php selected($cookie_enable, 'no'); ?>>Disable</option>
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
        define('REF_COOKIE', 'used_ref_id'); 
        define('LID_COOKIE', 'used_lid_id');  
        define('CID_COOKIE', 'used_cid_id');
        define('UTM_SOURCE_COOKIE', 'used_utm_source_id');
        define('UTM_MEDIUM_COOKIE', 'used_utm_medium_id');
        define('UTM_TERM_COOKIE', 'used_utm_term_id');
        define('UTM_CAMPAIGN_COOKIE', 'used_utm_campaign_id');
        define('UTM_CONTENT_COOKIE', 'used_utm_content_id');
    
        $cookie_enable = get_option('digiwoo_cookie_enable', 'no');
        if ($cookie_enable==='no') {
            return;
        }

        // 1. Set the Referral ID in WooCommerce Session and Cookies
        function set_marketing_params_in_session() {
            $marketing_params = [
                '_ref'         => REF_COOKIE,
                'utm_source'   => UTM_SOURCE_COOKIE,
                'utm_medium'   => UTM_MEDIUM_COOKIE,
                'utm_term'     => UTM_TERM_COOKIE,
                'utm_campaign' => UTM_CAMPAIGN_COOKIE,
                'utm_content'  => UTM_CONTENT_COOKIE,
                'cid'          => CID_COOKIE,
                'lid'          => LID_COOKIE
            ];

            $cookie_duration = get_option('digiwoo_cookie_duration', 365);
            $cookie_expiry = time() + ($cookie_duration * 24 * 60 * 60);

            foreach ($marketing_params as $urlParam => $cookieName) {
                if (isset($_GET[$urlParam])) {
                    $newValue = sanitize_text_field($_GET[$urlParam]);

                    // Set or update the cookie if the value is new or changed
                    if (!isset($_COOKIE[$cookieName]) || $_COOKIE[$cookieName] !== $newValue) {
                        setcookie($cookieName, $newValue, $cookie_expiry, "/", "", is_ssl(), true);
                    }
                }
            }
        }

        add_action('init', 'set_marketing_params_in_session', 10);



        // 2. Capture the Referral ID from the URL
        function get_marketing_params() {
            $params = [
                '_ref'         => REF_COOKIE,
                'utm_source'   => UTM_SOURCE_COOKIE,
                'utm_medium'   => UTM_MEDIUM_COOKIE,
                'utm_term'     => UTM_TERM_COOKIE,
                'utm_campaign' => UTM_CAMPAIGN_COOKIE,
                'utm_content'  => UTM_CONTENT_COOKIE,
                'cid'          => CID_COOKIE,
                'lid'          => LID_COOKIE
            ];

            $values = [];

            foreach ($params as $urlParam => $cookieName) {
                // Check the URL parameter first
                if (isset($_GET[$urlParam]) && !empty($_GET[$urlParam])) {
                    $values[$urlParam] = sanitize_text_field($_GET[$urlParam]);
                } elseif (isset($_COOKIE[$cookieName]) && !empty($_COOKIE[$cookieName])) {
                    // Fallback to cookie value if URL parameter isn't set
                    $values[$urlParam] = sanitize_text_field($_COOKIE[$cookieName]);
                } else {
                    $values[$urlParam] = '';
                }
            }

            return $values;
        }



        // 3. Add Hidden Field to WooCommerce Checkout
        function add_hidden_marketing_fields_to_checkout($checkout) {
            $marketing_params = get_marketing_params();

            foreach ($marketing_params as $param => $value) {
                woocommerce_form_field($param, array(
                    'type'          => 'hidden',
                    'class'         => array($param . '-hidden-field', 'form-hidden-field'),
                    'label_class'   => array('hidden'),
                    'input_class'   => array('hidden'),
                ), $value);
            }
        }

        add_action('woocommerce_after_checkout_billing_form', 'add_hidden_marketing_fields_to_checkout');



        // 4. Save Referral ID as Order Meta
        function save_marketing_params_in_order_meta( $order_id ) {
            $marketing_params = get_marketing_params();
            
            // Save the referral ID with a custom meta key
            if (!empty($marketing_params['_ref'])) {
                update_post_meta($order_id, 'referral_id_order', $marketing_params['_ref']);
            }

            // Save the UTM source with a different meta key
            if (!empty($marketing_params['lid'])) {
                update_post_meta($order_id, 'referral_lid_order', $marketing_params['lid']);
            }

            if (!empty($marketing_params['cid'])) {
                update_post_meta($order_id, 'referral_cid_order', $marketing_params['cid']);
            }

            if (!empty($marketing_params['utm_source'])) {
                update_post_meta($order_id, 'referral_utm_source_order', $marketing_params['utm_source']);
            }

            if (!empty($marketing_params['utm_medium'])) {
                update_post_meta($order_id, 'referral_utm_medium_order', $marketing_params['utm_medium']);
            }

            if (!empty($marketing_params['utm_term'])) {
                update_post_meta($order_id, 'referral_utm_term_order', $marketing_params['utm_term']);
            }

            if (!empty($marketing_params['utm_campaign'])) {
                update_post_meta($order_id, 'referral_utm_campaign_order', $marketing_params['utm_campaign']);
            }

            if (!empty($marketing_params['utm_content'])) {
                update_post_meta($order_id, 'referral_utm_content_order', $marketing_params['utm_content']);
            }
            
        }

        add_action('woocommerce_checkout_update_order_meta', 'save_marketing_params_in_order_meta');


    }

}
?>
