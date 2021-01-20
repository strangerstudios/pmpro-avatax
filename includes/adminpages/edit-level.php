<?php
/**
 * Add settings to edit level page:
 * - Dropdown: Use default product category (X)? Yes/No
 * - If No: product category (default to the default)
 * - store in level meta
 */

define("PMPROAVA_GENERAL_MERCHANDISE", "P0000000");

 /**
  * Add settings to edit level page.
  */
function pmproava_level_settings( $level_id ) {

  	if( empty( $level_id ) && isset( $_REQUEST['edit'] ) ) {
  		$level_id = intval( $_REQUEST['edit'] );
  	} elseif( !isset( $level_id ) ) {
  		$level_id = -1;
  	}

  	$pmproava_use_default_product_category = get_pmpro_membership_level_meta( $level_id, 'pmproava_use_default_product_category', true);
	if( empty( $pmproava_use_default_product_category) ) {
		$pmproava_use_default_product_category = 'yes';
	}

	$pmproava_product_category = get_pmpro_membership_level_meta( $level_id, 'pmproava_product_category', true);
	if( empty( $pmproava_product_category ) ) {
		$pmproava_product_category = PMPROAVA_GENERAL_MERCHANDISE;
	}

  	?>
  	<?php if( $level_id != 0 ) { ?>
  		<h3 class="topborder"> <?php _e('Tax Category', 'pmpro-avatax');?></h3>
  	<?php } ?>
  	<table class="form-table">
  		<tbody>
  			<tr>
  				<th scope="row" valign="top">
  					<label for="pmproava_default_product_category"><?php _e('Use Default Category for Taxes?', 'pmpro-avatax');?>:</label>
  				</th>
  			<td>
  				<select id="pmproava_default_product_category" name="pmproava_default_product_category">
					<option value="no" <?php selected( $pmproava_use_default_product_category, 'no' ); ?>><?php _e('No', 'pmpro-avatax');?></option>
					<option value="yes" <?php selected( $pmproava_use_default_product_category, 'yes' ); ?>><?php _e('Yes', 'pmpro-avatax');?></option>
  				</select><br />
  			</td>
  			</tr>
  			</tbody>
  	</table>

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


}
add_action('pmpro_membership_level_after_other_settings','pmproava_level_settings');

/**
 * Enqueue admin JavaScript and CSS
 */
function pmproava_admin_enqueue_scripts_edit_level() {
	// Check that we are editing PMPro levels.
	$screen = get_current_screen();
	if ( empty( $screen->id ) || $screen->id !== 'memberships_page_pmpro-membershiplevels' ) {
		return;
	}

    wp_register_script( 'pmproava_edit_level',
                        plugins_url( '../js/pmproava-edit-level.js', dirname(__FILE__) ),
                        array( 'jquery' ),
                        PMPROAVA_VERSION );
    wp_enqueue_script( 'pmproava_edit_level' );
}
add_action( 'admin_enqueue_scripts', 'pmproava_admin_enqueue_scripts_edit_level' );

/**
 * Save level settings.
 */
function pmproava_pmpro_save_membership_level($level_id) {
	if( $level_id <= 0 ) {
		return;
	}

	$pmproava_use_default_product_category = isset( $_REQUEST['pmproava_default_product_category'] ) && $_REQUEST['pmproava_default_product_category'] === 'no' ? 'no' : 'yes';
	update_pmpro_membership_level_meta( $level_id, "pmproava_use_default_product_category", $pmproava_use_default_product_category );

	$pmproava_product_category = isset( $_REQUEST['pmproava_product_id'] ) ? trim( preg_replace("[^a-zA-Z0-9\-]", "", $_REQUEST['pmproava_product_id']) ) : PMPROAVA_GENERAL_MERCHANDISE;
  	update_pmpro_membership_level_meta( $level_id, "pmproava_product_category", $pmproava_product_category );
}
add_action('pmpro_save_membership_level','pmproava_pmpro_save_membership_level', 10, 1);
