# Registration deadline overlapped the all-day event

The actual HTTP 400 response from easyVerein was a root JSON list:

```json
["Der Anmeldezeitraum darf sich nicht mit dem Termin überschneiden."]
```

Trip 2 starts on 2026-10-16 at 00:00 Europe/Berlin. The selected deadline 2026-10-16 was serialized at 23:59:59, inside the event. The fields were individually well typed, but the registration interval was invalid. Blank optional text was not the cause. The previous field-only error parser discarded this root-list response.

The wizard now limits the date picker to the previous day and explains that a date-only deadline means 23:59. It checks the rule before sending the setup request. The server review refuses to enqueue an overlapping deadline, including callers that bypass the browser. The event adapter also stops old invalid jobs before any provider request. Local drafts remain editable even when their dates are incomplete or need correction. Linking an existing event does not use the draft deadline.

The validation parser recognizes the observed root-list overlap error as the fixed local code `end_participation:overlap`. Other root-list strings yield only a generic event rejection; no raw provider response text or secrets are persisted.

## Actual test repair

The user explicitly authorized real test-event creation/deletion and diagnostic access. The failed original job was leased exclusively, the test deadline corrected to 2026-10-15 in both its payload and local draft, and the correction audited. The original operation key and provider marker were retained, with authorization, collision checks and per-write guards. The provider then accepted creation, the four price groups and activation. Reading back the event succeeded and the verified link was stored; job 1 finished `succeeded` with no error. No unrelated events or participations were deleted.

## Regression checks

Tests cover same-day/later deadline rejection before provider calls, a valid previous-day cutoff across daylight-saving time, server review/request rejection without creating a job, the real provider response shape, safe translations, leap-year/month boundaries, and the wizard input constraints. Browser/Lighthouse checks remain manual per project instructions.
