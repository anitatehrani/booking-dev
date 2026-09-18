<?php

namespace BookingPlugin\Ajax;

use BookingPlugin\Admin\Settings;
use BookingPlugin\Database\BookingRepository;
use BookingPlugin\Database\PaymentStatuses;
use BookingPlugin\Payments\PayPalClient;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayPalCreateOrder {

    public function __construct() {
        add_action( 'wp_ajax_booking_plugin_paypal_create_order', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_booking_plugin_paypal_create_order', [ $this, 'handle' ] );
    }

    public function handle() {
        $booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

        if ( ! $booking_id || ! check_ajax_referer( PayPalClient::nonce_action( $booking_id ), 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'booking-plugin' ) ], 403 );
        }

        $booking = BookingRepository::find( $booking_id );

        if ( ! $booking ) {
            wp_send_json_error( [ 'message' => __( 'Booking not found.', 'booking-plugin' ) ], 404 );
        }

        if ( PaymentStatuses::PAID === $booking->payment_status ) {
            wp_send_json_error( [ 'message' => __( 'This booking has already been paid for.', 'booking-plugin' ) ], 400 );
        }

        $price  = (float) get_post_meta( $booking->service_id, '_service_price', true );
        $amount = $price * (int) $booking->quantity;

        if ( $amount <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'This service does not have a valid price configured.', 'booking-plugin' ) ], 400 );
        }

        $settings = Settings::get();
        $order    = PayPalClient::create_order( $amount, $settings['currency'], (string) $booking->id );

        if ( is_wp_error( $order ) || empty( $order['id'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not start PayPal checkout. Please try again.', 'booking-plugin' ) ], 502 );
        }

        BookingRepository::set_paypal_order_id( $booking->id, $order['id'] );

        wp_send_json_success( [ 'order_id' => $order['id'] ] );
    }
}
