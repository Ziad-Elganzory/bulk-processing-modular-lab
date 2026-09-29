<?php

return [
    'exchanges' => [
        'bulk-processing.commands' => [
            'type' => 'direct',
            'durable' => true,
            'auto_delete' => false,
        ],
        'bulk-processing.events' => [
            'type' => 'topic',
            'durable' => true,
            'auto_delete' => false,
        ],
    ],

    'queues' => [
        'orders.order-chunks' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
        ],
    ],

    'bindings' => [
        [
            'queue' => 'orders.order-chunks',
            'exchange' => 'bulk-processing.commands',
            'routing_key' => 'orders.chunk.requested.v1',
        ],
    ],
];