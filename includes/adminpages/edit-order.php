<?php

function pmproava_after_order_settings( $order ) {
	if ( empty( $order->id ) ) {
		// This is a new order.
		return;
	}

	// This is temporary. We really need a hook in core for before an order is saved on the Edit Order page.
	if ( ! empty( $_REQUEST['save'] ) ) {
		if ( ! empty( $_REQUEST['pmproava_vat_number'] ) ) {
			update_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', sanitize_text_field( $_REQUEST['pmproava_vat_number'] ) );
		}
	}

	$pmproava_options     = pmproava_get_options();
	$pmpro_avatax         = PMPro_AvaTax::get_instance();
	$transaction_code     = pmproava_get_transaction_code( $order );
	$transaction          = $pmpro_avatax->get_transaction_for_order( $order );
	$last_sync            = get_pmpro_membership_order_meta( $order->id, 'pmproava_last_sync', true );
	if ( empty( $last_sync ) ) {
		$last_sync = __( 'Never', 'pmpro-avatax' );
	}
	?>
	<tr>
		<th><?php esc_html_e( 'AvaTax', 'pmpro-avatax' ); ?></th>
		<td>
			<table>
				<tr>
					<th><?php esc_html_e( 'Transaction Code', 'pmpro-avatax' ); ?></th>
					<td><?php esc_html_e( $transaction_code ); ?></td>
				</tr>
				<?php 
					$error = pmproava_get_order_error( $order );
					if ( ! empty( $error ) ) {
						?>
						<tr>
							<th><?php esc_html_e( 'Error', 'pmpro-avatax' ); ?></th>
							<td><?php esc_html_e( $error ); ?></td>
						</tr>
						<?php
					}
				?>
				<tr>
					<th><?php esc_html_e( 'Last Updated', 'pmpro-avatax' ); ?></th>
					<td><?php esc_html_e( $last_sync ); ?></td>
				</tr>
				<?php
				if ( ! empty( $transaction ) && empty( $transaction->error ) ) {
					?>
					<tr>
						<th><?php esc_html_e( 'Customer Code', 'pmpro-avatax' ); ?></th>
						<td><?php esc_html_e( $transaction->customerCode); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Locked', 'pmpro-avatax' ); ?></th>
						<td><?php esc_html_e( $transaction->locked ? __( 'Yes', 'pmpro-avatax' ) : __( 'No', 'pmpro-avatax' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'URL', 'pmpro-avatax' ); ?></th>
						<?php
						$url = 'https://' . ( $pmproava_options['environment'] != 'production' ? 'sandbox.' : '' ) . 'admin.avalara.com/cup/a/' . $pmproava_options['account_number'] . '/c/' . $transaction->companyId . '/transactions/' . $transaction->id;
						?>
						<td><a href="<?php echo $url ?>" target="_blank"><?php echo $url ?></a></td>
					</tr>
					<?php
					if ( ! empty( $transaction->businessIdentificationNo ) || $pmproava_options['vat_field'] === 'yes' ) {
						?>
						<tr>
							<th><?php esc_html_e( 'VAT Number', 'pmpro-avatax' ); ?></th>
							<?php
								$vat_number = get_pmpro_membership_order_meta( $order->id, 'pmproava_vat_number', true );
							?>
							<td><input id="pmproava_vat_number" name="pmproava_vat_number" type="text" size="50" value="<?php echo esc_attr( $vat_number ); ?>"/></td>
						</tr>
						<?php
					}
				}
				?>
			</table>
		</td>
	</tr>
	<?php
}
add_action( 'pmpro_after_order_settings', 'pmproava_after_order_settings', 10, 1 );