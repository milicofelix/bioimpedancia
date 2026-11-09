<?php

return [
    // USD per million tokens. Snapshot these rates on every recorded request.
    // https://developers.openai.com/api/docs/pricing (consulted 2026-11-08)
    'price_version' => 'openai-standard-2026-11-08',
    'prices' => [
        'gpt-6.1-terra' => [
            'input' => 1.00, 'cached' => 0.05, 'output' => 5.00,
            'write_30m' => 1.25, 'write_12h' => 2.50,
            'long_context_threshold' => 272000,
            'long_context' => ['input' => 2.00, 'cached' => 0.10, 'output' => 7.50, 'write_30m' => 2.50, 'write_12h' => 5.00],
        ],
        'gpt-5.1' => ['input' => 1.25, 'cached' => 0.125, 'output' => 10.00],
    ],
    // Optional, manually maintained reference exchange rate, not a live quote.
    'usd_brl_rate' => env('AI_USAGE_USD_BRL_RATE'),
    'exchange_rate_date' => env('AI_USAGE_EXCHANGE_RATE_DATE'),
];
