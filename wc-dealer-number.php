<?php
/**
 * Plugin Name: Dealer Number for WooCommerce
 * Plugin URI: http://www.kathyisawesome.com/
 * Description: Add a dealer/associate number to checkout and display in Recent Orders table
 * Version: 1.2.0
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com
 * Requires at least: 5.0
 * Tested up to: 5.5
 * 
 * Copyright: © 2020 Kathy Darling.
 * License: GNU General License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
*/

namespace WC_Dealer_Number;

define( 'WC_DEALER_NUMBER_VERSION', '1.2.0' );

  
/**
 * WC_Dealer_Number attach hooks and filters
 */
function add_hooks_and_filters() {

	// Load translation files.
	add_action( 'init', __NAMESPACE__ . '\load_plugin_textdomain' );

	// Add dealer number field to checkout.
	add_filter( 'woocommerce_shipping_fields' , __NAMESPACE__ . '\add_shipping_fields', 20 );
	add_filter( 'woocommerce_admin_shipping_fields' , __NAMESPACE__ . '\admin_shipping_fields' );
	
	// Display meta key in order overview.
	add_action( 'woocommerce_order_details_after_customer_details' , __NAMESPACE__ . '\after_customer_details', 20 );
	
	// Display meta key in emails.
	add_filter( 'woocommerce_email_customer_details_fields' , __NAMESPACE__ . '\email_customer_details', 20, 3 );

	// Add column to my orders table.
	add_filter( 'woocommerce_my_account_my_orders_columns' , __NAMESPACE__ . '\my_account_my_orders_columns' );
	add_action( 'woocommerce_my_account_my_orders_column_order-dealer_number', __NAMESPACE__ . '\column_order_dealer_number' );

	// Modify the my orders query params.
	add_filter( 'woocommerce_my_account_my_orders_query', __NAMESPACE__ . '\my_orders_query' );

	// Filter the my orders query.
	add_filter( 'posts_clauses', __NAMESPACE__ . '\edit_posts_clauses', 10, 2 );

}
// Launch the whole plugin.
add_action( 'woocommerce_loaded', __NAMESPACE__ . '\add_hooks_and_filters' );

/*-----------------------------------------------------------------------------------*/
/* Plugin Functions */
/*-----------------------------------------------------------------------------------*/

/**
 * Make the plugin translation ready
 *
 * @return void
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain( 'wc-shipping-tracking' , false , dirname( plugin_basename( __FILE__ ) ) .  '/languages/' );
}

/**
 * Add field to front-end billing fields
 *
 * @var  array $fields
 * @return  array
 * @since 1.0
 */
function add_shipping_fields( $fields ) {
	$fields['dealer_number'] = array(
		'label' 		=> esc_html__( 'Dealer Number', 'wc-dealer-number' ),
		'required' 		=> false,
		'wrapper_class'	=> 'full_width',
	);
	return $fields;
}

/**
 * Add email to Admin Order overview
 *
 * @var  array $fields
 * @return  array
 * @since 1.0
 */	
function admin_shipping_fields( $fields ) {
	$fields['dealer_number'] = array(
		'label' 		=> esc_html__( 'Dealer Number', 'wc-dealer-number' ),
		'wrapper_class'	=> 'form-field-wide',
	);
	return $fields;
}

/**
 * Display meta in my-account area Order overview
 *
 * @var  object WC_Order $order
 * @return  void
 * @since 1.0
 */
function after_customer_details( $order ){
		
	if( $value = $order->get_meta( '_shipping_dealer_number', true ) ){ ?>
		<tr>
			<th><?php esc_html_e( 'Dealer Number', 'wc-dealer-number' ); ?></th>
			<td><?php echo esc_html( $value ); ?></td>
		</tr>
		<?php
	}

}

/**
 * Display meta in email customer details.
 *
 * @param  array $fields
 * @param bool $sent_to_admin
 * @param WC_Order $order
 * @return  array
 * @since 1.2.0
 */

function email_customer_details( $fields, $sent_to_admin, $order ){
	$fields[] = array( 
		'label' => __( 'Dealer Number', 'wc-dealer-number' ),
		'value' => $order->get_meta( '_shipping_dealer_number', true )
	);
	return $fields;
}


/**
 * Add column to my account orders table.
 *
 * @var  array $columns
 * @return  string
 * @since 1.2.0
 */
function my_account_my_orders_columns( $columns ) {
	$new_columns = array();
	$inserted = false;
	foreach( $columns as $column_id => $column_name ){
		$new_columns[$column_id] = $column_name;
		// insert new column after order date
		if( 'order-date' == $column_id ){
			$inserted = true;
			$new_columns['order-dealer_number'] = __( 'Dealer Number', 'wc-dealer-number' );
		}
	}

	// Add to end of array in off-chance order-date was removed
	if( ! $inserted ){
		$new_columns['order-dealer_number'] = __( 'Dealer Number', 'wc-dealer-number' );
	}
	return $new_columns;
}


/**
 * Output for custom order table column.
 *
 * @var  WC_Order $order
 * @return  void
 * @since 1.2.0
 */
function column_order_dealer_number( $order ) {
	if ( $dealer_number = $order->get_meta( '_shipping_dealer_number', true ) ){
		echo esc_html( $dealer_number );
	}
}


/**
 * Add params to my-account/my-orders.php query
 * modified to suppress filters, custom orderby, and show last 2 months
 *
 * @var  array $query
 * @return  array
 * @since 1.1
 */
function my_orders_query( $query ){
	$query['query_id'] = 'woocommerce_my_account_my_orders_query';
	$query['orderby'] = 'dealer_date';
	$query['date_query'] = array(
		array(
			'after' => '2 months ago',
			),
	);
	$query['suppress_filters'] = false;
	return $query;
}


/**
 * Sort orders by possibly null meta field, then by post date
 *
 * Complex SQL props @bonger http://wordpress.stackexchange.com/a/163708/6477
 *
 * @var  array $pieces
 * @var  obj $query
 * @return  array
 * @since 1.0
 */
function edit_posts_clauses( $pieces, $query ) {
	if ( $query->get( 'orderby' ) == 'dealer_date' && $query->get( 'query_id' ) == "woocommerce_my_account_my_orders_query" ) {

		global $wpdb;

		// join the post_meta table
		$pieces[ 'join' ] .= $wpdb->prepare( ' LEFT JOIN ' . $wpdb->postmeta . ' dealer_pm ON dealer_pm.post_id = ' . $wpdb->posts . '.ID AND dealer_pm.meta_key = %s AND LENGTH(TRIM(dealer_pm.meta_value) )', '_shipping_dealer_number' );
					
		// Negate the meta_value if it exists to sort before zero.
		$pieces[ 'fields' ] .= ', CASE WHEN dealer_pm.meta_value IS NOT NULL THEN -dealer_pm.meta_value ELSE 0 END AS dealer_number';

		$pieces[ 'orderby' ] = $pieces[ 'orderby' ] = 'ISNULL(MAX(dealer_pm.meta_value)) OR LENGTH(TRIM(MAX(dealer_pm.meta_value))) = 0 ASC, MAX(dealer_pm.meta_value) ASC,' . $wpdb->posts . '.post_date DESC';
		
	}

	return $pieces;
}
