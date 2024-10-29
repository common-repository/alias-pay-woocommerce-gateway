<?php
/* AliasPay Payment Gateway Class */
class AliasPay_WC_Payment_Gateway extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "aliapay_wc_payment_gateway";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Alias Pay", 'aliapay_wc_payment_gateway' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Alias PayPayment Gateway Plug-in for WooCommerce", 'aliapay_wc_payment_gateway' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Alias Pay", 'aliapay_wc_payment_gateway' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = 'https://www.aliaspay.io/img/logos/aliaspay-logo_tagline.svg';

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} // End __construct()

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'aliapay_wc_payment_gateway' ),
				'label'		=> __( 'Enable this payment gateway', 'aliapay_wc_payment_gateway' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'aliapay_wc_payment_gateway' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'aliapay_wc_payment_gateway' ),
				'default'	=> __( 'Alias Pay', 'aliapay_wc_payment_gateway' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'aliapay_wc_payment_gateway' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'aliapay_wc_payment_gateway' ),
				'default'	=> __( 'Pay securely without revealing your credit card.', 'aliapay_wc_payment_gateway' ),
				'css'		=> 'max-width:350px;'
			),
			'merchant_client_token' => array(
				'title'		=> __( 'Alias Pay Client Token', 'aliapay_wc_payment_gateway' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the client token provided by Alias Pay when you signed up for an account.', 'aliapay_wc_payment_gateway' ),
			),
			'environment' => array(
				'title'		=> __( 'Alias Pay Test Mode', 'aliapay_wc_payment_gateway' ),
				'label'		=> __( 'Enable Test Mode', 'aliapay_wc_payment_gateway' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'aliapay_wc_payment_gateway' ),
				'default'	=> 'no',
			)
		);		
	}
	
	// Submit payment and handle response
	public function process_payment( $order_id ) {
		global $woocommerce;
		
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );
		
		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment ) 
						   ? 'https://api.aliaspay.io/wallet/purchase'
						   : 'https://api.aliaspay.io/wallet/purchase?sandbox=true'; // sandbox environment for testing

		// This is where the fun stuff begins
		$payload = array(			
			// Shipping Information
			"x_ship_to_first_name" 	=> $customer_order->shipping_first_name,
			"x_ship_to_last_name"  	=> $customer_order->shipping_last_name,
			"x_ship_to_company"    	=> $customer_order->shipping_company,
			"x_ship_to_address"    	=> $customer_order->shipping_address_1,
			"x_ship_to_city"       	=> $customer_order->shipping_city,
			"x_ship_to_country"    	=> $customer_order->shipping_country,
			"x_ship_to_state"      	=> $customer_order->shipping_state,
			"x_ship_to_zip"        	=> $customer_order->shipping_postcode,
		);

		$payloadJSON->amount = $customer_order->order_total;
		$payloadJSON->login = $_POST['alias_spender_username'];
		$payloadJSON->aliasCardNumber = $_POST['alias_spender_card'];
		$payloadJSON->merchantClientToken = $this->merchant_client_token;
		$payloadJSON->customerInvoiceNumber = str_replace( "#", "", $customer_order->get_order_number() );
		$payloadJSON->customerId = $customer_order->user_id;
		$payloadJSON->customerIPAddress = $_SERVER['REMOTE_ADDR'];
		$payloadJSON->websiteUrl = $_SERVER['HOST'];

		// Send this payload to Alias Pay for processing
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => $payloadJSON,
			'timeout'   => 120, // 2 minutes for a response from card holder
		) );

		if ( is_wp_error( $response ) ) 
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'aliapay_wc_payment_gateway' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'AliasPay\'s Response was empty.', 'aliapay_wc_payment_gateway' ) );
			
		// Retrieve the body's resopnse if no errors found
		$response_body = wp_remote_retrieve_body( $response );

		// Parse json response body
		$responseObj = json_decode($response_body);

		// Test the code to know if the transaction went through or not.
		if ($responseObj->success == true) {
			// Payment has been successful
			$customer_order->add_order_note( __( 'Alias Pay payment completed.', 'aliapay_wc_payment_gateway' ) );
												 
			// Mark order as Paid
			$customer_order->payment_complete();

			// Empty the cart (Very important step)
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			// Transaction was not succesful
			// Add notice to the cart
			wc_add_notice( $responseObj->err, 'error' );
			// Add note to the order for your reference
			$customer_order->add_order_note( 'Error: '.  $responseObj->err );
		}

	}
	
	public function payment_fields() {
		echo "
			<div>
				<label for='alias_spender_username'>Username or email</label>
				<br>
				<input name='alias_spender_username' type='text' placeholder='' size='15'/>
			</div>
			<br>
			<div>
				<label for='alias_spender_card'>Alias Card</label>
				<br>
				<input name='alias_spender_card' type='text' placeholder='' size='15'/>
			</div>
			<br>
			<a target='_blank' href='https://www.aliaspay.io'>Download the mobile app to create an alias card.</a>
		";
	}

	// Do not validate fields
	public function validate_fields() {
		return false;
	}
	
	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

} // End of AliasPay_WC_Payment_Gateway