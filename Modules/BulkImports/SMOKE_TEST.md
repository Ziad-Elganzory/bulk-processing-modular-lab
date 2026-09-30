# BulkImports Module Smoke Test

This guide manually verifies the current BulkImports and Orders flow with MySQL, MinIO, and RabbitMQ. It covers:

- A valid source CSV with one accepted and one row-level rejected order.
- An invalid source header.
- A header-only source CSV.
- A chunk-processing failure that reaches the Orders DLQ and returns `OrdersChunkFailed` to BulkImports.

These checks passed manually during implementation. They are smoke checks, not automated regression tests. The current design uses shared long-running workers; per-import worker orchestration is deferred.

## Prerequisites

Start Sail, confirm MinIO is the default disk, apply migrations, and declare the configured RabbitMQ topology:

```bash
sail up -d
sail artisan config:show filesystems.default
sail artisan migrate
sail artisan rabbitmq:topology:declare
```

The filesystem default should be `s3`. In RabbitMQ UI, verify these bindings exist:

- `bulk-imports.import-requests` ← `bulk-processing.commands` / `bulk-import.requested.v1`
- `orders.order-chunks` ← `bulk-processing.commands` / `orders.chunk.requested.v1`
- `bulk-imports.order-chunk-results` ← `bulk-processing.events` / `orders.chunk.committed.v1`
- `bulk-imports.order-chunk-results` ← `bulk-processing.events` / `orders.chunk.failed.v1`
- `bulk-imports.events.observer` ← `bulk-processing.events` / `bulk-import.progressed.v1` and `bulk-import.completed.v1`

## Start the workers

Run each command in its own terminal and leave it running:

```bash
sail artisan bulk-imports:consume-import-requests
```

```bash
sail artisan queue:work rabbitmq --queue=bulk-imports.process-imports --tries=3
```

```bash
sail artisan orders:consume-chunks
```

```bash
sail artisan orders:consume-failed-chunks
```

```bash
sail artisan bulk-imports:consume-order-chunk-results
```

The queue worker is expected to remain running after it handles a job. It waits for the next job until stopped with Ctrl+C.

For deterministic manual checks, do not run `schedule:work`; publish each module's outbox between stages. For automatic publishing, run `sail artisan schedule:work` in another terminal, but it runs the scheduled outbox commands once per minute and can publish a chunk before a test has time to modify its object.

## Creating a request without Dashboard

Dashboard does not exist yet. For these smoke tests, a Tinker command uploads the CSV to MinIO and inserts an `ImportRequested` envelope in the BulkImports outbox as a stand-in producer. `ImportRequested` currently requires a source checksum string; these examples use a placeholder because BulkImports does not verify it.

The request envelope uses the configured exchange and routing key:

- Exchange: `bulk-processing.commands`
- Routing key: `bulk-import.requested.v1`
- Source object key: `imports/{import_id}/source.csv`

After creating a request, publish it with:

```bash
sail artisan bulk-imports:outbox:publish
```

Wait for `bulk-imports:consume-import-requests` and the Laravel queue worker to handle it. The worker creates chunk object(s) and pending `orders.chunk.requested.v1` outbox row(s). Publish those with `sail artisan bulk-imports:outbox:publish` when ready.

## Test 1: Happy path with one rejected row

This source has one valid order and one invalid order (`amount=0`):

```csv
order_id,customer_id,amount,currency,order_date
SMOKE-HAPPY-001,CUST-001,125.50,USD,2026-09-28
SMOKE-HAPPY-002,CUST-002,0,USD,2026-09-28
```

The smoke run used import ID `smoke-happy-20260930-001` and source key `imports/smoke-happy-20260930-001/source.csv`. To create the MinIO object and request outbox row, run:

```bash
sail artisan tinker --execute '
$importId = "smoke-happy-20260930-001";
$sourceObjectKey = "imports/{$importId}/source.csv";
$csv = implode("\n", [
    "order_id,customer_id,amount,currency,order_date",
    "SMOKE-HAPPY-001,CUST-001,125.50,USD,2026-09-28",
    "SMOKE-HAPPY-002,CUST-002,0,USD,2026-09-28",
])."\n";

\Illuminate\Support\Facades\Storage::disk("s3")->put($sourceObjectKey, $csv);
$message = new \App\Messaging\Contracts\V1\ImportRequested(
    importId: $importId,
    sourceObjectKey: $sourceObjectKey,
    sourceChecksum: "sha256:smoke-fixture",
    requestedBy: "smoke-user",
);
$envelope = new \App\Messaging\Contracts\MessageEnvelope(
    messageId: "smoke-request:{$importId}",
    correlationId: $importId,
    occurredAt: new \DateTimeImmutable("now"),
    message: $message,
);
\Modules\BulkImports\Models\BulkImportsOutboxMessage::query()->create([
    "message_id" => $envelope->messageId,
    "message_type" => $message->messageType(),
    "correlation_id" => $envelope->correlationId,
    "exchange_name" => "bulk-processing.commands",
    "routing_key" => $message->messageType(),
    "payload" => $envelope->toArray(),
    "status" => "pending",
    "attempts" => 0,
    "available_at" => now(),
]);
dump($importId, $sourceObjectKey);
'
```

Advance the pipeline with these commands, waiting for the corresponding consumer between each stage:

```bash
sail artisan bulk-imports:outbox:publish
```

After the queue worker has created the chunk outbox row:

```bash
sail artisan bulk-imports:outbox:publish
```

After the Orders consumer handles the chunk:

```bash
sail artisan orders:outbox:publish
```

After the BulkImports results consumer handles the Orders outcome:

```bash
sail artisan bulk-imports:outbox:publish
```

Inspect the final run, chunk, persisted order, and completion event:

```bash
sail artisan tinker --execute '
$importId = "smoke-happy-20260930-001";
$run = \Modules\BulkImports\Models\ImportRun::query()
    ->where("import_id", $importId)
    ->first();
dump($run?->only(["status", "total_rows", "processed_rows", "accepted_rows", "rejected_rows", "total_chunks", "processed_chunks"]));
dump($run?->chunks()->get(["chunk_id", "status", "row_count", "accepted_rows", "rejected_rows"])->toArray());
dump(\Modules\Orders\Models\Order::query()
    ->whereIn("order_id", ["SMOKE-HAPPY-001", "SMOKE-HAPPY-002"])
    ->pluck("order_id")
    ->all());
$completed = \Modules\BulkImports\Models\BulkImportsOutboxMessage::query()
    ->where("correlation_id", $importId)
    ->where("message_type", "bulk-import.completed.v1")
    ->first();
dump($completed?->status, $completed?->payload["data"] ?? null);
'
```

Expected: import status `completed_with_errors`; 2 total/processed rows; 1 accepted and 1 rejected; one completed-with-errors chunk; only `SMOKE-HAPPY-001` in `orders`; completion outbox status `published`; and rejected report key `imports/{import_id}/reports/rejected.csv`.

## Test 2: Invalid source header

Use a unique import ID and this invalid CSV:

```csv
id,name
1,Example
```

Create and publish an `ImportRequested` the same way as Test 1, substituting a new import ID and this CSV content. The worker should mark the run failed before creating chunks. Publish the resulting BulkImports completion outbox row with `sail artisan bulk-imports:outbox:publish`.

Verify the run and event:

```bash
sail artisan tinker --execute '
$importId = "smoke-invalid-header-20260930-001";
$run = \Modules\BulkImports\Models\ImportRun::query()->where("import_id", $importId)->first();
$completed = \Modules\BulkImports\Models\BulkImportsOutboxMessage::query()
    ->where("correlation_id", $importId)
    ->where("message_type", "bulk-import.completed.v1")
    ->first();
dump($run?->only(["status", "total_rows", "failure_code", "failure_message"]));
dump($completed?->status, $completed?->payload["data"] ?? null);
'
```

Expected: `status` is `failed`, `failure_code` is `invalid_source_csv`, `total_rows` is `null`, and the completion event is `published` with zero accepted/rejected/processed rows.

## Test 3: Header-only source

Use the exact expected header and no data rows:

```csv
order_id,customer_id,amount,currency,order_date
```

Create and publish a request as above using a fresh import ID. After the queue worker handles it, publish the resulting BulkImports completion event and inspect the run.

Expected: `status` is `failed`, `failure_code` is `invalid_source_csv`, and `failure_message` is `The source CSV contains no data rows.` The completion event is published with `total_rows: null` and zero processed rows.

## Test 4: Orders chunk reaches DLQ and fails the import

This test deliberately corrupts a disposable generated chunk object before publishing its chunk request. It verifies the Orders DLQ flow and BulkImports result handling together.

1. Create and publish an import request for a **valid one-row CSV**, using a fresh import ID such as `smoke-chunk-dlq-20260930-001`.
2. Wait for `ProcessImport` to finish. Confirm the run has one pending chunk and the `orders.chunk.requested.v1` outbox message is still pending. Do not publish it yet. If `schedule:work` is running, stop it temporarily.
3. Overwrite only the generated chunk object with an invalid header:

```bash
sail artisan tinker --execute '
$objectKey = "imports/smoke-chunk-dlq-20260930-001/chunks/chunk-000001.csv";
$written = \Illuminate\Support\Facades\Storage::disk("s3")
    ->put($objectKey, "wrong_header\nbad-row\n");
dump($written);
'
```

4. Publish the chunk request with `sail artisan bulk-imports:outbox:publish`. The Orders consumer should reject/requeue it until RabbitMQ routes it to `orders.order-chunks.failed`. `orders:consume-failed-chunks` should consume it and create `orders.chunk.failed.v1` in the Orders outbox.
5. Publish the Orders failure event with `sail artisan orders:outbox:publish`. Wait for `bulk-imports:consume-order-chunk-results` to process it.
6. Publish the progress and completion events with `sail artisan bulk-imports:outbox:publish`.

Verify the final state:

```bash
sail artisan tinker --execute '
$importId = "smoke-chunk-dlq-20260930-001";
$run = \Modules\BulkImports\Models\ImportRun::query()->where("import_id", $importId)->first();
dump($run?->only(["status", "total_rows", "processed_rows", "total_chunks", "processed_chunks", "failure_code"]));
dump($run?->chunks()->get(["chunk_id", "status", "attempts", "failure_code"])->toArray());
$events = \Modules\BulkImports\Models\BulkImportsOutboxMessage::query()
    ->where("correlation_id", $importId)
    ->whereIn("message_type", ["bulk-import.progressed.v1", "bulk-import.completed.v1"])
    ->get(["message_type", "status", "payload"]);
dump($events->map(fn ($event) => ["message_type" => $event->message_type, "status" => $event->status, "data" => $event->payload["data"]])->all());
'
```

Expected: the Orders chunk run is `failed` with `delivery_limit_exceeded` after 4 deliveries; the BulkImports chunk is failed; the import status is `failed` with `chunk_processing_failed`; `processed_chunks` is 1 while `processed_rows` is 0; and both progress and completion events are published.

## Smoke results observed

- Happy path: 2 rows, 1 accepted, 1 rejected; rejected report created; import completed with errors.
- Invalid header: import failed with `invalid_source_csv`; unknown row total remained `null`; completion event published.
- Header-only CSV: import failed with the no-data-rows message; completion event published.
- DLQ integration: Orders recorded 4 deliveries and emitted `OrdersChunkFailed`; BulkImports marked the chunk/import failed and published progress and completion events.

## Repeating the smoke test

Use fresh import IDs, message IDs, and accepted order IDs for each run. Keep the manual publisher commands in sequence so the generated chunk can be inspected or modified before Orders receives it. Smoke records and MinIO objects remain available for review unless removed separately.
