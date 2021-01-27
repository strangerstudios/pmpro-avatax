<?php

function pmproava_checkout_boxes() {
	global $pmpro_level;

	?>
	<table id="pmpro_sales_tax" class="pmpro_checkout" width="100%" cellpadding="0" cellspacing="0" border="0">
	<thead>
		<tr>
			<th>
				Sales Tax
			</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				<div>
			<?php echo "<p id='pmproava_tax_estimate'>" . __( 'Tax has not yet been calculated.', 'pmpro-avatax' ) . "</p>"; ?>
				</div>
			</td>
			<td>
				<input id="pmproava_calculate_tax" class="button" name="pmproava_calculate_tax" value="<?php _e( 'Calculate Tax', 'pmpro-avatax' ); ?>" type="button"/>
			</td>
		</tr>
	</tbody>
	</table>
	<?php
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
		$localize_vars = array(
			'restUrl' => get_rest_url(),
		);
		wp_localize_script( 'pmproava_checkout', 'pmproava', $localize_vars );
		wp_enqueue_script( 'pmproava_checkout' );
	}
}
add_action( 'wp_enqueue_scripts', 'pmproava_enqueue_checkout_script' );