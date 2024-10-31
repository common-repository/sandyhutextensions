<?php
/**
* @package SandyhutExtensions
*/

/*
Plugin Name: SandyhutExtensions
Plugin URI: https://github.com/serialbandicoot/SandyhutExtensions
Description: Extensions for AppCart
Version: 2.3.0
Author: @serialbandicoot
Author URI: https://appcart.io
License: GPLv2 or later 
Text Domain: SandyhutExtensions
*/
	
defined( 'ABSPATH' ) or die('Silence is golden.');

define( 'APPCART_HIDE_CATEGORY_LABEL', 'AppCart Hide Category');
define( 'APPCART_HIDE_CATEGORY_DESCRIPTION', 'Selecting this option will hide the Category in AppCart');

class SandyhutExtensions 
{	

	function __construct(){
		add_action( 'rest_api_init', array( $this , 'activate_table_rate_shipping_data' ) );
		add_action( 'rest_api_init', array( $this , 'activate_static_page_routes' ) );
		add_action( 'init', array( $this, 'app_cart_static_pages' ) );

		add_filter( 'woocommerce_rest_prepare_product_object', array($this, 'get_product_media_images'), 10, 3 ); 
		add_filter( 'woocommerce_rest_prepare_product_object', array($this, 'get_product_make_offer'), 10, 3 ); 

		add_filter( 'woocommerce_rest_prepare_product_cat', array($this, 'get_product_categories_fields'), 10, 3 );
		add_filter( 'woocommerce_rest_prepare_product_cat', array($this, 'get_product_categories_images'), 10, 3 );
		
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'cfwc_make_offer_field' ), 10, 1 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'cfwc_save_make_offer_field' ), 10, 1 );

		add_action( 'product_cat_add_form_fields',  array( $this, 'wh_taxonomy_add_new_meta_field' ), 10, 1);
		add_action( 'product_cat_edit_form_fields',  array( $this, 'wh_taxonomy_edit_meta_field' ), 10, 1);
		add_action( 'edited_product_cat',  array( $this, 'wh_save_taxonomy_custom_meta' ), 10, 1);
		add_action( 'create_product_cat',  array( $this, 'wh_save_taxonomy_custom_meta' ), 10, 1);
	}

	function activate(){
		$this->get_table_rate_shipping_data();
		$this->activate_static_page_routes();
		$this->get_static_page_titles();
		flush_rewrite_rules();
	}

	function deactivate(){
		flush_rewrite_rules();
	}

	/*
		Make an offer on the product
	*/

	function cfwc_make_offer_field() {
		$args = array(
			'id' => '_checkbox_make_an_offer',
			'label' => __( 'Make an Offer', 'woocommerce' ),
			'class' => 'cfwc-custom-field',
			'description' => __( 'Select if you want customer to Make an Offer in AppCart.', 'woocommerce' ),
			);
		woocommerce_wp_checkbox( $args );
	}

	function cfwc_save_make_offer_field( $post_id ) {		
		$woocommerce_checkbox = isset( $_POST['_checkbox_make_an_offer'] ) ? 'yes' : 'no';
 		update_post_meta( $post_id, '_checkbox_make_an_offer', $woocommerce_checkbox );
	}

	static public function get_product_make_offer( $response, $product, $request  ) {
	
        $bool = get_post_meta( $product->id, '_checkbox_make_an_offer', true);
     	if ( $bool === 'yes') {
			$display = true;
		}
		else {
			$display = false;
		}
		$response->data['make_an_offer'] = $display;
		return $response;

	}

	/*
		Hide on AppCart
	*/

	function wh_taxonomy_add_new_meta_field() {
	    echo '<div class="form-field">';
			woocommerce_wp_checkbox( 
				array( 
					'id'            => 'wh_meta_hide_on_app_cart', 
					'wrapper_class' => '', 
					'label'         => __( APPCART_HIDE_CATEGORY_LABEL, 'woocommerce' ), 
					'description'   => __( APPCART_HIDE_CATEGORY_DESCRIPTION , 'woocommerce') 
					)
				);
		echo '</div>';
	}

	//Product Cat Edit page
	function wh_taxonomy_edit_meta_field($term) {

	    //getting term ID
	    $term_id = $term->term_id;

	    // retrieve the existing value(s) for this meta field.
	    $wh_meta_hide_on_app_cart = get_term_meta($term_id, 'wh_meta_hide_on_app_cart', true);
	    
	    ?>
	    <tr class="form-field">
	        <th scope="row" valign="top"><label for="wh_meta_hide_on_app_cart"><?php _e( APPCART_HIDE_CATEGORY_LABEL, 'wh'); ?></label></th>
	        <td>
	            <?php

	            	woocommerce_wp_checkbox( 
					array( 
							'id'            => 'wh_meta_hide_on_app_cart', 
							'wrapper_class' => '', 
							'value' => $wh_meta_hide_on_app_cart
						)
					)

	            ?>
	            <p class="description"><?php _e( APPCART_HIDE_CATEGORY_DESCRIPTION, 'wh'); ?></p>
	        </td>
	    </tr>
	    
	    <?php
	}

	// Save extra taxonomy fields callback function.
	function wh_save_taxonomy_custom_meta($term_id) {
		$wh_meta_hide_on_app_cart = isset( $_POST['wh_meta_hide_on_app_cart'] ) ? 'yes' : 'no';
	    update_term_meta($term_id, 'wh_meta_hide_on_app_cart', $wh_meta_hide_on_app_cart);
	}

	/*
		URL: http://lhost/wp-json/appcart/v1/app_cart_static
	*/

	function app_cart_static_pages(){		
		register_post_type('app_cart_static', 
			array(
				'public' => true, 
				'show_in_menu' => true, 
				'label' => 'App Cart',
				'show_in_rest' => true,
				'hierarchical' => true,
				'menu_icon'    => 'dashicons-cart'
			) 
		);
	}


	function activate_static_page_routes(){
		register_rest_route( 'appcart/v1', '/app_cart_static', array(
		    'methods' => 'GET',
		    'callback' => 'SandyhutExtensions::get_static_page_titles',
		  ) );
	}

	static function get_static_page_titles(){

		$args = array (
		    'post_status' => 'publish',
		    'post_type' => 'app_cart_static'
		);

		$items = array();
 
		if ( $pages = get_posts( $args ) ) {
			foreach ( $pages as $page ) {
		    $items[] = array(
		      'id' => $page->ID,
		      'title' => $page->post_title,
		      );
		    }
		  }
		  return $items;
	}

	/*
		URL: http://lhost/wp-json/appcart/v1/table_rate_data
	*/

	function activate_table_rate_shipping_data(){
		register_rest_route( 'appcart/v1', '/table_rate_data', array(
				'methods' => 'GET',
				'callback' => 'SandyhutExtensions::get_table_rate_shipping_data',
			));
	}

	static function get_table_rate_shipping_data(){
		global $woocommerce;
		global $wpdb;

		$active_methods   = array();
		$shipping_methods = $woocommerce->shipping->load_shipping_methods();

		foreach ( $shipping_methods as $id => $shipping_method ) {

			$data_arr = array( 'title' => $shipping_method->title, 'tax_status' => $shipping_method->tax_status );  

			if( $id == 'table_rate'){ 
					$raw_zones = $wpdb->get_results("SELECT zone_id, zone_name, zone_order FROM {$wpdb->prefix}woocommerce_shipping_zones order by zone_order ASC;");


	 			$shipping = array();
	    		$shippingarr = array();


				foreach ($raw_zones as $raw_zone) {

					$zones = new WC_Shipping_Zone($raw_zone->zone_id);

			        $zone_id 		= $zones->zone_id; 
			        $zone_name 		= $zones->zone_name; 
			        $zone_enabled 	= $zones->zone_enabled; 
			        $zone_type 		= $zones->zone_type; 
			        $zone_order 	= $zones->zone_order; 

			        $shipping['zone_id']  		= $zone_id;
			        $shipping['zone_name'] 		= $zone_name;
			        $shipping['zone_enabled'] 	= $zone_enabled;
			        $shipping['zone_type'] 		= $zone_type;
			        $shipping['zone_order'] 	= $zone_order;

			        $shipping_methods = $zones->shipping_methods; 

					foreach($shipping_methods as $shipping_method){
					    $methodid = $shipping_method["number"];
					    $raw_rates[$methodid]['rates'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_table_rates WHERE shipping_method_id={$methodid};", ARRAY_A);
					}

					$shipping['shipping_methods'] = $raw_rates;
					$raw_country = $wpdb->get_results("SELECT location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE zone_id={$zone_id};", ARRAY_N);
					$shipping['countries'] = $raw_country;
					$shippingarr[] = $shipping;
					
				}

			}

		}

	 
		return $shipping_methods;
	}

	static function get_product_media_images( $response, $product, $request ) {

		global $_wp_additional_image_sizes;

	    if (empty($response->data)) {
	        return $response;
	    }

	    foreach ($response->data['images'] as $key => $image) {
	        $image_urls = [];
	        foreach ($_wp_additional_image_sizes as $size => $value) {
	            $image_info = wp_get_attachment_image_src($image['id'], $size);
	            $response->data['images'][$key][$size] = $image_info[0];
	        }
		}

	    return $response;

	}


	static public function get_product_categories_fields( $response, $term, $request ) {
		$term_id = $term->term_id;
			
		if ( get_term_meta($term_id, 'wh_meta_hide_on_app_cart', true) === 'yes') {
			$display = true;
		}
		else {
			$display = false;
		}
		$response->data['hide_on_appcart'] = $display;

		return $response;
	}

	static public function get_product_categories_images($response, $term, $request) {
		global $_wp_additional_image_sizes;

	    if (empty($response->data)) {
	        return $response;
	    }

	    $id = $response->data['image']['id']; 	        
        foreach ($_wp_additional_image_sizes as $size => $value) {
            $image_info = wp_get_attachment_image_src($id, $size);
            $response->data['images'][$size] = $image_info[0];
        }
	   
	    return $response;
	}

}

if (class_exists('SandyhutExtensions')) {
	$sandyhut_extensions = new SandyhutExtensions();
}

//activate
register_activation_hook( __FILE__, array($sandyhut_extensions, 'activate') );


//deactivate
register_deactivation_hook( __FILE__, array($sandyhut_extensions, 'deactivate') );