<?php

namespace BookingPlugin\Ajax;

use BookingPlugin\Database\BookingStatuses;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingStatusAjax {

    public function __construct() {
        add_action( 'wp_ajax_booking_plugin_update_status', [ $this, 'handle' ] );
    }

    public function handle() {
        if ( ! check_ajax_referer( 'booking_plugin_update_status', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'booking-plugin' ) ], 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'booking-plugin' ) ], 403 );
        }

        $booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
        $status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! $booking_id || ! in_array( $status, BookingStatuses::ALL, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'booking-plugin' ) ], 400 );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'bookings';

        $updated = $wpdb->update(
            $table_name,
            [ 'status' => $status ],
            [ 'id' => $booking_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            wp_send_json_error( [ 'message' => __( 'Failed to update booking status.', 'booking-plugin' ) ], 500 );
        }

        wp_send_json_success( [
            'message' => __( 'Booking status updated.', 'booking-plugin' ),
            'status'  => $status,
        ] );
    }
}
