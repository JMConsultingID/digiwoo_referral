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
        function set_ref_id_in_session() {
            if (isset($_GET['_ref'])) {
                $ref_id = sanitize_text_field($_GET['_ref']);
                if (isset($_COOKIE[REF_COOKIE]) && $_COOKIE[REF_COOKIE] !== $ref_id) {
                    setcookie(REF_COOKIE, '', time() - 3600, "/", "", is_ssl(), true);
                }

                if (!isset($_COOKIE[REF_COOKIE])) {
                    WC()->session->set('ref_id', $ref_id);                
                    $cookie_duration = get_option('digiwoo_cookie_duration', 365);
                    $cookie_expiry = time() + ($cookie_duration * 24 * 60 * 60); 
                    setcookie(REF_COOKIE, $ref_id, $cookie_expiry, "/", "", is_ssl(), true);
                }
            }
        }
        add_action('init', 'set_ref_id_in_session', 10);

        // 1. Set the Referral ID in WooCommerce Session and Cookies
        function set_lid_id_in_session() {
            if (isset($_GET['lid'])) {
                $lid_id = sanitize_text_field($_GET['lid']);
                if (isset($_COOKIE[LID_COOKIE]) && $_COOKIE[LID_COOKIE] !== $lid_id) {
                    setcookie(LID_COOKIE, '', time() - 3600, "/", "", is_ssl(), true);
                }

                if (!isset($_COOKIE[LID_COOKIE])) {
                    WC()->session->set('lid_id', $lid_id);                
                    $cookie_duration = get_option('digiwoo_cookie_duration', 365);
                    $cookie_expiry = time() + ($cookie_duration * 24 * 60 * 60); 
                    setcookie(LID_COOKIE, $lid_id, $cookie_expiry, "/", "", is_ssl(), true);
                }
            }
        }
        add_action('init', 'set_lid_id_in_session', 10);

        // 2. Capture the Referral ID from the URL
        function get_referral_id_from_url() {
            if (isset($_COOKIE[REF_COOKIE]) && !empty($_COOKIE[REF_COOKIE])) {
                return sanitize_text_field( $_COOKIE[REF_COOKIE] ); 
            } elseif( isset($_GET['_ref']) && !empty($_GET['_ref'])) {
                return sanitize_text_field( $_GET['_ref'] );
            }
            return '';
        }

        // 2. Capture the Referral ID from the URL
        function get_referral_lid_from_url() {
            if (isset($_COOKIE[LID_COOKIE]) && !empty($_COOKIE[LID_COOKIE])) {
                return sanitize_text_field( $_COOKIE[LID_COOKIE] ); 
            } elseif( isset($_GET['lid']) && !empty($_GET['lid'])) {
                return sanitize_text_field( $_GET['lid'] );
            }
            return '';
        }

        // 3. Add Hidden Field to WooCommerce Checkout
        function add_hidden_referral_field_to_checkout( $checkout ) {
            $referral_id = get_referral_id_from_url();
            $referral_lid = get_referral_lid_from_url();

            // Add a hidden field to the checkout
            woocommerce_form_field( '_ref', array(
                'type'          => 'hidden',
                'class'         => array('referral-id-hidden-field'),
                'label_class'   => array('hidden'),
                'input_class'   => array('hidden'),
            ), $referral_id );

            // Add a hidden field to the checkout
            woocommerce_form_field( '_lid', array(
                'type'          => 'hidden',
                'class'         => array('referral-lid-hidden-field'),
                'label_class'   => array('hidden'),
                'input_class'   => array('hidden'),
            ), $referral_lid );
        }
        add_action('woocommerce_after_checkout_billing_form', 'add_hidden_referral_field_to_checkout');

        // 4. Save Referral ID as Order Meta
        function save_referral_id_in_order_meta( $order_id ) {
            if( !empty($_POST['_ref']) ) {
                $ref_id = $_POST['_ref'];
                update_post_meta( $order_id, 'referral_id_order', $ref_id );
            }
            if( !empty($_POST['_lid']) ) {
                $lid_id = $_POST['_lid'];
                update_post_meta( $order_id, 'referral_lid_order', $lid_id );
            }
        }
        add_action('woocommerce_checkout_update_order_meta', 'save_referral_id_in_order_meta');

    }

}
?>
