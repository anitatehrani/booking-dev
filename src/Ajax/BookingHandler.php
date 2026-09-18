<?php

namespace BookingPlugin\Ajax;

use BookingPlugin\Database\BookingRepository;
use BookingPlugin\Database\BookingStatuses;
use BookingPlugin\Payments\PayPalClient;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingHandler {

    public function __construct() {
        add_action( 'wp_ajax_booking_plugin_submit_booking', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_booking_plugin_submit_booking', [ $this, 'handle' ] );
    }

    public function handle() {
        if ( ! check_ajax_referer( 'booking_form_submit', 'booking_form_nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'booking-plugin' ) ], 403 );
        }

        $result = BookingRepository::validate( [
            'customer_name'  => wp_unslash( $_POST['customer_name'] ?? '' ),
            'customer_email' => wp_unslash( $_POST['customer_email'] ?? '' ),
            'service_id'     => wp_unslash( $_POST['service_id'] ?? '' ),
            'booking_date'   => wp_unslash( $_POST['booking_date'] ?? '' ),
            'time_slot'      => wp_unslash( $_POST['time_slot'] ?? '' ),
            'quantity'       => wp_unslash( $_POST['quantity'] ?? '' ),
            'payment_method' => wp_unslash( $_POST['payment_method'] ?? '' ),
        ] );

        if ( '' === $result['data']['customer_email'] ) {
            $result['errors']['customer_email'] = __( 'Please enter a valid email address.', 'booking-plugin' );
        }

        if ( ! empty( $result['errors'] ) ) {
            wp_send_json_error( [ 'message' => reset( $result['errors'] ) ], 400 );
        }

        $data = $result['data'];

        if ( ! BookingRepository::has_capacity( $data['service_id'], $data['booking_date'], $data['time_slot'], $data['quantity'] ) ) {
            wp_send_json_error( [ 'message' => __( 'This time slot no longer has enough available capacity.', 'booking-plugin' ) ], 409 );
        }

        $data['status'] = BookingStatuses::PENDING;

        $booking_id = BookingRepository::insert( $data );

        if ( false === $booking_id ) {
            wp_send_json_error( [ 'message' => __( 'Failed to save your booking. Please try again.', 'booking-plugin' ) ], 500 );
        }

        wp_send_json_success( [
            'message'        => __( 'Your booking has been received.', 'booking-plugin' ),
            'booking_id'     => $booking_id,
            'payment_method' => $data['payment_method'],
            'paypal_nonce'   => wp_create_nonce( PayPalClient::nonce_action( $booking_id ) ),
        ] );
    }
}
