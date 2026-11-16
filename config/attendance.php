<?php

return [
    // Institutions may enable the Excused status after defining their approval policy.
    'allow_excused' => (bool) env('ATTENDANCE_ALLOW_EXCUSED', false),

    // Location is required at the moment attendance is recorded; no background tracking.
    'require_location' => true,

    // Short-lived QR sessions are intentionally brief to reduce sharing and replay.
    'qr_session_ttl_seconds' => 120,
];
