jQuery(document).ready(function(){ 
	jQuery("#pmproava_calculate_tax").click(function(e) {
		jQuery.noConflict().ajax({
			url: pmproava.restUrl + 'pmpro/v1/checkout_levels',
			dataType: 'json',
			data: jQuery( "#pmpro_form" ).serialize(),
			success: function(data) {
				if ( data.hasOwnProperty('initial_payment') ) {
					var level_id   = jQuery('#level').val();
					var line1      = jQuery("#baddress1").val() || '';
					var city       = jQuery("#bcity").val() || '';
					var postalCode = jQuery("#bzipcode").val() || '';
					var country    = jQuery("select[name='bcountry']").val() || '';
					var region     = jQuery("#bstate").val() || '';

					jQuery.noConflict().ajax({
						url: pmproava.restUrl + 'pmpro-avatax/v1/calculate_tax_at_checkout',
						data: {
							'price':data.initial_payment,
							'level_id':level_id,
							'line1':line1,
							'city' : city,
							'postalCode' : postalCode,
							'country' : country,
							'region': region
						},
						success:function(data) {
							jQuery("#pmproava_tax_estimate").html("Based on your location you will be charged <b>"+ data.tax_formatted + "</b> sales tax");
						},
						error: function(error){
							jQuery("#pmproava_tax_estimate").html("AvaTax API call failed. See JavaScript console for error message.");
							console.log(error.responseJSON);
						}
					});
				}
			},
			error: function(error){
				jQuery("#pmproava_tax_estimate").html("checkout_levels API call failed. See JavaScript console for error message.");
				console.log(error.responseJSON);
			}
		});
	});
});