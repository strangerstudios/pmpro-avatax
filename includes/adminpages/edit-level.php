<?php
/**
 * Add settings to edit level page.
 *
 */
function pmproava_level_settings( $level_id ) {
	if ( empty( $level_id ) && isset( $_REQUEST['edit'] ) ) {
		$level_id = intval( $_REQUEST['edit'] );
	} elseif ( ! isset( $level_id ) ) {
		$level_id = -1;
	}
	$pmproava_product_category = pmproava_get_product_category( $level_id );
	?>
	<hr />
	<h2 class="title"><?php esc_html_e( 'Tax Category', 'pmpro-avatax' ); ?></h2>
	<table id="product_id" class="form-table">
		<tbody>
			<tr>
				<th scope="row" valign="top">
					<label for="pmproava_product_id"><?php esc_html_e( 'Product Category ID', 'pmpro-avatax' ); ?>:</label>
				</th>
				<td>
					<input type="text" name="pmproava_product_id" class="regular-text" value="<?php esc_attr_e( $pmproava_product_category ); ?>">
					<p class="description"><?php esc_html_e( 'Enter the Avalara Tax Code for this level.', 'pmpro-avatax' ); ?> <a target="_blank" href="https://taxcode.avatax.avalara.com"><?php esc_html_e( 'Product Category ID Reference &raquo;', 'pmpro-avatax' ); ?></a></p>
				</td>
			</tr>
		</tbody>
	</table>
  	<?php
}
add_action( 'pmpro_membership_level_after_other_settings', 'pmproava_level_settings' );

/**
 * Save level settings.
 *
 */
function pmproava_pmpro_save_membership_level( $level_id ) {
	if ( $level_id <= 0 ) {
		return;
	}

	$pmproava_product_category = isset( $_REQUEST['pmproava_product_id'] ) ? trim( preg_replace("[^a-zA-Z0-9\-]", "", $_REQUEST['pmproava_product_id']) ) : '';
	update_pmpro_membership_level_meta( $level_id, "pmproava_product_category", $pmproava_product_category );
}
add_action( 'pmpro_save_membership_level', 'pmproava_pmpro_save_membership_level', 10, 1 );
