<?php

return [
    'retention_days' => (int) env('SCREENING_RETENTION_DAYS', 1825),
    'minor_applications_enabled' => (bool) env('SCREENING_MINOR_APPLICATIONS_ENABLED', true),
];
