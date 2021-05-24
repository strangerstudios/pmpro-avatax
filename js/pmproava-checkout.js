jQuery(document).ready(function($){
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

	var pmproava_frontend = window.pmproava_frontend;

	/**
	 * AvaTax Frontend script
	 */

	// Show/hide the address validation button when the address country is changed
	$( document ).on( 'change', 'form.pmpro_form select[name="bcountry"]', function() {

		var button = $( '.pmproava_validate_address' );

		// Check if the newly selected country supports address validation
		if ( $.inArray( $( this ).val(), pmproava_frontend.address_validation_countries ) > -1 ) {
			$( button ).show();
		} else {
			$( button ).hide();
		}
	} );

	// Validate an address button
	$( '.pmproava_validate_address' ).on( 'click', function( e ) {
		e.preventDefault();
		$( document.body ).trigger( 'pmproava_update_checkout' );
	} );

	var pmproava_checkout_form = {
		updateTimer: false,
		dirtyInput: false,
		selectedPaymentMethod: false,
		xhr: false,
		$order_review: $('#pmpro_checkout_totals'),
		$checkout_form: $('form.pmpro_form'),
		init: function () {
			$(document.body).on('pmproava_update_checkout', this.update_checkout);
			$( document.body ).on( 'pmproava_init_checkout', this.init_checkout );

			// Prevent HTML5 validation which can conflict.
			this.$checkout_form.attr( 'novalidate', 'novalidate' );

			// Manual trigger
			this.$checkout_form.on( 'update', this.trigger_update_checkout );

			// Inputs/selects which update totals
			this.$checkout_form.on( 'change', 'select.update_totals_on_change, input[type="radio"].update_totals_on_change , input[type="checkbox"].update_totals_on_change', this.trigger_update_checkout ); // eslint-disable-line max-len
			this.$checkout_form.on( 'change', 'select.address-field', this.input_changed );
			this.$checkout_form.on( 'change', 'input[type="text"].update_totals_on_change', this.maybe_input_changed ); // eslint-disable-line max-len
			this.$checkout_form.on( 'keydown', 'input[type="text"].update_totals_on_change', this.queue_update_checkout ); // eslint-disable-line max-len

			$( document.body ).trigger( 'pmproava_init_checkout' );
		},
		reset_update_checkout_timer: function() {
			clearTimeout( pmproava_checkout_form.updateTimer );
		},
		maybe_input_changed: function( e ) {
			if ( pmproava_checkout_form.dirtyInput ) {
				pmproava_checkout_form.input_changed( e );
			}
		},
		init_checkout: function() {
			$( document.body ).trigger( 'pmproava_update_checkout' );
		},
		input_changed: function( e ) {
			pmproava_checkout_form.dirtyInput = e.target;
			pmproava_checkout_form.maybe_update_checkout();
		},
		queue_update_checkout: function( e ) {
			var code = e.keyCode || e.which || 0;

			if ( code === 9 ) {
				return true;
			}

			pmproava_checkout_form.dirtyInput = this;
			pmproava_checkout_form.reset_update_checkout_timer();
			pmproava_checkout_form.updateTimer = setTimeout( pmproava_checkout_form.maybe_update_checkout, '1000' );
		},
		trigger_update_checkout: function() {
			pmproava_checkout_form.reset_update_checkout_timer();
			pmproava_checkout_form.dirtyInput = false;
			$( document.body ).trigger( 'pmproava_update_checkout' );
		},
		maybe_update_checkout: function() {
			var update_totals = true;

			if ( $( pmproava_checkout_form.dirtyInput ).length ) {
				$required_inputs = $( pmproava_checkout_form.$checkout_form ).find( '.address-field.pmpro_required:visible' );

				if ( $required_inputs.length ) {
					$required_inputs.each( function() {
						if ( $( this ).val() === '' ) {
							update_totals = false;
						}
					});
				}
			}
			if ( update_totals ) {
				pmproava_checkout_form.trigger_update_checkout();
			}
		},
		update_checkout: function (event, args) {
			// Small timeout to prevent multiple requests when several fields update at the same time
			pmproava_checkout_form.reset_update_checkout_timer();
			pmproava_checkout_form.updateTimer = setTimeout(pmproava_checkout_form.update_checkout_action, '5', args);
		},
		update_checkout_action: function( args ) {
			if (pmproava_checkout_form.xhr) {
				pmproava_checkout_form.xhr.abort();
			}

			if ($('form.pmpro_form').length === 0) {
				return;
			}

			var form   = $( 'form.pmpro_form' ),
				address_1,
				address_2,
				city,
				state,
				country,
				postcode,
				level;

			form.addClass('clearfix');

			address_1 = $( 'input#baddress1' ).val();
			address_2 = $( 'input#baddress2' ).val();
			city      = $( 'input#bcity' ).val();
			state     = $( 'input#bstate' ).val();
			country   = $( 'select[name="bcountry"]' ).val();
			postcode  = $( 'input#bzipcode' ).val();
			level     = $( 'input#level' ).val();

			// Block the checkout form
			var form_data = form.data();

			if ( 1 !== form_data['blockUI.isBlocked'] ) {
				form.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}

			// Build request data
			var data = {
				action:   'pmproava_validate_customer_address',
				nonce:     pmproava_frontend.address_validation_nonce,
				baddress1: address_1,
				baddress2: address_2,
				bcity:      city,
				bstate:     state,
				bcountry:   country,
				bzipcode:  postcode,
				level: level
			};

			$.ajax( {
				type:     'POST',
				url:      pmproava_frontend.ajax_url,
				data:     data,
				dataType: 'json',
				success:  function( response ) {
					var notice = false;

					if ( response.code === 200 ) {

						if (response.address) {
							$.each(response.address, function (field, value) {
								$('#' + field).val(value).trigger('change');
							});
						}

						notice = '<div class="pmproava-address-validation-result pmproava-address-validation-success">' + pmproava_frontend.i18n.address_validated + '</div>';

						if (response.totals) {
							$('#pmpro_checkout_totals').html(response.totals).removeClass('hidden');
						}
					} else if ( response.error ) {
						notice = '<div class="pmproava-address-validation-result pmproava-address-validation-error">' + response.error + '</div>';
						if (response.totals) {
							$('#pmpro_checkout_totals').html(response.totals).removeClass('hidden');
						}
					}

					if ( notice ) {
						$( '.pmproava-address-validation-result' ).remove();
						$( '.pmproava_validate_address' ).css( 'margin-bottom', '20px' ).after( notice );
					}

					// Unblock the checkout form
					form.unblock();
				},
				error:	function( jqXHR, textStatus, errorThrown ) {
					// Detach the unload handler that prevents a reload / redirect
					pmproava_checkout_form.detachUnloadEventsOnSubmit();

					var notice = '<div class="pmproava-address-validation-result pmproava-address-validation-error">' + errorThrown + '</div>';
					$( '.pmproava-address-validation-result' ).remove();
					$( '.pmproava_validate_address' ).css( 'margin-bottom', '20px' ).after( notice );
				}
			} );
		},
		handleUnloadEvent: function( e ) {
			// Modern browsers have their own standard generic messages that they will display.
			// Confirm, alert, prompt or custom message are not allowed during the unload event
			// Browsers will display their own standard messages

			// Check if the browser is Internet Explorer
			if((navigator.userAgent.indexOf('MSIE') !== -1 ) || (!!document.documentMode)) {
				// IE handles unload events differently than modern browsers
				e.preventDefault();
				return undefined;
			}

			return true;
		},
		attachUnloadEventsOnSubmit: function() {
			$( window ).on('beforeunload', this.handleUnloadEvent);
		},
		detachUnloadEventsOnSubmit: function() {
			$( window ).unbind('beforeunload', this.handleUnloadEvent);
		}
	};

	pmproava_checkout_form.init();
} );
