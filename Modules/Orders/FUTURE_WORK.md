# Orders Module Future Work

This backlog records the hardening points discussed while implementing the Orders module. The module's current chunk flow has been manually verified for successful imports, row-level rejects, retry exhaustion, DLQ handling, and outbox publication. These items are follow-ups for the demo; they do not block starting BulkImports.

## Priority guide

- **P1**: Complete before increasing processing concurrency or relying on the module for operational recovery.
- **P2**: Complete when adding regression coverage, stronger integrity guarantees, or production-oriented topology.

## P1 — Before scaling or operational use

### Define cross-chunk duplicate `order_id` behavior

The processor checks existing IDs before insertion, and MySQL has a unique index on `orders.order_id`. Two workers processing separate chunks with the same previously unseen ID at the same time can both pass the check; one insert can then fail at the database constraint and retry the whole chunk.

**Work:** Choose the intended policy for this race (for example, reject only the duplicate row while committing other valid rows) and implement a database-safe path that enforces it.

**Complete when:** Simultaneous chunks containing the same `order_id` produce the documented result, no duplicate order is stored, and a normal duplicate row does not unnecessarily fail the entire chunk.

### Protect outbox rows from overlapping publishers

`OrdersOutboxPublisher` selects due `pending` rows without claiming them. The scheduled command prevents overlapping scheduled runs, but a manually started publisher can still overlap with the scheduler or another publisher and send the same row more than once.

**Work:** Add an atomic claim/lease for rows being published and document the relay's delivery guarantee. Keep downstream inbox deduplication because a broker publish may succeed even if the relay cannot persist the confirmation state.

**Complete when:** Concurrent publisher processes do not normally claim the same pending rows, abandoned claims can be retried after a timeout, and duplicate delivery remains safe for consumers.

### Improve dead-letter diagnostics and recovery

The main consumer reports processing exceptions to Laravel logs. The DLQ handler currently records the generic `delivery_limit_exceeded` code and message, so the event does not explain whether the source object was missing, the CSV header was invalid, or MySQL failed. The secondary `orders.order-chunks.failed.processing-errors` queue has no consumer or documented replay procedure.

**Work:** Decide which failure details should be retained safely, make them available to the relevant operator/module, and document how messages in the processing-errors queue are inspected and replayed or discarded.

**Complete when:** An operator can identify the failure using the import/chunk/message IDs and follow a defined recovery procedure without accidentally duplicating orders or events.

## P2 — When stronger guarantees or coverage are needed

### Add automated regression tests

Tests were deferred to keep the demo moving. When added, cover:

- Valid rows, invalid rows, currency normalization, duplicate IDs, and rejected CSV output.
- Empty/malformed chunks, incorrect headers, unexpected columns, and row-count mismatch.
- Inbox duplicate delivery and logical chunk idempotency.
- Atomic order/chunk/inbox/outbox persistence when the database transaction fails.
- DLQ failure recording and duplicate DLQ delivery.
- Outbox success, unroutable publish retry, and repeated publish behavior.

**Complete when:** The important Orders behavior is reproducible without relying only on manual RabbitMQ and MinIO checks.

### Reintroduce chunk checksum in a future contract version, if needed

The checksum was removed from `OrderChunkRequested` V1 because Orders did not verify it. `ImportRequested` still carries the original source file checksum. If chunk-level integrity checking becomes a requirement, add the checksum to a new `orders.chunk.requested.v2` contract and verify it against the referenced MinIO object before importing rows.

**Complete when:** V2 producer and consumer behavior is documented, checksum mismatches fail before any order insert, and V1/V2 rollout or compatibility behavior is defined.

### Keep the observer queue development-only

`orders.events.observer` is currently bound to `orders.chunk.#` so the outbox events can be inspected in RabbitMQ UI. It retains messages when no consumer reads them.

**Work:** When real event subscribers exist, give each interested module its own queue and binding. Remove the observer from shared topology or make its declaration development-only if inspection is still useful.

**Complete when:** Committed and failed events reach the intended subscribers, and the observer queue is not silently accumulating messages in deployed environments.

### Review memory use at the chosen chunk size

`OrderChunkProcessor` streams the source CSV, but holds accepted and rejected rows for one chunk in memory before writing result files and inserting accepted rows. BulkImports should keep chunks bounded and choose a size that fits the expected worker memory.

**Complete when:** The selected chunk size is documented and a representative import fits the worker's memory budget. Consider streaming result generation or bounded insert batches if measurements require it.

## Outside the Orders module

- Uploading the original CSV, splitting it, and tracking import-wide progress belong to BulkImports.
- Filament upload and status screens belong to Dashboard.
- Loading committed order chunks into ClickHouse belongs to Analytics.
