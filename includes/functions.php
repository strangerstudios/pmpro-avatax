<?php

define("PMPROAVA_GENERAL_MERCHANDISE", "P0000000");
/**
 * Get the Avalara product category for a particular level.
 *
 * @param int $level_id to get category for
 * @return string product category
 */
function pmproava_get_product_category( $level_id ) {
	$pmproava_product_category = get_pmpro_membership_level_meta( $level_id, 'pmproava_product_category', true);
	return $pmproava_product_category ?: PMPROAVA_GENERAL_MERCHANDISE;
}

/**
 * Get the Avalara address model for a particular level.
 *
 * @param int $level_id to get category for
 * @return string address model
 */
function pmproava_get_product_address_model( $level_id ) {
	$pmproava_address_model = get_pmpro_membership_level_meta( $level_id, 'pmproava_address_model', true);
	return $pmproava_address_model ?: 'shipToFrom';
}

function pmproava_tax_filter( $tax, $values, $order ) {
	$level_id = $order->membership_id;
	$product_category = pmproava_get_product_category( $level_id );
	$product_address_model    = pmproava_get_product_address_model( $level_id );

	$billing_address = new stdClass();
	$billing_address->line1 = '837 Winyah Ave';
	//$billing_address->line1 = isset( $values['billing_street'] ) ? $values['billing_street'] : '';
	$billing_address->city = isset( $values['billing_city'] ) ? $values['billing_city'] : '';
	$billing_address->region = isset( $values['billing_state'] ) ? $values['billing_state'] : '';
	$billing_address->postalCode = isset( $values['billing_zip'] ) ? $values['billing_zip'] : '';
	$billing_address->country = isset( $values['billing_country'] ) ? $values['billing_country'] : '';

	$pmproava_sdk_wrapper = PMProava_SDK_Wrapper::get_instance();
	// TODO: Update to check if we should use retrocative tax.
	$tax = $pmproava_sdk_wrapper->calculate_tax( $values['price'], $product_category, $product_address_model, $billing_address ) ?: 0;

	return $tax;
}
add_filter( 'pmpro_tax', 'pmproava_tax_filter', 100, 3 ); // Avalara should have the final say in taxes.