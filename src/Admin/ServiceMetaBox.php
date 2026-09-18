<?php

namespace BookingPlugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ServiceMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register' ] );
        add_action( 'save_post_bookable_service', [ $this, 'save' ] );
    }

    public function register() {
        add_meta_box(
            'booking_service_settings',
            __( 'Service Settings', 'booking-plugin' ),
            [ $this, 'render' ],
            'bookable_service',
            'normal',
            'high'
        );
    }

    public function render( $post ) {
        wp_nonce_field( 'booking_service_settings_save', 'booking_service_settings_nonce' );

        $capacity   = get_post_meta( $post->ID, '_service_capacity', true );
        $price      = get_post_meta( $post->ID, '_service_price', true );
        $duration   = get_post_meta( $post->ID, '_service_duration', true );
        $time_slots = get_post_meta( $post->ID, '_service_time_slots', true );

        if ( is_array( $time_slots ) ) {
            $time_slots = implode( "\n", $time_slots );
        }
        ?>
        <p>
            <label for="service_capacity"><strong><?php esc_html_e( 'Capacity', 'booking-plugin' ); ?></strong></label><br>
            <input type="number" min="1" step="1" id="service_capacity" name="service_capacity" value="<?php echo esc_attr( $capacity ); ?>" class="regular-text">
        </p>
        <p>
            <label for="service_price"><strong><?php esc_html_e( 'Price', 'booking-plugin' ); ?></strong></label><br>
            <input type="number" min="0" step="0.01" id="service_price" name="service_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text">
        </p>
        <p>
            <label for="service_duration"><strong><?php esc_html_e( 'Duration (minutes)', 'booking-plugin' ); ?></strong></label><br>
            <input type="number" min="1" step="1" id="service_duration" name="service_duration" value="<?php echo esc_attr( $duration ); ?>" class="regular-text">
        </p>
        <p>
            <label for="service_time_slots"><strong><?php esc_html_e( 'Available Time Slots', 'booking-plugin' ); ?></strong></label><br>
            <textarea id="service_time_slots" name="service_time_slots" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'One time slot per line, e.g. 09:00', 'booking-plugin' ); ?>"><?php echo esc_textarea( $time_slots ); ?></textarea>
        </p>
        <?php
    }

    public function save( $post_id ) {
        if ( ! isset( $_POST['booking_service_settings_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['booking_service_settings_nonce'] ) ), 'booking_service_settings_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['service_capacity'] ) ) {
            $capacity = sanitize_text_field( wp_unslash( $_POST['service_capacity'] ) );

            if ( '' === $capacity ) {
                delete_post_meta( $post_id, '_service_capacity' );
            } else {
                update_post_meta( $post_id, '_service_capacity', absint( $capacity ) );
            }
        }

        if ( isset( $_POST['service_price'] ) ) {
            update_post_meta( $post_id, '_service_price', floatval( $_POST['service_price'] ) );
        }

        if ( isset( $_POST['service_duration'] ) ) {
            update_post_meta( $post_id, '_service_duration', absint( $_POST['service_duration'] ) );
        }

        if ( isset( $_POST['service_time_slots'] ) ) {
            $lines = preg_split( '/[\r\n]+/', wp_unslash( $_POST['service_time_slots'] ) );
            $slots = array_values( array_filter( array_map( 'sanitize_text_field', $lines ) ) );
            update_post_meta( $post_id, '_service_time_slots', $slots );
        }
    }
}
