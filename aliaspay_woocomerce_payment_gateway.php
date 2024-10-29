<?php
/*
Plugin Name: Alias Pay - WooCommerce Gateway
Plugin URI: https://www.aliaspay.io/
Description: Allow your customers to shop online without ever revealing their credit card number.
Version: 1.0
Author: Alias Pay, Inc.
License: GPL2
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'aliapay_wc_payment_gateway_init', 0 );
function aliapay_wc_payment_gateway_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'aliaspay_gateway_class.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'aliapay_wc_payment_gateway' );
	function aliapay_wc_payment_gateway( $methods ) {
		$methods[] = 'AliasPay_WC_Payment_Gateway';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aliapay_wc_payment_gateway_links' );
function aliapay_wc_payment_gateway_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'aliapay_wc_payment_gateway' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}