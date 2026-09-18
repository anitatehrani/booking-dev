<?php

namespace BookingPlugin\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingStatuses {

    public const PENDING   = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';

    public const ALL = [ self::PENDING, self::CONFIRMED, self::CANCELLED ];
}
