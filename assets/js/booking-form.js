( function ( $ ) {
    'use strict';

    $( function () {
        var $form = $( '.booking-plugin-form' );

        if ( ! $form.length ) {
            return;
        }

        var $submitButton      = $form.find( 'button[type="submit"]' );
        var $paymentContainer  = $( '#booking-plugin-payment-container' );
        var $paymentIntro      = $( '#booking-plugin-payment-intro' );
        var $paymentStatus     = $( '#booking-plugin-payment-status' );
        var $availability      = $( '#booking-plugin-availability' );

        function checkAvailability() {
            var serviceId = $form.find( '[name="service_id"]' ).val();
            var bookingDate = $form.find( '[name="booking_date"]' ).val();
            var timeSlot = $form.find( '[name="time_slot"]:enabled' ).val();

            if ( ! serviceId || ! bookingDate || ! timeSlot ) {
                $availability.text( '' );
                return;
            }

            $.post( BookingPluginData.ajaxUrl, {
                action: 'booking_plugin_check_availability',
                nonce: BookingPluginData.availabilityNonce,
                service_id: serviceId,
                booking_date: bookingDate,
                time_slot: timeSlot
            } ).done( function ( res ) {
                if ( ! res.success ) {
                    $availability.text( '' );
                    return;
                }

                var remaining = res.data.remaining;

                if ( null === remaining ) {
                    $availability.text( '' );
                } else if ( remaining <= 0 ) {
                    $availability.text( BookingPluginData.i18n.fullyBooked );
                } else {
                    $availability.text( BookingPluginData.i18n.spotsRemaining.replace( '%d', remaining ) );
                }
            } );
        }

        $form.on( 'change', '[name="service_id"], [name="booking_date"], [name="time_slot"]', checkAvailability );

        $form.on( 'submit', function ( e ) {
            e.preventDefault();

            $submitButton.prop( 'disabled', true );

            $.post( BookingPluginData.ajaxUrl, $form.serialize() )
                .done( function ( response ) {
                    if ( response.success ) {
                        $form.hide();
                        $paymentContainer.show();

                        if ( 'cash' === response.data.payment_method ) {
                            $paymentIntro.text( BookingPluginData.i18n.cashConfirmation );
                        } else {
                            startPayment( response.data.booking_id, response.data.paypal_nonce );
                        }
                    } else {
                        window.alert( ( response.data && response.data.message ) || BookingPluginData.i18n.bookingFailed );
                        $submitButton.prop( 'disabled', false );
                    }
                } )
                .fail( function () {
                    window.alert( BookingPluginData.i18n.bookingFailed );
                    $submitButton.prop( 'disabled', false );
                } );
        } );

        function startPayment( bookingId, paypalNonce ) {
            if ( ! BookingPluginData.paypalConfigured || typeof window.paypal === 'undefined' ) {
                $paymentStatus.text( BookingPluginData.i18n.paymentUnavailable );
                return;
            }

            window.paypal.Buttons( {
                createOrder: function () {
                    return $.post( BookingPluginData.ajaxUrl, {
                        action: 'booking_plugin_paypal_create_order',
                        nonce: paypalNonce,
                        booking_id: bookingId
                    } ).then( function ( res ) {
                        if ( ! res.success ) {
                            throw new Error( ( res.data && res.data.message ) || BookingPluginData.i18n.paymentError );
                        }
                        return res.data.order_id;
                    } );
                },
                onApprove: function ( data ) {
                    return $.post( BookingPluginData.ajaxUrl, {
                        action: 'booking_plugin_paypal_capture_order',
                        nonce: paypalNonce,
                        booking_id: bookingId,
                        paypal_order_id: data.orderID
                    } ).then( function ( res ) {
                        if ( res.success ) {
                            $( '#paypal-button-container' ).hide();
                            $paymentStatus.text( ( res.data && res.data.message ) || '' );
                        } else {
                            window.alert( ( res.data && res.data.message ) || BookingPluginData.i18n.paymentNotConfirmed );
                        }
                    } );
                },
                onError: function () {
                    window.alert( BookingPluginData.i18n.paymentError );
                }
            } ).render( '#paypal-button-container' );
        }
    } );
} )( jQuery );
