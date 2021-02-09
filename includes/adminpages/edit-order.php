<?php

function pmproava_after_order_settings( $order ) {
	if ( empty( $order->id ) ) {
		// This is a new order.
		return;
	}

	$pmproava_options     = pmproava_get_options();
	$pmproava_sdk_wrapper = PMProava_SDK_Wrapper::get_instance();
	$transaction_code     = pmproava_get_transaction_code( $order );
	?>
	<tr>
		<th>AvaTax</th>
		<td>
			<table>
				<?php 
					$error = pmproava_get_order_error( $order );
					if ( ! empty( $error ) ) {
						?>
						<tr>
							<th>Error</th>
							<td><?php echo $error; ?></td>
						</tr>
						<?php
					}
				?>
				<tr>
					<th>Transaction Code</th>
					<td><?php echo $transaction_code; ?></td>
				</tr>
				<?php
				$transaction = $pmproava_sdk_wrapper->get_transaction_for_order( $order );
				if ( ! empty( $transaction ) && empty( $transaction->error ) ) {
					?>
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
					<?php
				} else {
					?>
					<tr>
						<th>Status</th>
						<td><?php echo 'This order has not yet been sent to AvaTax.'; ?></td>
					</tr>
					<?php
				}
				?>
			</table>
		</td>
	</tr>
	<?php
}
add_action( 'pmpro_after_order_settings', 'pmproava_after_order_settings', 10, 1 );