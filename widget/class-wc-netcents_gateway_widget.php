<?php
class NC_Widget_Payment_Gateway extends WC_Payment_Gateway {

  public function __construct() {
      global $woocommerce;

      $this->id             = 'ncgw2';
      $this->icon           = apply_filters( 'woocommerce_wcCpg2_icon', '' );
      $this->has_fields     = false;
      $this->method_title   = __( 'NetCents Widget', 'wcwcCpg2' );
      $this->order_button_text  = __( 'Proceed to NetCents Gateway ', 'woocommerce' );

      // Load the form fields.
      $this->init_form_fields();

      // Load the settings.
      $this->init_settings();

      // Define user set variables.
      $this->title          = $this->settings['title'];
      $this->description    = $this->settings['description'];
      $this->api_key    = $this->settings['api-key'];
      $this->secret_key    = $this->settings['secret-key'];
      $this->merchant_id    = $this->settings['merchant-id'];
  		$this->instructions       = $this->get_option( 'instructions' );
  		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
  		$this->widget_access_data = '';
  		$this->widget_access_token = '';

      // Actions.
      add_action('woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'callback_handler'));
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      else
        add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
  }

    /* Admin Panel Options.*/
	function admin_options() {
		?>
		<h3><?php _e('NetCents Widget','ncgw2'); ?></h3>
    	<table class="form-table">
    		<?php $this->generate_settings_html(); ?>
		</table> <?php
    }

    function callback_handler() {

     	if ($_GET['signature'] && $_GET['signing'] && $_GET['data']) {
        $signature = $_GET['signature'];
        $exploded_parts = explode(",", $signature);
        $timestamp = explode("=", $exploded_parts[0])[1];
        $signature = explode("=", $exploded_parts[1])[1];
        $decoded_data = json_decode(base64_decode(urldecode($_GET['data'])), true);
        $hashable_payload = $timestamp . '.' . urldecode($_GET['data']);
        $hash_hmac = hash_hmac("sha256", $hashable_payload, $_GET['signing']);
        $timestamp_tolerance = 5;
        $date = new DateTime();
        $current_timestamp = $date->getTimestamp();
        $order = wc_get_order( $decoded_data['external_id'] );

        if ($hash_hmac != $signature) {
          header('Location:'. esc_url_raw( $order->get_cancel_order_url_raw()));
          exit();
        }

        if (($current_timestamp - $timestamp) / 60 > $timestamp_tolerance) {
          header('Location:'. esc_url_raw( $order->get_cancel_order_url_raw()));
          exit();
        }

        $order->payment_complete();
        $order->update_status( 'completed' );
        header('Location:'. $this->get_return_url( $order ));
        exit();

	   } else {
	      header('Location:'. esc_url_raw( $order->get_cancel_order_url_raw()));
        exit();
     }
   }

    /* Initialise Gateway Settings Form Fields. */
    public function init_form_fields() {
    	global $woocommerce;

    	$shipping_methods = array();

    	if ( is_admin() )
	    	foreach ( $woocommerce->shipping->load_shipping_methods() as $method ) {
		    	$shipping_methods[ $method->id ] = $method->get_title();
	    	}

        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'wcwcCpg2' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Custom Widget Gateway', 'wcwcCpg2' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'wcwcCpg2' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees dur4242424242424424ing checkout.', 'wcwcCpg2' ),
                'desc_tip' => true,
                'default' => __( 'NetCents', 'wcwcCpg2' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcwcCpg2' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcwcCpg2' ),
                'default' => __( 'Pay with your custom gateway widget. Account Balance, Credit Card , Bitcoins and Ethereum', 'wcwcCpg2' )
            ),
			'instructions' => array(
				'title' => __( 'Instructions', 'wcwcCpg2' ),
				'type' => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'wcwcCpg2' ),
				'default' => __( 'Instructions for Custom Payment Gateways 1.', 'wcwcCpg2' )
			),
            'api-key' => array(
                'title' => __( 'API Key', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'Your API key.', 'wcwcCpg1' ),
                'default' => __( '', 'wcwcCpg1' )
            ),'secret-key' => array(
                'title' => __( 'Secret Key', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'Your Secret key.', 'wcwcCpg1' ),
                'default' => __( '', 'wcwcCpg1' )
            ),'merchant-id' => array(
                'title' => __( 'Merchant Widget ID', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'Your Merchant Widget ID', 'wcwcCpg1' ),
                'default' => __( '', 'wcwcCpg1' )
            )
        );

    }

    public function get_cancel_order_url_raw( $redirect = '' ) {
      return apply_filters( 'woocommerce_get_cancel_order_url_raw', add_query_arg( array(
          'cancel_order' => 'true',
          'order'        => $this->get_order_key(),
          'order_id'     => $this->get_id(),
          'redirect'     => $redirect,
          '_wpnonce'     => wp_create_nonce( 'woocommerce-cancel_order' ),
      ), $this->get_cancel_endpoint() ) );
    }

    public function get_cancel_endpoint() {
      $cancel_endpoint = wc_get_page_permalink( 'cart' );

      if ( ! $cancel_endpoint ) {
          $cancel_endpoint = home_url();
      }

      if ( false === strpos( $cancel_endpoint, '?' ) ) {
          $cancel_endpoint = trailingslashit( $cancel_endpoint );
      }
      return $cancel_endpoint;
    }

    function request_access ( $order, $order_id ) {
      $cancel_url = esc_url_raw( $order->get_cancel_order_url_raw());
      $callback_url = get_bloginfo('url') . '/?wc-api=nc_widget_payment_gateway';
      $api_key = $this->api_key;
      $secret = $this->secret_key;
      $order_amount = $order->get_total();
      $order_currency = $order->get_order_currency();
      $merchant_id = $this->merchant_id;
      $date = new DateTime();

      $encrypted_string = wp_remote_post('https://merchant.net-cents.com/api/v1/widget/encrypt/', array(
      		'method' => 'POST',
    			'body' => array(
    				'external_id' => $order_id,
    				'amount' => $order_amount,
    				'currency_iso' => $order_currency,
    				'callback_url' => $callback_url,
            'merchant_id' => $api_key
    			),
    			'headers' => array(
    				'Authorization' => 'Basic ' . base64_encode( $api_key. ':' . $secret)
    			)
      	)
      );
	    $decoded_body = json_decode($encrypted_string['body']);
	    $response = wp_remote_get('https://merchant.net-cents.com/api/v1/widget/authorization?widget_id=' . $merchant_id . '&data=' . $decoded_body->token . '&origin=' . $_SERVER["HTTP_ORIGIN"]);
      $json_response = json_decode($response['body']);
	     if ($json_response->status == 200) {
          return $decoded_body->token;
	     } else {
          return false;
	     }
    }

    /* Process the payment and return the result. */
	function process_payment ( $order_id ) {
		global $woocommerce;
        $order = new WC_Order( $order_id );
        $access = $this->request_access($order, $order_id);
        if ($access != false) {
            try {
                return array(
                    'result' => 'success',
                    'redirect' => 'https://merchant.net-cents.com/merchant/widget?data=' . $access . '&widget_id=' . $this->merchant_id
                );
            } catch (Exception $ex) {
                wc_add_notice(  $ex->getMessage(), 'error' );
            }
            return array(
                'result' => 'Error processing request. Please try again later.',
                'redirect' => ''
            );
        } else {
            wc_add_notice(  'Error processing request. Please try again later.', 'error' );
        }

	}

    /* Output for the order received page.   */
	function thankyou() {
		echo $this->instructions != '' ? wpautop( $this->instructions ) : '';
	}

}
