# Orders Module Smoke Test

This guide manually verifies the Orders module's successful chunk path and its dead-letter path using one import with two chunks:

- `chunk-001`: two accepted rows and one rejected row.
- `chunk-002`: an invalid CSV header that exhausts retries and reaches the DLQ.

These are smoke checks against the running MySQL, MinIO, and RabbitMQ services. They complement automated tests; they do not replace them.

## Prerequisites

Start Sail and verify that the default filesystem disk is `s3` (MinIO):

```bash
sail up -d
sail artisan config:show filesystems.default
```

Apply migrations and declare the configured RabbitMQ topology:

```bash
sail artisan migrate
sail artisan rabbitmq:topology:declare
```

In RabbitMQ UI, verify that:

- `orders.order-chunks` is bound to `bulk-processing.commands` with `orders.chunk.requested.v1`.
- `orders.order-chunks.failed` is bound to `bulk-processing.dead-letters` with `orders.order-chunks.failed`.
- `orders.events.observer` is bound to `bulk-processing.events` with `orders.chunk.#`.

The observer binding is needed because the outbox publisher uses mandatory routing. It queues events for inspection; no application consumer reads it.

## Start the main consumer

In Terminal 1, run the main Orders consumer and leave it running:

```bash
sail artisan orders:consume-chunks
```

It prints a listening message while idle and prints a processed message after successful work. Processing failures are reported to Laravel logs while RabbitMQ retries the delivery.

## Test 1: Successful chunk with accepted and rejected rows

Create the fixture at `storage/app/orders-smoke.csv`:

```bash
cat > storage/app/orders-smoke.csv <<'CSV'
order_id,customer_id,amount,currency,order_date
ORD-SMOKE-001,CUST-001,125.50,USD,2026-09-28
ORD-SMOKE-002,CUST-002,0,USD,2026-09-28
ORD-SMOKE-003,CUST-003,42.00,eur,2026-09-28
CSV
```

This should accept `ORD-SMOKE-001` and `ORD-SMOKE-003`, reject `ORD-SMOKE-002` because its amount is zero, and normalize `eur` to `EUR`.

In Terminal 2, upload the fixture to MinIO and publish `chunk-001`:

```bash
sail artisan tinker --execute '$csv = file_get_contents(storage_path("app/orders-smoke.csv")); $importId = "smoke-orders-20260929-001"; $objectKey = "imports/{$importId}/chunks/chunk-001.csv"; \Illuminate\Support\Facades\Storage::disk("s3")->put($objectKey, $csv); $contract = new \App\Messaging\Contracts\V1\OrderChunkRequested(importId: $importId, chunkId: "chunk-001", objectKey: $objectKey, rowStart: 2, rowCount: 3); $envelope = new \App\Messaging\Contracts\MessageEnvelope(messageId: "smoke-orders-message-001", correlationId: $importId, occurredAt: new \DateTimeImmutable("now"), message: $contract); $rabbitMq = app(\VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector::class)->connect(config("queue.connections.rabbitmq")); $rabbitMq->getChannel()->basic_publish(new \PhpAmqpLib\Message\AMQPMessage($envelope->toJson(), ["content_type" => "application/json", "delivery_mode" => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT, "message_id" => $envelope->messageId, "correlation_id" => $envelope->correlationId, "type" => $contract->messageType()]), "bulk-processing.commands", "orders.chunk.requested.v1", true); $rabbitMq->close(); dump($objectKey, $envelope->messageId);'
```

After the consumer reports the message processed, publish the committed event:

```bash
sail artisan orders:outbox:publish
```

The publish count may include other due outbox rows from earlier runs. Verify that this import's `orders.chunk.committed.v1` outbox row is `published`; the RabbitMQ observer queue should receive the event.

## Test 2: Chunk-level failure reaches the DLQ

Create a second fixture with an invalid header at `storage/app/orders-dlq-smoke.csv`:

```bash
cat > storage/app/orders-dlq-smoke.csv <<'CSV'
wrong_order_id,customer_id,amount,currency,order_date
ORD-DLQ-001,CUST-DLQ-001,20.00,USD,2026-09-29
CSV
```

Publish it as `chunk-002` in the same import. `rowStart` is `5` because `chunk-001` covered source rows 2–4:

```bash
sail artisan tinker --execute '$csv = file_get_contents(storage_path("app/orders-dlq-smoke.csv")); $importId = "smoke-orders-20260929-001"; $objectKey = "imports/{$importId}/chunks/chunk-002.csv"; \Illuminate\Support\Facades\Storage::disk("s3")->put($objectKey, $csv); $contract = new \App\Messaging\Contracts\V1\OrderChunkRequested(importId: $importId, chunkId: "chunk-002", objectKey: $objectKey, rowStart: 5, rowCount: 1); $envelope = new \App\Messaging\Contracts\MessageEnvelope(messageId: "smoke-orders-message-002", correlationId: $importId, occurredAt: new \DateTimeImmutable("now"), message: $contract); $rabbitMq = app(\VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector::class)->connect(config("queue.connections.rabbitmq")); $rabbitMq->getChannel()->basic_publish(new \PhpAmqpLib\Message\AMQPMessage($envelope->toJson(), ["content_type" => "application/json", "delivery_mode" => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT, "message_id" => $envelope->messageId, "correlation_id" => $envelope->correlationId, "type" => $contract->messageType()]), "bulk-processing.commands", "orders.chunk.requested.v1", true); $rabbitMq->close(); dump($objectKey, $envelope->messageId);'
```

Wait until RabbitMQ UI shows the message in `orders.order-chunks.failed`. Keep the failed-queue consumer stopped until you have seen it there. The configured delivery limit is 3; with the current RabbitMQ behavior, the recorded delivery count may be 4 when the message is dead-lettered.

Then, in Terminal 3, start the failed-queue consumer:

```bash
sail artisan orders:consume-failed-chunks
```

It should consume the dead-lettered command, record `chunk-002` as failed, and create an `orders.chunk.failed.v1` outbox event. Publish that event:

```bash
sail artisan orders:outbox:publish
```

Verify that this import's `orders.chunk.failed.v1` outbox row is `published` and that a failure event appears in `orders.events.observer`. The publish count may include other due outbox rows from earlier runs.

## Verify both outcomes

Run this query in another terminal:

```bash
sail artisan tinker --execute '$importId = "smoke-orders-20260929-001"; dump(\Modules\Orders\Models\Order::whereIn("order_id", ["ORD-SMOKE-001", "ORD-SMOKE-002", "ORD-SMOKE-003", "ORD-DLQ-001"])->get(["order_id", "currency"])->toArray()); dump(\Modules\Orders\Models\OrderChunkRun::where("import_id", $importId)->orderBy("chunk_id")->get(["chunk_id", "status", "attempts", "accepted_rows", "rejected_rows", "failure_code"])->toArray()); dump(\Modules\Orders\Models\OrdersInboxMessage::whereIn("message_id", ["smoke-orders-message-001", "smoke-orders-message-002"])->orderBy("message_id")->get(["message_id", "status"])->toArray()); dump(\Modules\Orders\Models\OrdersOutboxMessage::where("correlation_id", $importId)->orderBy("id")->get(["message_type", "status", "attempts", "published_at"])->toArray()); dump(\Illuminate\Support\Facades\Storage::disk("s3")->exists("imports/{$importId}/accepted/chunk-001.csv"), \Illuminate\Support\Facades\Storage::disk("s3")->exists("imports/{$importId}/rejected/chunk-001.csv"));'
```

Expected results:

- `chunk-001` is `committed`, with 2 accepted rows and 1 rejected row.
- `chunk-002` is `failed`, with failure code `delivery_limit_exceeded`.
- `ORD-SMOKE-001` and `ORD-SMOKE-003` exist; the latter has currency `EUR`.
- `ORD-SMOKE-002` and `ORD-DLQ-001` do not exist in `orders`.
- Both inbox messages are `processed`.
- The outbox has one committed event and one failed event, both `published`.
- Both accepted and rejected result objects for `chunk-001` exist in MinIO.

## Repeating the smoke test

The database has unique order IDs and message IDs. For another run, use a fresh import ID, message IDs, and order IDs in both CSV fixtures and Tinker commands. The observer queue may also contain events from previous runs; check event type and correlation ID rather than relying on its total ready-message count.
