<?php

function pmproava_after_order_settings( $order ) {
	if ( empty( $order->id ) ) {
		// This is a new order.
		return;
	}
	$pmproava_options     = pmproava_get_options();
	$pmproava_sdk_wrapper = PMProava_SDK_Wrapper::get_instance();
	$document_code        = pmproava_get_document_code( $order );
	if ( $pmproava_sdk_wrapper->transaction_exists_for_code( $document_code ) ) {
		$transaction = $pmproava_sdk_wrapper->get_transaction_by_code( $document_code );
		?>
		<tr>
			<th>AvaTax</th>
			<td>
				<table>
					<tr>
						<th>Document Code</th>
						<td><?php echo $document_code; ?></td>
					</tr>
					<tr>
						<th>Customer Code</th>
						<td><?php echo $transaction->customerCode; ?></td>
					</tr>
					<tr>
						<th>Date</th>
						<td><?php echo $transaction->date; ?></td>
					</tr>
					<tr>
						<th>Subtotal</th>
						<td><?php echo $transaction->totalAmount; ?></td>
					</tr>
					<tr>
						<th>Tax</th>
						<td><?php echo $transaction->totalTax; ?></td>
					</tr>
					<tr>
						<th>Status</th>
						<td><?php echo $transaction->status; ?></td>
					</tr>
					<tr>
						<th>URL</th>
						<?php
						$url = 'https://' . ( $pmproava_options['environment'] != 'production' ? 'sandbox.' : '' ) . 'admin.avalara.com/cup/a/' . $pmproava_options['account_number'] . '/c/' . $transaction->companyId . '/transactions/' . $transaction->id;
						?>
						<td><a href="<?php echo $url ?>" target="_blank"><?php echo $url ?></a></td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
	} else {
		?>
		<tr>
			<th>AvaTax</th>
			<td><?php _e( 'This order has not yet been sent to AvaTax.', 'pmpro-avatax' ) ?></td>
		</tr>
		<?php
	}
}
add_action( 'pmpro_after_order_settings', 'pmproava_after_order_settings', 10, 1 );