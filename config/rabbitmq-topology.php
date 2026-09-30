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

        'bulk-imports.import-requests' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-delivery-limit' => 3,
                'x-dead-letter-exchange' => 'bulk-processing.dead-letters',
                'x-dead-letter-routing-key' => 'bulk-imports.import-requests.failed',
            ],
        ],

        'bulk-imports.order-chunk-results' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-delivery-limit' => 3,
                'x-dead-letter-exchange' => 'bulk-processing.dead-letters',
                'x-dead-letter-routing-key' => 'bulk-imports.order-chunk-results.failed',
            ],
        ],

        'bulk-imports.import-requests.failed' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-delivery-limit' => 3,
                'x-dead-letter-exchange' => 'bulk-processing.dead-letters',
                'x-dead-letter-routing-key' => 'bulk-imports.import-requests.failed.processing-errors',
            ],
        ],

        'bulk-imports.import-requests.failed.processing-errors' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
        ],

        'bulk-imports.order-chunk-results.failed' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-delivery-limit' => 3,
                'x-dead-letter-exchange' => 'bulk-processing.dead-letters',
                'x-dead-letter-routing-key' => 'bulk-imports.order-chunk-results.failed.processing-errors',
            ],
        ],

        'bulk-imports.order-chunk-results.failed.processing-errors' => [
            'type' => 'quorum',
            'durable' => true,
            'auto_delete' => false,
        ],

        // For processing the import requests internally for laravel workers.
        'bulk-imports.process-imports' => [
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

        'bulk-imports.events.observer' => [
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

        [
            'queue' => 'bulk-imports.import-requests',
            'exchange' => 'bulk-processing.commands',
            'routing_key' => 'bulk-import.requested.v1',
        ],

        [
            'queue' => 'bulk-imports.order-chunk-results',
            'exchange' => 'bulk-processing.events',
            'routing_key' => 'orders.chunk.committed.v1',
        ],

        [
            'queue' => 'bulk-imports.order-chunk-results',
            'exchange' => 'bulk-processing.events',
            'routing_key' => 'orders.chunk.failed.v1',
        ],

        [
            'queue' => 'bulk-imports.import-requests.failed',
            'exchange' => 'bulk-processing.dead-letters',
            'routing_key' => 'bulk-imports.import-requests.failed',
        ],

        [
            'queue' => 'bulk-imports.import-requests.failed.processing-errors',
            'exchange' => 'bulk-processing.dead-letters',
            'routing_key' => 'bulk-imports.import-requests.failed.processing-errors',
        ],

        [
            'queue' => 'bulk-imports.order-chunk-results.failed',
            'exchange' => 'bulk-processing.dead-letters',
            'routing_key' => 'bulk-imports.order-chunk-results.failed',
        ],

        [
            'queue' => 'bulk-imports.order-chunk-results.failed.processing-errors',
            'exchange' => 'bulk-processing.dead-letters',
            'routing_key' => 'bulk-imports.order-chunk-results.failed.processing-errors',
        ],

        // For Inspecting the events that are being published to RabbitMQ.
        [
            'queue' => 'orders.events.observer',
            'exchange' => 'bulk-processing.events',
            'routing_key' => 'orders.chunk.#',
        ],

        // For Inspecting the events that are being published to RabbitMQ.
        [
            'queue' => 'bulk-imports.events.observer',
            'exchange' => 'bulk-processing.events',
            'routing_key' => 'bulk-import.progressed.v1',
        ],

        [
            'queue' => 'bulk-imports.events.observer',
            'exchange' => 'bulk-processing.events',
            'routing_key' => 'bulk-import.completed.v1',
        ],
    ],
];
