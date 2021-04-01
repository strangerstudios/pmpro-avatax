<?php

function pmproava_checkout_boxes() {
	global $pmpro_level;

	$options = pmproava_get_options();		
	$retroactive_tax = $options['retroactive_tax'] === 'yes' ? true : false;
	$vat_field = $options['vat_field'] === 'yes' ? true : false;

	if ( ! empty( $_REQUEST['pmproava_vat_number'] ) ) {
		$pmproava_show_vat = true;
		$pmproava_vat_number = sanitize_text_field( $_REQUEST['pmproava_vat_number'] );
	} else {
		$pmproava_show_vat = false;
		$pmproava_vat_number = '';
	}

	if ( ! $retroactive_tax || $vat_field ) {
		?>
		<div class="pmpro_checkout">
			<h3><span class="pmpro_checkout-h3-name"><?php _e( 'Tax', 'pmpro-avatax' ); ?></span></h3>
			<div class="pmpro_checkout-fields">
			<?php if ( ! $retroactive_tax ) { ?>				
					<div class="pmpro_checkout-field">
						<?php echo "<p id='pmproava_tax_estimate'>" . __( 'Tax has not yet been calculated.', 'pmpro-avatax' ) . "</p>"; ?>
						<input id="pmproava_calculate_tax" class="button" name="pmproava_calculate_tax" value="<?php _e( 'Calculate Tax', 'pmpro-avatax' ); ?>" type="button"/>				
					</div>				
			<?php } ?>
			<?php if ( $vat_field ) { ?>
				<div id="pmproava_have_vat_number" class="pmpro_checkout-field pmpro_checkout-field-checkbox">
					<input id="pmproava_show_vat" type="checkbox" name="pmproava_show_vat" value="1" <?php checked($pmproava_show_vat, 1);?>>
					<label for="pmproava_show_vat" class="pmpro_clickable"><?php printf( __( 'Check if you have a VAT Number for %s.', 'pmpro-avatax'), $options['company_address']->country );?></label>
				</div> <!-- end vat_have_number -->
				<div id="pmproava_vat_number_div" class="pmpro_checkout-field">
					<label for="pmproava_vat_number"><?php _e('Vat Number', 'pmpro-avatax');?></label>
					<input id="pmproava_vat_number" name="pmproava_vat_number" class="input" type="text"  size="20" value="<?php echo esc_attr($pmproava_vat_number);?>" />					
				</div> <!-- end vat_number_validation_tr -->
			<?php } ?>
			</div>			
		</div>
		<?php
	}
}
add_action("pmpro_checkout_after_billing_fields", "pmproava_checkout_boxes");

/**
 * Enqueue frontend JavaScript.
 */
function pmproava_enqueue_checkout_script() {
	$options = pmproava_get_options();
	$retroactive_tax = $options['retroactive_tax'] === 'yes' ? true : false;

	if ( pmpro_is_checkout() && ! $retroactive_tax ) {
		wp_register_script( 'pmproava_checkout',
			plugins_url( 'js/pmproava-checkout.js', dirname(__FILE__) ),
			array( 'jquery' ),
			PMPROAVA_VERSION
		);
		$localize_vars = array(
			'restUrl' => get_rest_url(),
		);
		wp_localize_script( 'pmproava_checkout', 'pmproava', $localize_vars );
		wp_enqueue_script( 'pmproava_checkout' );
	}
}
add_action( 'wp_enqueue_scripts', 'pmproava_enqueue_checkout_script' );

// if not, we should probably return 0 to prevent other plugins for interfering.
function pmproava_tax_filter( $tax, $values, $order ) {
	$level_id              = $order->membership_id;
	$product_category      = pmproava_get_product_category( $level_id );
	$product_address_model = pmproava_get_product_address_model( $level_id );

	$retroactive_tax = true;
	$options = pmproava_get_options();
	if ( ! pmpro_is_checkout() || $options['retroactive_tax'] === 'yes' ) {
		// Not at checkout or tax is being calculated retroactively. Don't need to calculate tax right now.
		return 0;
	}

	if ( $product_address_model === 'singleLocation' ) {
		// Improves caching.
		$billing_address = null;
	} else {
		$billing_address = new stdClass();
		$billing_address->line1 = isset( $values['billing_street'] ) ? $values['billing_street'] : '';
		$billing_address->city = isset( $values['billing_city'] ) ? $values['billing_city'] : '';
		$billing_address->region = isset( $values['billing_state'] ) ? $values['billing_state'] : '';
		$billing_address->postalCode = isset( $values['billing_zip'] ) ? $values['billing_zip'] : '';
		$billing_address->country = isset( $values['billing_country'] ) ? $values['billing_country'] : '';
	}

	$pmpro_avatax = PMPro_AvaTax::get_instance();
	return $pmpro_avatax->calculate_tax( $values['price'], $product_category, $product_address_model, $billing_address ) ?: 0;
}
add_filter( 'pmpro_tax', 'pmproava_tax_filter', 100, 3 ); // AvaTax should have the final say in taxes.

function pmproava_registration_checks( $okay ) {
	// There is already an error being thrown.
	if ( ! $okay ) {
		return $okay;
	}

	global $pmpro_level;
	if ( pmproava_get_product_address_model( $pmpro_level->id ) !== 'singleLocation' ) {
		// User needs to have a valid billing address.
		$billing_address = new stdClass();
		$billing_address->line1 = isset( $_REQUEST['baddress1'] ) ? $_REQUEST['baddress1'] : '';
		$billing_address->city = isset( $_REQUEST['bcity'] ) ? $_REQUEST['bcity'] : '';
		$billing_address->region = isset( $_REQUEST['bstate'] ) ? $_REQUEST['bstate'] : '';
		$billing_address->postalCode = isset( $_REQUEST['bzipcode'] ) ? $_REQUEST['bzipcode'] : '';
		$billing_address->country = isset( $_REQUEST['bcountry'] ) ? $_REQUEST['bcountry'] : '';

		$pmpro_avatax = PMPro_AvaTax::get_instance();
		if ( empty( $pmpro_avatax->validate_address( $billing_address ) ) ) {
			// Billing address validation failed.
			$okay = false;
			pmpro_setMessage("Billing address was not valid.", "pmpro_error");
		}
	}

	return $okay;
}
add_filter( 'pmpro_registration_checks', 'pmproava_registration_checks', 10, 1 );

function pmproava_enable_saving_vat_at_checkout() {
	add_action( 'pmpro_added_order', 'pmroava_save_vat_at_checkout', 9, 1 );
	add_action( 'pmpro_updated_order', 'pmroava_save_vat_at_checkout', 9, 1 );
}
add_action( 'pmpro_checkout_before_change_membership_level', 'pmproava_enable_saving_vat_at_checkout' );

function pmroava_save_vat_at_checkout( $order ) {
	if ( ! empty( $_REQUEST['pmproava_show_vat'] ) && ! empty( $_REQUEST['pmproava_vat_number'] ) ) {
		update_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', sanitize_text_field( $_REQUEST['pmproava_vat_number'] ) );
	}
}