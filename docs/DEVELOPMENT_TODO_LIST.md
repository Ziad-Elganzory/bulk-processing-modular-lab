## Stage 1 — Orders
### Goal: Process one order chunk safely and save valid orders to MySQL.
- Define the order columns and duplicate policy.
- Create the Orders-owned MySQL tables and migrations.
- Add Orders inbox/outbox tables and RabbitMQ handling.
- Consume OrderChunkRequested; read its chunk from MinIO, validate rows, and write accepted orders in a chunk-sized transaction.
- Publish OrdersChunkCommitted or, after terminal failure, OrdersChunkFailed.
- Make repeat delivery safe using message IDs and a stable chunk identity.
Done when: the same chunk can be delivered again without duplicating orders, and the result message reflects what was persisted or rejected.
## Stage 2 — BulkImports
### Goal: Turn an uploaded file into tracked, processable chunks.
- Add import-run and chunk records owned by BulkImports.
- Consume ImportRequested and read the source CSV from MinIO incrementally.
- Validate the CSV structure and split it into bounded chunk objects in MinIO.
- Publish OrderChunkRequested messages.
- Consume Orders success/failure results and update progress.
- Publish ImportProgressed and the terminal ImportCompleted message with final counts and the rejection-report key.
- Track Analytics status separately so ClickHouse lag doesn’t hold up order-import completion.
Done when: a run can track every chunk to a terminal outcome and report accurate totals.
## Stage 3 — Dashboard
### Goal: Let an admin start and monitor imports in Filament.
- Add the CSV upload page and basic file checks.
- Store the original file in MinIO and publish ImportRequested.
- Add Dashboard inbox handling and a status projection for progress and completion messages.
- Display row counts, import status, and a rejection-report download link.
Done when: an admin can start an import and follow its progress without keeping the upload request open.
## Stage 4 — Analytics
### Goal: Load accepted order data into ClickHouse independently.
- Create Analytics-owned ClickHouse tables.
- Consume committed Orders chunk events.
- Read accepted-row objects from MinIO and bulk-insert into ClickHouse.
- Make repeat delivery safe.
- Publish AnalyticsChunkLoaded or terminal AnalyticsChunkFailed.
Done when: accepted orders become queryable in ClickHouse, with loading status tracked separately from MySQL import status.
## Stage 5 — End-to-end reliability and demo
### Goal: Prove the complete workflow and its recovery behavior.
- Confirm every publishing module writes to its own outbox and every consuming module deduplicates through its own inbox.
- Configure retries, acknowledgements, and dead-letter handling.
- Run a large CSV through the whole flow.
- Demonstrate valid rows, rejected rows, a duplicate delivery, a terminal failure, and the final Filament status.
For clarity, each module owns its outbox/inbox tables through its own migrations, while shared messaging code can provide the reusable mechanics. The next development stage is Orders.