<?php
/**
  * Add settings to edit level page.
  */
function pmproava_level_settings( $level_id ) {

  	if( empty( $level_id ) && isset( $_REQUEST['edit'] ) ) {
  		$level_id = intval( $_REQUEST['edit'] );
  	} elseif( !isset( $level_id ) ) {
  		$level_id = -1;
  	}

	$pmproava_product_category = pmproava_get_product_category( $level_id );
  	?>
  	<?php if( $level_id != 0 ) { ?>
  		<h3 class="topborder"> <?php _e('Tax Category', 'pmpro-avatax');?></h3>
  	<?php } ?>
  	<table id="product_id" class="form-table">
  		<tbody>
			<tr>
  				<th scope="row" valign="top">
  					<label for="pmproava_product_id"><?php _e('Product Category ID', 'pmpro-avatax');?>:</label>
  				</th>
  				<td>
  				  <input type="text" name="pmproava_product_id" value=<?php echo $pmproava_product_category ?>><br>
  				</td>
  			</tr>
		</tbody>
  	</table>

	<?php
	$pmproava_address_model = get_address_model( $level_id );
	?>
	<table id="address_model" class="form-table">
		<tbody>
			<tr>
				<th scope="row" valign="top">
					<label for="pmproava_address_model"><?php _e('Address Model', 'pmpro-avatax');?>:</label>
				</th>
				<td>
					<select name="pmproava_address_model">
						<?php
							$options = array(
								'shipToFrom' => 'Variable Location',
								'singleLocation' => 'Single Location',
							);
							foreach ( $options as $value => $label ) {
								$selected_text = $pmproava_address_model === $value ? 'selected="selected"' : '';
								echo '<option value="' . $value . '" ' . $selected_text . '>' . $label . '</option>';
							}
						?>
					</select>
					<br>
				</td>
			</tr>
		</tbody>
  	</table>
  	<?php


}
add_action('pmpro_membership_level_after_other_settings','pmproava_level_settings');

/**
 * Save level settings.
 */
function pmproava_pmpro_save_membership_level($level_id) {
	if( $level_id <= 0 ) {
		return;
	}

	$pmproava_product_category = isset( $_REQUEST['pmproava_product_id'] ) ? trim( preg_replace("[^a-zA-Z0-9\-]", "", $_REQUEST['pmproava_product_id']) ) : '';
	update_pmpro_membership_level_meta( $level_id, "pmproava_product_category", $pmproava_product_category );

	$pmproava_address_model = isset( $_REQUEST['pmproava_address_model'] ) ? trim( preg_replace("[^a-zA-Z0-9\-]", "", $_REQUEST['pmproava_address_model']) ) : '';
	update_pmpro_membership_level_meta( $level_id, "pmproava_address_model", $pmproava_address_model );
}
add_action('pmpro_save_membership_level','pmproava_pmpro_save_membership_level', 10, 1);
