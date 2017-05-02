<?php
/**
 * WC wcCpg1 Gateway Class.
 * Built the wcCpg1 method.
 */
class WC_Custom_Payment_Gateway_1 extends WC_Payment_Gateway {


    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id             = 'ncgw1';
        $this->icon           = '';
        $this->has_fields     = true;
        $this->method_title   = __( 'NetCents Merchant API', 'ncgwApi' );
        $this->supports[] = 'default_credit_card_form';
        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->settings['title'];
        $this->description    = $this->settings['description'];
        $this->api_key    = $this->settings['api-key'];
        $this->secret_key    = $this->settings['secret-key'];
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );

        // Actions.
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        else
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

        // Hooks.
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

    }


    /* Admin Panel Options.*/
	function admin_options() {
		?>
		<h3><?php _e('Pay with NetCents API','wcwcCpg1'); ?></h3>
    	<table class="form-table">
    		<?php $this->generate_settings_html(); ?>
		</table> <?php
    }

    public function admin_notices() {
        global $netcents_for_wc, $pagenow, $wpdb;

        if ( $this->enabled == 'no') {
            return false;
        }

        // Check for API Keys
        if ( ! $this->settings['api_key'] && ! $this->settings['secret_key'] ) {
            echo '<div class="error"><p>' . __( 'Beanstream needs Merchand id & API pass Keys to work, please find your Merchand id and API pass Keys in the <a href="https://www.beanstream.com/admin/sDefault.asp" target="_blank">Beanstream accounts section</a>.', 'beanstream-for-woocommerce' ) . '</p></div>';
            return false;
        }

        // Force SSL on production
        if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' ) {
            echo '<div class="error"><p>' . __( 'Beanstream needs SSL in order to be secure. Read more about forcing SSL on checkout in <a href="http://docs.woothemes.com/document/ssl-and-https/" target="_blank">the WooCommerce docs</a>.', 'beanstream-for-woocommerce' ) . '</p></div>';
            return false;
        }

    }

    function payment_fields() {
        $this->credit_card_form();
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
                'title' => __( 'Enable/Disable', 'wcwcCpg1' ),
                'type' => 'checkbox',
                'label' => __( 'Enable your gateway API Payment Method', 'wcwcCpg1' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcwcCpg1' ),
                'desc_tip' => true,
                'default' => __( 'Your API Gateway', 'wcwcCpg1' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcwcCpg1' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcwcCpg1' ),
                'default' => __( 'Description for API payment method.', 'wcwcCpg1' )
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
			)
        );

    }

    /* Process the payment and return the result. */
	function process_payment ($order_id) {
        $order = new WC_Order( $order_id );
		global $woocommerce;
		$paramsData = $_POST;
        $order_amount = $order->get_total();
        $payment_attempt = $this->attempt_payment($order_amount, $paramsData);
        if ($payment_attempt != false) {
            wc_add_notice( __('Payment error: ', 'woothemes') . $payment_attempt['message'], 'error' );
            return;
        } else {
            $order->payment_complete();
            $order->update_status( 'completed' );
            return array(
                'result' 	=> 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }

	}
    function attempt_payment ($order_amount, $paramsData) {
        $number = str_replace(' ', '', $paramsData['ncgw1-card-number']);
        $date = array_map('trim', explode('/', $paramsData['ncgw1-card-expiry']));
        $api_key = $this->api_key;
        $secret_key = $this->secret_key;
        $postData = json_encode(array(
            'first_name' => $paramsData['billing_first_name'],
            'last_name' => $paramsData['billing_last_name'],
            'email' => $paramsData['billing_email'],
            'address' => $paramsData['billing_address_1'],
            'city' => $paramsData['billing_city'],
            'state' => $paramsData['billing_state'],
            'country' => $paramsData['billing_country'],
            'zip' => $paramsData['billing_postcode'],
            'phone' => $paramsData['billing_phone'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'currency' => "CAD",
            'card' => array(
                'number' => $number,
                'expiry_month' => $date[0],
                'expiry_year' => $date[1],
                'ccv' => $paramsData['ncgw1-card-cvc']
            ),
            'invoice_number' => '1',
            'amount' => $order_amount
        ));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://localhost:3000/api/v1/payment");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $api_key . ":" . $secret_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        $json = json_decode($server_output, true);
        if ($json['status'] == 0) {
            return $json;
        }
        return false;
    }
    /* Output for the order received page.   */
	function thankyou() {
		echo $this->instructions != '' ? wpautop( $this->instructions ) : '';
	}

}
