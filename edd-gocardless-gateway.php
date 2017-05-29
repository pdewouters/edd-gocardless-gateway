<?php
/*
Plugin Name: Easy Digital Downloads - GoCardless Gateway
Plugin URL: http://easydigitaldownloads.com/extension/gocardless-gateway
Description: A GoCardless gateway for Easy Digital Downloads
Version: 1.0.1
Author: Paul de Wouters
Author URI: http://wpconsult.net
Contributors: pauldewouters
*/

// plugin folder url
if (!defined('PDW_EDD_GC_PLUGIN_URL')) {
    define('PDW_EDD_GC_PLUGIN_URL', plugin_dir_url(__FILE__));
}
// plugin folder path
if (!defined('PDW_EDD_GC_PLUGIN_DIR')) {
    define('PDW_EDD_GC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
// plugin root file
if (!defined('PDW_EDD_GC_FILE')) {
    define('PDW_EDD_GC_FILE', __FILE__);
}

if( class_exists( 'EDD_License' ) ) {
    $eddgc_license = new EDD_License( __FILE__, 'GoCardless Gateway', '1.0.1', 'Paul de Wouters' );
}

// Include library - requires cURL on server
include_once PDW_EDD_GC_PLUGIN_DIR . 'gocardless-php/lib/GoCardless.php';

// registers the gateway
function pdw_edd_gc_register_gateway($gateways)
{
    $gateways['gocardless'] = array('admin_label' => 'GoCardless', 'checkout_label' => __('GoCardless', 'pw-edd-gc-locale'));
    return $gateways;
}

add_filter('edd_payment_gateways', 'pdw_edd_gc_register_gateway');

function pdw_edd_gc_gocardless_cc_form()
{
    // register the action to remove default CC form
    return;
}

add_action('edd_gocardless_cc_form', 'pdw_edd_gc_gocardless_cc_form');

// processes the payment
function pdw_edd_gc_process_payment($purchase_data)
{

    global $edd_options;

    /**********************************
     * set transaction mode
     **********************************/

    $account_details = pdw_edd_gc_get_credentials();

    // Fail nicely if no account details set
    if (!$account_details['app_id'] && !$account_details['app_secret']) {
        edd_set_error(0, __('You must enter your GoCardless credentials in settings', 'pw-edd-gc-locale'));
    }

    // check for any stored errors
    $errors = edd_get_errors();
    if (!$errors) {
        GoCardless::set_account_details($account_details);

        $purchase_summary = edd_get_purchase_summary($purchase_data);

        /**********************************
         * setup the payment details
         **********************************/

        $payment = array(
            'price' => $purchase_data['price'],
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => $edd_options['currency'],
            'downloads' => $purchase_data['downloads'],
            'cart_details' => $purchase_data['cart_details'],
            'user_info' => $purchase_data['user_info'],
            'status' => 'pending'
        );

        // record the pending payment
        $payment = edd_insert_payment($payment);

        $merchant_payment_confirmed = false;


        // New bill
        $success_page_permalink = get_permalink($edd_options['success_page']);
        $payment_details = array(
            'amount' => $purchase_data['price'],
            'name' => $purchase_summary,
            'user' => array(
                'first_name' => $purchase_data['user_info']['first_name'],
                'last_name' => $purchase_data['user_info']['last_name'],
                'email' => $purchase_data['user_email']
            ),
            'state' => $payment,
            'redirect_uri' => $success_page_permalink
        );


        $bill_url = GoCardless::new_bill_url($payment_details);
        wp_redirect($bill_url);
        exit;

    } else {
        $fail = true; // errors were detected
    }

    if ($fail !== false) {
        // if errors are present, send the user back to the purchase page so they can be corrected
        edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
    }
}

add_action('edd_gateway_gocardless', 'pdw_edd_gc_process_payment');

// adds the settings to the Payment Gateways section
function pdw_edd_gc_add_settings($settings)
{

    $gocardless_settings = array(
        array(
            'id' => 'gocardless_settings',
            'name' => '<strong>' . __('GoCardless Settings', 'pw-edd-gc-locale') . '</strong>',
            'desc' => __('Configure the gateway settings', 'pw-edd-gc-locale'),
            'type' => 'header'
        ),
        array(
            'id' => 'app_id',
            'name' => __('App ID', 'pw-edd-gc-locale'),
            'desc' => __('Enter your App ID from your merchant account settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'app_secret',
            'name' => __('App secret', 'pw-edd-gc-locale'),
            'desc' => __('Enter your app secret key, found in your GoCardless settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'merchant_id',
            'name' => __('Merchant ID', 'pw-edd-gc-locale'),
            'desc' => __('Enter your merchant ID, found in your GoCardless settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'access_token',
            'name' => __('Access token', 'pw-edd-gc-locale'),
            'desc' => __('Enter your access token, found in your GoCardless settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),

         array(
              'id' => 'gocardless_settings',
               'name' => '<strong>' . __('GoCardless Settings', 'pw-edd-gc-locale') . '</strong>',
               'desc' => __('Configure the gateway settings', 'pw-edd-gc-locale'),
               'type' => 'header'
         ),

        // test mode
        array(
            'id' => 'test_app_id',
            'name' => __('Test App ID', 'pw-edd-gc-locale'),
            'desc' => __('Enter your test App ID from your merchant account settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'test_app_secret',
            'name' => __('test App secret', 'pw-edd-gc-locale'),
            'desc' => __('Enter your test app secret key, found in your GoCardless settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'test_merchant_id',
            'name' => __('test Merchant ID', 'pw-edd-gc-locale'),
            'desc' => __('Enter your test merchant ID, found in your GoCardless settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'test_access_token',
            'name' => __('test Access token', 'pw-edd-gc-locale'),
            'desc' => __('Enter your  test access token, found in your GoCardless settings', 'pw-edd-gc-locale'),
            'type' => 'text',
            'size' => 'regular'
        )
    );

    return array_merge($settings, $gocardless_settings);
}

add_filter('edd_settings_gateways', 'pdw_edd_gc_add_settings');

function pdw_edd_gc_get_credentials()
{
    global $edd_options;

    if (edd_is_test_mode()) {
        // sandbox mode remove for production!
        GoCardless::$environment = 'sandbox';
        $account_details = array(
            'app_id' => isset( $edd_options['test_app_id'] ) ? $edd_options['test_app_id'] : '' ,
            'app_secret' => isset( $edd_options['test_app_secret'] ) ? $edd_options['test_app_secret'] :'' ,
            'merchant_id' => isset( $edd_options['test_merchant_id'] ) ? $edd_options['test_merchant_id'] : '' ,
            'access_token' => isset( $edd_options['test_access_token'] ) ? $edd_options['test_access_token'] : ''
        );

    } else {
        $account_details = array(
            'app_id' => isset( $edd_options['app_id'] ) ? $edd_options['app_id'] : '',
            'app_secret' => isset( $edd_options['app_secret'] ) ? $edd_options['app_secret'] : '',
            'merchant_id' => isset( $edd_options['merchant_id'] ) ? $edd_options['merchant_id']: '',
            'access_token' => isset( $edd_options['access_token'] )? $edd_options['access_token'] : ''
        );

    }
    return $account_details;
}

add_action('init', 'pdw_edd_gc_listen_for_complete_transaction');

function pdw_edd_gc_listen_for_complete_transaction(){
    $account_details = pdw_edd_gc_get_credentials();
    if (isset($_GET['resource_id']) && isset($_GET['resource_type'])) {
        // Get vars found so let's try confirming payment

        GoCardless::set_account_details($account_details);
        $confirm_params = array(
            'resource_id'   => $_GET['resource_id'],
            'resource_type' => $_GET['resource_type'],
            'resource_uri'  => $_GET['resource_uri'],
            'signature'     => $_GET['signature']
        );

        if (isset($_GET['state'])) {
            $confirm_params['state'] = $_GET['state'];
            $confirmed_resource = GoCardless::confirm_resource($confirm_params);
            edd_update_payment_status($confirm_params['state'], 'complete');
        }

    }
}