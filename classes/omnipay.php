<?php
	/**
	 * Gateway class
	 */
	class WC_omnipay_Hosted extends WC_Payment_Gateway {

		private $gateway 		= 'omnipay';
		public  $type 			= 'hosted';

		public function __construct() {

			$this->id     				= $this->gateway;
			$this->method_title   		= __(ucwords($this->gateway) , 'woocommerce_omnipay');
			$this->method_description 	= __(ucwords($this->gateway) . ' allows users to pay using Credit/Debit Cards', 'woocommerce_omnipay');
			$this->icon     			= str_replace('/classes', '/', plugins_url( '/', __FILE__ )) . '/img/visa_master_with_omnipay_logo.png';
			$this->has_fields    		= true;
			$this->order_button_text = __( 'Proceed to OmniPay', 'woocommerce' );

			$this->init_form_fields();

			$this->init_settings();

			// Get setting values
			$this->enabled       		= isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
			$this->title        		= isset( $this->settings['title'] ) ? sanitize_title($this->settings['title']) : 'OmniPay';
			$this->description       	= isset( $this->settings['description'] ) ? sanitize_textarea_field($this->settings['description']) : 'Pay via Credit / Debit Card with omnipay secure card processing.';
			$this->gateway 				= isset( $this->settings['gateway'] ) ? sanitize_text_field($this->settings['gateway']) : 'omnipay';

			// Hooks
			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action('woocommerce_receipt_omnipay', array($this, 'receipt_page'));
			add_action('woocommerce_api_wc_omnipay_hosted', array($this, 'process_response'));

		}

		/**
		 * Initialise Gateway Settings
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'enabled'		=> array(
					'title'   		=> __( 'Enable/Disable', 'woocommerce_omnipay' ),
					'label'   		=> __( 'Enable omnipay', 'woocommerce_omnipay' ),
					'type'    		=> 'checkbox',
					'description'  	=> '',
					'default'   	=> 'no'
				),

				'accessKey'	=> array(
					'title'   		=> __( 'Access Key', 'woocommerce_omnipay' ),
					'type'    		=> 'text',
					'description'  	=> __( 'Please enter the Access key for the merchant account.', 'woocommerce_omnipay' )
				),
				'secretKey'	=> array(
					'title'   		=> __( 'Secret Key', 'woocommerce_omnipay' ),
					'type'    		=> 'text',
					'description'  	=> __( 'Please enter the signature key of your merchant account. This can be changed in the <a href="'.esc_url("https://portal.omni-pay.com").'" target="_blank">MMS</a>', 'woocommerce_omnipay' )
				),
				'currencyCode' => array(
					'title' => __( 'Currency', 'woocommerce_omnipay' ),
					'description' => __( 'Please select the currency for transactions', 'woocommerce_omnipay' ),
					'type' => 'select',
					'default' => 'GBP',
					'options' => array(
							 '826' => 'GBP'
					)
				),
				'production_mode'			=> array(
					'title'   		=> __( 'Production mode', 'woocommerce_cardstream' ),
					'type'    		=> 'select',
					'options'       => array(
						'prod'    => 'production',
						'custom'    => 'custom'
					),
					'description'  	=> __( 'Use this option to change between using prduction/sandbox mode', 'woocommerce_cardstream' ),
					'default'   	=> 'Production'
				),
				'customForm' => array(
					'title' => __('Custom form URL', 'woocommerce_omnipay'),
					'type' => 'text',
					'description' => __('Enter the customer frontend Omnipay gateway payment url', 'woocommerce_omnipay')
				),
				'apiUrl' => array(
					'title' => __('API url', 'woocommerce_omnipay'),
					'type' => 'text',
					'description' => __('Enter the customer api url', 'woocommerce_omnipay')
				)
			);
		}


		/**
		 * Generate the form buton
		 */

		public function generate_omnipay_form($order_id) {
			if ( $this->type == 'hosted' ) {
				$this->generate_omnipay_hosted_form($order_id);
			} else {
				return null;
			}
		}

		

		public function generate_omnipay_hosted_form($order_id) {

			$order 		= new WC_Order( $order_id );
			// var_dump($order);die;
			$amount 	= $order->get_total() * 100;
			$redirect 	= add_query_arg('wc-api', 'WC_omnipay_Hosted', home_url( '/' ));

			$billing_address  = $order->billing_address_1 . "\n";
			if (isset($order->billing_address_2) && !empty($order->billing_address_2)) {
				$billing_address .= $order->billing_address_2 . "\n";
			}
			$billing_address .= $order->billing_city . "\n";
			$billing_address .= $order->billing_state;

			//make sure all mandatory fields have been configured with wc-omnipay admin panel
			if(!$this->settings['currencyCode'] 
					|| !$this->settings['secretKey'] 
					|| !$this->settings['accessKey'] 
					|| !$this->settings['production_mode']
					|| $this->settings['production_mode'] === 'custom' && (!$this->settings['customForm'] || !$this->settings['apiUrl'])
				){
				die( '<p style="color:red;font-size:24px;">Sorry, could not proceed to payment! Contact site admin. [MP#4001]</p>');
			}

			$gateway_frontend_url = 'https://gateway.omni-pay.com';
			$gateway_backend_url = 'https://kql31gmtxb.execute-api.eu-west-1.amazonaws.com/prod/api/v1/mypay';
			if($this->settings['production_mode'] == 'custom'){
				$gateway_frontend_url = $this->settings['customForm'];
				$gateway_backend_url = $this->settings['apiUrl'];
			}

			$firstName		 = $order->billing_first_name ? sanitize_text_field($order->billing_first_name) : "";
			$lastName			 = $order->billing_last_name ? sanitize_text_field($order->billing_last_name): "";
			$email				 = $order->billing_email ? sanitize_email($order->billing_email): "";
			$address_1		 = $order->billing_address_1 ? sanitize_text_field($order->billing_address_1): "";
			$address_2		 = $order->billing_address_2 ? sanitize_text_field($order->billing_address_2): "";
			$postcode			 = $order->billing_postcode ? sanitize_text_field($order->billing_postcode): "";
			$city				 	 = $order->billing_city ? sanitize_text_field($order->billing_city): "";

			$paylooad = [
				"amount" => $amount, //mandatory
				"currency_code" => (int)$this->settings['currencyCode'], //mandatory//preg_replace('/[^0-9]+/', '', )
				"user_order_ref" => "$order_id", //mandatory
				"meta_data" => ["redirect_url" => esc_url($redirect),"from"=>"WP_WC"],
				"items" =>  [['woocommerce_order_id' => $order_id]],
				"shoppers" => [
						'first_name'=>$firstName,
						'last_name'=>$lastName,
						'email'=>$email,
						'address' => $address_1.",".$address_2.",".$postcode.",".$city
					]
			];
			
			$accessKey = sanitize_text_field($this->settings['accessKey']);
			$this->settings['secretKey'];
			$signature = $this->createSignature($paylooad, sanitize_text_field($this->settings['secretKey']));
			$omnipay_header = 'MP '.$accessKey.':'.$signature;
			
			$args = array(
				'headers' => array(
						'Authorization' => $omnipay_header,
						'Content-Type' => 'application/json'
				),
				'body' => json_encode($paylooad)
			);
			$response = wp_remote_post( $gateway_backend_url."/internal/session/create", $args );
			$response_code = wp_remote_retrieve_response_code($response);
			$response_body = wp_remote_retrieve_body( $response );
			if($response_code == 200){
				$response_body = json_decode( $response_body );
				$session_id = $response_body->data->session_id;
				header("Location:".$gateway_frontend_url."/pay/$session_id");die;
			}else{
				echo "Could not process the payment, please try again!";
			} 
		}
		

		function createSignature(array $data, $key) {
			
			if (!$key || !is_string($key) || $key === '' || !$data || !is_array($data)) {
					return null;
			}
			return hash_hmac('sha256',json_encode($data,JSON_UNESCAPED_SLASHES),$key,FALSE);
		
		}


		function process_payment( $order_id ) {

			$order = new WC_Order($order_id);

			return array(
				'result'    => 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);

		}


		/**
		 * receipt_page
		 */
		function receipt_page( $order ) {
				$this->generate_omnipay_form($order);
		}



		/**
		 * Check for omnipay Response
		 */
		function process_response() {

			global $woocommerce;

			$responseCode 	  = preg_replace('/[^0-9]+/', '', $_POST['responseCode']);
			$responseOrderRef = sanitize_text_field($_POST['orderRef']);
			$responsetransactionUnique = sanitize_text_field($_POST['transactionUnique']);
			$responseAmount = preg_replace('/[^0-9]+/', '', $_POST['amount']);
			$responseMessage = sanitize_textarea_field($_POST['responseMessage']);

			if (isset($responseCode)) {

				$order	= new WC_Order((int) $responseOrderRef);

				if ($order->status == 'completed') {

				} else {

					$orderNotes  =  "\r\nResponse Code : {$responseCode}\r\n";
					$orderNotes .=  "Message : {$responseMessage}\r\n";
					$orderNotes .=  "Amount Received : " . number_format($responseAmount / 100, 2, '.', ',') . "\r\n";
					$orderNotes .=  "Unique Transaction Code : {$responsetransactionUnique}";

					if ($responseCode === '0') {

						$order->add_order_note( __(ucwords( $this->gateway ).' payment completed.' . $orderNotes, 'woocommerce_omnipay') );
						$order->payment_complete();

						wp_safe_redirect( $this->get_return_url( $order ) );
						exit;

					} else {

						$message = __('Payment error: ', 'woothemes') . $responseMessage;

						if (method_exists($woocommerce, add_error)) {
							$woocommerce->add_error($message);
						} else {
							wc_add_notice($message, $notice_type = 'error');
						}

						$order->add_order_note( __(ucwords( $this->gateway ).' payment failed.' . $orderNotes, 'woocommerce_omnipay') );
						wp_safe_redirect( $order->get_cancel_order_url( $order ) );
						exit;

					}

				}

			} else {
				exit;
			}

		}

	}
