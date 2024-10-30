<?php

/*
 * Plugin Name: Mijireh Checkout for Jigoshop
 * Plugin URI: http://www.patsatech.com
 * Description: Mijireh Checkout Plugin for accepting payments on your Jigoshop Store.
 * Author: PatSaTECH
 * Version: 1.0.0
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Text Domain: patsatech-jigoshop-mijireh
 * Domain Path: /lang
*/

// Register activation hook to install page slurp page
register_activation_hook(__FILE__, 'install_slurp_page');
register_uninstall_hook(__FILE__, 'remove_slurp_page');

function install_slurp_page() {
	if(!get_page_by_path('mijireh-secure-checkout')) {
    	$page = array(
      		'post_title' => 'Mijireh Secure Checkout',
      		'post_name' => 'mijireh-secure-checkout',
      		'post_parent' => 0,
      		'post_status' => 'private',
      		'post_type' => 'page',
      		'comment_status' => 'closed',
      		'ping_status' => 'closed',
      		'post_content' => "<h1>Checkout</h1>\n\n{{mj-checkout-form}}",
    	);
    	wp_insert_post($page);
  	}
}

function remove_slurp_page() {
	$force_delete = true;
  	$post = get_page_by_path('mijireh-secure-checkout');
  	wp_delete_post($post->ID, $force_delete);
}

add_action('plugins_loaded', 'init_jigoshop_mijireh', 0);
 
function init_jigoshop_mijireh() {
 
    if ( ! class_exists( 'jigoshop_payment_gateway' ) ) { return; }
	
	class jigoshop_mijireh extends jigoshop_payment_gateway {
		
	    /**
	     * Constructor for the gateway.
	     *
	     * @access public
	     * @return void
	     */
		public function __construct() {

			parent::__construct();
	
			$this->id 			= 'mijireh';
			$this->title		= __( 'Mijireh Checkout', 'patsatech-jigoshop-mijireh' );
			$this->icon 		= WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/images/credit_cards.png';
			$this->has_fields = false;
		
			// Define user set variables
			$this->enabled   	= Jigoshop_Base::get_options()->get_option('jigoshop_mijireh_enabled');
			$this->title    	= Jigoshop_Base::get_options()->get_option('jigoshop_mijireh_title');
			$this->description  = Jigoshop_Base::get_options()->get_option('jigoshop_mijireh_description');
			$this->access_key   = Jigoshop_Base::get_options()->get_option('jigoshop_mijireh_access_key');
			
			// Actions
			add_action( 'init', array( $this, 'mijireh_notification' ) );
	  		add_action( 'add_meta_boxes', array( $this, 'add_page_slurp_meta' ) );
	  		add_action( 'wp_ajax_page_slurp', array( $this, 'page_slurp' ) );
	
		}
		
		/**
		 * mijireh_notification function.
		 *
		 * @access public
		 * @return void
		 */
		public function mijireh_notification() {
		    if( isset( $_GET['order_number'] ) ) {
	
		  		$this->init_mijireh();
		
				try {
				
		  			$mj_order 	= new Mijireh_Order( esc_attr( $_GET['order_number'] ) );
					
		  		    $order_id 	= $mj_order->get_meta_value( 'jigo_order_id' );
					
		  		    $order		= new jigoshop_order( absint( $order_id ) );
		
	  		      	// Mark order complete
	  		      	$order->payment_complete();
		
	  		      	// Empty cart and clear session
	  		      	jigoshop_cart::empty_cart();
						
					// filter redirect page
					$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );
		
	  		      	wp_redirect( add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink( $checkout_redirect ))) );
					exit;
		
		  		} catch (Mijireh_Exception $e) {
		
		  			jigoshop::add_error( __( 'Mijireh Error:', 'patsatech-jigoshop-mijireh' ) . $e->getMessage() );
					
					// filter redirect page
					$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('checkout') );
		
	  		      	wp_redirect( get_permalink( $checkout_redirect ) );
					exit;
		
		  		}
		    }elseif( isset( $_POST['page_id'] ) ){
		    	if( isset( $_POST['access_key'] ) && $_POST['access_key'] == $this->access_key ) {
		        	wp_update_post( array( 'ID' => $_POST['page_id'], 'post_status' => 'private' ) );
		    	}
		    }
		}
		
		
		/**
		 * Default Option settings for WordPress Settings API using the Jigoshop_Options class
		 *
		 * These will be installed on the Jigoshop_Options 'Payment Gateways' tab by the parent class 'jigoshop_payment_gateway'
		 *
		 */
		protected function get_default_options() {
	
			$defaults = array();
	
			// Define the Section name for the Jigoshop_Options
			$defaults[] = array( 	
				'name' => __('Mijireh Checkout', 'patsatech-jigoshop-mijireh'), 
				'type' => 'title', 
				'desc' => __('Mijireh Checkout provides a fully PCI Compliant, secure way to collect and transmit credit card data to your payment gateway while keeping you in control of the design of your site.', 'patsatech-jigoshop-mijireh') 
			);
	
			// List each option in order of appearance with details
			$defaults[] = array(
				'name'		=> __('Enable Mijireh Checkout','patsatech-jigoshop-mijireh'),
				'desc' 		=> '',
				'tip' 		=> '',
				'id' 		=> 'jigoshop_mijireh_enabled',
				'std' 		=> 'no',
				'type' 		=> 'checkbox',
				'choices'	=> array(
					'no'			=> __('No', 'patsatech-jigoshop-mijireh'),
					'yes'			=> __('Yes', 'patsatech-jigoshop-mijireh') )
			);
	
			$defaults[] = array(
				'name'		=> __('Method Title','patsatech-jigoshop-mijireh'),
				'desc' 		=> '',
				'tip' 		=> __('This controls the title which the user sees during checkout.','patsatech-jigoshop-mijireh'),
				'id' 		=> 'jigoshop_mijireh_title',
				'std' 		=> __('Credit Card','patsatech-jigoshop-mijireh'),
				'type' 		=> 'text'
			);
	
			$defaults[] = array(
				'name'		=> __('Access Key','patsatech-jigoshop-mijireh'),
				'desc' 		=> '',
				'tip' 		=> __('The Mijireh access key for your store.','patsatech-jigoshop-mijireh'),
				'id' 		=> 'jigoshop_mijireh_access_key',
				'std' 		=> '',
				'type' 		=> 'text'
			);
	
			$defaults[] = array(
				'name'		=> __('Description','patsatech-jigoshop-mijireh'),
				'desc' 		=> '',
				'tip' 		=> __('This controls the description which the user sees during checkout.','patsatech-jigoshop-mijireh'),
				'id' 		=> 'jigoshop_mijireh_description',
				'std' 		=> __('Pay securely with your credit card.','patsatech-jigoshop-mijireh'),
				'type' 		=> 'longtext'
			);
			
			return $defaults;
		}
	
		/**
		 * There are no payment fields for paypal, but we want to show the description if set.
		 **/
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
	
	    /**
	     * Process the payment and return the result
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
	    public function process_payment( $order_id ) {
	
			$this->init_mijireh();
	
			$mj_order = new Mijireh_Order();
			$order = new jigoshop_order( $order_id );

			$item_names = array();

			if ( sizeof( $order->items ) > 0 ) {
			
				foreach ( $order->items as $item ) {
				
					$_product = $order->get_product_from_item( $item );
					$title = $_product->get_title();
					//if variation, insert variation details into product title
					if ($_product instanceof jigoshop_product_variation) {
						$title .= ' (' . jigoshop_get_formatted_variation( $item['variation'], true) . ')';
					}
					
					$item_names[] = $title . ' x ' . $item['qty'];
					
				}
				
			}
			
			$mj_order->add_item( sprintf( __('Order %s' , 'patsatech-jigoshop-mijireh'), $order->get_order_number() ) . ' - ' . implode(', ', $item_names), number_format( $order->order_total - $order->order_shipping - $order->order_shipping_tax + $order->order_discount, 2, '.', '' ), '1', '' );
			
			// add billing address to order
			$billing 					= new Mijireh_Address();
			$billing->first_name 		= $order->billing_first_name;
			$billing->last_name 		= $order->billing_last_name;
			$billing->street 			= $order->billing_address_1;
			$billing->apt_suite 		= $order->billing_address_2;
			$billing->city 				= $order->billing_city;
			$billing->state_province 	= $order->billing_state;
			$billing->zip_code 			= $order->billing_postcode;
			$billing->country 			= $order->billing_country;
			$billing->company 			= $order->billing_company;
			$billing->phone 			= $order->billing_phone;
			if ( $billing->validate() )
				$mj_order->set_billing_address( $billing );

			// add shipping address to order
			$shipping 					= new Mijireh_Address();
			$shipping->first_name 		= $order->shipping_first_name;
			$shipping->last_name 		= $order->shipping_last_name;
			$shipping->street 			= $order->shipping_address_1;
			$shipping->apt_suite 		= $order->shipping_address_2;
			$shipping->city 			= $order->shipping_city;
			$shipping->state_province 	= $order->shipping_state;
			$shipping->zip_code 		= $order->shipping_postcode;
			$shipping->country 			= $order->shipping_country;
			$shipping->company 			= $order->shipping_company;
			if ( $shipping->validate() )
				$mj_order->set_shipping_address( $shipping );	
	
			// set order name
			$mj_order->first_name 		= $order->billing_first_name;
			$mj_order->last_name 		= $order->billing_last_name;
			$mj_order->email 			= $order->billing_email;
	
			// set order totals
			$mj_order->total 			= $order->order_total;
			$mj_order->discount 		= $order->order_discount;
			
			if ( ( $order->order_shipping + $order->order_shipping_tax ) > 0 ) {
				$mj_order->shipping 	= number_format( $order->order_shipping + $order->order_shipping_tax , 2, '.', '' );
			}
	
			// add meta data to identify jigoshop_mijireh order
			$mj_order->add_meta_data( 'jigo_order_id', $order_id );
	
			// Set URL for mijireh payment notification - use WC API
			$mj_order->return_url 		= str_replace( 'https:', 'http:', add_query_arg( 'mijireh', 'ipn', home_url( '/' ) ) );
	
			// Identify PatSaTECH
			$mj_order->partner_id 		= 'patsatech';
	
			try {
				$mj_order->create();
				
				$result = array(
					'result' => 'success',
					'redirect' => $mj_order->checkout_url
				);
				
				return $result;
			} catch (Mijireh_Exception $e) {
				jigoshop::add_error( __('Mijireh Error:', 'patsatech-jigoshop-mijireh' ) . $e->getMessage() );
				
				jigoshop::show_messages();
			}
	    }
	
	
		/**
		 * init_mijireh function.
		 *
		 * @access public
		 */
		public function init_mijireh() {
			if ( ! class_exists( 'Mijireh' ) ) {
		    	require_once 'mijireh/Mijireh.php';
	
		    	if ( ! isset( $this ) ) {
					$access_key = get_option( 'jigoshop_mijireh_access_key', null );
			    	$key = ! empty( $access_key ) ? $access_key : '';
		    	} else {
			    	$key = $this->access_key;
		    	}
	
		    	Mijireh::$access_key = $key;
		    }
		}
	
	
	    /**
	     * page_slurp function.
	     *
	     * @access public
	     * @return void
	     */
	    public function page_slurp() {
		
	    	self::init_mijireh();
	
			$page 	= get_page( absint( $_POST['page_id'] ) );
			$url 	= get_permalink( $page->ID );
	    	$job_id = $url;
			if ( wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) ) ) {
			  $job_id = Mijireh::slurp( $url, $page->ID, str_replace( 'https:', 'http:', add_query_arg( 'mijireh', 'ipn', home_url( '/' ) ) ) );
	    }
			echo $job_id;
			die;
		}
	    
	
	
	    /**
	     * add_page_slurp_meta function.
	     *
	     * @access public
	     * @return void
	     */
	    public function add_page_slurp_meta() {
	    	
	    	if ( self::is_slurp_page() ) {
	        	wp_enqueue_style( 'mijireh_css', WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/css/mijireh.css' );
	        	wp_enqueue_script( 'pusher', 'https://d3dy5gmtp8yhk7.cloudfront.net/1.11/pusher.min.js', null, false, true );
	        	wp_enqueue_script( 'page_slurp', WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/js/page_slurp.js', array('jquery'), false, true );
	
				add_meta_box(
					'slurp_meta_box', 		// $id
					'Mijireh Page Slurp', 	// $title
					array( 'jigoshop_mijireh', 'draw_page_slurp_meta_box' ), // $callback
					'page', 	// $page
					'normal', 	// $context
					'high'		// $priority
				);
			}
	    }
	
	
	    /**
	     * is_slurp_page function.
	     *
	     * @access public
	     * @return void
	     */
	    public function is_slurp_page() {
			global $post;
			$is_slurp = false;
			if ( isset( $post ) && is_object( $post ) ) {
				$content = $post->post_content;
				if ( strpos( $content, '{{mj-checkout-form}}') !== false ) {
					$is_slurp = true;
				}
			}
			return $is_slurp;
	    }
	
	
	    /**
	     * draw_page_slurp_meta_box function.
	     *
	     * @access public
	     * @param mixed $post
	     * @return void
	     */
	    public function draw_page_slurp_meta_box( $post ) {
	    	
	    	self::init_mijireh();
			
			$access_key = get_option( 'jigoshop_mijireh_access_key', NULL );
	
			echo "<div id='mijireh_notice' class='mijireh-info alert-message info' data-alert='alert'>";
			echo    "<h2>Slurp your custom checkout page!</h2>";
			echo    "<p>Get the page designed just how you want and when you're ready, click the button below and slurp it right up.</p>";
			echo    "<div id='slurp_progress' class='meter progress progress-info progress-striped active' style='display: none;'><div id='slurp_progress_bar' class='bar' style='width: 20%;'>Slurping...</div></div>";
			
			if(!empty($access_key)){
			
				echo    "<p><a href='#' id='page_slurp' rel=". $post->ID ." class='button-primary'>Slurp This Page!</a> ";

				echo    '<a class="nobold" href="https://secure.mijireh.com/checkout/' . $access_key . '" id="view_slurp" target="_blank">Preview Checkout Page</a></p>';
				
			}else{
			
				echo '<p style="color:red;font-size:15px;text-shadow: none;"><b>Please enter you Access Key in Mijireh Settings. <a class="nobold" target="_blank" href="' . home_url('/wp-admin/admin.php?page=jigoshop_settings&tab=payment-gateways') . '" id="view_slurp" target="_new">Enter Access Key</a></b></p>';
				
			}
			
			echo  "</div>";
			
	    }
	}

	/**
	 * Add the gateway to Jigoshop
	 **/
	function add_mijireh_gateway( $methods ) {
		$methods[] = 'jigoshop_mijireh'; return $methods;
	}
	
	add_filter('jigoshop_payment_gateways', 'add_mijireh_gateway' );
}