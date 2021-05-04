<?php
/**
 * Code to aid with user data privacy, e.g. GDPR compliance
 * 
 * @since  1.9.5
 */

/** 
 * Add suggested Privacy Policy language for PMPro
 * @since 1.9.5
 */
function pmproava_add_privacy_policy_content() {	
	// Check for support.
	if ( ! function_exists( 'wp_add_privacy_policy_content') ) {
		return;
	}

	$content = '';
	$content .= '<h2>' . __( 'Tax Amount Charged for Recurring Subscriptions', 'pmpro-avatax' ) . '</h2>';
	$content .= '<p>' . __( "All taxes are included in the price shown at checkout. For recurring subscriptions, there is a chance that the tax amount changes during the subscription. In those cases, the amount charged to you will NOT be updated to reflect the new tax amount.", 'pmpro-avatax' ) . '</p>';

	wp_add_privacy_policy_content( 'Paid Memberships Pro - AvaTax Add On', $content );
}
add_action( 'admin_init', 'pmproava_add_privacy_policy_content', 11 );
