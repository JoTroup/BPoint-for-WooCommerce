<?php
/**
 * WooCommerce.
 *
 * @package WC_BPOINT
 * @category Payment_Gateway
 * @author BPOINT Development Team
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WC_BPOINT_Payment_Gateway') && class_exists('WC_Payment_Gateway')) :

    class WC_BPOINT_Payment_Gateway extends WC_Payment_Gateway
    {

        private $bpoint;
        public $total;
        public $api_url;
        public $api_username;
        public $api_password;
        public $membership_id;
        public $test_mode;
        public $payment_action;
        public $send_bpoint_email;




        // Constructor for the gateway.
        public function __construct()
        {
            $this->id = "bpoint";

            $this->method_title = __("BPOINT", 'woo-bpoint');
            $this->method_description = __("The Commonwealth Bank's BPOINT solution allows businesses to easily and securely accept payments online.",
                'woo-bpoint');
            $this->title = __("Credit Card (Secured by Commonwealth Bank)", 'woo-bpoint');
            $this->has_fields = true;
            $this->supports = array(
                'default_credit_card_form',
                'products',
                'refunds'
            );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables
            foreach ($this->settings as $setting_key => $value) {
                $this->$setting_key = $value;
            }
            // Change test mode become 'true' or 'false'
            if ($this->test_mode == 'yes') {
                $this->test_mode = true;
            } else if ($this->test_mode == 'no') {
                $this->test_mode = false;
            }
            // Change payment action become 'Payment' or 'PreAuth'
            if ($this->payment_action == '0') {
                $this->payment_action = 'Payment';
            } else if ($this->payment_action == '1') {
                $this->payment_action = 'PreAuth';
            }
            include_once('lib/BpointApi.php');
            include_once('lib/constants.php');
            $this->bpoint = new BpointApi;
            $this->bpoint->setBaseURL($this->api_url);
            $this->bpoint->setCredentials($this->api_username, $this->api_password, $this->membership_id);
            $user_agent = 'BPOINT:' . BPOINT_USER_AGENT_PLUGIN_ID . ':' . BPOINT_PLUGIN_VERSION . '|wooCommerce ' . WC()->version . ' - WordPress ' . get_bloginfo('version');
            $this->bpoint->setUserAgent($user_agent);
            // Save our administration options.
            if (is_admin()) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id,
                    array($this, 'process_admin_options'));
            }
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields()
        {

            add_action('woocommerce_admin_field_send_bpoint_email_button', array($this, 'render_send_bpoint_email_button_field'));

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable / Disable', 'woo-bpoint'),
                    'label' => __('Enable BPOINT payment gateway', 'woo-bpoint'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'woo-bpoint'),
                    'type' => 'text',
                    'desc_tip' => __('Payment title the customer will see during the checkout process.', 'woo-bpoint'),
                    'default' => __('Credit Card (Secured by Commonwealth Bank)', 'woo-bpoint'),
                ),
                'description' => array(
                    'title' => __('Description', 'woo-bpoint'),
                    'type' => 'textarea',
                    'desc_tip' => __('Payment description the customer will see during the checkout process.',
                        'woo-bpoint'),
                    'default' => __('Pay securely using your card. Our payments are secured by Commonwealth Bank.',
                        'woo-bpoint'),
                    'css' => 'max-width:350px;'
                ),
                'api_url' => array(
                    'title' => __('Base API URL', 'woo-bpoint'),
                    'type' => 'text',
                    'desc_tip' => __('URL for the BPOINT API.', 'woo-bpoint'),
                    'default' => 'https://www.bpoint.com.au/rest/',
                ),
                'membership_id' => array(
                    'title' => __('Merchant Number', 'woo-bpoint'),
                    'type' => 'text',
                    'desc_tip' => __('Your BPOINT merchant number as provided by the bank.', 'woo-bpoint'),
                ),
                'api_username' => array(
                    'title' => __('API Username', 'woo-bpoint'),
                    'type' => 'text',
                    'desc_tip' => __('API username created within BPOINT.', 'woo-bpoint'),
                ),
                'api_password' => array(
                    'title' => __('API Password', 'woo-bpoint'),
                    'type' => 'password',
                    'desc_tip' => __('API password received from BPOINT.', 'woo-bpoint'),
                ),
                'payment_action' => array(
                    'title' => __('Payment Action', 'woo-bpoint'),
                    'type' => 'select',
                    'options' => array(__('Purchase', 'woo-bpoint'), __('Pre-Auth/Capture', 'woo-bpoint')),
                    'desc_tip' => __('Choose between Purchase or Pre-Auth/Capture. Purchase transactions charge the card immediately. Pre-auths check if the card has available funds and places a hold on the card for the nominated amount. Captures complete a pre-auth, charging the card.',
                        'woo-bpoint'),
                ),
                'test_mode' => array(
                    'title' => __('Test Mode', 'woo-bpoint'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'desc_tip' => __('If test mode is enabled, all transactions will be processed in test mode. You will not receive funds.',
                        'woo-bpoint'),
                ),
                'total' => array(
                    'title' => __('Minimum Amount', 'woo-bpoint'),
                    'type' => 'text',
                    'desc_tip' => __('This is the lowest amount of money that the order total must reach to use this payment gateway.',
                        'woo-bpoint'),
                    'default' => 0,
                ),
                // 'send_bpoint_email' => array(
                //     'title' => __('Send BPOINT Email', 'woo-bpoint'),
                //     'type' => 'send_bpoint_email_button', // custom type
                //     'desc_tip' => __('Click to test the send_bpoint_email_callback function.', 'woo-bpoint'),
                // )
            );
        }

        public function render_send_bpoint_email_button_field($field) {
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php echo esc_html($field['title']); ?></th>
                <td class="forminp">
                    <button type="button" class="button" id="send_bpoint_email_btn"><?php echo esc_html($field['title']); ?></button>
                    <span class="description"><?php echo esc_html($field['desc_tip']); ?></span>
                </td>
            </tr>
            <script>
                jQuery(document).ready(function($){
                    $('#send_bpoint_email_btn').on('click', function(){
                        // AJAX call here
                        send_bpoint_email_callback();
                    });
                });
            </script>
            <?php
        }

        /**
         * Process when capture an order
         */
        public function process_capture($order_id)
        {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            if ($user_id == 0) {
                $user_id = "";
            }
            $transaction_id = $order->get_meta('_transaction');
            $capture_status = $order->get_meta('_capture_id');
            if (isset($capture_status)) {
                if ($capture_status == -1) { //return error if payment completed (payment only success, capture success)
                    WC_Admin_Meta_Boxes::add_error(__('Payment has already completed.', 'woo-bpoint'));
                } elseif ($capture_status == 0) { //return error if preauth not success or payment only failed
                    WC_Admin_Meta_Boxes::add_error(__('Pre-Authorization should be completed to perform this action.',
                        'woo-bpoint'));
                } else {
                    $amount = $this->bpoint->getLowestDenominationAmount($order->get_total(), $order->get_order_currency());
                    $this->bpoint->setAction("Capture");
                    $this->bpoint->setAmount($amount);
                    $this->bpoint->setCurrency($order->get_order_currency());
                    $this->bpoint->setMerchantReference("");
                    $this->bpoint->setCrn1($order_id);
                    $this->bpoint->setCrn2($user_id);
                    $this->bpoint->setCrn3("");
                    $this->bpoint->setBillerCode(null);
                    $this->bpoint->setSubType("Single");
                    $this->bpoint->setType("Internet");
                    $this->bpoint->setOriginalTxnNumber($transaction_id);
                    $this->bpoint->setTestMode($this->test_mode);
                    $result = $this->bpoint->processTransaction();
                    if (isset($result->responseCode)) {
                        if ($result->responseCode == "0") {
                            $order->delete_meta_data('_transaction');
                            $order->delete_meta_data('_capture_id');
                            $order->update_meta_data('_transaction', $result->txnNumber);
                            $order->update_meta_data('_capture_id', -1);
                            $order->update_status('on-hold',
                                sprintf(__('BPOINT capture of %s approved.', 'woo-bpoint'),
                                    '<b style="color:green;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                        $order->get_order_currency()) . '</b>') .
                                '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'),
                                    $result->receiptNumber) . '.<br/>'
                            );
                            $order->payment_complete();
                        } else {
                            $order->add_order_note(
                                sprintf(__('BPOINT capture of %s declined.', 'woo-bpoint'),
                                    '<b style="color:red;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                        $order->get_order_currency()) . '</b>') .
                                '<br/>' . sprintf(__('Decline reason: %s.', 'woo-bpoint'),
                                    $result->responseText) .
                                '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'),
                                    $result->receiptNumber) . '.'
                            );
                        }
                    } else {
                        WC_Admin_Meta_Boxes::add_error(__('Error processing your request.'));
                    }
                }
            } else {
                WC_Admin_Meta_Boxes::add_error(__('This order is not processed via BPOINT.'));
            }
        }

        /**
         * Process bulk actions when capture an order
         */
        public function process_capture_bulk_actions($order_id)
        {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            if ($user_id == 0) {
                $user_id = "";
            }
            $transaction_id = $order->get_meta('_transaction');
            $capture_status = $order->get_meta('_capture_id');

            if ($capture_status == 1) {
                $amount = $this->bpoint->getLowestDenominationAmount($order->get_total(), $order->get_order_currency());
                $this->bpoint->setAction("capture");
                $this->bpoint->setAmount($amount);
                $this->bpoint->setCurrency($order->get_order_currency());
                $this->bpoint->setMerchantReference("");
                $this->bpoint->setCrn1($order_id);
                $this->bpoint->setCrn2($user_id);
                $this->bpoint->setCrn3("");
                $this->bpoint->setBillerCode(null);
                $this->bpoint->setSubType("Single");
                $this->bpoint->setType("Internet");
                $this->bpoint->setOriginalTxnNumber($transaction_id);
                $this->bpoint->setTestMode($this->test_mode);
                $result = $this->bpoint->processTransaction();
                if (isset($result->responseCode)) {
                    if ($result->responseCode == "0") {
                        $order->delete_meta_data('_transaction');
                        $order->delete_meta_data('_capture_id');
                        $order->update_meta_data('_transaction', $result->txnNumber);
                        $order->update_meta_data('_capture_id', -1);
                        $order->update_status('on-hold',
                            sprintf(__('BPOINT captured by bulk edit:<br/>BPOINT capture of %s approved.', 'woo-bpoint'),
                                '<b style="color:green;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                    $order->get_order_currency()) . '</b>') .
                            '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->receiptNumber) . '.<br/>');
                        $order->payment_complete();
                        return true;
                    }
                }
            }
        }

        /**
         * Process a refund if supported
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);
            $transaction_id = $order->get_meta('_transaction');
            $capture_status = $order->get_meta('_capture_id');
            if ($reason == '') {
                $reas = '';
                $reas2 = '';
            } else {
                $reas = '<br/>' . sprintf(__('Refund reason: %s.', 'woo-bpoint'), $reason);
                $reas2 = ' ' . sprintf(__('Refund reason: %s.', 'woo-bpoint'), $reason);
            }

            if (isset($capture_status)) {
                if ($capture_status == 1) {
                    return new WP_Error('ms-error',
                        __('Capture should be completed to perform this action.', 'woo-bpoint'));
                }
                if ($capture_status == 0) {
                    return new WP_Error('ms-error',
                        __('Payment should be completed to perform this action.', 'woo-bpoint'));
                }
            }
            if(!is_numeric($amount) || $amount <= 0){
                return new WP_Error('ms-error',
                    __('Invalid refund amount.', 'woo-bpoint'));
            }
            $user_id = $order->get_user_id();
            if ($user_id == 0) {
                $user_id = "";
            }
            $getAmount = $this->bpoint->getLowestDenominationAmount($amount, $order->get_order_currency());
            $this->bpoint->setAction("Refund");
            $this->bpoint->setAmount($getAmount);
            $this->bpoint->setCurrency($order->get_order_currency());
            $this->bpoint->setMerchantReference("");
            $this->bpoint->setCrn1($order_id);
            $this->bpoint->setCrn2($user_id);
            $this->bpoint->setCrn3("");
            $this->bpoint->setBillerCode(null);
            $this->bpoint->setSubType("Single");
            $this->bpoint->setType("Internet");
            $this->bpoint->setOriginalTxnNumber($transaction_id);
            $this->bpoint->setTestMode($this->test_mode);
            $result = $this->bpoint->processTransaction();
            if (isset($result->responseCode)) {
                if ($result->responseCode == "0") {
                    $order->add_order_note(
                        sprintf(__('BPOINT refund of %s approved.', 'woo-bpoint'),
                            '<b style="color:green;">' . $this->bpoint->formatAmountCurrency($amount,
                                $order->get_order_currency()) . '</b>') .
                        $reas .
                        '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->receiptNumber) . '.'
                    );
                    return true;
                } else {
                    $order->add_order_note(
                        sprintf(__('BPOINT refund of %s declined.', 'woo-bpoint'),
                            '<b style="color:red;">' . $this->bpoint->formatAmountCurrency($amount, $order->get_order_currency()) . '</b>') .
                        '<br/>' . sprintf(__('Decline reason: %s.', 'woo-bpoint'), $result->responseText) .
                        $reas .
                        '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->receiptNumber) . '.'
                    );
                    return new WP_Error('ms-error',
                        sprintf(__('BPOINT refund of %s declined.', 'woo-bpoint'),
                            $this->bpoint->formatAmountCurrency($amount, $order->get_order_currency())) .
                        sprintf(__('Decline reason: %s.', 'woo-bpoint'), $result->responseText) .
                        $reas2 . ' ' .
                        '. ' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->receiptNumber) . '.'
                    );
                }
            } else {
                return new WP_Error('ms-error',
                    __('Error processing your request. Please contact the store administrator.', 'woo-bpoint'));
            }
            return false;
        }

        /**
         * Check the payment result and change status order
         */
        public function process_payment($order_id)
        {


            error_log('Processing payment');
            try {
        
                wc_email_log('BPOINT Payment Processing', 'Starting payment processing for order ID: ' . $order_id);
                global $woocommerce;
                $order = wc_get_order($order_id);
                $woocommerce->cart->empty_cart();
                $result = $this->bpoint->createAuthkey();

                error_log('Auth key result: ' . print_r($result, true)); 

                if (isset($result->authkey)) {
                    $user_id = $order->get_user_id();
                    if ($user_id == 0) {
                        $user_id = "";
                    }
                    $amount = $this->bpoint->getLowestDenominationAmount($order->get_total(), $order->get_order_currency());
                    $this->bpoint->setAction($this->payment_action);
                    $this->bpoint->setAmount($amount);
                    $this->bpoint->setCurrency($order->get_order_currency());
                    $this->bpoint->setMerchantReference("");
                    $this->bpoint->setCrn1($order_id);
                    $this->bpoint->setCrn2($user_id);
                    $this->bpoint->setCrn3("");
                    $this->bpoint->setBillerCode(null);
                    $this->bpoint->setType("Internet");
                    $this->bpoint->setSubType("Single");
                    $this->bpoint->setTestMode($this->test_mode);
                    $txn_details = $this->bpoint->attachTxnDetails($result->authkey);
                    if ($txn_details === true) {
                        $order->add_order_note(__('Awaiting cheque payment.', 'woo-bpoint'));
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order),
                            'auth_key' => $result->authkey,
                            'redirect_url' => admin_url('admin-ajax.php?action=bpoint_load_process_payment&AuthKey=' . $result->authkey)
                        );
                    } else {
                        if (isset($txn_details->message)) {
                            $order->update_status('failed',
                            __('BPOINT create authorization key fail.', 'woo-bpoint') .
                            '<br/>' . sprintf(__('Reason fail: %s.', 'woo-bpoint'), $txn_details->message) . '<br/>');
                        }
                        wc_add_notice(__('Error processing your request. Please contact the store administrator.',
                            'woo-bpoint'), 'error');
                    }
                } else {
                    error_log('Failed to create auth key: ' . print_r($result, true));
                    if (isset($result->message)) {
                        $order->update_status('failed',
                        __('BPOINT create authorization key fail.', 'woo-bpoint') .
                        '<br/>' . sprintf(__('Reason fail: %s.', 'woo-bpoint'), $result->message) . '<br/>');
                    }

                    error_log('Error: ' . (isset($result->message) ? $result->message : 'Unknown error'));
                    wc_add_notice(__('HELLO Error processing your request. Please contact the store administrator.', 'woo-bpoint'),
                        'error');
                }
            } catch (thrownError $e) {
                wc_add_notice(__('Failed to process order and run into exception:' + $e, 'woo-bpoint'),'error');
                wc_email_log('BPOINT Payment Processing Exception', 'An exception occurred during payment processing: ' . $e);
            }            
            
        }

        public function load_process_payment($authkey)
        {
            $result = $this->bpoint->processTransactionAuthkey($authkey);
            if (isset($result->txn->responseCode)) {
                $order_id = $result->txn->crn1;
                $order = wc_get_order($order_id);
                if ($result->txn->amount != $this->bpoint->getLowestDenominationAmount($order->get_total(),
                        $order->get_order_currency())
                ) {
                    return array(
                        'result' => 'error',
                        'messages' => __('Error processing your request. Please contact the store administrator.',
                            'woo-bpoint'),
                    );
                }
                if ($result->txn->responseCode == "0") {
                    if ($this->payment_action == 'PreAuth') {
                        $order->update_meta_data('_capture_id', 1);
                        $order->update_meta_data('_transaction', $result->txn->txnNumber);
                        $order->update_status('pending-capture',
                            sprintf(__('BPOINT authorization of %s approved.', 'woo-bpoint'),
                                '<b style="color:green;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                    $order->get_order_currency()) . '</b>') .
                            '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->txn->receiptNumber) . '.<br/>'
                        );
                    } else if ($this->payment_action == 'Payment') {
                        $order->update_meta_data('_capture_id', -1);
                        $order->update_meta_data('_transaction', $result->txn->txnNumber);
                        $order->add_order_note(
                            sprintf(__('BPOINT payment of %s approved.', 'woo-bpoint'),
                                '<b style="color:green;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                    $order->get_order_currency()) . '</b>') .
                            '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->txn->receiptNumber) . '.'
                        );
                        $order->payment_complete();
                    }
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                } else {
                    $order->update_meta_data('_capture_id', 0);
                    if ($this->payment_action == 'PreAuth') {
                        $order->add_order_note(
                            sprintf(__('BPOINT authorization of %s declined.', 'woo-bpoint'),
                                '<b style="color:red;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                    $order->get_order_currency()) . '</b>') .
                            '<br/>' . sprintf(__('Decline reason: %s.', 'woo-bpoint'), $result->txn->responseText) .
                            '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->txn->receiptNumber) . '.'
                        );
                        $order->update_status('failed');
                    } else if ($this->payment_action == 'Payment') {
                        $order->add_order_note(
                            sprintf(__('BPOINT payment of %s declined.', 'woo-bpoint'),
                                '<b style="color:red;">' . $this->bpoint->formatAmountCurrency($order->get_total(),
                                    $order->get_order_currency()) . '</b>') .
                            '<br/>' . sprintf(__('Decline reason: %s.', 'woo-bpoint'), $result->txn->responseText) .
                            '<br/>' . sprintf(__('Receipt Number: %s', 'woo-bpoint'), $result->txn->receiptNumber) . '.'
                        );
                        $order->update_status('failed');
                    }
                    return array(
                        'result' => 'error',
                        'messages' => __('Your payment has declined.', 'woo-bpoint') . ' ' .
                            sprintf(__('Decline reason: %s.', 'woo-bpoint'), $result->txn->responseText),
                    );
                }
            } else {
                return array(
                    'result' => 'error',
                    'messages' => __('Error processing your request. Please contact the store administrator.',
                        'woo-bpoint'),
                );
            }
        }

        /**
         *  Payment fields
         */
        public function payment_fields()
        {
            if ($this->is_valid_total()) {
                //generate list of month and year
                $month_html = '<select style="width:100%; border: 1px solid #cccccc; font-family: inherit; padding: 0.428571rem;" id="' . esc_attr($this->id) . '-card-month-expiry">';
                $month_html .= '<option value="">' . __('Month', 'woo-bpoint') . '</option>';
                for ($i = 1; $i <= 12; $i++) {
                    $month_html .= '<option value="' . sprintf('%02d', $i) . '">' . sprintf('%02d', $i) . ' - ' . date('F', mktime(0, 0, 0, $i, 1, 2000)) . '</option>';
                }
                $month_html .= '</select>';
                $today = getdate();
                $year_html = '<select style="width:100%; border: 1px solid #cccccc; font-family: inherit; padding: 0.428571rem;" id="' . esc_attr($this->id) . '-card-year-expiry">';
                $year_html .= '<option value="">' . __('Year', 'woo-bpoint') . '</option>';
                for ($i = $today['year']; $i < $today['year'] + 11; $i++) {
                    $year_tmp = date('Y', mktime(0, 0, 0, 1, 1, $i));
                    $year_html .= '<option value="' . $year_tmp . '">' . $year_tmp . '</option>';
                }
                $year_html .= '</select>';
                $fields = array();
                wp_enqueue_script('wc-credit-card-form');
                $default_fields = array(
                    'card-holder-name' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr($this->id) . '-card-holder-name">' . __('Cardholder name', 'woo-bpoint') . ' <span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-holder-name" class="input-text" type="text" maxlength="50" autocomplete="off" placeholder="" style= "" />
            </p>',
                    'card-number-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr($this->id) . '-card-number">' . __('Card number', 'woo-bpoint') . ' <span class="required">*</span></label>
                <input id="' . esc_attr($this->id) . '-card-number" class="input-text" type="text" maxlength="16" autocomplete="off" />
            </p>',
                    'card-expiry-month-field' => '<p class="form-row form-row-first">
                <label for="' . esc_attr($this->id) . '-card-month-expiry">' . __('Expiry date', 'woo-bpoint') . ' <span class="required">*</span></label>'
                        . $month_html .
                        '</p>',
                    'card-expiry-year-field' => '<p class="form-row form-row-last">
                <label for="' . esc_attr($this->id) . '-card-year-expiry">&nbsp</label>'
                        . $year_html .
                        '</p>',
                    'card-cvc-field' => '<p class="form-row form-row-wide">
                <label for="' . esc_attr($this->id) . '-card-cvc">' . __('CVN', 'woo-bpoint') . ' <span class="required">*</span><abbr title="' . __('Card Verification Number',
                            'woo-bpoint') . '"></abbr></label>
                <input maxlength="4" id="' . esc_attr($this->id) . '-card-cvc" class="input-text" type="text" autocomplete="off" placeholder="Card Verification Number" />
            </p>',
                    'validated-field' => '<p class="form-row woocommerce-validated">
                <input id="' . esc_attr($this->id) . '-validated-field" class="input-text" style="display: none" />
            </p>'
                ,
                    'invalid-field' => '<p class="form-row woocommerce-invalid validate-required">
                <input id="' . esc_attr($this->id) . '-invalid-field" class="input-text" style="display: none" />
            </p>'
                );

                $fields = wp_parse_args($fields,
                    apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
                ?>
                <fieldset id="<?php echo $this->id; ?>-cc-form">
                    <?php if ($this->description != "") { ?>
                        <p class="form-row form-row-wide" style="margin: 0px;">
                            <?php echo $this->description; ?>
                        </p>
                    <?php } ?>
                    <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
                    <?php
                    foreach ($fields as $field) {
                        echo $field;
                    }
                    ?>
                    <p class="form-row form-row-wide woocommerce-validated">
                        <?php
                        echo sprintf(__('Your credit card will be charged for %s', 'woo-bpoint'),
                            $this->bpoint->formatAmountCurrency($this->get_order_total(), get_woocommerce_currency()));
                        ?>
                    </p>
                    <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>

                    <div class="clear"></div>

                </fieldset>
                <?php
                if (isset($_GET['key'])) {
                    ?>
                    <input type="text" id="<?php echo esc_attr($this->id); ?>-key" value="<?php echo $_GET['key']; ?>"
                           readonly hidden/>
                <?php } ?>
                <div class="block"></div>
            <?php
            } else {
                ?>
                <input type="radio" id="submit_disable" hidden checked/>
                <fieldset id="<?php echo esc_attr($this->id); ?>-cc-form">
                    <p class="form-row form-row-wide woocommerce-validated">
                        <?php
                        echo sprintf(__('Your order total must reach %s to use this payment method.', 'woo-bpoint'),
                            wc_price($this->total, array('currency' => get_woocommerce_currency())));
                        ?>
                    </p>
                </fieldset>
            <?php
            }
        }

        /**
         *  Validate fields
         */
        public function validate_fields()
        {
            return true;
        }

        /**
         * Check if this gateway is enabled and setting total is greater than order total
         */
        public function is_valid_total()
        {
            return (WC()->cart && 0 < $this->get_order_total() && $this->get_order_total() >= $this->total);
        }

        /**
         * Outputs scripts used for simplify payment
         */
        public function payment_scripts()
        {
            wp_register_script('bpoint_api', $this->bpoint->getJsApiUrl());
            wp_enqueue_script('bpoint_api');
            wp_enqueue_script('wc-bpoint', plugins_url('assets/js/bpoint.js', dirname(__FILE__)),array( 'jquery' ), BPOINT_PLUGIN_VERSION , true );
            wp_localize_script('wc-bpoint', 'bpoint_checkout_language',
                array(
                    'card_require' => __('Card number is a required field.', 'woo-bpoint'),
                    'date_require' => __('Expiry date is a required field.', 'woo-bpoint'),
                    'month_require' => __('Expiry month is a required field.', 'woo-bpoint'),
                    'year_require' => __('Expiry year is a required field.', 'woo-bpoint'),
                    'cvn_require' => __('CVN is a required field.', 'woo-bpoint'),
                    'name_require' => __('Cardholder name is a required field.', 'woo-bpoint'),
                    'card_valid' => __('Please enter a valid card number</b>.', 'woo-bpoint'),
                    'cvn_valid' => __('Invalid CVN (Card Verification Number).', 'woo-bpoint'),
                    'date_valid' => __('Incorrect expiry date.', 'woo-bpoint'),
                    'result_fail' => __('Result failure.', 'woo-bpoint'),
                    'invalid_res' => __('Invalid response.', 'woo-bpoint'),
                    'gateway_disabled' => '<b>' . $this->title . '</b> ' . __('disabled.', 'woo-bpoint'),
                    'id' => esc_attr($this->id),
                    'ajax_url' => WC()->ajax_url(),
                    'checkout_url' => add_query_arg('action', 'woocommerce_checkout', WC()->ajax_url())
                ));
        }

        public function wc_email_log($log_title, $log_message)
        {
            $to = 'jtroup@barossa.coop';
            $subject = $log_title;
            $message = $log_message;
            $headers = 'From: noreply@barossacoop.com.au' . "\r\n" .
                    'Reply-To: noreply@barossacoop.com.au' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();

            if (mail($to, $subject, $message, $headers)) {
                error_log('Email sent successfully!');
            } else {
                error_log('Email sending failed.');
            }
        }
    }
endif;