<?php

namespace BookingPlugin\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaymentStatuses {

    public const UNPAID = 'unpaid';
    public const PAID   = 'paid';

    public const ALL = [ self::UNPAID, self::PAID ];
}
