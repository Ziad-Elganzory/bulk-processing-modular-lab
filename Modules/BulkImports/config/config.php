<?php

return [
    'name' => 'BulkImports',
    'chunk_size' => 1000,

    'csv_headers' => [
        'order_id',
        'customer_id',
        'amount',
        'currency',
        'order_date',
    ],
];
