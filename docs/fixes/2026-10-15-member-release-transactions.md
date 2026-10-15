# Member release: provider calls inside SQLite transactions

## Cause

Saving an enabled member release called `ParticipationPort::snapshot()` inside `AccessPolicy::writeTrip()`. The production `ProviderRequestBudget` explicitly rejects provider I/O while PDO has an open transaction, yielding `provider_network_transaction_forbidden`. Existing member test doubles did not enforce this guard.

The same pattern also affected live term-status reads and the inner authorization checks for member booking, answer correction and cancellation. An idempotent term request or resume could assemble a live status response before its write transaction committed.

## Correction

- Fetch the authoritative provider deadline before opening the release write transaction. Inside the transaction, recheck permissions, registration mode, lifecycle, provider gates and the bound event reference before saving settings and the audit record atomically.
- Read local term state in short transactions, obtain the live deadline outside them, then recheck permissions and current release/link/lifecycle before returning. Assemble responses to term writes after commit, including idempotent paths.
- Retain the live preflight event/deadline for member mutations. Recheck local release, mode, lifecycle, event reference and deadline under the write lock without making another provider request. Actual participation jobs retain their separate fresh worker preflights before external writes.
- Keep the production budget guard unchanged. Give an understandable UI message if that internal error is encountered elsewhere.

## Verification

Every fake provider snapshot in the member integration suite now runs the real `ProviderRequestBudget::reserve()` check. Existing family-booking, correction, cancellation, guest, reconciliation and worker tests therefore enforce the same transaction boundary as production.

The original implementation was temporarily checked against the regression test and reproduced the exact `provider_network_transaction_forbidden` failure; restoring the corrected implementation made that test pass. Targeted suites passed 61 tests / 3,974 assertions plus the added idempotent-response test (6 assertions). Additional cases check live term status, changed link/permissions/release during preflight, provider failure without partial writes, and disabling during a provider outage. CI runs the full suite. No real participation or member email was created during tests. Browser/Lighthouse checks remain manual.
