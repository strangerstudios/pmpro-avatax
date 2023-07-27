<?php

function pmproava_checkout_boxes() {
	global $pmpro_level;

	$options = pmproava_get_options();

	$show_vat_fields = $options['vat_field'] === 'yes' && ! pmpro_isLevelFree( $pmpro_level );
	if ( ! empty( $_REQUEST['pmproava_vat_number'] ) ) {
		$pmproava_vat_number = sanitize_text_field( $_REQUEST['pmproava_vat_number'] );
	} else {
		$pmproava_vat_number = '';
	}

	// This variable should "and" all "show x field" options together.
	$show_checkout_options = $show_vat_fields;

	if ( $show_checkout_options ) {
		?>
		<div class="pmpro_checkout">
			<h2><span class="pmpro_checkout-h2-name"><?php _e( 'Tax', 'pmpro-avatax' ); ?></span></h2>
			<div class="pmpro_checkout-fields">
			<?php if ( $show_vat_fields ) { ?>
				<div id="pmproava_have_vat_number" class="pmpro_checkout-field pmpro_checkout-field-checkbox pmpro_checkout-field-pmproava_show_vat">
					<input id="pmproava_show_vat" type="checkbox" name="pmproava_show_vat" value="1" <?php checked( ! empty( $pmproava_vat_number ), true );?>>
					<label for="pmproava_show_vat" class="pmpro_clickable"><?php esc_html_e( 'Check if you have a VAT Number.', 'pmpro-avatax');?></label>
				</div> <!-- end vat_have_number -->
				<div id="pmproava_vat_number_div" class="pmpro_checkout-field pmpro_checkout-field-pmproava_vat_number">
					<label for="pmproava_vat_number"><?php _e('VAT Number', 'pmpro-avatax');?></label>
					<input id="pmproava_vat_number" name="pmproava_vat_number" class="input" type="text"  size="30" value="<?php echo esc_attr($pmproava_vat_number);?>" />					
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
	if ( pmpro_is_checkout() ) {
		wp_register_script( 'pmproava_checkout',
			plugins_url( 'js/pmproava-checkout.js', dirname(__FILE__) ),
			array( 'jquery' ),
			PMPROAVA_VERSION
		);
		wp_enqueue_script( 'pmproava_checkout' );
	}
}
add_action( 'wp_enqueue_scripts', 'pmproava_enqueue_checkout_script' );

function pmproava_registration_checks( $okay ) {
	// There is already an error being thrown.
	if ( ! $okay ) {
		return $okay;
	}

	global $pmpro_level;
	if ( ! pmpro_isLevelFree( $pmpro_level ) ) {
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
