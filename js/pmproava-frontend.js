jQuery( function( $ ) {

    'use-strict';

    var pmproava_frontend = window.pmproava_frontend;

    /**
     * PMPro AvaTax Frontend scripts
     */

    // Show/hide the address validation button when the address country is changed
    $( document ).on( 'change', 'form.pmpro_form .country_select', function() {

        var button = $( '.pmproava_validate_address' );

        // Check if the newly selected country supports address validation
        if ( $.inArray( $( this ).val(), pmproava_frontend.address_validation_countries ) > -1 ) {
            $( button ).show();
        } else {
            $( button ).hide();
        }

    } );

    // force the country and "different address" checkbox fields to change
    $( 'form.pmproava-checkout .country_select' ).change();
    $( 'form.pmproava-checkout #ship-to-different-address-checkbox' ).change();

    // Validate an address
    $( '.pmproava_validate_address' ).on( 'click', function( e ) {

        e.preventDefault();

        var form   = $( 'form.pmpro_form' ),
            type,
            address_1,
            address_2,
            city,
            state,
            country,
            postcode;

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

        address_1 = $( 'input#' + type + '_address_1' ).val();
        address_2 = $( 'input#' + type + '_address_2' ).val();
        city      = $( '#' + type + '_city' ).val();
        state     = $( '#' + type + '_state' ).val();
        country   = $( '#' + type + '_country' ).val();
        postcode  = $( 'input#' + type + '_postcode' ).val();

        // Build request data
        var data = {
            action:   'pmproava_validate_customer_address',
            nonce:     pmproava_frontend.address_validation_nonce,
            type:      type,
            address_1: address_1,
            address_2: address_2,
            city:      city,
            state:     state,
            country:   country,
            postcode:  postcode
        };

        $.ajax( {
            type:     'POST',
            url:      pmproava_frontend.ajax_url,
            data:     data,
            dataType: 'json',
            success:  function( response ) {

                var notice = false;

                if ( response.code === 200 ) {

                    $.each( response.address, function( field, value ) {
                        $( '#' + field ).val( value ).trigger( 'change' );
                    } );

                    notice = '<div class="pmproava-address-validation-result pmproava-address-validation-success">' + pmproava_frontend.i18n.address_validated + '</div>';

                } else if ( response.error ) {

                    notice = '<div class="pmproava-address-validation-result pmproava-address-validation-error">' + response.error + '</div>';
                }

                if ( notice ) {
                    $( '.pmproava-error, .pmproava-message, .pmproava-address-validation-result' ).remove();
                    $( '.pmproava_validate_address' ).css( 'margin-bottom', '20px' ).after( notice );
                }

                // Unblock the checkout form
                form.unblock();
            }
        } );

    } );

} );
