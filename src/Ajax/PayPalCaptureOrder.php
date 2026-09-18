<?php

namespace BookingPlugin\Ajax;

use BookingPlugin\Database\BookingRepository;
use BookingPlugin\Database\PaymentStatuses;
use BookingPlugin\Payments\PayPalClient;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayPalCaptureOrder {

    public function __construct() {
        add_action( 'wp_ajax_booking_plugin_paypal_capture_order', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_booking_plugin_paypal_capture_order', [ $this, 'handle' ] );
    }

    public function handle() {
        $booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

        if ( ! $booking_id || ! check_ajax_referer( PayPalClient::nonce_action( $booking_id ), 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'booking-plugin' ) ], 403 );
        }

        $paypal_order_id = isset( $_POST['paypal_order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_order_id'] ) ) : '';

        $booking = BookingRepository::find( $booking_id );

        if ( ! $booking || '' === $paypal_order_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'booking-plugin' ) ], 400 );
        }

        if ( PaymentStatuses::PAID === $booking->payment_status ) {
            wp_send_json_success( [ 'message' => __( 'Payment already confirmed.', 'booking-plugin' ) ] );
        }

        if ( $booking->paypal_order_id !== $paypal_order_id ) {
            wp_send_json_error( [ 'message' => __( 'This payment does not match the booking.', 'booking-plugin' ) ], 400 );
        }

        $capture = PayPalClient::capture_order( $paypal_order_id );

        if ( is_wp_error( $capture ) || empty( $capture['status'] ) || 'COMPLETED' !== $capture['status'] ) {
            wp_send_json_error( [ 'message' => __( 'Payment could not be confirmed. Please try again or contact us.', 'booking-plugin' ) ], 402 );
        }

        $captured_amount = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;

        $price           = (float) get_post_meta( $booking->service_id, '_service_price', true );
        $expected_amount = $price * (int) $booking->quantity;

        if ( null === $captured_amount || abs( (float) $captured_amount - $expected_amount ) > 0.01 ) {
            wp_send_json_error( [ 'message' => __( 'Payment amount did not match. Please contact us.', 'booking-plugin' ) ], 400 );
        }

        BookingRepository::mark_paid( $booking->id, $paypal_order_id );

        wp_send_json_success( [ 'message' => __( 'Payment confirmed. Your booking is now confirmed.', 'booking-plugin' ) ] );
    }
}
