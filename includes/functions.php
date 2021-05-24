<?php

/**
 * Get PMPro AvaTax options.
 */
function pmproava_get_options() {
	static $options = null;
	if ( $options === null ) {
		$set_options = get_option( 'pmproava_options' );
		$set_options = is_array( $set_options ) ? $set_options : array();

		$default_address             = new stdClass();
		$default_address->line1      = '';
		$default_address->line2      = '';
		$default_address->line3      = '';
		$default_address->city       = '';
		$default_address->region     = '';
		$default_address->postalCode = '';
		$default_address->country    = '';

		$default_options = array(
			'account_number'   => '',
			'license_key'      => '',
			'environment'      => 'sandbox',
			'company_code'     => '',
			'company_address'  => $default_address,
			'record_documents' => 'yes',
			'vat_field'        => 'no',
			'validate_address' => 'yes',
			'site_prefix'      => 'PMPRO',
		);
		$options         = array_merge( $default_options, $set_options );
	}

	return $options;
}

/**
 * Validate Avatax settings.
 */
function pmproava_options_validate( $input ) {
	$newinput = array();
	if ( isset( $input['account_number'] ) ) {
		$newinput['account_number'] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input['account_number'] ) );
	}
	if ( isset( $input['license_key'] ) ) {
		$newinput['license_key'] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input['license_key'] ) );
	}
	if ( isset( $input['environment'] ) && $input['environment'] === 'production' ) {
		$newinput['environment'] = 'production';
	}
	if ( isset( $input['company_code'] ) ) {
		$newinput['company_code'] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input['company_code'] ) );
	}
	if ( isset( $input['company_address'] ) ) {
		$newinput['company_address'] = (object) $input['company_address'];
	}
	if ( isset( $input['record_documents'] ) && $input['record_documents'] === 'no' ) {
		$newinput['record_documents'] = 'no';
	} elseif ( isset( $input['record_documents'] ) ) {
		$newinput['record_documents'] = 'yes';
	}
	if ( isset( $input['vat_field'] ) && $input['vat_field'] === 'no' ) {
		$newinput['vat_field'] = 'no';
	} elseif ( isset( $input['vat_field'] ) ) {
		$newinput['vat_field'] = 'yes';
	}
	if ( isset( $input['validate_address'] ) && $input['validate_address'] === 'no' ) {
		$newinput['validate_address'] = 'no';
	} elseif ( isset( $input['validate_address'] ) ) {
		$newinput['validate_address'] = 'yes';
	}
	if ( isset( $input['site_prefix'] ) ) {
		$newinput['site_prefix'] = trim( preg_replace( "[^a-zA-Z0-9\-]", "", $input['site_prefix'] ) );
	}

	return $newinput;
}

define( "PMPROAVA_GENERAL_MERCHANDISE", "P0000000" );
/**
 * Get the AvaTax product category for a particular level.
 *
 * @param int $level_id to get category for
 *
 * @return string product category
 */
function pmproava_get_product_category( $level_id ) {
	$pmproava_product_category = get_pmpro_membership_level_meta( $level_id, 'pmproava_product_category', true );

	return $pmproava_product_category ?: PMPROAVA_GENERAL_MERCHANDISE;
}

/**
 * Get the AvaTax customer code for a given user_id.
 *
 * @param int $user_id to get customer code for.
 *
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
 * Check whether the PMPro AvaTax environment is the same as
 * an order's gateway environment.
 *
 * @param MemberOrder $order to compare gateway environemnt for.
 *
 * @return bool
 */
function pmproava_environment_same_as_order_environment( $order ) {
	$pmproava_options     = pmproava_get_options();
	$pmproava_environment = $pmproava_options['environment'] === 'sandbox' ? 'sandbox' : 'live';

	return $pmproava_environment === $order->gateway_environment;
}

/**
 * Get the Avalara transaction code for a particular order.
 *
 * @param MemberOrder $order to get transaction code for.
 *
 * @return string
 */
function pmproava_get_transaction_code( $order ) {
	$transaction_code = get_pmpro_membership_order_meta( $order->id, 'pmproava_transaction_code', true );
	if ( empty( $customer_code ) ) {
		$pmproava_options = pmproava_get_options();
		$transaction_code = $pmproava_options['site_prefix'] . '-' . $order->code;
		update_pmpro_membership_order_meta( $order->id, 'pmproava_transaction_code', $transaction_code );
	}

	return $transaction_code;
}

function pmproava_updated_order( $order ) {
	global $wpdb;

	// Save extra fields.
	if ( pmpro_is_checkout() || ( is_admin() && $_REQUEST['page'] === 'pmpro-orders' && ! empty( $_REQUEST['save'] ) ) ) {
		if ( isset( $_REQUEST['pmproava_vat_number'] ) ) {
			update_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', sanitize_text_field( $_REQUEST['pmproava_vat_number'] ) );
		}
	}

	// Check if gateway environments match for order and Avalara creds. If not, return.
	if ( ! pmproava_environment_same_as_order_environment( $order ) ) {
		return;
	}

	$pmpro_avatax = PMPro_AvaTax::get_instance();
	$transaction  = $pmpro_avatax->get_transaction_for_order( $order );

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
			`tax` = '" . esc_sql( $transaction->totalTax ) . "',
			`total` = '" . esc_sql( $transaction->totalAmount + $transaction->totalTax ) . "' 	
		WHERE id = '" . esc_sql( $order->id ) . "'
		LIMIT 1"
		);
	}

	// Clear AvaTax errors for order.
	pmproava_save_order_error( $order );
}

add_action( 'pmpro_added_order', 'pmproava_updated_order' );
add_action( 'pmpro_updated_order', 'pmproava_updated_order' );

function pmproava_order_should_sync_with_transaction( $order, $transaction ) {
	// Check if correct environment.
	if ( ! pmproava_environment_same_as_order_environment( $order ) ) {
		return false;
	}

	// If transaction does not already exist in AvaTax and order is voided in PMPro, return false.
	if ( empty( $transaction ) && in_array( $order->status, array( 'refunded', 'error' ) ) ) {
		return false;
	}

	// If transaction does not already exist in AvaTax and order is free, return false.
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
	$line_item = $transaction->lines[0];

	$avatax_destination_address_id = $transaction->destinationAddressId;
	$avatax_address                = null;
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

	$pmpro_address             = new stdClass();
	$pmpro_address->line1      = $order->billing->street;
	$pmpro_address->city       = $order->billing->city;
	$pmpro_address->region     = $order->billing->state;
	$pmpro_address->postalCode = $order->billing->zip;
	$pmpro_address->country    = $order->billing->country;

	$pmpro_avatax            = PMPro_AvaTax::get_instance();
	$pmpro_address_validated = $pmpro_avatax->validate_address( $pmpro_address );
	if ( empty( $pmpro_address_validated ) ) {
		// Don't try to update. We had an error with  address validation.
		pmproava_save_order_error( $order );

		return false;
	}

	$vat_number = get_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', true );

	$exemption_reason = get_user_meta( $order->user_id, 'pmproava_user_exemption_reason', true );
	$entity_use_code  = empty( $exemption_reason ) ? null : $exemption_reason;

	$r = false;
	if ( $transaction->customerCode != pmproava_get_customer_code( $order->user_id ) ) { // User
		$r = true;
	} elseif ( ( empty( $transaction->entityUseCode ) ? null : $transaction->entityUseCode ) != $entity_use_code ) {
		$r = true;
	} elseif ( ( empty( $transaction->businessIdentificationNo ) ? null : $transaction->businessIdentificationNo ) != ( empty( $vat_number ) ? null : $vat_number ) ) { // VAT
		$r = true;
	} elseif ( $line_item->itemCode != $order->membership_id ) { // Membership Level
		$r = true;
	} elseif ( $line_item->taxCode != pmproava_get_product_category( $order->membership_id ) ) {
		$r = true;
	} elseif ( $avatax_address->line1 != $pmpro_address_validated->line1 ) { // Address
		$r = true;
	} elseif ( $avatax_address->line2 != $pmpro_address_validated->line2 ) {
		$r = true;
	} elseif ( $avatax_address->line3 != $pmpro_address_validated->line3 ) {
		$r = true;
	} elseif ( $avatax_address->city != $pmpro_address_validated->city ) {
		$r = true;
	} elseif ( $avatax_address->region != $pmpro_address_validated->region ) {
		$r = true;
	} elseif ( $avatax_address->country != $pmpro_address_validated->country ) {
		$r = true;
	} elseif ( $avatax_address->postalCode != $pmpro_address_validated->postalCode ) {
		$r = true;
	} elseif ( $transaction->totalAmount + $transaction->totalTax != $order->total ) { // Totals
		$r = true;
	} elseif ( $transaction->totalTax != $order->tax ) {
		$r = true;
	} elseif ( $transaction->totalAmount != $order->subtotal ) {
		$r = true;
	} elseif ( $transaction->status == 'Committed' && ! in_array( $order->status, array(
			'success',
			'cancelled'
		) ) ) { // Status
		$r = true;
	} elseif ( $transaction->status == 'Saved' && ! in_array( $order->status, array(
			'pending',
			'token',
			'review'
		) ) ) {
		$r = true;
	} elseif ( $transaction->status == 'Cancelled' && ! in_array( $order->status, array( 'error', 'refunded' ) ) ) {
		$r = true;
	} elseif ( $transaction->date != date( 'Y-m-d', strtotime( $order->datetime ) ) ) { // Date
		$r = true;
	}

	return $r;
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

function pmproava_order_deleted( $order_id, $order ) {
	$pmpro_avatax = PMPro_AvaTax::get_instance();
	$pmpro_avatax->void_transaction_for_order( $order );
	delete_pmpro_membership_order_meta( $order_id, 'pmproava_transaction_code' );
	delete_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number' );
}

add_action( 'pmpro_delete_order', 'pmproava_order_deleted', 10, 2 );

/**
 * Add Avalara data to CSV order export.
 */
function pmproava_pmpro_orders_csv_extra_columns( $columns ) {
	$columns['avatax_transaction_code'] = 'pmproava_transaction_code_for_orders_csv';
	$columns['avatax_vat_number']       = 'pmproava_vat_number_for_orders_csv';

	return $columns;
}

add_filter( 'pmpro_orders_csv_extra_columns', 'pmproava_pmpro_orders_csv_extra_columns' );

function pmproava_transaction_code_for_orders_csv( $order ) {
	return pmproava_get_transaction_code( $order );
}

function pmproava_vat_number_for_orders_csv( $order ) {
	$vat_number = get_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', true );

	return empty( $vat_number ) ? '' : $vat_number;
}

function pmproava_get_price_parts( $price_parts, $invoice ) {
	if ( ! empty( $price_parts['tax'] ) ) {
		$pmpro_avatax = PMPro_AvaTax::get_instance();
		$transaction  = $pmpro_avatax->get_transaction_for_order( $invoice );
		if ( $transaction && count( $transaction->summary ) === 1 ) {
			$price_parts['tax']['label'] = esc_html( $transaction->summary[0]->taxName );
		} else {
			$price_part_index_tax = array_search( 'tax', array_keys( $price_parts ) ) + 1;
			$tax_breakdown        = array();
			foreach ( $transaction && $transaction->summary as $tax_element ) {
				$tax_breakdown[ 'avatax_breakdown_' . $tax_element->taxName ] = array(
					'label' => esc_html( $tax_element->taxName ),
					'value' => pmpro_escape_price( pmpro_formatPrice( $tax_element->taxCalculated ) ),
				);
			}
			$price_parts = array_slice( $price_parts, 0, $price_part_index_tax, true ) +
			               $tax_breakdown +
			               array_slice( $price_parts, $price_part_index_tax, count( $price_parts ) - 1, true );
		}
	}

	return $price_parts;
}

add_filter( 'pmpro_get_price_parts', 'pmproava_get_price_parts', 10, 2 );

function pmproava_tax_breakdown_element_class( $class, $element ) {
	if ( substr( $element, 0, 34 ) === 'pmpro_price_part-avatax_breakdown_' ) {
		$class[] = 'pmpro_price_part_sub';
	}

	return $class;
}

add_filter( 'pmpro_element_class', 'pmproava_tax_breakdown_element_class', 10, 2 );

/**
 * Set global variables from checkout request
 */
function pmproava_set_checkout_values() {
	global $baddress1, $baddress2, $bcity, $bstate, $bzipcode, $bcountry, $pmpro_level;

	if ( isset( $_REQUEST['baddress1'] ) ) {
		$baddress1 = trim( sanitize_text_field( $_REQUEST['baddress1'] ) );
	}
	if ( isset( $_REQUEST['baddress2'] ) ) {
		$baddress2 = trim( sanitize_text_field( $_REQUEST['baddress2'] ) );
	}
	if ( isset( $_REQUEST['bcity'] ) ) {
		$bcity = trim( sanitize_text_field( $_REQUEST['bcity'] ) );
	}
	if ( isset( $_REQUEST['bstate'] ) ) {
		$bstate = trim( sanitize_text_field( $_REQUEST['bstate'] ) );
	}
	if ( isset( $_REQUEST['bzipcode'] ) ) {
		$bzipcode = trim( sanitize_text_field( $_REQUEST['bzipcode'] ) );
	}
	if ( isset( $_REQUEST['bcountry'] ) ) {
		$bcountry = trim( sanitize_text_field( $_REQUEST['bcountry'] ) );
	}

	$pmpro_level = pmpro_getLevelAtCheckout();
}

/**
 * Validate the customer address at checkout.
 */
function pmproava_validate_customer_address() {

	global $pmproava_error;

	try {

		/**
		 * Fire before validating a customer address.
		 *
		 * @param array $address The validated and normalized address.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pmproava_before_customer_address_validated' );

		pmproava_set_checkout_values();

		$morder = pmpro_build_order_for_checkout();

		$billing_address = pmpro_avatax()->construct_billing_address( $morder );
		if ( ( $billing_address->line1 || $billing_address->city || $billing_address->region || $billing_address->postalCode )
		     && $billing_address->country
		) {
			$valid_address = pmpro_avatax()->validate_address( $billing_address );
		} else {
			$valid_address = false;
		}

		$_REQUEST['valid_address'] = $valid_address ? true : false;

		if ( $valid_address ) {
			// calculate taxes and validate address
			$transaction = pmpro_avatax()->calculate_pmpro_tax( $morder, $morder->InitialPayment );

			if ( null === $transaction ) {
				throw new \Exception( $pmproava_error ?? 'Unknown error occurred on tax calculation.' );
			}
		} else {
			$morder->total    = $morder->initial_amount;
			$morder->subtotal = $morder->InitialPayment;

			pmproava_update_tax_parts( null, $morder->subtotal );
			$totals = pmpro_get_price_parts( $morder, 'span' );

			if ( $pmproava_error ) {
				throw new \Exception( $pmproava_error );
			} else {
				throw new \Exception( 'Validate address to calculate taxes.' );
			}
		}

		// everything went OK, and taxes have been calculated
		$morder->tax      = $transaction->totalTax ?? 0;
		$morder->subtotal = $transaction->totalAmount ?? $morder->InitialPayment;
		$morder->total    = $morder->subtotal + $morder->tax;

		pmproava_update_tax_parts( $morder->tax, $morder->subtotal );

		$totals = pmpro_get_price_parts( $morder, 'span' );

		if ( $valid_address ) {
			$response_address = array(
				'baddress1' => $valid_address->line1 ?? '',
				'baddress2' => $valid_address->line2 ?? '',
				'bcity'     => $valid_address->city ?? '',
				'bstate'    => $valid_address->region ?? '',
				'bzipcode'  => $valid_address->postalCode ?? '',
				'bcountry'  => $valid_address->country ?? '',
			);
		} else {
			$response_address = null;
		}

		/**
		 * Fire after validating a customer address.
		 *
		 * @param array $address The validated and normalized address.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pmproava_after_customer_address_validated', $response_address );

		// Off you go
		wp_send_json(
			array(
				'code'    => 200,
				'address' => $response_address,
				'totals'  => $totals,
			)
		);

	} catch ( \Exception $e ) {

		wp_send_json(
			array(
				'code'   => (int) $e->getCode(),
				'error'  => esc_html( $e->getMessage() ),
				'totals' => $totals,
			)
		);
	}
}

function pmproava_tax_rate( $tax_response ) {
	if ( false === $tax_response || ! isset( $tax_response->totalTaxCalculated ) || ! isset( $tax_response->totalTaxable ) ) {
		$tax_rate = null;
	} else {
		if ( $tax_response->totalTaxable == 0 || $tax_response->totalTaxCalculated == 0 ) {
			$tax_rate = 0;
		} else {
			$tax_rate = round( $tax_response->totalTaxable / $tax_response->totalTaxCalculated, 2 );
		}
	}

	return ( $tax_rate > 1 ) ? $tax_rate / 100 : $tax_rate;
}

function pmproava_update_tax_parts( $tax_rate, $subtotal ) {
	$tax_parts             = array();
	$tax_parts['subtotal'] = array(
		'label' => __( 'Subtotal', 'pmpro-avatax' ),
		'value' => pmpro_escape_price( pmpro_formatPrice( $subtotal ) ),
	);

	if ( null === $tax_rate ) {
		$tax_parts['tax'] = array(
			'label' => __( 'Tax', 'pmpro-avatax' ),
			'value' => __( 'Taxes will be calculated after you validate your address', 'pmpro-avatax' ),
		);
	} elseif ( 0 === (int) $tax_rate ) {
		$tax_parts['tax'] = array(
			'label' => __( 'Tax', 'pmpro-avatax' ),
			'value' => pmpro_escape_price( pmpro_formatPrice( 0 ) ),
		);
	}

	$fn = function ( $parts ) use ( $tax_parts ) {
		return array_merge( $parts, $tax_parts );
	};

	add_filter( 'pmpro_get_price_parts', $fn, 10, 2 );
}

function pmproava_tax_before_charge($tax, $values, $order) {
	if ( isset($_REQUEST['valid_address']) && $_REQUEST['valid_address'] && $tax == 0 ) {
		// Get new/updated transaction.
		$transaction = pmpro_avatax()->calculate_pmpro_tax( $order, $values['price'] );

		if ($transaction) {
			return $transaction->totalTax ?? 0;
		}
	}

	return $tax;
}
add_filter("pmpro_tax", 'pmproava_tax_before_charge', 10, 3);
