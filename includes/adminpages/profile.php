<?php

/**
 * Show the shipping address in the profile
 */
function pmproava_show_extra_profile_fields( $user ) {
    // Get all possible entity use codes.
    $pmpro_avatax = PMPro_AvaTax::get_instance();
    $entity_use_codes = $pmpro_avatax->get_entity_use_codes();

    // Get user's set exepmtion reason.
    $exemption_reason = get_user_meta( $user->ID, 'pmproava_user_exemption_reason', true );
    $exempt = ! empty( $exemption_reason );

	// Show the shipping fields if the membership level includes fields or the user is an admin.
	if ( current_user_can( 'manage_options' ) ) { ?>
	    <h3><?php esc_html_e( 'PMPro AvaTax', 'pmpro-avatax' ); ?></h3>
	    <table class="form-table">
	        <tr>
	            <th><?php esc_html_e( 'Exempt From Tax', 'pmpro-avatax' ); ?></th>
	            <td>
                    <input id="pmproava_user_exempt_present" name="pmproava_user_exempt_present" type="hidden" value='1' />
	                <input id="pmproava_user_exempt" name="pmproava_user_exempt" type="checkbox" <?php checked( $exempt ); ?>/>
	            </td>
	        </tr>
            <tr id='pmproava_user_exemption_reason_tr'>
	            <th><?php esc_html_e( 'Exemption Reason', 'pmpro-avatax' ); ?></th>
	            <td>
	                <select id="pmproava_user_exemption_reason" name="pmproava_user_exemption_reason">
                    <?php
                        foreach ( $entity_use_codes as $entity_use_code ) {
                            $selected_modifier = ( $exemption_reason == $entity_use_code->code ) ? ' selected ' : '';
                            ?>
                            <option value="<?php esc_html_e( $entity_use_code->code ) ?>" <?php echo $selected_modifier ?>><?php esc_html_e( $entity_use_code->name ) ?></option>
                            <?php
                        }
                    ?>
                    </select>
	            </td>
	        </tr>
	    </table>
		<?php
	}
}
add_action( 'show_user_profile', 'pmproava_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'pmproava_show_extra_profile_fields' );

/**
 * Save profile fields
 */
function pmproava_save_extra_profile_fields( $user_id ) {
	// Bail if the user cannot edit the profile, they aren't updating, or the exepmtion fields were not shown.
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_REQUEST['submit'] ) || ! isset( $_REQUEST['pmproava_user_exempt_present'] ) ) {
		return false;
	}

    if ( empty( $_REQUEST['pmproava_user_exempt'] ) || 'TAXABLE' === $_REQUEST['pmproava_user_exemption_reason'] ) {
        delete_user_meta( $user_id, 'pmproava_user_exemption_reason' );
    } else {
        update_user_meta( $user_id, 'pmproava_user_exemption_reason', sanitize_text_field( $_REQUEST['pmproava_user_exemption_reason'] ) );
    }
}
add_action( 'personal_options_update', 'pmproava_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'pmproava_save_extra_profile_fields' );
