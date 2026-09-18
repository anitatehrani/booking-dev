<?php

namespace BookingPlugin\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PaymentMethods {

    public const PAYPAL = 'paypal';
    public const CASH   = 'cash';

    public const ALL = [ self::PAYPAL, self::CASH ];
}
