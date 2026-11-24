<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Referral payout release gate
    |--------------------------------------------------------------------------
    |
    | Keep payouts disabled until course-payment refunds, partial refunds,
    | chargebacks and reversals have an authoritative link to referral
    | commissions and a tested reconciliation policy.
    |
    */
    'payouts_enabled' => env('AEC_REFERRAL_PAYOUTS_ENABLED', false),
];
