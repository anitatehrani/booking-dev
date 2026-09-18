<?php
/**
 * Plugin Name: Booking Plugin
 * Description: A custom WordPress booking plugin.
 * Version: 1.0.0
 * Author: Anita
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BOOKING_PLUGIN_FILE', __FILE__ );
define( 'BOOKING_PLUGIN_DB_VERSION', '1.2' );

require_once __DIR__ . '/vendor/autoload.php';

new \BookingPlugin\PostTypes\BookableService();
new \BookingPlugin\Admin\ServiceMetaBox();
new \BookingPlugin\Admin\BookingsAdmin();
new \BookingPlugin\Admin\ManualBooking();
new \BookingPlugin\Admin\Settings();
new \BookingPlugin\Ajax\BookingStatusAjax();
new \BookingPlugin\Frontend\BookingForm();
new \BookingPlugin\Ajax\BookingHandler();
new \BookingPlugin\Ajax\AvailabilityCheck();
new \BookingPlugin\Ajax\PayPalCreateOrder();
new \BookingPlugin\Ajax\PayPalCaptureOrder();

register_activation_hook( __FILE__, 'booking_plugin_create_tables' );

add_action( 'plugins_loaded', 'booking_plugin_maybe_upgrade_db' );

function booking_plugin_maybe_upgrade_db() {
    if ( get_option( 'booking_plugin_db_version' ) !== BOOKING_PLUGIN_DB_VERSION ) {
        booking_plugin_create_tables();
    }
}

function booking_plugin_create_tables() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'bookings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        booking_date DATETIME NOT NULL,
        service_id INT NOT NULL,
        time_slot VARCHAR(10) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
        paypal_order_id VARCHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    if ( booking_plugin_table_is_current( $table_name ) ) {
        update_option( 'booking_plugin_db_version', BOOKING_PLUGIN_DB_VERSION );
    }
}

function booking_plugin_table_is_current( string $table_name ): bool {
    global $wpdb;

    $required_columns = [ 'payment_status', 'payment_method', 'paypal_order_id' ];
    $existing_columns  = $wpdb->get_col( "DESCRIBE {$table_name}", 0 );

    return empty( array_diff( $required_columns, $existing_columns ) );
}

register_deactivation_hook( __FILE__, 'booking_plugin_deactivate' );

function booking_plugin_deactivate() {
}
