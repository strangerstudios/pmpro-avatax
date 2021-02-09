<?php

/**
 * Get PMPro AvaTax options.
 */
function pmproava_get_options() {
	static $options = null;
	if ( $options === null ) {
		$set_options = get_option('pmproava_options');
		$set_options = is_array( $set_options ) ? $set_options : array();

		$default_address = new stdClass();
		$default_address->line1 = '';
		$default_address->line2 = '';
		$default_address->line3 = '';
		$default_address->city = '';
		$default_address->region = '';
		$default_address->postalCode = '';
		$default_address->country = '';

		$default_options = array(
			'account_number'  => '',
			'license_key'     => '',
			'environment'     => 'sandbox',
			'company_code'    => '',
			'company_address' => $default_address,
			'retroactive_tax' => 'yes',
			'site_prefix'     => 'PMPRO',
		);
		$options = array_merge( $default_options, $set_options );
	}
	return $options;
}

/**
 * Validate Avatax settings.
 */
function pmproava_options_validate($input) {
	$newinput = array();
	if ( isset($input['account_number'] ) ) {
		$newinput['account_number'] = trim( preg_replace("[^a-zA-Z0-9\-]", "", $input['account_number'] ) );
	}
	if ( isset($input['license_key'] ) ) {
		$newinput['license_key'] = trim( preg_replace("[^a-zA-Z0-9\-]", "", $input['license_key'] ) );
	}
	if ( isset($input['environment']) && $input['environment'] === 'production' ) {
		$newinput['environment'] = 'production';
	}
	if ( isset($input['company_code'] ) ) {
		$newinput['company_code'] = trim( preg_replace("[^a-zA-Z0-9\-]", "", $input['company_code'] ) );
	}
	if ( isset($input['company_address'] ) ) {
		$newinput['company_address'] = (object)$input['company_address'];
	}
	if ( isset($input['retroactive_tax']) && $input['retroactive_tax'] === 'no' ) {
		$newinput['retroactive_tax'] = 'no';
	}
	if ( isset($input['site_prefix'] ) ) {
		$newinput['site_prefix'] = trim( preg_replace("[^a-zA-Z0-9\-]", "", $input['site_prefix'] ) );
	}
	return $newinput;
}

define("PMPROAVA_GENERAL_MERCHANDISE", "P0000000");
/**
 * Get the AvaTax product category for a particular level.
 *
 * @param int $level_id to get category for
 * @return string product category
 */
function pmproava_get_product_category( $level_id ) {
	$pmproava_product_category = get_pmpro_membership_level_meta( $level_id, 'pmproava_product_category', true);
	return $pmproava_product_category ?: PMPROAVA_GENERAL_MERCHANDISE;
}

/**
 * Get the AvaTax address model for a particular level.
 *
 * @param int $level_id to get category for
 * @return string address model
 */
function pmproava_get_product_address_model( $level_id ) {
	$pmproava_address_model = get_pmpro_membership_level_meta( $level_id, 'pmproava_address_model', true);
	return $pmproava_address_model ?: 'shipToFrom';
}

/**
 * Get the AvaTax customer code for a given user_id.
 *
 * @param int $user_id to get customer code for.
 * @return string
 */
function pmproava_get_customer_code( $user_id ) {
	$customer_code = get_user_meta( $user_id, 'pmproava_customer_code', true );
	if ( empty( $customer_code ) ) {
		$pmproava_options = pmproava_get_options();
		$customer_code    = $pmproava_options['site_prefix'] . '-' . str_pad( $user_id, 8, '0', STR_PAD_LEFT );
		update_user_meta( $user_id, 'pmproava_customer_code', $customer_code );
	}
	return $customer_code;
}

/**
 * Get the Avalara transaction code for a particular order.
 *
 * @param MemberOrder $order to get transaction code for.
 * @return string
 */
function pmproava_get_transaction_code( $order ) {
	$transaction_code = get_pmpro_membership_order_meta( $order->id, 'pmproava_transaction_code', true );
	if ( empty( $customer_code ) ) {
		$pmproava_options = pmproava_get_options();
		$transaction_code    = $pmproava_options['site_prefix'] . '-' . $order->code;
		update_pmpro_membership_order_meta( $order->id, 'pmproava_transaction_code', $transaction_code );
	}
	return $transaction_code;
}

function pmproava_updated_order( $order ) {
	global $wpdb;

	// Check if gateway environments match for order and Avalara creds. If not, return.
	$pmproava_options = pmproava_get_options();
	$pmproava_environment = $pmproava_options['environment'] === 'sandbox' ? 'sandbox' : 'live' ;
	$gateway_environment = pmpro_getOption( 'gateway_environment' );
	if ( $pmproava_environment !== $gateway_environment ) {
		return;
	}

	$pmpro_avatax            = PMPro_AvaTax::get_instance();
	$transaction             = $pmpro_avatax->get_transaction_for_order( $order );

	// If transaction does not already exist in AvaTax and order is voided in PMPro, return.
	if ( empty( $transaction ) && in_array( $order->status, array( 'refunded', 'error' ) ) ) {
		return;
	}

	// Void transaction if refunded/error and not already voided
	if ( in_array( $order->status, array( 'error', 'refunded' ) ) ) {
		if ( $transaction->status !== 'Cancelled' ) {
			$pmpro_avatax->void_transaction_for_order( $order );
		}
		return;
	}

	// Create/update transaction.
	$pmpro_avatax->update_transaction_from_order( $order );

	// Get new/updated transaction.
	$transaction = $pmpro_avatax->get_transaction_for_order( $order );

	// Update subtotal and tax fields in PMPro.
	if ( $transaction ) {
		$wpdb->query( "
		UPDATE $wpdb->pmpro_membership_orders
		SET `subtotal` = '" . esc_sql( $transaction->totalAmount ) . "',
			`tax` = '" . esc_sql( $transaction->totalTax ) . "'
		WHERE id = '" . esc_sql( $order->id ) . "'
		LIMIT 1"
		);
	}

	// Clear AvaTax errors for order.
	pmproava_save_order_error( $order );
}
add_filter( 'pmpro_added_order', 'pmproava_updated_order' );
add_filter( 'pmpro_updated_order', 'pmproava_updated_order' );

function pmproava_save_order_error( $order ) {
	global $pmproava_error;
	if ( ! empty( $pmproava_error ) ) {
		update_pmpro_membership_order_meta( $order->id, 'pmproava_error', $pmproava_error );
	} else {
		delete_pmpro_membership_order_meta( $order->id, 'pmproava_error' );
	}
}

function pmproava_get_order_error( $order ) {
	return get_pmpro_membership_order_meta( $order->id, 'pmproava_error', true ) ?: '';
}
