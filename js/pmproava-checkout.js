jQuery(document).ready(function(){ 
	// Hide/show VAT Number field.
	function pmproava_toggleVATField() {
		if ( jQuery( '#pmproava_show_vat' ).is( ':checked' ) ) {
			jQuery( '#pmproava_vat_number_div' ).show();
		} else {
			jQuery( '#pmproava_vat_number_div' ).hide();
		}
	}
	
	// Run on load.
	pmproava_toggleVATField();
	
	// Bind to updates to the checkbox.
	jQuery( '#pmproava_show_vat' ).change( function() { pmproava_toggleVATField(); } );
});