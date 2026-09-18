<?php

namespace BookingPlugin\Admin;

use BookingPlugin\Database\BookingRepository;
use BookingPlugin\Database\BookingStatuses;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ManualBooking {

    private const PAGE_SLUG = 'booking-plugin-add-booking';

    private $page_hook;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_booking_plugin_create_manual_booking', [ $this, 'handle_submit' ] );
    }

    public function register_menu() {
        $this->page_hook = add_submenu_page(
            'booking-plugin-bookings',
            __( 'Add New Booking', 'booking-plugin' ),
            __( 'Add New', 'booking-plugin' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );

        $inline_script = "
        jQuery( function ( $ ) {
            var slotsMap = " . wp_json_encode( $this->get_slots_map() ) . ";
            var \$service = $( '#manual_booking_service_id' );
            var \$slotSelect = $( '#manual_booking_time_slot_select' );
            var \$slotInput = $( '#manual_booking_time_slot_input' );

            function updateSlots() {
                var slots = slotsMap[ \$service.val() ] || [];

                if ( slots.length ) {
                    \$slotSelect.empty().append( $( '<option>' ).val( '' ).text( " . wp_json_encode( __( '-- Select a time slot --', 'booking-plugin' ) ) . " ) );
                    $.each( slots, function ( i, slot ) {
                        \$slotSelect.append( $( '<option>' ).val( slot ).text( slot ) );
                    } );
                    \$slotSelect.prop( 'disabled', false ).prop( 'required', true ).show();
                    \$slotInput.prop( 'disabled', true ).prop( 'required', false ).hide();
                } else {
                    \$slotSelect.prop( 'disabled', true ).prop( 'required', false ).hide();
                    \$slotInput.prop( 'disabled', false ).prop( 'required', true ).show();
                }
            }

            \$service.on( 'change', updateSlots );
            updateSlots();
        } );
        ";

        wp_add_inline_script( 'jquery', $inline_script );
    }

    private function get_slots_map(): array {
        $services = get_posts( [
            'post_type'      => 'bookable_service',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        $map = [];
        foreach ( $services as $service ) {
            $slots               = get_post_meta( $service->ID, '_service_time_slots', true );
            $map[ $service->ID ] = is_array( $slots ) ? array_values( $slots ) : [];
        }

        return $map;
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $services = get_posts( [
            'post_type'      => 'bookable_service',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Add New Booking', 'booking-plugin' ); ?></h1>

            <?php if ( isset( $_GET['booking_created'] ) ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Booking created successfully.', 'booking-plugin' ); ?></p></div>
            <?php elseif ( isset( $_GET['booking_error'] ) ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $this->error_message( sanitize_text_field( wp_unslash( $_GET['booking_error'] ) ) ) ); ?></p></div>
            <?php endif; ?>

            <p class="description"><?php esc_html_e( 'Use this form to record an over-the-counter or phone booking on behalf of a customer.', 'booking-plugin' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'booking_plugin_manual_booking', 'booking_plugin_manual_booking_nonce' ); ?>
                <input type="hidden" name="action" value="booking_plugin_create_manual_booking">

                <table class="form-table">
                    <tr>
                        <th><label for="manual_booking_customer_name"><?php esc_html_e( 'Customer Name', 'booking-plugin' ); ?></label></th>
                        <td><input type="text" id="manual_booking_customer_name" name="customer_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="manual_booking_customer_email"><?php esc_html_e( 'Customer Email', 'booking-plugin' ); ?></label></th>
                        <td>
                            <input type="email" id="manual_booking_customer_email" name="customer_email" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Optional for walk-in customers.', 'booking-plugin' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="manual_booking_service_id"><?php esc_html_e( 'Service', 'booking-plugin' ); ?></label></th>
                        <td>
                            <select id="manual_booking_service_id" name="service_id" required>
                                <option value=""><?php esc_html_e( '-- Select a service --', 'booking-plugin' ); ?></option>
                                <?php foreach ( $services as $service ) : ?>
                                    <option value="<?php echo esc_attr( $service->ID ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="manual_booking_date"><?php esc_html_e( 'Date', 'booking-plugin' ); ?></label></th>
                        <td><input type="date" id="manual_booking_date" name="booking_date" required></td>
                    </tr>
                    <tr>
                        <th><label for="manual_booking_time_slot_input"><?php esc_html_e( 'Time Slot', 'booking-plugin' ); ?></label></th>
                        <td>
                            <select id="manual_booking_time_slot_select" name="time_slot" style="display:none;" disabled></select>
                            <input type="time" id="manual_booking_time_slot_input" name="time_slot">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="manual_booking_quantity"><?php esc_html_e( 'Quantity', 'booking-plugin' ); ?></label></th>
                        <td><input type="number" id="manual_booking_quantity" name="quantity" min="1" step="1" value="1" required></td>
                    </tr>
                    <tr>
                        <th><label for="manual_booking_status"><?php esc_html_e( 'Status', 'booking-plugin' ); ?></label></th>
                        <td>
                            <select id="manual_booking_status" name="status">
                                <?php foreach ( BookingStatuses::ALL as $status ) : ?>
                                    <option value="<?php echo esc_attr( $status ); ?>" <?php selected( BookingStatuses::CONFIRMED, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Over-the-counter sales are typically marked Confirmed immediately.', 'booking-plugin' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Create Booking', 'booking-plugin' ) ); ?>
            </form>
        </div>
        <?php
    }

    private function error_message( string $code ): string {
        $messages = [
            'validation' => __( 'Please correct the highlighted fields and try again.', 'booking-plugin' ),
            'capacity'   => __( 'This time slot no longer has enough available capacity.', 'booking-plugin' ),
            'db'         => __( 'Failed to save the booking. Please try again.', 'booking-plugin' ),
        ];

        return $messages[ $code ] ?? __( 'Something went wrong. Please try again.', 'booking-plugin' );
    }

    public function handle_submit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'booking-plugin' ), '', [ 'response' => 403 ] );
        }

        check_admin_referer( 'booking_plugin_manual_booking', 'booking_plugin_manual_booking_nonce' );

        $result = BookingRepository::validate( [
            'customer_name'  => wp_unslash( $_POST['customer_name'] ?? '' ),
            'customer_email' => wp_unslash( $_POST['customer_email'] ?? '' ),
            'service_id'     => wp_unslash( $_POST['service_id'] ?? '' ),
            'booking_date'   => wp_unslash( $_POST['booking_date'] ?? '' ),
            'time_slot'      => wp_unslash( $_POST['time_slot'] ?? '' ),
            'quantity'       => wp_unslash( $_POST['quantity'] ?? '' ),
        ] );

        if ( ! empty( $result['errors'] ) ) {
            $this->redirect( [ 'booking_error' => 'validation' ] );
        }

        $data = $result['data'];

        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : BookingStatuses::CONFIRMED;
        if ( ! in_array( $status, BookingStatuses::ALL, true ) ) {
            $status = BookingStatuses::CONFIRMED;
        }

        if ( ! BookingRepository::has_capacity( $data['service_id'], $data['booking_date'], $data['time_slot'], $data['quantity'] ) ) {
            $this->redirect( [ 'booking_error' => 'capacity' ] );
        }

        $data['status'] = $status;

        $booking_id = BookingRepository::insert( $data );

        if ( false === $booking_id ) {
            $this->redirect( [ 'booking_error' => 'db' ] );
        }

        $this->redirect( [ 'booking_created' => '1' ] );
    }

    private function redirect( array $args ) {
        $url = add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        wp_safe_redirect( $url );
        exit;
    }
}
