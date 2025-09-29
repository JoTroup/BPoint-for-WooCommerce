<?php
/**
 * Plugin Name: BPOINT (WooCommerce Payment Gateway)
 * Plugin URI: https://www.bpoint.com.au/
 * Description: The Commonwealth Bank’s BPOINT solution allows businesses to easily and securely accept payments online.
 * Version: 1.1.3
 * Author: Josiah Troup
 * Author URI: https://www.bpoint.com.au/
 * Text Domain: woo-bpoint
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('WC_BPOINT')) :

    class WC_BPOINT
    {

        /**
         * Construct the plugin.
         */
        public function __construct()
        {
            add_action('plugins_loaded', array($this, 'init'), 0);
        }

        /**
         * Initialize the plugin.
         */
        public function init()
        {
            $plugin_dir = basename(dirname(__FILE__));
            // Checks if WooCommerce is installed.
            if (class_exists('WC_BPOINT')) {
                // Include our integration class
                include_once 'includes/class-wc-bpoint-payment-gateway.php';
                // Register the payment gateway
                add_filter('woocommerce_payment_gateways', array($this, 'add_payment_gateway'));
                // Load language
                load_plugin_textdomain('woo-bpoint', false, $plugin_dir . '/languages');
                // Register a new action
                add_action('woocommerce_order_actions', array($this, 'add_order_meta_box_actions'));
                // Register process capture action
                add_action('woocommerce_order_action_bpoint_capture', array($this, 'process_order_meta_box_actions'));
                // Ajax load process payment
                add_action('wp_ajax_bpoint_load_process_payment', array($this, 'ajax_load_process_payment'));
                // Ajax load process payment for unauthenticated users
                add_action('wp_ajax_nopriv_bpoint_load_process_payment', array($this, 'ajax_load_process_payment'));
                // Ajax order review
                add_action('wp_ajax_bpoint_order_review', array($this, 'ajax_order_review'));
                // Ajax order review for unauthenticated users
                add_action('wp_ajax_nopriv_bpoint_order_review', array($this, 'ajax_order_review'));
                // Add 'Settings' action links
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'bpoint_action_links'));
                // Bulk capture action
                add_action('admin_footer-edit.php', array($this, 'custom_bulk_admin_footer'));
                add_action('load-edit.php', array($this, 'custom_bulk_action'));
                add_action('admin_notices', array($this, 'custom_bulk_admin_notices'));
                // Add pending capture status
                add_filter('init', array($this, 'wc_register_post_statuses'));
                add_filter('wc_order_statuses', array($this, 'wc_add_order_statuses'));
                add_action('wp_print_scripts', array($this, 'add_custom_order_status_icon'));
                if (!function_exists('curl_version')) {
                    add_action('admin_notices', array($this, 'wc_bpoint_admin_notice_curl_error'));
                }
            } else {
                return false;
            }
        }

        /**
         * Add order status icon
         */
        function add_custom_order_status_icon()
        {
            if (!is_admin()) {
                return;
            }
            ?>
            <style>
                .widefat .column-order_status mark.pending-capture::after {
                    content: "\e012";
                    color: #999;
                    font-family: WooCommerce;
                    speak: none;
                    font-weight: 400;
                    font-variant: normal;
                    text-transform: none;
                    line-height: 1;
                    -webkit-font-smoothing: antialiased;
                    margin: 0;
                    text-indent: 0;
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    text-align: center;
                }
            </style>
        <?php
        }

        /**
         * Check curl
         */
        function wc_bpoint_admin_notice_curl_error()
        {
            ?>
            <div class="error">
                <p>
                    <?php
                    _e('PHP\'s curl extension is not installed. The BPOINT plugin is required to enable php5-curl in your server.',
                        'woo-bpoint');
                    ?>
                </p>
            </div>
        <?php
        }

        /**
         * Register new order statuses
         */
        function wc_register_post_statuses()
        {
            register_post_status('wc-pending-capture',
                array(
                    'label' => __('Pending Capture', 'woo-bpoint'),
                    'public' => true,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    'label_count' => _n_noop('Pending Capture <span class="count">(%s)</span>',
                        'Pending Capture <span class="count">(%s)</span>', 'text_domain')
                ));
        }

        /**
         * Add new order statuses to WooCommerce
         */
        function wc_add_order_statuses($order_statuses)
        {
            $new_order_statuses = array();
            // add new order status after on hold
            foreach ($order_statuses as $key => $status) {
                $new_order_statuses[$key] = $status;
                if ('wc-on-hold' === $key) {
                    $new_order_statuses['wc-pending-capture'] = __('Pending Capture', 'woo-bpoint');
                }
            }
            return $new_order_statuses;
        }

        /**
         * Add bulk capture in select box
         */
        public function custom_bulk_admin_footer()
        {
            global $post_type;
            $wcbpoint = new WC_BPOINT_Payment_Gateway();
            if ($post_type == 'shop_order' && $wcbpoint->payment_action == 'PreAuth') {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function () {
                        jQuery('<option>').val('bpoint_capture').text('<?php _e('Capture via BPOINT', 'woo-bpoint'); ?>').appendTo("select[name='action']");
                        jQuery('<option>').val('bpoint_capture').text('<?php _e('Capture via BPOINT', 'woo-bpoint'); ?>').appendTo("select[name='action2']");
                    });
                </script>
            <?php
            }
        }

        /**
         * Process bulk capture
         */
        public function custom_bulk_action()
        {
            global $typenow;
            $post_type = $typenow;

            $wcbpoint = new WC_BPOINT_Payment_Gateway();

            if ($post_type == 'shop_order') {
                $wp_list_table = _get_list_table('WP_Posts_List_Table');
                $action = $wp_list_table->current_action();

                $allowed_actions = array("bpoint_capture");

                if (!in_array($action, $allowed_actions)) {
                    return;
                }

                if (isset($_REQUEST['post'])) {
                    $orderids = array_map('intval', $_REQUEST['post']);
                }

                $changed = 0;
                $post_ids = array_map('absint', (array)$_REQUEST['post']);
                switch ($action) {
                    case "bpoint_capture":
                        foreach ($orderids as $orderid) {
                            $result = $wcbpoint->process_capture_bulk_actions($orderid);
                            if ($result) {
                                $changed++;
                            }
                        }
                        break;
                    default:
                        return;
                }
                $sendback = add_query_arg(array('post_type' => 'shop_order', 'bpoint_capture' => $changed, 'ids' => join(',',
                    $post_ids)), '');
                wp_redirect($sendback);
                exit();
            }
        }

        /**
         * Notice when bulk capture is complete
         */
        public function custom_bulk_admin_notices()
        {
            global $post_type;
            if ($post_type == 'shop_order' && isset($_GET['bpoint_capture'])) {
                if ($_GET['bpoint_capture'] > 0) {
                    ?>
                    <div class="updated">
                        <p>
                            <?php
                            echo sprintf(_n('%d order has been captured.', '%d orders have been captured.',
                                $_GET['bpoint_capture'], 'woo-bpoint'), $_GET['bpoint_capture']);
                            ?>
                        </p>
                    </div>
                <?php
                } else {
                    ?>
                    <div class="updated">
                        <p>
                            <?php
                            echo sprintf(__('%d order have been captured.', 'woo-bpoint'), $_GET['bpoint_capture']);
                            ?>
                        </p>
                    </div>
                <?php
                }
            }
        }

        /**
         * Add a new payment gateway to WooCommerce.
         */
        public function add_payment_gateway($methods)
        {
            $methods[] = 'WC_BPOINT_Payment_Gateway';
            return $methods;
        }

        /**
         * Add action 'Capture' to the order actions meta box.
         */
        public function add_order_meta_box_actions($actions)
        {
            global $theorder;
            $wcbpoint = new WC_BPOINT_Payment_Gateway();
            if ($wcbpoint->payment_action == 'PreAuth' && $theorder->get_payment_method() == 'bpoint') {
                $actions['bpoint_capture'] = __('Capture via BPOINT', 'woo-bpoint');
            }
            return $actions;
        }

        /**
         * Process the 'Capture' order meta box order action
         */
        public function process_order_meta_box_actions($order)
        {
            $wcbpoint = new WC_BPOINT_Payment_Gateway();
            $wcbpoint->process_capture($order->id);
        }

        /**
         * Process the payment
         */
        public function ajax_load_process_payment()
        {
            $wcbpoint = new WC_BPOINT_Payment_Gateway();
            if (isset($_GET['AuthKey'])) {
                $authkey = wc_clean($_GET['AuthKey']);
                $result = $wcbpoint->load_process_payment($authkey);
            } else {
                $result = array(
                    'result' => 'error',
                    'messages' => __('Error processing your request. Please contact the store administrator.', 'woo-bpoint'),
                );
            }
            if ($result['result'] == 'success') {
                wp_safe_redirect($result['redirect']);
            } else {
                wc_add_notice($result['messages'], 'error');
                wp_safe_redirect(wc_get_cart_url());
            }
            exit;
        }

        /**
         * Process order review payment
         */
        public function ajax_order_review()
        {

            console.log('AJAX Order Review called');
            $wcbpoint = new WC_BPOINT_Payment_Gateway();
            if (isset($_POST['key'])) {
                $order_id = wc_get_order_id_by_order_key(wc_clean($_POST['key']));
            }

            console.log('Order ID: ' . $order_id);
            $result = $wcbpoint->process_payment($order_id);

            console.log('Result: ' . print_r($result, true));
            echo '<!--WC_START-->' . json_encode($result) . '<!--WC_END-->';
            exit;
        }

        /**
         * Add 'Settings' action links
         */
        public function bpoint_action_links($links)
        {
            $plugin_links = array(
                '<a title="' . __('View BPOINT Settings', 'woo-bpoint') . '" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_bpoint_payment_gateway')) . '">' . __('Settings',
                    'woo-bpoint') . '</a>',
            );

            return array_merge($plugin_links, $links);
        }
    }

    $WC_BPOINT = new WC_BPOINT(__FILE__);

endif;
