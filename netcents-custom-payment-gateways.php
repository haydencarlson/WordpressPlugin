<?php
/*
Plugin Name: NetCents Payment Gateway
Plugin URI: http://wordpress.org/plugins/wooCommerce-custom-payment-gateways/
Description: Add Custom Payment Gateways for WooCommerce.
Version: 1.0.0
Author: HC
Author URI: https://github.com/haydencarlson
License: GPLv2
*/

//Additional links on the plugin page
add_filter( 'plugin_row_meta', 'wcCpg_register_plugin_links', 10, 2 );
function wcCpg_register_plugin_links($links, $file) {
	$base = plugin_basename(__FILE__);
	if ($file == $base) {

	}
	return $links;
}



/* WooCommerce fallback notice. */
function woocommerce_cpg_fallback_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Custom Payment Gateways depends on the last version of %s to work!', 'wcCpg' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

/* Load functions. */
function custom_payment_gateway_load() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'woocommerce_cpg_fallback_notice' );
        return;
    }
   
    function wc_Custom_add_gateway( $methods ) {
        $methods[] = 'WC_Custom_Payment_Gateway_1';
        $methods[] = 'WC_Custom_Payment_Gateway_2';
        return $methods;
    }
	add_filter( 'woocommerce_payment_gateways', 'wc_Custom_add_gateway' );
	
	
    // Include the WooCommerce Custom Payment Gateways classes.
    require_once plugin_dir_path( __FILE__ ) . 'api/class-wc-netcents_payment_gateway_api.php';
    require_once plugin_dir_path( __FILE__ ) . 'widget/class-wc-netcents_payment_gateway_widget.php';
}

add_action( 'plugins_loaded', 'custom_payment_gateway_load', 0 );



/* Adds custom settings url in plugins page. */
function wcCpg_action_links( $links ) {
    $settings = array(
		'settings' => sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways' )
		)
    );

    return array_merge( $settings, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wcCpg_action_links' );


?>