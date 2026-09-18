<?php

namespace BookingPlugin\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookableService {

    public function __construct() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register() {
        $labels = [
            'name'               => __( 'Services', 'booking-plugin' ),
            'singular_name'      => __( 'Service', 'booking-plugin' ),
            'add_new'            => __( 'Add New', 'booking-plugin' ),
            'add_new_item'       => __( 'Add New Service', 'booking-plugin' ),
            'edit_item'          => __( 'Edit Service', 'booking-plugin' ),
            'new_item'           => __( 'New Service', 'booking-plugin' ),
            'view_item'          => __( 'View Service', 'booking-plugin' ),
            'search_items'       => __( 'Search Services', 'booking-plugin' ),
            'not_found'          => __( 'No services found', 'booking-plugin' ),
            'not_found_in_trash' => __( 'No services found in Trash', 'booking-plugin' ),
            'menu_name'          => __( 'Bookable Services', 'booking-plugin' ),
        ];

        $args = [
            'labels'        => $labels,
            'public'        => true,
            'show_in_menu'  => true,
            'show_in_rest'  => true,
            'menu_icon'     => 'dashicons-calendar-alt',
            'supports'      => [ 'title', 'editor', 'thumbnail' ],
            'has_archive'   => true,
            'rewrite'       => [ 'slug' => 'services' ],
            'capability_type' => 'post',
        ];

        register_post_type( 'bookable_service', $args );
    }
}
