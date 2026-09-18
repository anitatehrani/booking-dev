<?php

namespace BookingPlugin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    public const OPTION_NAME = 'booking_plugin_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'booking-plugin-bookings',
            __( 'Booking Settings', 'booking-plugin' ),
            __( 'Settings', 'booking-plugin' ),
            'manage_options',
            'booking-plugin-settings',
            [ $this, 'render' ]
        );
    }

    public function register_settings() {
        register_setting( 'booking_plugin_settings_group', self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
            'default'           => self::defaults(),
        ] );
    }

    public static function defaults(): array {
        return [
            'paypal_client_id'   => '',
            'paypal_secret'      => '',
            'paypal_environment' => 'sandbox',
            'currency'           => 'USD',
        ];
    }

    public static function get(): array {
        return wp_parse_args( get_option( self::OPTION_NAME, [] ), self::defaults() );
    }

    public function sanitize( $input ): array {
        $input = is_array( $input ) ? $input : [];

        $currency = strtoupper( sanitize_text_field( $input['currency'] ?? 'USD' ) );
        if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
            $currency = 'USD';
        }

        $environment = ( 'live' === ( $input['paypal_environment'] ?? '' ) ) ? 'live' : 'sandbox';

        return [
            'paypal_client_id'   => sanitize_text_field( $input['paypal_client_id'] ?? '' ),
            'paypal_secret'      => sanitize_text_field( $input['paypal_secret'] ?? '' ),
            'paypal_environment' => $environment,
            'currency'           => $currency,
        ];
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Booking Settings', 'booking-plugin' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'booking_plugin_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bp_paypal_client_id"><?php esc_html_e( 'PayPal Client ID', 'booking-plugin' ); ?></label></th>
                        <td><input type="text" id="bp_paypal_client_id" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[paypal_client_id]" value="<?php echo esc_attr( $settings['paypal_client_id'] ); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="bp_paypal_secret"><?php esc_html_e( 'PayPal Secret', 'booking-plugin' ); ?></label></th>
                        <td><input type="password" id="bp_paypal_secret" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[paypal_secret]" value="<?php echo esc_attr( $settings['paypal_secret'] ); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="bp_paypal_environment"><?php esc_html_e( 'PayPal Environment', 'booking-plugin' ); ?></label></th>
                        <td>
                            <select id="bp_paypal_environment" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[paypal_environment]">
                                <option value="sandbox" <?php selected( $settings['paypal_environment'], 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (testing)', 'booking-plugin' ); ?></option>
                                <option value="live" <?php selected( $settings['paypal_environment'], 'live' ); ?>><?php esc_html_e( 'Live', 'booking-plugin' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bp_currency"><?php esc_html_e( 'Currency Code', 'booking-plugin' ); ?></label></th>
                        <td><input type="text" id="bp_currency" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" maxlength="3" class="small-text" style="text-transform:uppercase;"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
