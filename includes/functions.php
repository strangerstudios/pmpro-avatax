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
			'account_number'   => '',
			'license_key'      => '',
			'environment'      => 'sandbox',
			'company_code'     => '',
			'company_address'  => $default_address,
			'record_documents' => 'yes',
			'retroactive_tax'  => 'yes',
			'site_prefix'      => 'PMPRO',
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
	if ( isset($input['record_documents']) && $input['record_documents'] === 'no' ) {
		$newinput['record_documents'] = 'no';
	} elseif ( isset( $input['record_documents'] ) ) {
		$newinput['record_documents'] = 'yes';
	}
	if ( isset($input['retroactive_tax']) && $input['retroactive_tax'] === 'no' ) {
		$newinput['retroactive_tax'] = 'no';
	} elseif ( isset( $input['retroactive_tax'] ) ) {
		$newinput['retroactive_tax'] = 'yes';
	}
	if ( isset($input['vat_field']) && $input['vat_field'] === 'no' ) {
		$newinput['vat_field'] = 'no';
	} elseif ( isset( $input['vat_field'] ) ) {
		$newinput['vat_field'] = 'yes';
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

	// Detect if order is in sync with AvaTax. If so, return.
	if ( ! pmproava_order_should_sync_with_transaction( $order, $transaction ) ) {
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

function pmproava_order_should_sync_with_transaction( $order, $transaction ) {
	// If transaction does not already exist in AvaTax and order is voided in PMPro, return true.
	if ( empty( $transaction ) && in_array( $order->status, array( 'refunded', 'error' ) ) ) {
		return false;
	}

	// If transaction does not already exist in AvaTax and order is free, return true.
	if ( empty( $transaction ) && empty( intval( $order->total ) ) ) {
		return false;
	}

	// If transaction is locked, don't try to update.
	if ( ! empty( $transaction ) && $transaction->locked ) {
		return false;
	}

	// If we need a transaction but it does not yet exist, create it.
	if ( empty( $transaction ) ) {
		return true;
	}

	// Gather information for comparison...
	$line_item       = $transaction->lines[0];

	$avatax_destination_address_id = $transaction->destinationAddressId;
	$avatax_address = null;
	foreach ( $transaction->addresses as $address ) {
		if ( $address->id == $avatax_destination_address_id ) {
			$avatax_address = $address;
			break;
		}
	}
	if ( empty( $avatax_address ) ) {
		// We should never get here, but if we do, update.
		return true;
	}

	switch( pmproava_get_product_address_model( $order->membership_id ) ) {
		case 'singleLocation':
			$pmproava_options = pmproava_get_options();
			$pmpro_address    = $pmproava_options['company_address'];
			break;
		case 'shipToFrom':
			$pmpro_address             = new stdClass();
			$pmpro_address->line1      = $order->billing->street;
			$pmpro_address->city       = $order->billing->city;
			$pmpro_address->region     = $order->billing->state;
			$pmpro_address->postalCode = $order->billing->zip;
			$pmpro_address->country    = $order->billing->country;
			break;
		default:
			$pmpro_address = null;
	}
	$pmpro_avatax            = PMPro_AvaTax::get_instance();
	$pmpro_address_validated = $pmpro_avatax->validate_address( $pmpro_address );
	if ( empty( $pmpro_address_validated ) ) {
		// Don't try to update. We had an error with  address validation.
		pmproava_save_order_error( $order );
		return false;
	}

	// Is the order synced with the transaction?
	$synced = (
		// User
		$transaction->customerCode == pmproava_get_customer_code( $order->user_id ) &&

		// Membership Level
		$line_item->itemCode == $order->membership_id &&
		$line_item->taxCode == pmproava_get_product_category( $order->membership_id ) &&

		// Address
		$avatax_address->line1      == $pmpro_address_validated->line1 &&
		$avatax_address->line2      == $pmpro_address_validated->line2 &&
		$avatax_address->line3      == $pmpro_address_validated->line3 &&
		$avatax_address->city       == $pmpro_address_validated->city &&
		$avatax_address->region     == $pmpro_address_validated->region &&
		$avatax_address->country    == $pmpro_address_validated->country &&
		$avatax_address->postalCode == $pmpro_address_validated->postalCode &&

		// Totals
		$transaction->totalAmount + $transaction->totalTax == $order->total &&
		$transaction->totalTax == $order->tax &&
		$transaction->totalAmount == $order->subtotal &&

		// Status
		( $transaction->status != 'Committed' || in_array( $order->status, array( 'success', 'cancelled' ) ) ) &&
		( $transaction->status != 'Saved' || in_array( $order->status, array( 'pending', 'token', 'review' ) ) ) &&
		( $transaction->status != 'Cancelled' || in_array( $order->status, array( 'error', 'refunded' ) ) ) &&

		// Date
		// This won't work immediately when the date is newly changed on the edit order page.
		// On that admin page, timestamp is updated separately after order itself.
		$transaction->date == date( 'Y-m-d', strtotime( $order->datetime ) )
	);
	return ! $synced;
}

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
