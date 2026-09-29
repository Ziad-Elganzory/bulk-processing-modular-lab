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
        'bulk-processing.dead-letters' => [
            'type' => 'direct',
            'durable' => true,
            'auto_delete' => false,
        ],
    ],

    'queues' => [
        'orders.order-chunks' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-delivery-limit' => 3,
                'x-dead-letter-exchange' => 'bulk-processing.dead-letters',
                'x-dead-letter-routing-key' => 'orders.order-chunks.failed',
            ],
        ],
        'orders.order-chunks.failed' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-delivery-limit' => 3,
                'x-dead-letter-exchange' => 'bulk-processing.dead-letters',
                'x-dead-letter-routing-key' => 'orders.order-chunks.failed.processing-errors',
            ],
        ],
        'orders.order-chunks.failed.processing-errors' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
        ],
        // For Inspecting the events that are being published to RabbitMQ.
        'orders.events.observer' => [
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
        [
            'queue' => 'orders.order-chunks.failed',
            'exchange' => 'bulk-processing.dead-letters',
            'routing_key' => 'orders.order-chunks.failed',
        ],
        [
            'queue' => 'orders.order-chunks.failed.processing-errors',
            'exchange' => 'bulk-processing.dead-letters',
            'routing_key' => 'orders.order-chunks.failed.processing-errors',
        ],
        // For Inspecting the events that are being published to RabbitMQ.
        [
            'queue' => 'orders.events.observer',
            'exchange' => 'bulk-processing.events',
            'routing_key' => 'orders.chunk.#',
        ],
    ],
];
