<?php
/*
Plugin Name: WooCommerce OneCash Gateway
Plugin URI: http://onecash.com/
Description: Use OneCash as a payment processor for WooCommerce.
Author: OneCash
Version: 1.3.7
Author URI: http://www.onecash.com/

Copyright: © 2017 OneCash

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/**
 * Plugin updates
 */
//woothemes_queue_update( plugin_basename( __FILE__ ), '6cf86aef9b610239ed70ecd9a2ab069a', '185012' );

add_action('plugins_loaded', 'woocommerce_onecash_init', 99,1);

function woocommerce_onecash_init() {

  if (!class_exists('WC_Payment_Gateway'))  return;

  class WC_Gateway_OneCash extends WC_Payment_Gateway {

    /**
       * @var Singleton The reference to the singleton instance of this class
       */
    private static $_instance = NULL;

    /**
     * @var boolean Whether or not logging is enabled
     */
    public static $log_enabled = false;

    /**
     * @var WC_Logger Logger instance
     */
    public static $log = false;


    /**
     * Main WC_Gateway_OneCash Instance
     *
     * Used for WP-Cron jobs when
     *
     * @since 1.0
     * @return WC_Gateway_OneCash Main instance
     */
    public static function instance() {
      if (is_null(self::$_instance)) {
        self::$_instance = new self();
      }
      return self::$_instance;
    }

    public function __construct() {

      global $woocommerce;

      $this->id           = 'onecash';
      $this->method_title     = __('OneCash', 'woo_onecash');
      $this->method_description   = __('Use OneCash as a credit card processor for WooCommerce.', 'woo_onecash');
      $this->icon         = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/onecash_logo.png';

      $this->supports       = array( 'products', 'refunds' );

      // Load the form fields.
      $this->init_environment_config();

      // Load the form fields.
      $this->init_form_fields();

      // Load the settings.
      $this->init_settings();

      // Load the frontend scripts.
      $this->init_scripts_js();
      $this->init_scripts_css();

      if( !empty($this->environments[ $this->settings['testmode'] ]['api_url']) ) {
        $api_url = $this->environments[ $this->settings['testmode'] ]['api_url'];
      }
      if( !empty($this->environments[ $this->settings['testmode'] ]['web_url']) ) {
        $web_url = $this->environments[ $this->settings['testmode'] ]['web_url'];
      }

      $this->log( 'Pre override $api_url url: '.($api_url) );

      if( empty($api_url) ) {
        $api_url = $this->environments[ 'sandbox' ]['api_url'];
      }
      if( empty($web_url) ) {
        $web_url = $this->environments[ 'sandbox' ]['web_url'];
      }

      $this->log( 'Post override $api_url url: '.($api_url) );


      $this->orderurl = $api_url . 'orders';
      $this->limiturl = $api_url . 'configuration';
      $this->buyurl = $web_url . 'buy';
      $this->jsurl = $web_url . 'onecash.js';

      // Define user set variables
      $this->title = '';
      if (isset($this->settings['title'])) {
        $this->title = $this->settings['title'];
      }
      $this->description = __('Credit cards accepted: Visa, Mastercard','woo_onecash');

      self::$log_enabled  = $this->settings['debug'];

        // Hooks
      add_action( 'woocommerce_receipt_'.$this->id, array($this, 'receipt_page'),99,1 );

      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

      add_action('woocommerce_settings_start', array($this,'update_payment_limits'));

      add_filter( 'woocommerce_thankyou_order_id',array($this,'payment_callback'));

      add_action( 'woocommerce_order_status_refunded',array($this,'create_refund'));

      // Don't enable OneCash if the amount limits are not met
      add_filter('woocommerce_available_payment_gateways',array($this,'check_cart_within_limits'), 99, 1);
    }


    /**
       * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
       */
    function init_form_fields() {

      $env_values = array();
      foreach( $this->environments as $key => $item ) {
        $env_values[$key] = $item["name"];
      }

      $this->form_fields = array(
        'enabled' => array(
            'title' => __( 'Enable/Disable', 'woo_onecash' ),
            'type' => 'checkbox',
            'label' => __( 'Enable OneCash', 'woo_onecash' ),
            'default' => 'yes'
        ),
        'title' => array(
            'title' => __( 'Title', 'woo_onecash' ),
            'type' => 'text',
            'description' => __( 'This controls the payment method title which the user sees during checkout.', 'woo_onecash' ),
            'default' => __( 'OneCash', 'woo_onecash' )
        ),
        'testmode' => array(
        'title' => __( 'Test mode', 'woo_onecash' ),
        'label' => __( 'Enable Test mode', 'woo_onecash' ),
        'type' => 'select',
        'options' => $env_values,
        'description' => __( 'Process transactions in Test/Sandbox mode. No transactions will actually take place.', 'woo_onecash' ),
        ),
        'debug' => array(
          'title' => __( 'Debug logging', 'woo_onecash' ),
          'label' => __( 'Enable debug logging', 'woo_onecash' ),
          'type' => 'checkbox',
          'description' => __('The OneCash log is in the <code>wc-logs</code> folder.','woo_onecash'),
          'default' => 'no'
        ),
        'prod-id' => array(
          'title' => __( 'OneCash token (live)', 'woo_onecash' ),
          'type' => 'text',
          'default' => ''
        ),
        'prod-secret-key' => array(
          'title' => __( 'Secret key (live)', 'woo_onecash' ),
          'type' => 'password',
          'default' => '',
          ''
        ),
        'test-id' => array(
          'title' => __( 'OneCash token (test)', 'woo_onecash' ),
          'type' => 'text',
          'default' => ''
        ),
        'test-secret-key' => array(
          'title' => __( 'Secret key (test)', 'woo_onecash' ),
          'type' => 'password',
          'default' => ''
        ),
        'pay-over-time-heading' => array(
          'title'       => __( 'Pay Over Time', 'woocommerce' ),
          'type'        => 'title',
          'description' => __( 'These settings relate to the Pay Over Time (PBI) payment method.', 'woo_onecash' ),
        ),
        'pay-over-time' => array(
            'title' => __( 'Enable Pay Over Time', 'woo_onecash' ),
            'type' => 'checkbox',
            'label' => __( 'Enable the OneCash Pay Over Time payment method?', 'woo_onecash' ),
            'default' => 'yes'
        ),
        'pay-over-time-limit-min' => array(
            'title' => __( 'Pay Over Time payment amount minimum', 'woo_onecash' ),
            'type' => 'input',
            'description' => __( 'This information is supplied by OneCash and cannot be edited.', 'woo_onecash' ),
            'custom_attributes' => array(
              'readonly'=>'true'
            ),
            'default' => ''
        ),
        'pay-over-time-limit-max' => array(
            'title' => __( 'Pay Over Time payment amount maximum', 'woo_onecash' ),
            'type' => 'input',
            'description' => __( 'This information is supplied by OneCash and cannot be edited.', 'woo_onecash' ),
            'custom_attributes' => array(
              'readonly'=>'true'
            ),
            'default' => ''
        ),
        // 'pay-over-time-display' => array(
        //     'title' => __( 'Pay Over Time checkout information', 'woo_onecash' ),
        //     'type' => 'wysiwyg',
        //     'label' => __( 'This information will be displayed on the checkout page if you enable Pay Over Time.', 'woo_onecash' ),
        //     'default' => $this->default_pay_over_time_message()
        // ),
        'shop-messaging' => array(
        'title'       => __( 'Payment alternative information', 'woocommerce' ),
        'type'        => 'title',
        'description' => __( 'You can choose to display an additional message to customers about the Pay Over Time payment method on your shop pages.', 'woo_onecash' ),
        ),
        'show-info-on-category-pages' => array(
          'title' => __( 'Payment info on product listing pages', 'woo_onecash' ),
          'label' => __( 'Enable', 'woo_onecash' ),
          'type' => 'checkbox',
          'description' => __( 'Enable to display Pay Over Time payment information on category pages', 'woo_onecash' ),
          'default' => 'yes'
        ),
        'category-pages-info-text' => array(
          'title' => __( 'Payment info on product listing pages', 'woo_onecash' ),
          'type' => 'wysiwyg',
          'default' => 'or 4 payments of [AMOUNT] with OneCash',
          'description' => 'Use [AMOUNT] to insert the repayment amount. If you use [AMOUNT], this message won\'t be displayed for products with variable pricing.'
        ),
        'show-info-on-product-pages' => array(
          'title' => __( 'Payment info on individual product pages', 'woo_onecash' ),
          'label' => __( 'Enable', 'woo_onecash' ),
          'type' => 'checkbox',
          'description' => __( 'Enable to display Pay Over Time payment information on individual product pages', 'woo_onecash' ),
          'default' => 'yes'
        ),
        'product-pages-info-text' => array(
          'title' => __( 'Payment info on individual product pages', 'woo_onecash' ),
          'type' => 'wysiwyg',
          'default' => 'or 4 payments of [AMOUNT] with OneCash',
          'description' => 'Use [AMOUNT] to insert the repayment amount. If you use [AMOUNT], this message won\'t be displayed for products with variable pricing.'
        )
      );
    } // End init_form_fields()

    /**
     * Init JS Scripts Options
     *
     * @since 1.2.1
     */
    public function init_scripts_js() {
      //use WP native jQuery
      wp_enqueue_script("jquery");

      wp_register_script('onecash_fancybox_js', plugins_url('js/fancybox3/jquery.fancybox.min.js', __FILE__ ));
      wp_register_script('onecash_js', plugins_url('js/onecash.js', __FILE__ ));
      wp_register_script('onecash_admin_js', plugins_url('js/onecash-admin.js', __FILE__ ));

      wp_enqueue_script('onecash_fancybox_js');
      wp_enqueue_script('onecash_js');
      wp_enqueue_script('onecash_admin_js');
    }

    /**
     * Init Scripts Options
     *
     * @since 1.2.1
     */
    public function init_scripts_css() {
      wp_register_style('onecash_fancybox_css', plugins_url('js/fancybox3/jquery.fancybox.min.css', __FILE__ ));
      wp_register_style('onecash_css', plugins_url('css/onecash.css', __FILE__ ));

      wp_enqueue_style('onecash_fancybox_css');
      wp_enqueue_style('onecash_css');
    }

    /**
     * Init Environment Options
     *
     * @since 1.2.3
     */
    public function init_environment_config() {
      if ( empty( $this->environments ) ) {
        //config separated for ease of editing
        require( 'config/config.php' );
        $this->environments = $environments;
      }
    }

    /**
     * Admin Panel Options
     *
     * @since 1.0.0
     */
    public function admin_options() {
      ?>
        <h3><?php _e('OneCash Gateway', 'woo_onecash'); ?></h3>

        <table class="form-table">
          <?php
          // Generate the HTML For the settings form.
          $this->generate_settings_html();
          ?>
        </table><!--/.form-table-->
      <?php
    } // End admin_options()

    /**
    * Generate wysiwyg input field
    *
    * @since 1.0.0
    */
    function generate_wysiwyg_html ( $key, $data ) {
      $html = '';

      //if ( isset( $data['title'] ) && $data['title'] != '' ) $title = $data['title']; else $title = '';
      $data['class'] = (isset( $data['class'] )) ? $data['class'] : '';
      $data['css'] = (isset( $data['css'] )) ? '<style>'.$data['css'].'</style>' : '';
      $data['label'] = (isset( $data['label'] )) ? $data['label'] : '';

      $value = ( isset( $this->settings[ $key ] ) ) ? esc_attr( $this->settings[ $key ] ) : '';

      ob_start();
      echo '<tr valign="top">
        <th scope="row" class="titledesc">
        <label for="'.str_replace('-','',$key).'">';
      echo $data['title'];
      echo '</label>
        </th>
        <td class="forminp">';

      wp_editor(html_entity_decode($value),str_replace('-','',$key),array('textarea_name'=>$this->plugin_id . $this->id . '_' . $key,'editor_class'=>$data['class'],'editor_css'=>$data['css'],'autop'=>true,'textarea_rows'=>8));
      echo '<p class="description">'.$data['label'].'</p>';
      echo '</td></tr>';

      $html = ob_get_clean();

      return $html;
    }

    /**
       * Display payment options on the checkout page
    *
    * @since 1.0.0
    */
    function payment_fields() {
      global $woocommerce;

      $ordertotal = $woocommerce->cart->total;

      // Check which options are available for order amount
      $validoptions = $this->check_payment_options_for_amount($ordertotal);

      if( count($validoptions) == 0 ) {
        echo "Unfortunately, orders of this value cannot be processed through OneCash";
        return false;
      }

      // Payment form
      if ($this->settings['testmode'] != 'production') : ?><p><?php _e('TEST MODE ENABLED', 'woo_onecash'); ?></p><?php endif;
      //if ($this->description) { echo '<p>'.$this->description.'</p>'; }
      ?>


      <input type="hidden" name="onecash_payment_type" value="PBI" checked="checked" />


      <?php include("checkout/installments.php"); ?>
      <?php include("checkout/modal.php"); ?>

        <?php
      }

    /**
     * Request an order token from onecash
     *
     * @param  string $type, defaults to PBI
     * @param  WC_Order $order
     * @return  string or boolean false if no order token generated
     * @since 1.0.0
     */
    function get_order_token( $type = 'PBI', $order = false) {
      // Setup order items
      $orderitems = $order->get_items();
      $items = array();
      if (count($orderitems)) {
        foreach ($orderitems as $item) {
          // get SKU
          if ($item['variation_id']) {

            if(function_exists("wc_get_product")) {
                $product = wc_get_product($item['variation_id']);
              }
              else {
                $product = new WC_Product($item['variation_id']);
              }
            }
            else {

              if(function_exists("wc_get_product")) {
                $product = wc_get_product($item['product_id']);
              }
              else {
                $product = new WC_Product($item['product_id']);
              }
            }

          $product =
            $items[] = array(
              'name'    => $item['name'],
              'sku'     => $product->get_sku(),
              'quantity'  => $item['qty'],
              'price'   => array(
                'amount' => number_format(($item['line_subtotal'] / $item['qty']),2,'.',''),
                'currency' => get_woocommerce_currency()
              )
            );
        }
      }

      $body = array(
        'consumer' => array(
          'phoneNumber' => $order->billing_phone,
          'givenNames' => $order->billing_first_name,
          'surname' => $order->billing_last_name,
          'email' => $order->billing_email
          ),
        'paymentType' => $type, // PBI
        'items' => $items,
        'merchantReference' => $order->id,

        'taxAmount'=> array(
           "amount" => number_format($order->get_cart_tax(),2,'.',''),
           "currency" =>  get_woocommerce_currency()
        ),

        'shipping' => array(
          'name' => $order->shipping_first_name.' '.$order->shipping_last_name,
          'line1' => $order->shipping_address_1,
          'line2' => $order->shipping_address_2,
          'suburb' => $order->shipping_city,
          'postcode' => $order->shipping_postcode
          ),
        'billing' => array(
          'name' => $order->billing_first_name.' '.$order->billing_last_name,
          'line1' => $order->billing_address_1,
          'line2' => $order->billing_address_2,
          'suburb' => $order->billing_city,
          'postcode' => $order->billing_postcode
          ),

        'totalAmount' => array(
          'amount' => number_format($order->get_total(),2,'.',''),
          'currency' => get_woocommerce_currency()
          ),
        'merchant' => array(
          'redirectConfirmUrl' => $this->get_return_url($order),
          'redirectCancelUrl'  => $order->get_cancel_order_url_raw(),
        ),
        'capture' => true,

        );

      // Check whether to add shipping
      if ($order->get_shipping_method()) {

        $body['courier'] = array(
          'name' => $order->get_shipping_method()
          // 'priority' => 'STANDARD', // STANDARD or EXPRESS
          );

        $body['shippingAmount'] = array(
            'amount' => number_format($order->get_total_shipping(),2,'.',''),
            'currency' => get_woocommerce_currency()
            );
      }

      // Check whether to add discount
      if ($order->get_total_discount()) {

        $discounts = array();
        $discountArr = array(

            'displayName' => 'Discount',
            'amount' =>  array(
              'amount' => number_format($order->get_total_discount(),2,'.',''),
              'currency' => get_woocommerce_currency()
              )
            );
        $discounts[] = $discountArr;

        $body['discounts'] = $discounts;

      }

      $args = array(
        'headers' => array(
                'Authorization' => $this->get_onecash_authorization_code(),
                'Content-Type'  => 'application/json',
              ),
        'body' => json_encode($body)
      );

      $this->log( 'Order token request url: '.($this->orderurl) );
      $this->log( 'Order token request: '.print_r($args,true) );

      $response = wp_remote_post($this->orderurl,$args);
      $body = json_decode(wp_remote_retrieve_body($response));

      $this->log( 'Order token result: '.print_r($body,true) );

      if (isset($body->token)) {
        return $body->token;
      } else {
        return false;
      }
    } // get_order_token()

    /**
     * Process the payment and return the result
     * - redirects the customer to the pay page
     *
     * @param int $order_id
     * @since 1.0.0
     */
    function process_payment( $order_id ) {
      global $woocommerce;
      $ordertotal = $woocommerce->cart->total;

      if( function_exists("wc_get_order") ) {
        $order = wc_get_order( $order_id );
      }
      else {
        $order = new WC_Order( $order_id );
      }

      // Get the order token
      $token = $this->get_order_token('PBI', $order);
      $validoptions = $this->check_payment_options_for_amount($ordertotal);

      if( count($validoptions) == 0 ) {
        // amount is not supported
        $order->add_order_note(__('Order amount: $' . number_format($ordertotal, 2) . ' is not supported.', 'woo_onecash'));
        wc_add_notice(__('Unfortunately, an order of $' . number_format($ordertotal, 2) . ' cannot be processed through OneCash.', 'woo_onecash'),'error');

        //delete the order but retain the items
        $order->update_status('trash');
        WC()->session->order_awaiting_payment = NULL;

        return array(
                'result' => 'failure',
                'redirect' => $order->get_checkout_payment_url(true)
              );

      }
      else if ($token == false) {
        $this->log( 'Token extracted: '.($token) );
        // Couldn't generate token
          $order->add_order_note(__('Unable to generate the order token. Payment couldn\'t proceed.', 'woo_onecash'));
          wc_add_notice(__('Sorry, there was a problem preparing your payment.', 'woo_onecash'),'error');
          return array(
            'result' => 'failure',
            'redirect' => $order->get_checkout_payment_url(true)
          );

        } else {
          // Order token successful, save it so we can confirm it later
          update_post_meta($order_id,'_onecash_token',$token);
        }

      $redirect = $order->get_checkout_payment_url( true );

      return array(
        'result'  => 'success',
        'redirect'  => $redirect
      );

    }

    /**
     * Trigger SecurePay Javascript on receipt/intermediate page
     *
     * @since 1.0.0
     */
    function receipt_page($order_id) {
      global $woocommerce;

      if( function_exists("wc_get_order") ) {
        $order = wc_get_order( $order_id );
      }
      else {
        $order = new WC_Order( $order_id );
      }

      // Get the order token

      $token = get_post_meta($order_id,'_onecash_token',true);

      // Now redirect the user to the URL
      $returnurl = $this->get_return_url($order);
      $this->log( 'Return URL: '.($returnurl) );

      $blogurl = str_replace(array('https:','http:'),'',get_bloginfo('url'));
      $returnurl = str_replace(array('https:','http:',$blogurl),'',$returnurl);

      // Update order status if it isn't already
      $is_pending = false;
      if ( function_exists("has_status") ) {
        $is_pending = $order->has_status('pending');
      }
      else {
        if( $order->status == 'pending' ) {
          $is_pending = true;
        }
      }

      if ( !$is_pending ) {
        $order->update_status('pending');
      }?>
      <script src="<?php echo $this->jsurl; ?>"></script>
      <script>
        document.addEventListener("DOMContentLoaded", function(event) {
          console.log("initing frame")
          var theElement=document.createElement("div");
          theElement.setAttribute("id", "loader");
          document.body.appendChild(theElement);
          var everythingLoaded = setInterval(function() {
          document.getElementById("loader").style.display = "block";
          if (/loaded|complete/.test(document.readyState)) {
            clearInterval(everythingLoaded);
            if ("undefined" != typeof OneCash) {
              document.getElementById("loader").style.display = "none";
              var b = <?php echo json_encode($returnurl); ?>,
                  c = <?php echo json_encode($token); ?>;

              OneCash.init({
                relativeCallbackURL: b
              }), c ? (clearInterval(everythingLoaded), OneCash.display({
                token: c
                })) : console.error("OneCash Error: Order Token is not defined.")
              }
            }
          }, 2000)
        });

        function resizeIFrameToFitContent( iFrame ) {
          iFrame.width  = iFrame.contentWindow.document.body.scrollWidth;
          iFrame.height = iFrame.contentWindow.document.body.scrollHeight;
          console.log("resizing iframe height to " + iFrame.height);
        }

        // window.addEventListener('DOMContentLoaded', function(e) {
        //   console.log("addEventListener")
        //     var iFrame = document.getElementById( 'buyframe' );
        //     resizeIFrameToFitContent( iFrame );
        // } );

      </script>
    <?php
    }


    /**
     * Validate the order status on the Thank You page
     *
     * @param  int $order_id
     * @return  int Order ID as-is
     * @since 1.0.0
     */
    function payment_callback($order_id) {
      global $woocommerce;
      $this->log( 'Hitting thankyou page for WC Order ID '.$order_id);
      $this->log( 'Onecash Order ID: '.$_GET['orderId']);
      if( function_exists("wc_get_order") ) {
        $order = wc_get_order( $order_id );
      }
      else {
        $order = new WC_Order( $order_id );
      }

      // Avoid emptying the cart if it's cancelled
      if (isset($body->status) && $body->status == "CANCELLED") {
        return $order_id;
      }

      // Double check the onecash orderId using the status
      if (isset($_GET['orderId'])) {
        $this->log( 'Checking order status for WC Order ID '.$order_id.', OneCash Order ID '.$_GET['orderId']);

        $response = wp_remote_get(
            $this->orderurl.'/'.$_GET['orderId'],
              array(
                'headers' =>  array(
                    'Authorization' => $this->get_onecash_authorization_code()
                  )
                )
              );
        $body = json_decode(wp_remote_retrieve_body($response));

        $this->log( 'Checking order status result: '.print_r($body,true) );

        //backwards compatibility with WooCommerce 2.1.x
        $is_completed = $is_processing = $is_pending = $is_on_hold =  $is_failed = false;

        if ( function_exists("has_status") ) {
          $is_completed = $order->has_status('completed');
          $is_processing = $order->has_status('processing');
          $is_pending = $order->has_status('pending');
          $is_on_hold = $order->has_status('on-hold');
          $is_failed = $order->has_status('failed');
        }
        else {
          if( $order->status == 'completed' ) {
            $is_completed = true;
          }
          else if( $order->status == 'processing' ) {
            $is_processing = true;
          }
          else if( $order->status == 'pending' ) {
            $is_pending = true;
          }
          else if( $order->status == 'on-hold' ) {
            $is_on_hold = true;
          }
          else if( $order->status == 'failed' ) {
            $is_failed = true;
          }
        }

        // Check status of order
        if ($body->status == "APPROVED") {

          if (!$is_completed && !$is_processing) {
            $order->add_order_note(sprintf(__('Payment approved. OneCash Order ID: %s','woo_onecash'),$body->id));
            $order->payment_complete($body->id);
            woocommerce_empty_cart();
          }
        } elseif ($body->status == "PENDING") {
          if (!$is_on_hold) {
            $order->add_order_note(sprintf(__('OneCash payment is pending approval. OneCash Order ID: %s','woo_onecash'),$body->id));
            $order->update_status( 'on-hold' );
            update_post_meta($order_id,'_transaction_id',$body->id);
          }
        } elseif ($body->status == "FAILURE" || $body->status == "FAILED") {
          if (!$is_failed) {
            $order->add_order_note(sprintf(__('OneCash payment declined. Order ID from OneCash: %s','woo_onecash'),$body->id));
            $order->update_status( 'failed' );
          }
        } else {
          if (!$is_pending) {
            $order->add_order_note(sprintf(__('Payment %s. OneCash Order ID: %s','woo_onecash'),strtolower($body->status),$body->id));
            $order->update_status( 'pending' );
          }
        }
      }
      return $order_id;
    }

    /**
     * Build the onecash Authorization code
     *
     * @return  string Authorization code
     * @since 1.0.0
     */
    function get_onecash_authorization_code() {

      $token_id = ($this->settings['testmode'] != 'production') ? $this->settings['test-id'] : $this->settings['prod-id'];
      $secret_key = ($this->settings['testmode'] != 'production') ? $this->settings['test-secret-key'] : $this->settings['prod-secret-key'];

      return 'Basic '.base64_encode($token_id.':'.$secret_key);
    }

    /**
     * Default HTML for Pay Over Time message
     *
     * @return  string HTML markup
     * @since 1.0.0
     */
    function default_pay_over_time_message() {
      return '<h5 style="margin:10px 0;">How does OneCash work?</h5> <p>'.get_bloginfo('name').' and OneCash have teamed up to provide interest-free installment payments with no additional fees.</p>
        <table style="margin-top:10px">
        <tr>
        <td style="padding-left: 15px;"><h5 style="margin:0 0 5px;">4 Easy Payments</h5> OneCash offers customers the ability to pay in four equal payments over 60 days. All you need is a debit or credit card for instant approval.</td>
        </tr>
        <tr>
        <td style="padding-left:15px;">
        <h5 style="margin:0 0 5px;">Flexible Payment Options</h5>
        The credit or debit card you provide will be automatically charged on the due dates of your invoice or log in to the customer portal to repay with an alternative method.
        </td>
        </tr>
        <tr>
        <td></td>
        <td style="padding-left:15px;padding-top:5px;">
        <i>Click here to learn more about <a href="http://www.onecash.com/terms-and-conditions.html" target="_blank">OneCash</a>.</i>
        </td>
        </tr>
        </table>';
    }


    /**
     * Check which payment options are within the payment limits set by OneCash
     *
     * @param  float $ordertotal
     * @return  object containing available payment options
     * @since 1.0.0
     */
    function check_payment_options_for_amount($ordertotal) {
      $body = array(
        'orderAmount' => array(
          'amount' => number_format($ordertotal,2,'.',''),
          'currency' => get_woocommerce_currency()
          )
        );

      $args = array(
        'headers' => array(
          'Authorization' => $this->get_onecash_authorization_code(),
          'Content-Type' => 'application/json'
          ),
        'body' => json_encode($body)
        );

      $this->log( 'Check payment options url: '.($this->limiturl) );
      $this->log( 'Check payment options request: '.print_r($args,true) );

      // comment out the previous behaviour, which was to post to the server
      // the order amount and dynamically clear it
      // $response = wp_remote_post($this->limiturl,$args);

      // this is the same "dumb" GET code used to update the site config
      $response = wp_remote_get($this->limiturl,array('headers'=>array('Authorization' => $this->get_onecash_authorization_code())));

      $body = json_decode(wp_remote_retrieve_body($response));

      $this->log( 'Check payment options response: '.print_r($body,true) );

      return $body;
    }

    /**
     * Retrieve the payment limits set by OneCash and save to the gateway settings
     *
     * @since 1.0.0
     */
    function update_payment_limits() {
      // Get existing limits
      $settings = get_option('woocommerce_onecash_settings');

      $this->log( 'Updating payment limits requested');

      $response = wp_remote_get($this->limiturl,array('headers'=>array('Authorization' => $this->get_onecash_authorization_code())));
      $body = json_decode(wp_remote_retrieve_body($response));

      $this->log( 'Updating payment limits response: '.print_r($body,true) );

      if (is_array($body)) {
        foreach ($body as $paymenttype) {
          if ($paymenttype->type == "PBI") {
            // Min
            $settings['pay-over-time-limit-min'] = (is_object($paymenttype->minimumAmount)) ? $paymenttype->minimumAmount->amount : 0;
            // Max
            $settings['pay-over-time-limit-max'] = (is_object($paymenttype->maximumAmount)) ? $paymenttype->maximumAmount->amount : 0;

            $settings['accept_currency'] = ($paymenttype->accept_currency) ? $paymenttype->accept_currency : 0;

            $settings['accept_country'] = ($paymenttype->accept_country) ? $paymenttype->accept_country : 0;

            $settings['enabled_onecash'] = ($paymenttype->enabled) ? $paymenttype->enabled : 1;
          }
        }
      }
      update_option('woocommerce_onecash_settings',$settings);
      $this->init_settings();
    }

    /**
     * Notify OneCash that an order has shipped and send shipping details
     *
     * @param  int $order_id
     * @since 1.0.0
     */
    public function notify_order_shipped($order_id) {
      $payment_method = get_post_meta( $order->id, '_payment_method', true );
      if ($payment_method != "onecash") return;

      if( function_exists("wc_get_order") ) {
        $order = wc_get_order( $order_id );
      }
      else {
        $order = new WC_Order( $order_id );
      }

      // Skip if shipping not required
      if (!$order->needs_shipping_address()) return;

      // Get onecash order ID
      $onecash_id = $order->get_transaction_id();

      $body = array(
        'trackingNumber' => get_post_meta($order_id,'_tracking_number',true),
        'courier' => $order->get_shipping_method()
        );

      $args = array(
        'method' => 'PUT',
        'headers' => array(
          'Authorization' => $this->get_onecash_authorization_code(),
          'Content-Type' => 'application/json'
          ),
        'body' => json_encode($body)
        );

      $this->log( 'Shipping notification request: '.print_r($args,true) );

      $response = wp_remote_request($this->orderurl.'/'.$onecash_id.'/shippedstatus',$args);
      $responsecode = wp_remote_retrieve_response_code($response);

      $this->log( 'Shipping notification response: '.print_r($response,true) );

      if ($responsecode == 200) {
        $order->add_order_note(__('OneCash successfully notified of order shipment.', 'woo_onecash'));
      } elseif ($responsecode == 415) {
        $order->add_order_note(__('OneCash declined notification of order shipment. Order either couldn\'t be found or was not in an approved state.', 'woo_onecash'));
      } else {
        $order->add_order_note(sprintf(__('Unable to notify OneCash of order shipment. Response code: %s.', 'woo_onecash'),$responsecode));
      }
    }

    /**
     * Can the order be refunded?
     *
     * @param  WC_Order $order
     * @return bool
     */
    public function can_refund_order( $order ) {
      return $order && $order->get_transaction_id();
    }

    /**
     * Process a refund if supported
     *
     * @param  int $order_id
     * @param  float $amount
     * @param  string $reason
     * @return  boolean True or false based on success
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {

      if( function_exists("wc_get_order") ) {
        $order = wc_get_order( $order_id );
      }
      else {
        $order = new WC_Order( $order_id );
      }

      if ( ! $this->can_refund_order( $order ) ) {
        // $this->log( 'Refund Failed: No transaction ID' );
        return false;
      }

      $body = array(
        'amount' => array(
          'amount' => '-'.number_format($amount,2,'.',''),
          'currency' => $order->get_order_currency()
          ),
        'merchantRefundId' => ''
        );

      $args = array(
        'headers' => array(
          'Authorization' => $this->get_onecash_authorization_code(),
          'Content-Type' => 'application/json'
          ),
        'body' => json_encode($body)
        );

      $this->log( 'Refund request: '.print_r($args,true) );

      $response = wp_remote_post($this->orderurl.'/'.$order->get_transaction_id().'/refunds',$args);

      $body = json_decode(wp_remote_retrieve_body($response));
      $responsecode = wp_remote_retrieve_response_code($response);

      $this->log( 'Refund response: '.print_r($body,true) );

      if ($responsecode == 201 || $responsecode == 200) {
        $order->add_order_note(sprintf(__('Refund of $%s successfully sent to OneCash.', 'woo_onecash'),$amount));
        return true;
      } else {
        if (isset($body->message)) {
          $order->add_order_note(sprintf(__('Refund couldn\'t be processed: %s', 'woo_onecash'),$body->message));
        } else {
          $order->add_order_note(sprintf(__('There was an error submitting the refund to OneCash.', 'woo_onecash')));
        }
      }
      return false;
    }

    /**
     * Check the order status of all orders that didn't return to the thank you page or marked as Pending by OneCash
     *
     * @since 1.0.0
     */
    function check_pending_abandoned_orders() {
      // Get ON-HOLD orders that are "pending" at OneCash that need to be checked whether approved or denied
      $onhold_orders = get_posts(array('post_type'=>'shop_order','post_status'=>'wc-on-hold'));

      foreach ( $onhold_orders as $onhold_order ) {

        if( function_exists("wc_get_order") ) {
          $order = wc_get_order( $onhold_order->ID );
        }
        else {
          $order = new WC_Order( $onhold_order->ID );
        }

        //skip all orders that are not onecash
        $payment_method = get_post_meta( $onhold_order->ID, '_payment_method', true );
        if( $payment_method != "onecash" ) {
          continue;
        }

        // Check if there's an order ID. If not, it's not an onecash order
        //it is pending payment which has been approved and ssigned with ID
        $onecash_orderid = get_post_meta($onhold_order->ID,'_transaction_id',true);
        if (!$onecash_orderid) continue;

        // Check if the order is just created (prevent premature order note posting)
        if ( strtotime('now') - strtotime($order->order_date) < 120 ) continue;

        $this->log( 'Checking pending order for WC Order ID '.$order->ID.', OneCash Order ID '.$onecash_orderid);

        $response = wp_remote_get(
                $this->orderurl.'/'.$onecash_orderid,
                array(
                  'headers'=>array(
                    'Authorization' => $this->get_onecash_authorization_code()
                  )
                )
              );
        $body = json_decode(wp_remote_retrieve_body($response));

        $this->log( 'Checking pending order result: '.print_r($body,true) );

        // Check status of order
        if ($body->totalResults == 1) {
          if ($body->status == "APPROVED") {
            $order->add_order_note(sprintf(__('Checked payment status with OneCash. Payment approved. onecash Order ID: %s','woo_onecash'),$body->id));
            $order->payment_complete($body->id);
          } elseif ($body->status == "PENDING") {
            $order->add_order_note(__('Checked payment status with OneCash. Still pending approval.','woo_onecash'));
          } else {
            $order->add_order_note(sprintf(__('Checked payment status with OneCash. Payment %s. OneCash Order ID: %s','woo_onecash'),strtolower($body->status),$body->id));
            $order->update_status( 'failed' );
          }
        } else {

          if( strtotime('now') - strtotime($order->order_date) > 3600 ) {
            $order->add_order_note(sprintf(__('On Hold Order Expired')));
            $order->update_status( 'cancelled' );
          }
          else {
          }
        }
      }

      // Get PENDING orders that may have been abandoned, or browser window closed after approved
      $pending_orders = get_posts(array('post_type'=>'shop_order','post_status'=>'wc-pending'));

      foreach ( $pending_orders as $pending_order ) {

        if( function_exists("wc_get_order") ) {
          $order = wc_get_order( $pending_order->ID );
        }
        else {
          $order = new WC_Order( $pending_order->ID );
        }

        //skip all orders that are not onecash
        $payment_method = get_post_meta( $pending_order->ID, '_payment_method', true );
        if( $payment_method != "onecash" ) {
          continue;
        }

        $onecash_token = get_post_meta($pending_order->ID,'_onecash_token',true);
        // Check if there's a stored order token. If not, it's not an onecash order.
        if (!$onecash_token) continue;

        $this->log( 'Checking abandoned order for WC Order ID '.$order->ID.', OneCash Token '.$onecash_token);

        $response = wp_remote_get(
                $this->orderurl.'?token='.$onecash_token,
                array(
                  'headers'=>array(
                    'Authorization' => $this->get_onecash_authorization_code(),
                  )
                )
              );
        $body = json_decode(wp_remote_retrieve_body($response));

        $this->log( 'Checking abandoned order result: '.print_r($body,true) );

        if ($body->totalResults == 1) {
          // Check status of order
          if ($body->results[0]->status == "APPROVED") {
            $order->add_order_note(sprintf(__('Checked payment status with OneCash. Payment approved. OneCash Order ID: %s','woo_onecash'),$body->results[0]->id));
            $order->payment_complete($body->results[0]->id);
          } elseif ($body->results[0]->status == "PENDING") {
            $order->add_order_note(__('Checked payment status with OneCash. Still pending approval.','woo_onecash'));
            $order->update_status( 'on-hold' );
          } else {
            $order->add_order_note(sprintf(__('Checked payment status with OneCash. Payment %s. OneCash Order ID: %s','woo_onecash'),strtolower($body->results[0]->status),$body->results[0]->id));
            $order->update_status( 'failed' );
          }
        } else {

          if( strtotime('now') - strtotime($order->order_date) > 3600 ) {
            $order->add_order_note(sprintf(__('Pending Order Expired')));
            $order->update_status( 'cancelled' );
          }
          else {
          }
          //$order->add_order_note(__('Unable to confirm payment status with onecash. Please check status manually.','woo_onecash'));
        }

      }

    }

    /**
     * Check whether the cart amount is within payment limits
     *
     * @param  array $gateways Enabled gateways
     * @return  array Enabled gateways, possibly with onecash removed
     * @since 1.0.0
     */
    // Bug Fix when use Woocommerce Payment Gateway Based Fees - Plugin
    // woocommerce_available_payment_gateways - called twice and first total was zero in checkout. Used cart_contents_total
    function check_cart_within_limits($gateways) {
      if (is_admin()) return $gateways;

      global $woocommerce;
      $total = $woocommerce->cart->total;
      $amount = $woocommerce->cart->cart_contents_total;
      $accept_country = $this->settings['accept_country'];
      $accept_currency = $this->settings['accept_currency'];

      $pbi = ($total >= $this->settings['pay-over-time-limit-min'] && $total <= $this->settings['pay-over-time-limit-max']);

      $cart_content_total = ($amount >= $this->settings['pay-over-time-limit-min'] && $amount <= $this->settings['pay-over-time-limit-max']);

      $get_customer = WC()->session->get( 'customer' );

      //print_r($this->settings['accept_country']);

      if ($get_customer['country'] !=   $this->settings['accept_country']) {
        unset($gateways['onecash']);
      }

      if (!$this->settings['enabled_onecash']) {
        unset($gateways['onecash']);
      }

      if (!($pbi || $cart_content_total)) {
        unset($gateways['onecash']);
      }

      return $gateways;
    }

    /**
     * Logging method
     * @param  string $message
     */
    public static function log( $message ) {
      if ( self::$log_enabled ) {
        if ( empty( self::$log ) ) {
          self::$log = new WC_Logger();
        }
        self::$log->add( 'onecash', $message );
      }
    }

    function create_refund( $order_id ) {
      $order = new WC_Order( $order_id );
      $order_refunds = $order->get_refunds();

      if( !empty($order_refunds) ) {
        $refund_amount = $order_refunds[0]->get_refund_amount();
        if( !empty($refund_amount) ) {
          $this->process_refund($order, $refund_amount, "Admin Performed Refund");
        }
      }
    }
  }


  /**
   * Add the onecash gateway to WooCommerce
   *
   * @param  array $methods Array of Payment Gateways
   * @return  array Array of Payment Gateways
   * @since 1.0.0
   **/
  function add_onecash_gateway( $methods ) {
    $methods[] = 'WC_Gateway_onecash';
    return $methods;
  }
  add_filter('woocommerce_payment_gateways', 'add_onecash_gateway' );


  add_action('woocommerce_single_product_summary','onecash_show_pay_over_time_info_product_page',15);
  add_action('woocommerce_after_shop_loop_item_title','onecash_show_pay_over_time_info_index_page',15);

  /**
   * Showing the Pay Over Time information on the individual product page
   *
   * @since 1.0.0
   **/
  function onecash_show_pay_over_time_info_product_page() {
    $settings = get_option('woocommerce_onecash_settings');

    if (!isset($settings['enabled']) || $settings['enabled'] !== 'yes') return;

    if (isset($settings['show-info-on-product-pages']) && $settings['show-info-on-product-pages'] == 'yes' && isset($settings['product-pages-info-text'])) {

      global $post;

      if( function_exists("wc_get_product") ) {
        $product = wc_get_product($post->ID);
      }
      else {
        $product = new WC_Product($post->ID);
      }
      $price = $product->get_price();

      // Don't display if the product is a subscription product
      if ($product->is_type('subscription')) return;

      // Don't show if the string has [AMOUNT] and price is variable, if the amount is zero, or if the amount doesn't fit within the limits
      if ((strpos($settings['product-pages-info-text'],'[AMOUNT]') !== false && strpos($product->get_price_html(),'&ndash;') !== false) || $price == 0 || $settings['pay-over-time-limit-max'] < $price || $settings['pay-over-time-limit-min'] > $price) return;

      $amount = wc_price($price/4);
      $text = str_replace(array('[AMOUNT]'),$amount,$settings['product-pages-info-text']);
      echo '<p class="onecash-payment-info">'.$text.'</p>';
    }
  }

  function onecash_edit_variation_price_html( $price, $variation ) {
    $return_html = $price;
    $settings = get_option('woocommerce_onecash_settings');

    if (!isset($settings['enabled']) || $settings['enabled'] !== 'yes') return;

    if (isset($settings['show-info-on-product-pages']) && $settings['show-info-on-product-pages'] == 'yes' && isset($settings['product-pages-info-text'])) {
      $price = $variation->get_price();

      // Don't display if the parent product is a subscription product
      if ($variation->parent->is_type('subscription')) return;

      // Don't show if the amount is zero, or if the amount doesn't fit within the limits
      if ($price == 0 || $settings['pay-over-time-limit-max'] < $price || $settings['pay-over-time-limit-min'] > $price) return;

      $instalment_price_html = wc_price($price / 4);
      $onecash_paragraph_html = str_replace('[AMOUNT]', $instalment_price_html, $settings['product-pages-info-text']);
      $return_html .= '<p class="onecash-payment-info">' . $onecash_paragraph_html . '</p>';
    }
    return $return_html;
  }

  add_filter( 'woocommerce_variation_price_html', 'onecash_edit_variation_price_html', 10, 2);
  add_filter( 'woocommerce_variation_sale_price_html', 'onecash_edit_variation_price_html', 10, 2);

  /**
   * Showing the Pay Over Time information on the product index pages
   *
   * @since 1.0.0
   **/
  function onecash_show_pay_over_time_info_index_page() {
    $settings = get_option('woocommerce_onecash_settings');

    if (!isset($settings['enabled']) || $settings['enabled'] !== 'yes') return;

    if (isset($settings['show-info-on-category-pages']) && $settings['show-info-on-category-pages'] == 'yes' && isset($settings['category-pages-info-text'])) {

      global $post;
      if( function_exists("wc_get_product") ) {
        $product = wc_get_product($post->ID);
      }
      else {
        $product = new WC_Product($post->ID);
      }
      $price = $product->get_price();

      // Don't display if the product is a subscription product
      if ($product->is_type('subscription')) return;

      // Don't show if the string has [AMOUNT] and price is variable, if the amount is zero, or if the amount doesn't fit within the limits
      if ((strpos($settings['category-pages-info-text'],'[AMOUNT]') !== false && strpos($product->get_price_html(),'&ndash;') !== false) || $price == 0 || $settings['pay-over-time-limit-max'] < $price || $settings['pay-over-time-limit-min'] > $price) return;

      $amount = wc_price($price/4);
      $text = str_replace(array('[AMOUNT]'),$amount,$settings['category-pages-info-text']);
      echo '<p class="onecash-payment-info">'.$text.'</p>';
    }

  }

  /**
   * Call the cron task related methods in the gateway
   *
   * @since 1.0.0
   **/
  function onecash_do_cron_jobs() {
    $gateway = WC_Gateway_onecash::instance();
    $gateway->check_pending_abandoned_orders();
    $gateway->update_payment_limits();
  }
  add_action('onecash_do_cron_jobs','onecash_do_cron_jobs');

  /**
   * Call the notify_order_shipped method in the gateway
   *
   * @param int $order_id
   * @since 1.0.0
   **/
  function onecash_notify_order_shipped($order_id) {
    $gateway = WC_Gateway_onecash::instance();
    $gateway->notify_order_shipped($order_id);
  }
  add_action('woocommerce_order_status_completed','onecash_notify_order_shipped',10,1);

  /**
   * Check for the CANCELLED payment status
   * We have to do this before the gateway initalises because WC clears the cart before initialising the gateway
   *
   * @since 1.0.0
   */
  function onecash_check_for_cancelled_payment() {
    // Check if the payment was cancelled
    if (isset($_GET['status']) && $_GET['status'] == "CANCELLED" && isset($_GET['key']) && isset($_GET['token'])) {

      $gateway = WC_Gateway_onecash::instance();

      $order_id = wc_get_order_id_by_order_key($_GET['key']);

      if( function_exists("wc_get_order") ) {
        $order = wc_get_order( $order_id );
      }
      else {
        $order = new WC_Order( $order_id );
      }

      if ($order) {
        $gateway->log( 'Order '.$order_id.' payment cancelled by the customer while on the OneCash checkout pages.' );

        if( method_exists($order, "get_cancel_order_url_raw") ) {
          wp_redirect($order->get_cancel_order_url_raw());
        }
        else {
          wp_redirect($order->get_cancel_order_url());
        }
        exit;
      }
    }
  }
  add_action('template_redirect','onecash_check_for_cancelled_payment');

}

/* WP-Cron activation and schedule setup */

/**
 * Schedule onecash WP-Cron job
 *
 * @since 1.0.0
 **/
function onecash_create_wpcronjob() {
  $timestamp = wp_next_scheduled('onecash_do_cron_jobs');
  if ($timestamp == false) {
    wp_schedule_event(time(),'fifteenminutes','onecash_do_cron_jobs');
  }
}
register_activation_hook( __FILE__, 'onecash_create_wpcronjob' );

/**
 * Delete onecash WP-Cron job
 *
 * @since 1.0.0
 **/
function onecash_delete_wpcronjob(){
  wp_clear_scheduled_hook( 'onecash_do_cron_jobs' );
}
register_deactivation_hook( __FILE__, 'onecash_delete_wpcronjob' );

/**
 * Add a new WP-Cron job scheduling interval of every 15 minutes
 *
 * @param  array $schedules
 * @return array Array of schedules with 15 minutes added
 * @since 1.0.0
 **/
function onecash_add_fifteen_minute_schedule( $schedules ) {
    $schedules['fifteenminutes'] = array(
      'interval' => 15 * 60,
      'display' => __( 'Every 15 minutes', 'woo_onecash' )
    );
    return $schedules;
}
add_filter('cron_schedules', 'onecash_add_fifteen_minute_schedule');

/**
 * Add a new operations that will pull the lightbox pictures from AWS
 *
 * @since 1.2.1
 **/
function onecash_get_aws_assets() {

  // The Assets AWS directory - make sure it correct
  $onecash_assets_modal = dirname(__FILE__) . '/images/checkout/banner-large.jpg';
  $onecash_assets_modal_mobile = dirname(__FILE__) . '/images/checkout/banner-mobile.jpg';


  //$path = dirname(__FILE__) . '/images/checkout';

  // Create folder structure if not exist
  /*  if (!is_dir($path) || !is_writable($path)) {
    mkdir($path);
    }

    // By pass try catch, always log it if fails
    try {
      copy($onecash_assets_modal, $path . '/banner-large.png');
        copy($onecash_assets_modal_mobile, $path . '/modal-mobile.png');
  }
  catch (Exception $e) {
      // log now if fails
      $this->log('Error Updating assets from source. %s', $e->getMessage());
  }*/
}
add_action('wp_login', 'onecash_get_aws_assets');
?>
