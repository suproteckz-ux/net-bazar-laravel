<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4 Measurement ID
    |--------------------------------------------------------------------------
    | Set GA4_MEASUREMENT_ID=G-XXXXXXXXXX in .env to enable analytics.
    | If empty, all GA4 scripts and events are suppressed — no errors.
    */
    'ga4_measurement_id' => env('GA4_MEASUREMENT_ID', ''),
];
