# easyVerein term POST validation

## Observed failure

The production UI reports `term_create_invalid` after the explicit create/resume action. This means the event POST received HTTP 400/422. The previous transport discarded the response body, so the actual field rejection cannot be recovered from the existing job. Reading the calendar does not establish that the event payload is accepted.

## Change

- Omit blank optional `location_name` and `prologue` on event creation, leaving provider defaults. This addresses blank optional fields without inventing replacement text. It is not proof that these fields caused the reported production failure.
- Reduce validation responses to an allowlisted field name and a fixed local reason code. No original messages, values, contact details, URLs or tokens are persisted or returned.
- Persist these structured details on the original job and display translated field errors in the term status. Clear them when an explicit resume is queued.
- Preserve existing guards: no automatic retry of rejected creation; uncertain writes only reconcile; explicit resume checks for existing marked and similar events before creating.

## Validation and limits

Fake provider/unit tests cover blank option omission and validation propagation. Integration coverage checks rejection, persistence, API response schema, explicit resume and clearing old errors. Frontend scenarios cover safe translated output. No real provider event or participation is created during tests (see AGENTS.md).

The original response was discarded, and local development has no production API token. An authenticated read-only OPTIONS request confirmed the submitted field names are writable and exposed no additional required fields. Production job inspection was blocked by automatic approval review because it would copy private trip data into Actions logs. A successful real provider creation is therefore unverified. If the next explicit attempt is rejected, the new field details are needed to identify the remaining cause. Unknown or malformed response shapes produce no raw diagnostics.

Local checks: 397 PHPUnit tests / 6,273 assertions passed (12 pre-existing OIDC deprecations); 21 isolated frontend scenarios passed.
