<?php

namespace BookingPlugin\Payments;

use BookingPlugin\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayPalClient {

    /**
     * Nonce action scoped to a single booking, so a nonce minted for one
     * booking's checkout can never be replayed against a different booking.
     */
    public static function nonce_action( int $booking_id ): string {
        return 'booking_plugin_paypal_' . $booking_id;
    }

    private static function base_url(): string {
        $settings = Settings::get();
        return 'live' === $settings['paypal_environment']
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function get_access_token() {
        $cached = get_transient( 'booking_plugin_paypal_token' );
        if ( $cached ) {
            return $cached;
        }

        $settings = Settings::get();

        if ( '' === $settings['paypal_client_id'] || '' === $settings['paypal_secret'] ) {
            return new \WP_Error( 'paypal_not_configured', __( 'PayPal is not configured.', 'booking-plugin' ) );
        }

        $response = wp_remote_post( self::base_url() . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $settings['paypal_client_id'] . ':' . $settings['paypal_secret'] ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => [ 'grant_type' => 'client_credentials' ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            return new \WP_Error( 'paypal_auth_failed', __( 'Could not authenticate with PayPal.', 'booking-plugin' ) );
        }

        $expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 300;
        set_transient( 'booking_plugin_paypal_token', $body['access_token'], max( 60, $expires_in - 60 ) );

        return $body['access_token'];
    }

    private static function request( string $method, string $path, array $body = [] ) {
        $token = self::get_access_token();

        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( self::base_url() . $path, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        $code    = wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( 'paypal_api_error', __( 'PayPal request failed.', 'booking-plugin' ), $decoded );
        }

        return $decoded;
    }

    /**
     * @return array|\WP_Error
     */
    public static function create_order( float $amount, string $currency, string $reference_id ) {
        return self::request( 'POST', '/v2/checkout/orders', [
            'intent'         => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $reference_id,
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => number_format( $amount, 2, '.', '' ),
                    ],
                ],
            ],
        ] );
    }

    /**
     * @return array|\WP_Error
     */
    public static function capture_order( string $paypal_order_id ) {
        return self::request( 'POST', '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) . '/capture' );
    }
}
