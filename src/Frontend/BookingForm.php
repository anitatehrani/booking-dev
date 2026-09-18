<?php

namespace BookingPlugin\Frontend;

use BookingPlugin\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingForm {

    public function __construct() {
        add_shortcode( 'booking_form', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        $settings           = Settings::get();
        $paypal_configured  = '' !== $settings['paypal_client_id'];

        wp_enqueue_script(
            'booking-plugin-form',
            plugins_url( 'assets/js/booking-form.js', BOOKING_PLUGIN_FILE ),
            [ 'jquery' ],
            '1.0.0',
            true
        );

        if ( $paypal_configured ) {
            wp_enqueue_script(
                'paypal-checkout-sdk',
                'https://www.paypal.com/sdk/js?client-id=' . rawurlencode( $settings['paypal_client_id'] ) . '&currency=' . rawurlencode( $settings['currency'] ),
                [],
                null,
                true
            );
        }

        wp_localize_script( 'booking-plugin-form', 'BookingPluginData', [
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'availabilityNonce'  => wp_create_nonce( 'booking_plugin_check_availability' ),
            'paypalConfigured'   => $paypal_configured,
            'i18n'               => [
                'bookingFailed'       => __( 'Booking failed. Please try again.', 'booking-plugin' ),
                'paymentUnavailable'  => __( 'Online payment is not available right now. We will contact you to arrange payment.', 'booking-plugin' ),
                'paymentError'        => __( 'Payment failed. Please try again.', 'booking-plugin' ),
                'paymentNotConfirmed' => __( 'Payment could not be confirmed.', 'booking-plugin' ),
                /* translators: %d: number of spots remaining for the selected date/time */
                'spotsRemaining'      => __( '%d spot(s) left for this time.', 'booking-plugin' ),
                'fullyBooked'         => __( 'Fully booked for this time - please choose another.', 'booking-plugin' ),
                'cashConfirmation'    => __( 'Your booking is pending. Please pay in cash when you arrive.', 'booking-plugin' ),
            ],
        ] );
    }

    public function render( $atts = [] ): string {
        $atts = shortcode_atts( [
            'service_id' => 0,
        ], $atts, 'booking_form' );

        $service       = null;
        $requested_id  = absint( $atts['service_id'] );

        if ( $requested_id ) {
            $post = get_post( $requested_id );
            if ( $post && 'bookable_service' === $post->post_type && 'publish' === $post->post_status ) {
                $service = $post;
            }
        }

        $services = [];
        if ( ! $service ) {
            $services = get_posts( [
                'post_type'      => 'bookable_service',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ] );
        }

        $time_slots = [];
        $capacity   = '';

        if ( $service ) {
            $time_slots = get_post_meta( $service->ID, '_service_time_slots', true );
            if ( ! is_array( $time_slots ) ) {
                $time_slots = [];
            }
            $capacity = get_post_meta( $service->ID, '_service_capacity', true );
        }

        ob_start();
        ?>
        <form class="booking-plugin-form" method="post">
            <?php wp_nonce_field( 'booking_form_submit', 'booking_form_nonce' ); ?>
            <input type="hidden" name="action" value="booking_plugin_submit_booking">

            <p>
                <label for="booking_customer_name"><?php esc_html_e( 'Name', 'booking-plugin' ); ?></label><br>
                <input type="text" id="booking_customer_name" name="customer_name" required>
            </p>

            <p>
                <label for="booking_customer_email"><?php esc_html_e( 'Email', 'booking-plugin' ); ?></label><br>
                <input type="email" id="booking_customer_email" name="customer_email" required>
            </p>

            <?php if ( $service ) : ?>
                <p>
                    <strong><?php esc_html_e( 'Service', 'booking-plugin' ); ?>:</strong>
                    <?php echo esc_html( get_the_title( $service ) ); ?>
                </p>
                <input type="hidden" name="service_id" value="<?php echo esc_attr( $service->ID ); ?>">
            <?php else : ?>
                <p>
                    <label for="booking_service_id"><?php esc_html_e( 'Service', 'booking-plugin' ); ?></label><br>
                    <select id="booking_service_id" name="service_id" required>
                        <option value=""><?php esc_html_e( '-- Select a service --', 'booking-plugin' ); ?></option>
                        <?php foreach ( $services as $service_option ) : ?>
                            <option value="<?php echo esc_attr( $service_option->ID ); ?>"><?php echo esc_html( get_the_title( $service_option ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endif; ?>

            <p>
                <label for="booking_date"><?php esc_html_e( 'Date', 'booking-plugin' ); ?></label><br>
                <input type="date" id="booking_date" name="booking_date" required>
            </p>

            <p>
                <label for="booking_time_slot"><?php esc_html_e( 'Time Slot', 'booking-plugin' ); ?></label><br>
                <?php if ( $service && ! empty( $time_slots ) ) : ?>
                    <select id="booking_time_slot" name="time_slot" required>
                        <option value=""><?php esc_html_e( '-- Select a time slot --', 'booking-plugin' ); ?></option>
                        <?php foreach ( $time_slots as $slot ) : ?>
                            <option value="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot ); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="time" id="booking_time_slot" name="time_slot" required>
                <?php endif; ?>
                <span id="booking-plugin-availability" class="description"></span>
            </p>

            <p>
                <label for="booking_quantity"><?php esc_html_e( 'Quantity', 'booking-plugin' ); ?></label><br>
                <input
                    type="number"
                    id="booking_quantity"
                    name="quantity"
                    min="1"
                    step="1"
                    value="1"
                    <?php echo ( $service && '' !== $capacity ) ? 'max="' . esc_attr( $capacity ) . '"' : ''; ?>
                    required
                >
                <?php if ( $service && '' !== $capacity ) : ?>
                    <span class="description">
                        <?php
                        printf(
                            /* translators: %d: maximum capacity per time slot for the service */
                            esc_html__( 'Maximum per time slot: %d', 'booking-plugin' ),
                            (int) $capacity
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </p>

            <p>
                <span><?php esc_html_e( 'Payment Method', 'booking-plugin' ); ?></span><br>
                <label>
                    <input type="radio" name="payment_method" value="paypal" checked>
                    <?php esc_html_e( 'Pay with PayPal', 'booking-plugin' ); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="payment_method" value="cash">
                    <?php esc_html_e( 'Pay in Cash', 'booking-plugin' ); ?>
                </label>
            </p>

            <p>
                <button type="submit"><?php esc_html_e( 'Book Now', 'booking-plugin' ); ?></button>
            </p>
        </form>

        <div id="booking-plugin-payment-container" style="display:none;">
            <p id="booking-plugin-payment-intro"><?php esc_html_e( 'Your booking has been received. Complete payment below to confirm it.', 'booking-plugin' ); ?></p>
            <div id="paypal-button-container"></div>
            <p id="booking-plugin-payment-status"></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
