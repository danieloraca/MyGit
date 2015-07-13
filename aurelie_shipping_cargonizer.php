<?php
/*
    Plugin Name: Aurelie Shipping Cargonizer
    Plugin URI: http://aurelie.no
    Description: Aurelie Shipping Cargonizer
    Version: 1
    Author: Daniel Oraca
    Author URI:     
 */

/**
 * Check if WooCommerce is active
 **/

add_action('plugins_loaded', 'init_aurelie_shipping', 0);

function init_aurelie_shipping() {
    if ( ! class_exists( 'WC_Shipping_Method' ) ) return;
    
    class aurelie_Shipping extends WC_Shipping_Method {
    	
    	function __construct() { 
			$this->id = 'aurelie_shipping';
			$this->method_title = __( 'Aurelie Shipping', 'woocommerce' );
		
			$this->admin_page_heading 	= __( 'Weight based shipping', 'woocommerce' );
			$this->admin_page_description 	= __( 'Define shipping by weight and country', 'woocommerce' );

			//cargonizer info
			$this->crg_api_key 				= "5ef3837dd827b5431c8ce8f542b0aa8cd6844793"; 
			$this->crg_sender_id 			= "1051";
			$this->crg_consignment_url 		= "http://sandbox.cargonizer.no/consignments.xml";
			$this->crg_transport_url 		= "http://sandbox.cargonizer.no/transport_agreements.xml";
			$this->crg_transport_cost_url	= "http://sandbox.cargonizer.no/consignment_costs.xml";
			$this->crg_data 				= NULL;
			$this->crg_debug 				= 0;
			$this->crg_package_number		= NULL;
			$this->crg_save_response_as_text= TRUE;
			
			//data to be send to cargonizer
			$this->shipping_country 		= NULL;
			$this->shipping_first_name 		= NULL;
			$this->shipping_last_name 		= NULL;
			$this->shipping_company 		= NULL;
			$this->shipping_address_1 		= NULL;
			$this->shipping_address_2 		= NULL;
			$this->shipping_postcode 		= NULL;
			$this->shipping_city 			= NULL;
			$this->shipping_state 			= NULL;
			$this->shipping_email			= NULL;
			$this->shipping_mobile			= NULL;
			$this->billing_phone			= NULL;
			$this->customer_number			= NULL;
			$this->order_id					= NULL;
			
			$this->items 					= array();
			
			$this->shipping_method_md5		= NULL;
			$this->shipping_method_name		= NULL;
			$this->alert_method				= array();
			$this->mypack_location			= NULL;
			$this->total_price				= NULL;
			
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'sync_countries' ) );
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( &$this, 'process_admin_options' ) );

			add_action( 'woocommerce_checkout_order_processed', array($this, 'extra_checkout' ) );
			
			load_plugin_textdomain('aurelie_shipping', PLUGINDIR.'/aurelie_shipping_cargonizer/languages','aurelie_shipping_cargonizer/languages');
			
			
			$this->init();      
    	}
    
		function init() {
			$this->init_form_fields();
			$this->init_settings();

			$this->enabled			= $this->settings['enabled'];
			$this->title			= $this->settings['title'];
			$this->country_group_no	= $this->settings['country_group_no'];
			$this->sync_countries	= $this->settings['sync_countries'];
			$this->availability		= 'specific';
			$this->countries		= $this->settings['countries'];
			$this->type				= 'order';
			$this->tax_status		= $this->settings['tax_status'];
			$this->fee				= $this->settings['fee'];
			
			//$this->options			= isset( $this->settings['options'] ) ? $this->settings['options'] : '';
			//$this->options			= (array) explode( "\n", $this->options );
			
			$this->set_options();
						
    	}
    	
    	function set_options(){
    		/**
    		 * set the checked shipping methods
    		 * also, set 'eVarsling' state 
    		 */

    		$this->options = array();
    		
    		for ($k = 1; $k <= 4; $k++){
	    		if ( $this->settings["shipping_option_$k"] == 'yes' ){
	    			$this->options[] = $this->form_fields["shipping_option_$k"]['label'];
	    		}
    		}
    		
    		if ($this->settings["without_cargonizer"] == 'yes'){
    			$this->options[] = $this->form_fields["without_cargonizer"]['label'];
    		}
    		
    		$this->bring_notification = ( $this->settings["bring_notification"] == "yes" ) ? TRUE : FALSE;
    	}
    	
    function init_form_fields() {
    	global $woocommerce;

        	$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
					'type' 			=> 'checkbox',
					'label' 		=> __( 'Enable this shipping method', 'woocommerce' ),
					'default' 		=> 'no',
				),
				'title' => array(
					'title' 		=> __( 'Method Title', 'woocommerce' ),
					'type' 			=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'		=> __( 'Aurelie Shipping', 'woocommerce' ),
				),
				'crg_api_key_p' 	=> array(
					'title'			=> __( 'Cargonizer API Key (Production)', 'aurelie_shipping' ),
					'type'			=> 'text',
					'description'	=> ''
				),
				'crg_sender_id_p' 	=> array(
					'title'			=> __( 'Cargonizer Sender ID (Production)', 'aurelie_shipping' ),
					'type'			=> 'text',
					'description'	=> ''
				),
				'crg_api_key_s' 	=> array(
					'title'			=> __( 'Cargonizer API Key (Sandbox)', 'aurelie_shipping' ),
					'type'			=> 'text',
					'description'	=> ''
				),
				'crg_sender_id_s' 	=> array(
					'title'			=> __( 'Cargonizer Sender ID (Sandbox)', 'aurelie_shipping' ),
					'type'			=> 'text',
					'description'	=> ''
				),
				'working_env'		=> array(
					'title'			=> __( 'Sandbox or Production Environment', 'aurelie_shipping'),
					'type'			=> 'select',
					'desc_tip'	=>  __( 'This option is important as it will affect how consignments are send to Cargonizer', 'aurelie_shipping' ),
					'options'=> array('sandbox' => 'Sandbox Environment', 'production' => 'Production Environment')
				),
				/*'tax_status' => array(
					'title' 		=> __( 'Tax Status', 'woocommerce' ),
					'type' 			=> 'select',
					'description' 	=> '',
					'default' 		=> 'taxable',
					'options'		=> array(
						'taxable' 	=> __( 'Taxable', 'woocommerce' ),
						'none' 		=> __( 'None', 'woocommerce' ),
					),
				),
				'fee' => array(
					'title' 		=> __( 'Handling Fee', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> __( 'Fee excluding tax. Enter an amount, e.g. 2.50. Leave blank to disable.', 'woocommerce' ),
					'default'		=> '',
				),*/
				/**
				 * 'options' is replaced by 'shipping_option_X'
				 
				'options' => array(
					'title' 		=> __( 'Shipping Rates', 'woocommerce' ),
					'type' 			=> 'textarea',
					'description'	=> __( 'Set available shipping rates. Example: <code>Henting i butikk (MyPack)</code>. Example: <code>Post i butikk - Postkontor</code>. Example: <code>Bring</code>.', 'woocommerce' ),
					'default'		=> '',
				),
				*/

				'shipping_option_1' => array(
					'title'			=> __( 'Free Shipping', 'aurelie_shipping' ),
					'label'			=> __( 'Fri frakt', 'aurelie_shipping' ),
					'type'			=> 'checkbox',
					'description'	=> 'Package will be sent with Bring/Posten with the option "Post i butikk - Postkontor"'
					//'product'		=> ''
				),
				'shipping_option_2' => array(
					'title'			=> __( 'Tollpost Globe (MyPack)', 'aurelie_shipping' ),
					'label'			=> __( 'MyPack', 'aurelie_shipping' ),
					'type'			=> 'checkbox',
					'description'	=> __( '', 'aurelie_shipping' ),
					//'product'		=> 'Tollpost Globe'
				),
				'shipping_option_3' => array(
					'title'			=> __( 'Bring (På Døren)', 'aurelie_shipping' ),
					'label'			=> __( 'På Døren', 'aurelie_shipping' ),
					'type'			=> 'checkbox',
					'description'	=> __( '', 'aurelie_shipping' ),
					//'product'		=> 'Bring',
				),
				'shipping_option_4' => array(
					'title'			=> __( 'Bring (Minipakke)', 'aurelie_shipping' ),
					'label'			=> __( 'Minipakke', 'aurelie_shipping' ),
					'type'			=> 'checkbox',
					'description'	=> __( '', 'aurelie_shipping' ),
					//'product'		=> 'Bring',
				),
				'bring_notification'=> array(
					'title'			=> __( '', 'aurelie_shipping' ),
					'label'			=> __( 'Use Bring Notification Service (eVarsling)', 'aurelie_shipping'),
					'type'			=> 'checkbox',
					'description'	=> __( 'When checked, it allows buyer to enter his mobile phone number or email address to receive notifications', 'aurelie_shipping')
				),
				'without_cargonizer'=> array(
					'title'			=> __( 'Small Packages', 'aurelie_shipping' ),
					'label'			=> __( 'Small Packages', 'aurelie_shipping' ),
					'type'			=> 'checkbox',
					'description'	=> __( 'This shipping method is using Bring Småpakker to calculate shipping cost based on information provided bellow', 'aurelie_shipping' )
				),
				'wout_c_per_pk'		=> array(
					'title'			=> __( 'Price per package', 'aurelie_shipping' ),
					'label'			=> __( 'Price per package', 'aurelie_shipping' ),
					'type'			=> 'text',
					'description'	=> ''
    			),
				'wout_c_per_kg'		=> array(
					'title'			=> __( 'Price per kilogram', 'aurelie_shipping' ),
					'label'			=> __( 'Price per kilogram', 'aurelie_shipping' ),
					'type'			=> 'text',
					'description'	=> ''
    			),
    			'pickup_address'	=> array(
					'title'			=> __( 'Pickup Address', 'aurelie_shipping' ),
    				'label'			=> __( 'Pickup Address', 'aurelie_shipping' ),
    				'type'			=> 'text',
    				'desc_tip'		=> __( 'This is the addess that will be used on Google Maps' )
				),
				'pickup_postcode'	=> array(
					'title'			=> __( 'Pickup Postcode', 'aurelie_shipping' ),
    				'label'			=> __( 'Pickup Postcode', 'aurelie_shipping' ),
    				'type'			=> 'text',
    				'desc_tip'		=> __( 'This is the postcode that will be used on Google Maps' )
				),
				'pickup_city'		=> array(
					'title'			=> __( 'Pickup City', 'aurelie_shipping' ),
    				'label'			=> __( 'Pickup City', 'aurelie_shipping' ),
    				'type'			=> 'text',
    				'desc_tip'		=> __( 'This is the city that will be used on Google Maps' )
				),
			);  
			
    	}
    	/*
	    function display_country_groups() {
	        global $woocommerce;  
	        $number = $this->country_group_no;
	        for($counter = 1; $number >= $counter; $counter++) {
	
	            $this->form_fields['countries'.$counter] =  array(
	                    'title'     => sprintf(__( 'Country Group %s', 'woocommerce' ), $counter),
	                    'type'      => 'multiselect',
	                    'class'     => 'chosen_select',
	                    'css'       => 'width: 450px;',
	                    'default'   => '',
	                    'options'   => $woocommerce->countries->countries
	            );
	        }    
	    }*/
		    
	    function calculate_shipping($package = array()) {
	        global $woocommerce;
            $rates      = $this->get_rates();
            $weight     = $woocommerce->cart->cart_contents_weight;
            $taxable    = ($this->tax_status == 'taxable') ? true : false;

            //echo 'hasas'; die();
            foreach ($rates as $a){
            	$rate = array(
					'id'        => $this->id . "__" . md5(trim($a['name'])),
					'label'     => $a['name'],
					'cost'      => $a['value'],
					'taxes'     => '',
					'calc_tax'  => 'per_order'
				);
        		$this->add_rate( $rate );
            }	        
	    }
	    
	    public function get_rates(){
	    	global $woocommerce;
	    	$rates = array();

	    	if ( sizeof( $this->options ) > 0){
	    		$k = 0; 
		    	foreach ( $this->options as $option => $value ) {
		    		$rates[$k]['name'] = $value;
		    		$rates[$k]['value'] = "1";
		    		$k++;
		    	}
	    	}
	    	return $rates;
	    }
	    
	    private function product_get_dimensions($string){
	    	$a = explode(" ", $string);
	    	$dimensions['length'] = $a[0];
	    	$dimensions['width'] = $a[2];
	    	$dimensions['height'] = $a[4];
	    	return $dimensions;
	    }
	    
	    public function get_rate_value($name){
	    	global $woocommerce;
			//echo 'DANIEL';
			//require_once("include/cargonizer.php");
			$crg_api_key = "5ef3837dd827b5431c8ce8f542b0aa8cd6844793";
			$crg_sender_id = "1051";
			
			$crg_consignment_url = "http://sandbox.cargonizer.no/consignment_costs.xml";
			$crg_transport_url = "http://sandbox.cargonizer.no/transport_agreements.xml";
			
			$debug = 0;
			
			$crg = new cargonizer($crg_api_key,$crg_sender_id,$crg_consignment_url);
			
			//now get the values to be send to cargonizer
	
			$checkout = $woocommerce->checkout();
			
			$shipping_country = $checkout->get_value('shipping_country');
			
			$shipping_first_name = $checkout->get_value('shipping_first_name');
			
			$shipping_last_name = $checkout->get_value('shipping_last_name');
			$shipping_company = $checkout->get_value('shipping_company');
			$shipping_address_1 = $checkout->get_value('shipping_address_1');
			$shipping_address_2 = $checkout->get_value('shipping_address_2');
			$shipping_postcode = $checkout->get_value('shipping_postcode');
			$shipping_city = $checkout->get_value('shipping_city');
			$shipping_state = $checkout->get_value('shipping_state');
			
			$items = array();
		
			if (sizeof($woocommerce->cart->get_cart())>0) :
				$k = 0;
				foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) :
					$_product = $values['data'];
					
					$dimensions = $this->product_get_dimensions($_product->get_dimensions());
					
					$items[$k]['item']['_attribs']['amount'] = $values['quantity'];
					$items[$k]['item']['_attribs']['weight'] = $_product->get_weight() * $values['quantity'];
					$items[$k]['item']['_attribs']['length'] = $dimensions['length'];
					$items[$k]['item']['_attribs']['width'] = $dimensions['width'];
					$items[$k]['item']['_attribs']['height'] = $dimensions['height'];
					$items[$k]['item']['_attribs']['description'] = $_product->get_title();
					$items[$k]['item']['_attribs']['type'] = "PK";
					$k++;
					
				endforeach;
			endif;
			
	    	if ($name == 'Bring'){
	    		$crg_data['consignments'] = array(
					"consignment" => array(
						"_attribs" => array(
							"transport_agreement" => "1048",
							"estimate" => "true",
						),
						"values" => array(
							"value" => array(
								"_attribs" => array(
									"name" => "ordre_id",
									"value" => "123456",
								),
							),
						),
						"collection" => array(
							"name" => "Dagens",
							"transfer_date" => date("Y-m-d\TH:i:s",strtotime("+2 hour")),
						),
						"product" => "bring_servicepakke",
						"parts" => array(
							"consignee" => array(
								"customer-number" => "555555555",
								"name" => $shipping_first_name . " " . $shipping_last_name,
								"address1" => $shipping_address_1,
								"address2" => $shipping_address_2,
								"country" => $shipping_country,
								"postcode" => $shipping_postcode,
								"city" => $shipping_city,
								"phone" => "66006600",
							),
						),
						"items" => $items, 
						"services" => array(
							array("service" => array(
								"_attribs" => array("id"=>"bring_oppkrav"),
								"amount" => "100",
								"account_number" => "666666666",
								"kid" => "777777777",
							)),
						),
						"references" => array(
							"consignor" => "123456",
							"consignee" => __("Ordre nr.", "aurelie_shipping") . "123456",
						),
						"messages" => array(
							"carrier" => "test_message_carrier",
							"consignee" => "test_message_consignee",
						),
					),
				);
	    	} else if ($name == 'Post i butikk - Postkontor') {
	    		return 1000;
	    	} else if ($name == 'Henting i butikk (MyPack)') {
	    		return 2000;
	    	} else return 3000;
	    	
	    	$crg->requestConsignment($crg_data,$debug,$crg_consignment_url,$this->crg_save_response_as_text);
			$cost = $crg->getEstimatedCostFromXml();
				
			return $cost;
	    }
	    
	    public function admin_options() {
    		?>
	    	<h3><?php _e('Aurelie shipping', 'woocommerce'); ?></h3>
	    	<p><?php _e('The description', 'woocommerce'); ?></p>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				var free_shipping_only = $('#woocommerce_aurelie_shipping_shipping_option_1').is(':checked');
				if (free_shipping_only) uncheck_the_other_shipping_options();
				$('#woocommerce_aurelie_shipping_shipping_option_1').click(function(){
					free_shipping_only = $(this).is(':checked');
					if (free_shipping_only) uncheck_the_other_shipping_options();
				});

				$('#woocommerce_aurelie_shipping_shipping_option_2').click(function(){
					if ($(this).is(':checked')) uncheck_free_shipping_option();
				});

				$('#woocommerce_aurelie_shipping_shipping_option_3').click(function(){
					if ($(this).is(':checked')) uncheck_free_shipping_option();
				});

				$('#woocommerce_aurelie_shipping_shipping_option_4').click(function(){
					if ($(this).is(':checked')) uncheck_free_shipping_option();
				});
				
				function uncheck_the_other_shipping_options(){
					$("#woocommerce_aurelie_shipping_shipping_option_2").attr('checked', false);
					$("#woocommerce_aurelie_shipping_shipping_option_3").attr('checked', false);
					$("#woocommerce_aurelie_shipping_shipping_option_4").attr('checked', false);
				}

				function uncheck_free_shipping_option(){
					$("#woocommerce_aurelie_shipping_shipping_option_1").attr('checked', false);
				}
			});
			</script>
	    	<?php
	    }
	    
	    private function get_selected_rate_name($shipping_method){
	    	$rates = $this->get_rates();
			foreach ($rates as $a){
				if ($this->id . "__" . md5(trim($a['name'])) == $shipping_method){
					return trim($a['name']);
				}
			}
	    }
	    
	    private function get_mypack_location($post){
	    	$mypack_location = array();
	    	$mypack_location['pickup_customer_number'] 	= $post['pickup_customer_number'];
	    	$mypack_location['pickup_name'] 			= $post['pickup_name'];
	    	$mypack_location['pickup_address1'] 		= $post['pickup_address1'];
	    	$mypack_location['pickup_country'] 			= $post['pickup_country'];
	    	$mypack_location['pickup_postcode'] 		= $post['pickup_postcode'];
	    	$mypack_location['pickup_city'] 			= $post['pickup_city'];
	    	
	    	return $mypack_location;
	    }
	    
	    private function get_alert_method($post){
	    	$alert_info = array();
	    	$method = $post['retrieve_method_' . $this->shipping_method_md5];
	    	if (stristr($method, "_email_")){
	    		$alert_info['value'] = $post["retrieve_method_email_value_" . $this->shipping_method_md5];
	    		$alert_info['type'] = 'email';
	    	} else if (stristr($method, "_sms_")){
	    		$alert_info['value'] = $post["retrieve_method_sms_number_" . $this->shipping_method_md5];
	    		$alert_info['type'] = 'sms';
	    	}
	    	return $alert_info;
	    }
	    
	    private function set_customer_number(){
	    	$current_user = wp_get_current_user();
	    	if ($current_user){
	    		$this->customer_number = $current_user->ID;
	    	} else {
	    		$this->customer_number = "000 - guest";
	    	}
	    }
	    
	    private function get_form_data_from_checkout($checkout, $name){
	    	$value = $checkout->get_value("billing_$name");
	    	if (!$value){
	    		$value = $checkout->get_value("shipping_$name");
	    	}
	    	return $value;
	    }
	    private function set_data_for_cargonizer($estimate = NULL){
	    	global $woocommerce;
			
			$checkout = $woocommerce->checkout();
			
			$this->shipping_method_md5 		= $_POST['shipping_method'];
			$this->shipping_method_name 	= $this->get_selected_rate_name($this->shipping_method_md5);
			$this->alert_method 			= $this->get_alert_method($_POST);
			$this->mypack_location 			= $this->get_mypack_location($_POST);
			
			/*$this->shipping_country 		= $checkout->get_value('shipping_country');
			$this->shipping_first_name 		= $checkout->get_value('shipping_first_name');
			$this->shipping_last_name 		= $checkout->get_value('shipping_last_name');
			$this->shipping_company 		= $checkout->get_value('shipping_company');
			$this->shipping_address_1 		= $checkout->get_value('shipping_address_1');
			$this->shipping_address_2 		= $checkout->get_value('shipping_address_2');
			$this->shipping_postcode 		= $checkout->get_value('shipping_postcode');
			$this->shipping_city 			= $checkout->get_value('shipping_city');
			$this->shipping_state 			= $checkout->get_value('shipping_state');
			$this->billing_phone 			= $checkout->get_value('billing_phone');*/
			/**
			 * following values are retrieved from billing form if the shipping form was not completed
			 * actually, now the billing form is the only one available.
			 */
			$this->shipping_country 		= $this->get_form_data_from_checkout($checkout, 'country');
			$this->shipping_first_name 		= $this->get_form_data_from_checkout($checkout, 'first_name');
			$this->shipping_last_name 		= $this->get_form_data_from_checkout($checkout, 'last_name');
			$this->shipping_company 		= $this->get_form_data_from_checkout($checkout, 'company');
			$this->shipping_address_1 		= $this->get_form_data_from_checkout($checkout, 'address_1');
			$this->shipping_address_2 		= $this->get_form_data_from_checkout($checkout, 'address_2');
			$this->shipping_postcode 		= $this->get_form_data_from_checkout($checkout, 'postcode');
			$this->shipping_city 			= $this->get_form_data_from_checkout($checkout, 'city');
			$this->shipping_state 			= $this->get_form_data_from_checkout($checkout, 'state');
			$this->billing_phone 			= $this->get_form_data_from_checkout($checkout, 'phone');
			
			if (isset($estimate) && $estimate){
				/**
				 * the request comes from convert_available_methods()
				 */
				if (isset($_POST['s_address']))
					$this->shipping_address_1 = $_POST['s_address'];
				if (isset($_POST['s_postcode']))
					$this->shipping_postcode = $_POST['s_postcode'];
				if (isset($_POST['s_city']))
					$this->shipping_city  = $_POST['s_city'];
			}
			
			$this->set_customer_number();
			
			switch ($this->alert_method['type']){
				case "email":
					$this->shipping_email = $this->alert_method['value'];
					break;
				case "sms": 
					$this->shipping_mobile = $this->alert_method['value'];
					break;
			}
			
			if (sizeof($woocommerce->cart->get_cart())>0) :
				$k = 0;
				foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) :
					$_product = $values['data'];
					
					$dimensions = $this->product_get_dimensions($_product->get_dimensions());
					
					$this->items[$k]['item']['_attribs']['amount'] = $values['quantity'];
					$this->items[$k]['item']['_attribs']['weight'] = $_product->get_weight() * $values['quantity'];
					$this->items[$k]['item']['_attribs']['length'] = $dimensions['length'];
					$this->items[$k]['item']['_attribs']['width'] = $dimensions['width'];
					$this->items[$k]['item']['_attribs']['height'] = $dimensions['height'];
					//20140727 send volume to cargonizer
					$this->items[$k]['item']['_attribs']['volume'] = $dimensions['height'] * $dimensions['width'] * $dimensions['length'] / 1000;
					
					$this->items[$k]['item']['_attribs']['description'] = $_product->get_title();
					$this->items[$k]['item']['_attribs']['type'] = "PK";
					$k++;
					$this->total_price += $_product->price;
				endforeach;
			endif;
	    }
	    
    private function check_for_bring_notification_service(){
		$option = get_option( 'woocommerce_aurelie_shipping_settings' );
		$bns = array();
		
		if ($option['bring_notification'] == 'yes'){
			$bns[] = array("service" => array(
				"_attribs" => array("id" => "bring_e_varsle_for_utlevering")
			));
		}
		return $bns;
	}
	
    private function set_crg(){
    	$option = get_option( 'woocommerce_aurelie_shipping_settings' );
    	$crg = array();
    	$crg['debug'] = 0;
    	
    	if ($option['working_env'] == 'sandbox'){
    		$crg['api_key'] = $option['crg_api_key_s'];
    		$crg['sender_id'] = $option['crg_sender_id_s'];
    		$crg['consignment_url'] = "http://sandbox.cargonizer.no/consignments.xml";
    		$crg['transport_cost_url'] = "http://sandbox.cargonizer.no/consignment_costs.xml";
    		$crg['transport_agreement_tollpost'] = "1047";
    		$crg['transport_agreement_bring'] = "1048";
    	} else if ($option['working_env'] == 'production'){
    		$crg['api_key'] = $option['crg_api_key_p'];
    		$crg['sender_id'] = $option['crg_sender_id_p'];
    		$crg['consignment_url'] = "http://cargonizer.no/consignments.xml";
    		$crg['transport_cost_url'] = "http://cargonizer.no/consignment_costs.xml";
    		$crg['transport_agreement_tollpost'] = "1228";
    		$crg['transport_agreement_bring'] = "1229";
    	}

    	return $crg;
    }
	
	    public function extra_checkout($order_id = NULL){
	    	if ( ! class_exists( 'cargonizer' ) ) {
	    		require_once("include/cargonizer.php");
	    	}
                
                $order = new WC_Order( $order_id );
                //echo "<pre>";var_dump($order);die();
                $set_account_number     = "16382338263";
                $set_customer_number    = $order->customer_user;
                $set_kid                = $order->id;
                
                $cargonizer = $this->set_crg();
	    	$this->set_data_for_cargonizer();

	    	$this->order_id = $order_id;
	    	
	    	if ( $this->shipping_method_name !== 'Small Packages' ) {
		    	$bring_e_varsle_for_utlevering = $this->check_for_bring_notification_service();
		    	
		    	$crg = new cargonizer($cargonizer['api_key'], $cargonizer['sender_id'], $cargonizer['consignment_url']);
		    	
		    	switch ($this->shipping_method_name){
		    		case "MyPack":
		    			$crg_transport_agreement = $cargonizer['transport_agreement_tollpost'];
		    			$crg_product = "mypack";
		    			$crg_service_partner= array("service_partner" => array(
                                                    "number" => "3042033",
                                                    "customer_number" 	=> $this->mypack_location['pickup_customer_number'],
                                                    "name" 		=> $this->mypack_location['pickup_name'],
                                                    "address1" 		=> $this->mypack_location['pickup_address1'],
                                                    "country" 		=> $this->mypack_location['pickup_country'],
                                                    "postcode" 		=> $this->mypack_location['pickup_postcode'],
                                                    "city" 		=> $this->mypack_location['pickup_city']
                                            ));
						$crg_services = array(
							array("service" => array(
								"_attribs" => array("id"=>"tg_etterkrav"),
								"amount" => $this->total_price,
								"currency" => "NOK",
								"kid" => "1000076353545222",
							)),
						);
		    			break;
		    		case "Minipakke":
		    			$crg_transport_agreement = $cargonizer['transport_agreement_bring'];
		    			$crg_product = "bring_minipakke";
		    			$crg_service_partner = NULL;
		    			$crg_services = array(
		    				/**
		    				 * following service enables 'cash on delivery'
		    				 */
							/*array("service" => array(
								"_attribs" => array("id"=>"bring_oppkrav"), // was bring_oppkrav
								"amount" => "100",
								"account_number" => "123456789",
								"kid" => "123456789",
							)),*/
		    				/*
							array("service" => array(
								"_attribs" => array("id" => "bring_e_varsle_for_utlevering")
							))*/
		    				$bring_e_varsle_for_utlevering
						);;
		    			break;
		    		case "På Døren":
		    			$crg_transport_agreement = $cargonizer['transport_agreement_bring'];
		    			$crg_product = "bring_pa_doren";
		    			$crg_service_partner = NULL;
		    			$crg_services = array(
		    				/**
		    				 * following service enables 'cash on delivery'
		    				 */
							/*array("service" => array(
								"_attribs" => array("id"=>"bring_oppkrav"), // was bring_oppkrav
								"amount" => "100",
								"account_number" => "123456789",
								"kid" => "123456789",
							)),*/
		    				/*
							array("service" => array(
								"_attribs" => array("id" => "bring_e_varsle_for_utlevering")
							))*/
		    				$bring_e_varsle_for_utlevering
						);
		    			break;
		    		case "Fri frakt":
		    			$crg_transport_agreement = $cargonizer['transport_agreement_bring'];
		    			$crg_product = "bring_servicepakke";
		    			$crg_service_partner = NULL;
		    			$crg_services = array(
		    				/**
		    				 * following service enables 'cash on delivery'
		    				 */
							/*array("service" => array(
								"_attribs" => array("id"=>"bring_oppkrav"), // was bring_oppkrav
								"amount" => "100",
								"account_number" => "123456789",
								"kid" => "123456789",
							)),*/
		    				/*
							array("service" => array(
								"_attribs" => array("id" => "bring_e_varsle_for_utlevering")
							))*/
							/**
							 * commented on 20140725 by Kjetil's request. 
							 
		    				array("service" => array(
								"_attribs" => array("id"=>"bring_oppkrav"), // was bring_oppkrav
								"amount" => $this->total_price,
								"account_number" => $set_account_number,
								"kid" => $set_kid,
							))*/
						);
		    			break;
		    	}
	
		    	$this->crg_data['consignments'] = array(
					"consignment" => array(
						"_attribs" => array(
							"transport_agreement" => $crg_transport_agreement, 
							"estimate" => "true",
						),
						"transfer_date" => date("Y-m-d\TH:i:s",strtotime("+2 hour")),
						"values" => array(
							"value" => array(
								"_attribs" => array(
									"name" => "ordre_id",
									"value" => $this->order_id,
								),
							),
						),
						//collection is not part of cargonizer XML
						/*"collection" => array(
							"name" => "Dagens",
							"transfer_date" => date("Y-m-d\TH:i:s",strtotime("+2 hour")), //Automatically transfers EDI after 2 hours
						),*/
						"product" => $crg_product, 
						"parts" => array(
							"consignee" => array(
								//"customer-number" => $this->customer_number,
								"name" => $this->shipping_first_name . " " . $this->shipping_last_name,
								"address1" => $this->shipping_address_1,
								"address2" => $this->shipping_address_2,
								"country" => $this->shipping_country,
								"postcode" => $this->shipping_postcode,
								"city" => $this->shipping_city,
								"phone" => $this->billing_phone,
								"email" => $this->shipping_email,
								"mobile" => $this->shipping_mobile
							),
							$crg_service_partner
						),
						"items" => $this->items, 
						"services" => $crg_services,
						"references" => array(
							"consignor" => $this->order_id,
							//"consignee" => "Ordre.nr: " . $this->order_id,
						),
						/*"messages" => array(
							"carrier" => "test_message_carrier",
							"consignee" => "test_message_consignee",
						),*/
					),
				);
		    	
				///** remove the comment
				$crg->requestConsignment(
					$this->crg_data, 
					$cargonizer['debug'], 
					$cargonizer['consignment_url'], 
					$this->crg_save_response_as_text,
					$this->shipping_method_name
				);
				$this->crg_package_number = $crg->getPkgNumber();
				//*/
				
				/** remove the comment
				$result_xml = $crg->getResultXml();
				*/
				//echo "<pre>".print_r($result_xml,1)."</pre>";
				
				$package_number = $this->crg_package_number;
	    	} else if ( $this->shipping_method_name == 'Small Packages' ) {
	    		$package_number = 'Small Packages Shipping Option';
	    	}
			/**
			 * this is to save the retrieved package number in order's custom fields - visible in admin
			 */
			update_post_meta( $this->order_id, __('Package Number', 'aurelie_shipping'), $package_number);
			
			//die(' from aurelie shipping plugin i should be removed');
	    }
	    
	    public function convert_available_methods($available_methods){
	    	if ( ! class_exists( 'cargonizer' ) ) {
	    		require_once("include/cargonizer.php");
	    	}
	    	global $woocommerce;
	    	$crg = new cargonizer($this->crg_api_key, $this->crg_sender_id, $this->crg_transport_cost_url);
	    	$this->set_data_for_cargonizer(TRUE);
	    	$this->order_id = 'estimate';

		    foreach ($available_methods as $key=>$value){
		    	if ($key == 'aurelie_shipping__' . md5('Henting i butikk (MyPack)')){
		    		$crg_transport_agreement = "1047";
	    			$crg_product = "mypack";
	    			$crg_service_partner= array("service_partner" => array(
						"number" => "3042033",
						"customer_number" => "AND001",
						"name" => "ROMSDAL BLOMSTER OG GAVER",
						"address1" => "RAUMASENTERET ØRAN",
						"country" => "NO",
						"postcode" => "6300",
						"city" => "Åndalsnes"
					));
					$crg_services = array(
						array("service" => array(
							"_attribs" => array("id"=>"tg_etterkrav"),
							"amount" => "4571",
							"currency" => "NOK",
							"kid" => "1000076353545222",
						)),
					);
				}
				else if ($key == 'aurelie_shipping__' . md5('Post i butikk - Postkontor')){
					$crg_transport_agreement = "1047";
	    			$crg_product = "tg_parti";
	    			$crg_service_partner = NULL;
	    			$crg_services = NULL;
				} 
				else if ($key == 'aurelie_shipping__' . md5('Bring')){
					$crg_transport_agreement = "1048";
	    			$crg_product = "bring_servicepakke";
	    			$crg_service_partner = NULL;
	    			$crg_services = array(
						array("service" => array(
							"_attribs" => array("id"=>"bring_oppkrav"), // was bring_oppkrav
							"amount" => "100",
							"account_number" => $set_account_number,
							"kid" => $set_kid,
						))
					);
				}
				
				$this->crg_data['consignments'] = array(
					"consignment" => array(
						"_attribs" => array(
							"transport_agreement" => $crg_transport_agreement, 
							"estimate" => "true",
						),
						"values" => array(
							"value" => array(
								"_attribs" => array(
									"name" => "ordre_id",
									"value" => $this->order_id,
								),
							),
						),
						"collection" => array(
							"name" => "Dagens",
							"transfer_date" => date("Y-m-d\TH:i:s",strtotime("+2 hour")), //Automatically transfers EDI after 2 hours
						),
						"product" => $crg_product, 
						"parts" => array(
							"consignee" => array(
								"customer-number" => $this->customer_number,
								"name" => $this->shipping_first_name . " " . $this->shipping_last_name,
								"address1" => $this->shipping_address_1,
								"address2" => $this->shipping_address_2,
								"country" => $this->shipping_country,
								"postcode" => $this->shipping_postcode,
								"city" => $this->shipping_city,
								"phone" => $this->billing_phone,
								"email" => $this->shipping_email,
								"mobile" => $this->shipping_mobile
							),
							$crg_service_partner
						),
						"items" => $this->items, 
						"services" => $crg_services,
						"references" => array(
							"consignor" => $this->order_id,
							"consignee" => "Ordre.nr: " . $this->order_id,
						),
						"messages" => array(
							"carrier" => "test_message_carrier",
							"consignee" => "test_message_consignee",
						),
					),
				);
				
				$crg->requestConsignment($this->crg_data, $this->debug, $this->crg_transport_cost_url,$this->crg_save_response_as_text);
				
				$error = $crg->check_xml_response();
				
				if (!$error) {
					$available_methods[$key]->cost = $crg->getEstimatedCostFromXml();
					$available_methods[$key]->error = FALSE;
				}
				else {
					$available_methods[$key]->error = TRUE;
				}
				
				$result_xml = $crg->getResultXml();
	
			}
			return $available_methods;
	    }
    }
}

/**
 * Add shipping method to WooCommerce
 **/
function add_aurelie_shipping( $methods ) {
	$methods[] = 'aurelie_shipping'; return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_aurelie_shipping' );

function woocommerce_ajax_update_order_levering(){
	global $woocommerce;

	check_ajax_referer( 'update-order-levering', 'security' ); // update-order-levering is defined in update_order_levering_nonce on woocommerce.php

	if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) )
		define( 'WOOCOMMERCE_CHECKOUT', true );
		
	if ( sizeof( $woocommerce->cart->get_cart() ) == 0 ) {
		echo '<div class="woocommerce-error">' . __( 'Sorry, your session has expired.', 'woocommerce' ) . ' <a href="' . home_url() . '">' . __( 'Return to homepage &rarr;', 'woocommerce' ) . '</a></div>';
		die();
	}

	$woocommerce->session->chosen_shipping_method = empty( $_POST['shipping_method'] ) ? '' : $_POST['shipping_method'];
	$woocommerce->session->chosen_payment_method  = empty( $_POST['payment_method'] ) ? '' : $_POST['payment_method'];

	if ( isset( $_POST['country'] ) )
		$woocommerce->customer->set_country( $_POST['country'] );

	if ( isset( $_POST['state'] ) )
		$woocommerce->customer->set_state( $_POST['state'] );

	if ( isset( $_POST['postcode'] ) )
		$woocommerce->customer->set_postcode( $_POST['postcode'] );

	if ( isset( $_POST['city'] ) )
		$woocommerce->customer->set_city( $_POST['city'] );

	if ( isset( $_POST['address'] ) )
		$woocommerce->customer->set_address( $_POST['address'] );

	if ( isset( $_POST['address_2'] ) )
		$woocommerce->customer->set_address_2( $_POST['address_2'] );

	if ( isset( $_POST['s_country'] ) )
		$woocommerce->customer->set_shipping_country( $_POST['s_country'] );

	if ( isset( $_POST['s_state'] ) )
		$woocommerce->customer->set_shipping_state( $_POST['s_state'] );

	if ( isset( $_POST['s_postcode'] ) )
		$woocommerce->customer->set_shipping_postcode( $_POST['s_postcode'] );

	if ( isset( $_POST['s_city'] ) )
		$woocommerce->customer->set_shipping_city( $_POST['s_city'] );

	if ( isset( $_POST['s_address'] ) )
		$woocommerce->customer->set_shipping_address( $_POST['s_address'] );

	if ( isset( $_POST['s_address_2'] ) )
		$woocommerce->customer->set_shipping_address_2( $_POST['s_address_2'] );

	$woocommerce->cart->calculate_totals();

	do_action( 'woocommerce_checkout_order_levering' ); // Display review order table

	die();
	
}

add_action('wp_ajax_woocommerce_update_order_levering', 'woocommerce_ajax_update_order_levering');
add_action('wp_ajax_nopriv_woocommerce_update_order_levering', 'woocommerce_ajax_update_order_levering');



function daniel_extra_checkout(){
	global $woocommerce;
	
	$checkout = $woocommerce->checkout();
	
	$shipping_country = $checkout->get_value('shipping_country');
	$shipping_first_name = $checkout->get_value('shipping_first_name');
	$shipping_last_name = $checkout->get_value('shipping_last_name');
	$shipping_company = $checkout->get_value('shipping_company');
	$shipping_address_1 = $checkout->get_value('shipping_address_1');
	$shipping_address_2 = $checkout->get_value('shipping_address_2');
	$shipping_postcode = $checkout->get_value('shipping_postcode');
	$shipping_city = $checkout->get_value('shipping_city');
	$shipping_state = $checkout->get_value('shipping_state');
	
	$items = array();

	if (sizeof($woocommerce->cart->get_cart())>0) :
		$k = 0;
		foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) :
			$_product = $values['data'];
			
			$dimensions = $this->product_get_dimensions($_product->get_dimensions());
			
			$items[$k]['item']['_attribs']['amount'] = $values['quantity'];
			$items[$k]['item']['_attribs']['weight'] = $_product->get_weight();
			$items[$k]['item']['_attribs']['length'] = $dimensions['length'];
			$items[$k]['item']['_attribs']['width'] = $dimensions['width'];
			$items[$k]['item']['_attribs']['height'] = $dimensions['height'];
			$items[$k]['item']['_attribs']['description'] = $_product->get_title();
			$items[$k]['item']['_attribs']['type'] = "PK";
			$k++;
			
		endforeach;
	endif;

	
	include( 'my_cargonizer.php' );
	
	die('here on extra_checkout');
}