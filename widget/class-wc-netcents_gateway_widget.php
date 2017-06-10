<?php
/**
 * WC wcCpg2 Gateway Class.
 * Built the wcCpg2 method.
 */
class WC_Custom_Payment_Gateway_2 extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     *
     * @return void
     */
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
        $this->callback_url    = $this->settings['callback-url'];
    		$this->instructions       = $this->get_option( 'instructions' );
    		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
    		$this->widget_access_data;
    		$this->widget_access_token;

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
	    if (isset($_GET["order_id"])) {
	        $id = $_GET["order_id"];
            $order = wc_get_order( $id );
            $order->payment_complete();
            $order->update_status( 'completed' );
            if ($_GET["status"] == 1) {
                header('Location:'. $this->get_return_url( $order ));
                exit();
            } else {
                header('Location:'. esc_url_raw( $order->get_cancel_order_url_raw()));
                exit();
            }
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
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcwcCpg2' ),
                'desc_tip' => true,
                'default' => __( 'Custom Widget Payment', 'wcwcCpg2' )
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
            ),'callback-url' => array(
                'title' => __( 'Callback URL', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'Where customer will be redirected after successful checkout', 'wcwcCpg1' ),
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
        $callback_url = 'http://localhost/netcents_wordpress/?wc-api=wc_custom_payment_gateway_2&';
        $api_key = $this->api_key;
        $secret = $this->secret_key;
        $order_amount = $order->get_total();
        $date = new DateTime();
        $parameters = array(
            'nonce' => $date->getTimestamp(),
            'merchant_id' => 3
        );
        $s = hash_hmac('sha256', base64_encode(JSON_encode($parameters)), $secret, true);
        $signature = preg_replace('/\s+/', '', base64_encode($s));
        $parameters = preg_replace('/\s+/', '', base64_encode(JSON_encode($parameters)));
        $data = array(
            'total_price' => $order_amount,
            'currency' => 'CAD',
            'payer_id' => $_POST['billing_email'],
            'callback_url' => $callback_url,
            'held_url' => 'http://localhost/netcents_wordpress/held/',
            'cancel_url' => $cancel_url,
            'order_id' =>  $order_id
        );
        $this->widget_access_data = preg_replace('/\s+/', '', base64_encode(JSON_encode($data)));
        $url = 'http://localhost:3000/merchant/authorize?api_key=' . $api_key;

        $ch = curl_init();
        $curlOpts = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded",
                "Origin: {$_SERVER["HTTP_ORIGIN"]}",
                "X-Parameters: {$parameters}",
                "X-Signature: {$signature}"
            ),
            CURLOPT_FOLLOWLOCATION => true
        );

        curl_setopt_array($ch, $curlOpts);
        $answer = curl_exec($ch);
        $json = json_decode($answer, true);

        if ($json['access_token'] != '') {
            return $json['access_token'];
        }
        return false;
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
                    'redirect' => 'http://localhost:3000/merchant/checkout?token=' . $access . '&data=' . $this->widget_access_data . '&api_key=' . $this->api_key
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
