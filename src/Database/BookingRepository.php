<?php

namespace BookingPlugin\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingRepository {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'bookings';
    }

    public static function validate( array $input ): array {
        $customer_name  = sanitize_text_field( $input['customer_name'] ?? '' );
        $customer_email = sanitize_email( $input['customer_email'] ?? '' );
        $service_id     = absint( $input['service_id'] ?? 0 );
        $booking_date   = sanitize_text_field( $input['booking_date'] ?? '' );
        $time_slot      = sanitize_text_field( $input['time_slot'] ?? '' );
        $quantity       = absint( $input['quantity'] ?? 0 );
        $payment_method = sanitize_text_field( $input['payment_method'] ?? '' );

        if ( ! in_array( $payment_method, PaymentMethods::ALL, true ) ) {
            $payment_method = PaymentMethods::CASH;
        }

        $errors = [];

        if ( '' === $customer_name ) {
            $errors['customer_name'] = __( 'Please enter a customer name.', 'booking-plugin' );
        }

        if ( '' !== $customer_email && ! is_email( $customer_email ) ) {
            $errors['customer_email'] = __( 'Please enter a valid email address.', 'booking-plugin' );
        }

        $service = get_post( $service_id );
        if ( ! $service || 'bookable_service' !== $service->post_type || 'publish' !== $service->post_status ) {
            $errors['service_id'] = __( 'Please select a valid service.', 'booking-plugin' );
        }

        $date_obj = \DateTime::createFromFormat( 'Y-m-d', $booking_date );
        if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $booking_date ) {
            $errors['booking_date'] = __( 'Please enter a valid booking date.', 'booking-plugin' );
        }

        if ( '' === $time_slot || strlen( $time_slot ) > 10 ) {
            $errors['time_slot'] = __( 'Please select a valid time slot.', 'booking-plugin' );
        } elseif ( $service ) {
            $available_slots = get_post_meta( $service_id, '_service_time_slots', true );
            if ( ! is_array( $available_slots ) ) {
                $available_slots = [];
            }

            if ( ! empty( $available_slots ) ) {
                if ( ! in_array( $time_slot, $available_slots, true ) ) {
                    $errors['time_slot'] = __( 'The selected time slot is not available for this service.', 'booking-plugin' );
                }
            } elseif ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time_slot ) ) {
                $errors['time_slot'] = __( 'Please select a valid time slot.', 'booking-plugin' );
            }
        }

        if ( $quantity < 1 ) {
            $errors['quantity'] = __( 'Quantity must be at least 1.', 'booking-plugin' );
        }

        return [
            'errors' => $errors,
            'data'   => [
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'service_id'     => $service_id,
                'booking_date'   => $booking_date,
                'time_slot'      => $time_slot,
                'quantity'       => $quantity,
                'payment_method' => $payment_method,
            ],
        ];
    }

    private static function booked_quantity( int $service_id, string $booking_date, string $time_slot ): int {
        global $wpdb;
        $table_name = self::table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE( SUM( quantity ), 0 ) FROM {$table_name} WHERE service_id = %d AND booking_date = %s AND time_slot = %s AND status != 'cancelled'",
                $service_id,
                $booking_date,
                $time_slot
            )
        );
    }

    public static function has_capacity( int $service_id, string $booking_date, string $time_slot, int $quantity ): bool {
        $capacity = get_post_meta( $service_id, '_service_capacity', true );

        if ( '' === $capacity ) {
            return true;
        }

        $booked_quantity = self::booked_quantity( $service_id, $booking_date, $time_slot );

        return ( $booked_quantity + $quantity ) <= (int) $capacity;
    }

    /**
     * @return int|null Remaining capacity for this date/time slot, or null if the service has no capacity limit.
     */
    public static function remaining_capacity( int $service_id, string $booking_date, string $time_slot ): ?int {
        $capacity = get_post_meta( $service_id, '_service_capacity', true );

        if ( '' === $capacity ) {
            return null;
        }

        $booked_quantity = self::booked_quantity( $service_id, $booking_date, $time_slot );

        return max( 0, (int) $capacity - $booked_quantity );
    }

    /**
     * @return int|false The new booking ID, or false on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::table_name(),
            [
                'customer_name'  => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'booking_date'   => $data['booking_date'],
                'service_id'     => $data['service_id'],
                'time_slot'      => $data['time_slot'],
                'quantity'       => $data['quantity'],
                'status'         => $data['status'],
                'payment_status' => $data['payment_status'] ?? PaymentStatuses::UNPAID,
                'payment_method' => $data['payment_method'] ?? PaymentMethods::CASH,
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' ]
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    public static function find( int $booking_id ) {
        global $wpdb;
        $table_name = self::table_name();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $booking_id )
        );
    }

    public static function set_paypal_order_id( int $booking_id, string $paypal_order_id ): bool {
        global $wpdb;

        $updated = $wpdb->update(
            self::table_name(),
            [ 'paypal_order_id' => $paypal_order_id ],
            [ 'id' => $booking_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return false !== $updated;
    }

    public static function mark_paid( int $booking_id, string $paypal_order_id ): bool {
        global $wpdb;

        $updated = $wpdb->update(
            self::table_name(),
            [
                'status'          => BookingStatuses::CONFIRMED,
                'payment_status'  => PaymentStatuses::PAID,
                'paypal_order_id' => $paypal_order_id,
            ],
            [ 'id' => $booking_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        return false !== $updated;
    }
}
