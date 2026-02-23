<?php
/**
 * Plugin Name: Order Print Invoice & Gift Message
 * Description: Adds functionality to print order details and custom gift messages from the admin. Major uppdate to taxes and single pdf generation
 * Version: 3.0.5
 * Author: Aliyan Faisal
 * Author URI: https://aliyanfaisal.com
 * Text Domain: afb-offcanvas
 * Domain Path: /languages
 */


if (!defined('ABSPATH')) {
    exit;
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

define('OPIGM_VERSION', '1.0.0');
define('OPIGM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPIGM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Manual Tax Constants
define('OPIGM_VAT_RATE', 0.18);
define('OPIGM_VAT_LABEL', 'VAT 18%');


if (file_exists(OPIGM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once OPIGM_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once OPIGM_PLUGIN_DIR . 'includes/class-utils.php';
require_once OPIGM_PLUGIN_DIR . 'includes/class-admin-ui.php';
require_once OPIGM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
require_once OPIGM_PLUGIN_DIR . 'includes/class-order-details-generator.php';

function opigm_init()
{
    new Opigm_Admin_UI();
    new Opigm_PDF_Generator();
    new Opigm_Order_Details_Generator();


    if (file_exists(OPIGM_PLUGIN_DIR . 'includes/invoice/class-invoice-generator.php')) {
        require_once OPIGM_PLUGIN_DIR . 'includes/invoice/class-invoice-generator.php';
    }

    if (file_exists(OPIGM_PLUGIN_DIR . 'includes/invoice/class-invoice-integration.php')) {
        require_once OPIGM_PLUGIN_DIR . 'includes/invoice/class-invoice-integration.php';
        new Opigm_Invoice_Integration();
    }
}
add_action('plugins_loaded', 'opigm_init');

// Auto-check "Prepared" when order is completed
// Hooking into general order update to catch programmatic changes too
$opigm_auto_prepared_check = function ($order_id) {
    if (!$order_id)
        return;

    // Check if we are in an infinite loop
    if (did_action('opigm_checking_prepared_status') > 1)
        return;

    $order = wc_get_order($order_id);
    if (!$order)
        return;

    if ($order->get_status() === 'completed') {
 
        $is_prepared = $order->get_meta('_opigm_is_prepared');
        if ($is_prepared !== 'yes') {
          
            do_action('opigm_checking_prepared_status');
            $order->update_meta_data('_opigm_is_prepared', 'yes');
            $order->save();
        }
    }
};

add_action('woocommerce_order_status_completed', $opigm_auto_prepared_check);
// add_action('woocommerce_update_order', $opigm_auto_prepared_check);
// add_action('save_post_shop_order', $opigm_auto_prepared_check);









if (!function_exists('afb_get_store_info')) {
    function afb_get_store_info($raw)
    {
        $val = is_string($raw) ? trim($raw) : (string) $raw;
        $info = ['name' => '', 'address' => '', 'city' => '', 'phone' => ''];
        if ($val === '') {
            return $info;
        }
        if (ctype_digit($val)) {
            $uid = (int) $val;
            if ($uid > 0) {
                $user = get_user_by('id', $uid);
                if ($user) {
                    $info['name'] = $user->display_name ? $user->display_name : $user->user_login;
                    $info['address'] = (string) get_user_meta($uid, 'store_address', true);
                    $info['city'] = (string) get_user_meta($uid, 'store_city', true);
                    $info['phone'] = (string) get_user_meta($uid, 'store_phone', true);
                }
            }
        } else {
            $info['name'] = $val;
        }
        return $info;
    }
}