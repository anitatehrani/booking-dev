<?php

namespace BookingPlugin\Ajax;

use BookingPlugin\Database\BookingRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AvailabilityCheck {

    public function __construct() {
        add_action( 'wp_ajax_booking_plugin_check_availability', [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_booking_plugin_check_availability', [ $this, 'handle' ] );
    }

    public function handle() {
        if ( ! check_ajax_referer( 'booking_plugin_check_availability', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'booking-plugin' ) ], 403 );
        }

        $service_id   = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $booking_date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
        $time_slot    = isset( $_POST['time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['time_slot'] ) ) : '';

        $service = get_post( $service_id );
        if ( ! $service || 'bookable_service' !== $service->post_type || 'publish' !== $service->post_status ) {
            wp_send_json_error( [ 'message' => __( 'Invalid service.', 'booking-plugin' ) ], 400 );
        }

        $date_obj = \DateTime::createFromFormat( 'Y-m-d', $booking_date );
        if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $booking_date || '' === $time_slot ) {
            wp_send_json_error( [ 'message' => __( 'Invalid date or time slot.', 'booking-plugin' ) ], 400 );
        }

        $remaining = BookingRepository::remaining_capacity( $service_id, $booking_date, $time_slot );

        wp_send_json_success( [ 'remaining' => $remaining ] );
    }
}
