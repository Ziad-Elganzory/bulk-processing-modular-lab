<?php

namespace Modules\Dashboard\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Dashboard\Models\DashboardInboxMessage;

/**
 * @extends Factory<DashboardInboxMessage>
 */
class DashboardInboxMessageFactory extends Factory
{
    protected $model = DashboardInboxMessage::class;

    public function definition(): array
    {
        $importId = (string) Str::uuid();
        $messageId = (string) Str::uuid();
        $occurredAt = now()->toAtomString();

        return [
            'message_id' => $messageId,
            'message_type' => 'bulk-import.progressed.v1',
            'correlation_id' => $importId,
            'payload' => [
                'message_id' => $messageId,
                'message_type' => 'bulk-import.progressed.v1',
                'correlation_id' => $importId,
                'occurred_at' => $occurredAt,
                'data' => [
                    'import_id' => $importId,
                    'processed_chunks' => 1,
                    'total_chunks' => 10,
                    'processed_rows' => 1000,
                    'total_rows' => 10000,
                    'accepted_rows' => 990,
                    'rejected_rows' => 10,
                ],
            ],
            'status' => 'received',
            'received_at' => now(),
            'processed_at' => null,
            'last_error' => null,
        ];
    }
}
