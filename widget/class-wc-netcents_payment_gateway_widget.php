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
        $this->order_button_text  = __( 'Proceed to NetCents', 'woocommerce' );

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

        // Actions.
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
                'label' => __( 'Enable NetCents Widget', 'wcwcCpg2' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Title', 'wcwcCpg2' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcwcCpg2' ),
                'desc_tip' => true,
                'default' => __( 'NetCents Widget Payment', 'wcwcCpg2' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcwcCpg2' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcwcCpg2' ),
                'default' => __( 'Pay with NetCents. Account Balance, Credit Card , Bitcoins and Ethereum', 'wcwcCpg2' )
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

    function access_widget($order) {
        $order_amount = $order->get_total();
        $api_key = $this->api_key;
        $secret = $this->secret_key;
        $callback_url = $this->callback_url;
        $date = new DateTime();
        $parameters = array(
            'nonce' => $date->getTimestamp(),
            'merchant_id' => '1',
            'callback_url' => $callback_url
        );
        $s = hash_hmac('sha256', base64_encode(JSON_encode($parameters)), $secret, true);
        $signature = preg_replace('/\s+/', '', base64_encode($s));
        $parameters = preg_replace('/\s+/', '', base64_encode(JSON_encode($parameters)));
        $data = array(
            'total_price' => $order_amount,
            'currency' => 'CAD',
            'payer_id' => 'mehdi@glsys.com'
        );
        $data = preg_replace('/\s+/', '', base64_encode(JSON_encode($data)));
        ?>
        <script>
            var signature = '<?php echo $signature; ?>';
            var parameters = '<?php echo $parameters; ?>';
            var apiKey = '<?php echo $api_key; ?>';
            var data = '<?php echo $data; ?>';
        </script>
        <?php
    }

    /* Process the payment and return the result. */
	function process_payment ($order_id) {
		global $woocommerce;
        $order = new WC_Order( $order_id );
        ?><script>
        $('#place_order').on('click', function() {
            alert('were here');
        });
        </script> <?php
		$request = $this->access_widget($order);


		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
		);
	}


    /* Output for the order received page.   */
	function thankyou() {
		echo $this->instructions != '' ? wpautop( $this->instructions ) : '';
	}



}
