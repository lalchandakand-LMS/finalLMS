<?php

return [
    'alert_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SYSTEM_HEALTH_ALERT_EMAILS', '')),
    ))),
    'alert_cooldown_minutes' => max(5, (int) env('SYSTEM_HEALTH_ALERT_COOLDOWN_MINUTES', 30)),
];
