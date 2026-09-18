<?php

namespace BookingPlugin\Admin;

use BookingPlugin\Database\BookingStatuses;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingsAdmin {

    private $page_hook;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu() {
        $this->page_hook = add_menu_page(
            __( 'Bookings', 'booking-plugin' ),
            __( 'Bookings', 'booking-plugin' ),
            'manage_options',
            'booking-plugin-bookings',
            [ $this, 'render' ],
            'dashicons-calendar-alt',
            25
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );

        $inline_script = "
        jQuery( function ( $ ) {
            $( '.booking-status-select' ).on( 'change', function () {
                var \$select = $( this );
                var bookingId = \$select.data( 'booking-id' );
                var previousValue = \$select.data( 'current-status' );
                var newStatus = \$select.val();

                \$select.prop( 'disabled', true );

                $.post( ajaxurl, {
                    action: 'booking_plugin_update_status',
                    nonce: " . wp_json_encode( wp_create_nonce( 'booking_plugin_update_status' ) ) . ",
                    booking_id: bookingId,
                    status: newStatus
                } ).done( function ( response ) {
                    if ( response.success ) {
                        \$select.data( 'current-status', newStatus );
                    } else {
                        \$select.val( previousValue );
                        window.alert( ( response.data && response.data.message ) || 'Update failed.' );
                    }
                } ).fail( function () {
                    \$select.val( previousValue );
                    window.alert( 'Update failed.' );
                } ).always( function () {
                    \$select.prop( 'disabled', false );
                } );
            } );
        } );
        ";

        wp_add_inline_script( 'jquery', $inline_script );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'bookings';
        $bookings   = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY booking_date DESC" );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bookings', 'booking-plugin' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Customer Name', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Customer Email', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Booking Date', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Service ID', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Time Slot', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Quantity', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Payment', 'booking-plugin' ); ?></th>
                        <th><?php esc_html_e( 'Created At', 'booking-plugin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $bookings ) ) : ?>
                    <tr>
                        <td colspan="10"><?php esc_html_e( 'No bookings found.', 'booking-plugin' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $bookings as $booking ) : ?>
                        <tr>
                            <td><?php echo esc_html( $booking->id ); ?></td>
                            <td><?php echo esc_html( $booking->customer_name ); ?></td>
                            <td><?php echo esc_html( $booking->customer_email ); ?></td>
                            <td><?php echo esc_html( substr( $booking->booking_date, 0, 10 ) ); ?></td>
                            <td><?php echo esc_html( $booking->service_id ); ?></td>
                            <td><?php echo esc_html( $booking->time_slot ); ?></td>
                            <td><?php echo esc_html( $booking->quantity ); ?></td>
                            <td>
                                <select class="booking-status-select" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-current-status="<?php echo esc_attr( $booking->status ); ?>">
                                    <?php foreach ( BookingStatuses::ALL as $status ) : ?>
                                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $booking->status, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?php echo esc_html( ucfirst( $booking->payment_method ) . ' - ' . ucfirst( $booking->payment_status ) ); ?></td>
                            <td><?php echo esc_html( $booking->created_at ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
