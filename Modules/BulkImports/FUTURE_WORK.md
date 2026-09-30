# BulkImports Module Future Work

This backlog records deferred work for the BulkImports module. The current shared-worker pipeline has been manually smoke-tested for successful imports with row-level rejects, invalid source files, and an Orders chunk reaching its DLQ and returning a terminal failure event.

## Priority guide

- **P1**: Needed before implementing the intended per-import worker lifecycle or relying on imports for operational recovery.
- **P2**: Useful hardening, automated coverage, and performance improvements after the demo flow is integrated.

## P1 — Before adding per-import worker orchestration

### Design and implement pipeline orchestration

The current system has shared long-running workers. The target design starts temporary workers for one import and stops them when that import finishes.

**Work:** Define who accepts a pipeline-start request, how workers become ready, how they are scoped to an `import_id`, how completion/failure stops them, and how cleanup works after timeout or process crash. Keep process management out of the Filament request. Decide whether this is an Orchestrator module or an application/runtime service.

**Complete when:** A pipeline can start the required workers for one import, route only that import's work to them, stop them on terminal completion/failure, and recover/clean up after an interrupted run.

### Choose per-import message isolation

The current `orders.order-chunks` and `bulk-imports.order-chunk-results` queues are shared by all imports. Starting a worker per import without changing routing does not guarantee it will receive only that import's messages.

**Work:** Compare import-specific queues/bindings with another partitioning strategy. Include RabbitMQ resource cleanup, durable queue behavior, queue naming, topology deployment, and how failures/retries remain correlated to the right import.

**Complete when:** The routing design gives each worker the intended import scope and has a defined lifecycle for queues/bindings without unbounded broker-resource growth.

### Recover imports after terminal Laravel job failure

Transient MinIO or MySQL errors can make `ProcessImport` retry. If the job exhausts retries, there is currently no explicit hook that marks the import failed and emits `ImportCompleted`.

**Work:** Define a failed-job handler or recovery command that records a stable failure code/message, makes the import terminal, and writes its completion event through the outbox. Keep transient retries distinct from invalid source CSV failures.

**Complete when:** An exhausted processing job cannot leave an import indefinitely in `processing`, and a repeated failure notification is idempotent.

## P2 — Reliability, latency, and coverage

### Add an after-commit publisher wake-up

Outbox rows are currently published by a scheduled command once per minute. This is reliable for eventual delivery but adds latency.

**Work:** Consider dispatching a publisher job after the database transaction commits, while retaining the scheduled publisher as a recovery sweep. Add an atomic claim/lease for pending rows before allowing multiple publisher processes to run concurrently. Keep inbox idempotency because broker delivery can still be repeated.

**Complete when:** Newly committed outbox rows are published promptly, stale pending rows are recovered, and concurrent relay processes do not routinely publish the same row at the same time.

### Add automated regression tests

Tests were deferred while building the demo. Cover:

- Import request idempotency and job dispatch behavior.
- Source header/column validation, empty files, chunk boundaries, row numbering, and deterministic chunk IDs.
- Chunk/outbox creation and retry behavior when MinIO or MySQL operations fail.
- Committed and failed Orders results, duplicate event delivery, progress counters, and final status.
- Rejected-report merging, report read/write failures, and completion event payloads.
- Outbox publication success, unroutable-message retry, and concurrent relay behavior.

**Complete when:** Important BulkImports behavior can be reproduced without RabbitMQ UI and manual Tinker steps.

### Review report generation and database transaction duration

The rejected report builder streams objects with bounded temporary storage, but the finalizer currently invokes it from the chunk-result transaction.

**Work:** Measure the duration and lock impact for large rejected reports. If it holds MySQL locks too long, separate report assembly into an idempotent finalization job/state while preserving the rule that `ImportCompleted` is emitted only after the report is stored.

**Complete when:** Large rejected reports do not create unacceptable transaction duration, and retries cannot publish completion before a valid report exists.

### Decide source checksum semantics

`ImportRequested` requires `source_checksum`, but BulkImports does not verify it. Chunk-level checksums were removed from the current Orders command contract.

**Work:** Either document the source checksum as producer metadata only, or define where/how BulkImports computes and verifies it. If wire semantics change after external consumers exist, version the contract appropriately.

**Complete when:** Producers and consumers agree on whether the checksum is required evidence or informational metadata.

## Outside BulkImports

- Dashboard owns Filament upload/status screens, its own outbox for `ImportRequested`, and its own inbox/projection for progress and completion events.
- Orders owns row validation, order persistence, chunk-level retry/DLQ handling, and its outcome events. See [Orders future work](../Orders/FUTURE_WORK.md).
- Analytics can consume committed chunk data/events after its module is implemented.
