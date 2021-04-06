jQuery(document).ready(function(){ 
	// Hide/show exemption reason.
	function pmproava_toggle_exemption_reason() {
		if ( jQuery( '#pmproava_user_exempt' ).is( ':checked' ) ) {
			jQuery( '#pmproava_user_exemption_reason_tr' ).show();
		} else {
			jQuery( '#pmproava_user_exemption_reason_tr' ).hide();
		}
	}
	
	// Run on load.
	pmproava_toggle_exemption_reason();
	
	// Bind to updates to the checkbox.
	jQuery( '#pmproava_user_exempt' ).change( function() { pmproava_toggle_exemption_reason(); } );
});