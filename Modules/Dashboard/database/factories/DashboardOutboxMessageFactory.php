<?php

namespace Modules\Dashboard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Dashboard\Models\DashboardOutboxMessage;

/**
 * @extends Factory<DashboardOutboxMessage>
 */
class DashboardOutboxMessageFactory extends Factory
{
    protected $model = DashboardOutboxMessage::class;

    public function definition(): array
    {
        $importId = (string) Str::uuid();
        $messageId = (string) Str::uuid();
        $sourceObjectKey = "imports/{$importId}/source.csv";
        $sourceChecksum = 'sha256:'.str_repeat('a', 64);
        $requestedBy = 'demo-user';

        return [
            'message_id' => $messageId,
            'message_type' => 'bulk-import.requested.v1',
            'correlation_id' => $importId,
            'exchange_name' => 'bulk-processing.commands',
            'routing_key' => 'bulk-import.requested.v1',
            'payload' => [
                'message_id' => $messageId,
                'message_type' => 'bulk-import.requested.v1',
                'correlation_id' => $importId,
                'occurred_at' => now()->toAtomString(),
                'data' => [
                    'import_id' => $importId,
                    'source_object_key' => $sourceObjectKey,
                    'source_checksum' => $sourceChecksum,
                    'requested_by' => $requestedBy,
                ],
            ],
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
            'published_at' => null,
            'last_error' => null,
        ];
    }
}
