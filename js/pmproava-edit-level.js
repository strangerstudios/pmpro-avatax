function pmproava_edit_level_update_tr() {
    var product_id = jQuery( '#pmproava_default_product_category' ).val();
    if(product_id == "no") {
        jQuery( '#product_id' ).show();
    } else {
        jQuery( '#product_id' ).hide();
    }
}

jQuery(document).ready(function() {
    pmproava_edit_level_update_tr();
	jQuery( '#pmproava_default_product_category' ).change( function() {		
		pmproava_edit_level_update_tr();
	});
});