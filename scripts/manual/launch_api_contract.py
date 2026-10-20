#!/usr/bin/env python3
"""Manual Actions entrypoint; reuses existing secrets without printing them."""
import json
import os
from pathlib import Path
import re
import sys

import api_contract_test as runner


def prepare(environment):
    env = dict(environment)
    mode = env.get("FUF_API_TEST_MODE", "run")
    runner.require(mode in ("run", "cleanup"), "invalid_test_mode")
    run_id = env.get("FUF_API_CLEANUP_RUN_ID", "") if mode == "cleanup" else env.get("FUF_API_RUN_ID", "")
    runner.require(re.fullmatch(r"[1-9][0-9]*-[1-9][0-9]*", run_id), "invalid_run_id")
    env["FUF_TEST_CALCULATOR_URL"] = env.get("APP_URL", "")
    if mode == "run":
        env["FUF_TEST_CALENDAR_NAME"] = "FuF Verein"
        env["FUF_TEST_MEMBER_NAME"] = "Benedikt Schaller"
        # Prefer the explicitly configured existing website service and current key.
        services = json.loads(env.get("FUF_INTEGRATION_SERVICES", "{}"))
        service_id = env.get("FUF_TEST_SERVICE_ID") or "website"
        service = services.get(service_id, {})
        runner.require(isinstance(service, dict) and "member.read" in service.get("scopes", []),
                       "integration_service_member_read_required")
        keys = service.get("keys", {})
        runner.require(isinstance(keys, dict) and keys, "integration_keys_missing")
        key_id = env.get("FUF_TEST_KEY_ID") or ("current" if "current" in keys else next(iter(keys)) if len(keys) == 1 else "")
        runner.require(key_id in keys, "set_FUF_TEST_KEY_ID_for_multiple_keys")
        env.update(FUF_TEST_SERVICE_ID=service_id, FUF_TEST_KEY_ID=key_id,
                   FUF_TEST_ISSUER=service.get("issuer", ""), FUF_TEST_SIGNING_KEY=keys[key_id])
    required = ["EASYVEREIN_API_TOKEN", "FUF_TEST_CALCULATOR_URL"]
    if mode == "run":
        required += ["FUF_INTEGRATION_AUDIENCE",
                     "FUF_TEST_ISSUER", "FUF_TEST_SIGNING_KEY"]
    for key in required:
        runner.require(isinstance(env.get(key), str) and bool(env[key]), "missing_" + key)
    return env, mode, run_id


def resolve_calendar(env, provider):
    name = env["FUF_TEST_CALENDAR_NAME"]
    if env.get("FUF_TEST_CALENDAR_ID"):
        rid = runner.reference(env["FUF_TEST_CALENDAR_ID"])
        row = provider.get("calendar/" + rid + "/?query={id,name}")
        runner.require(runner.reference(row) == rid and row.get("name") == name,
                       "configured_calendar_is_not_FuF_Verein")
    else:
        matches = [row for row in provider.rows("calendar", query="{id,name}")
                   if row.get("name") == name]
        runner.require(len(matches) == 1, "FuF_Verein_calendar_missing_or_ambiguous")
        rid = runner.reference(matches[0])
    return {**env, "FUF_TEST_CALENDAR_ID": rid}


def resolve_member(env, provider):
    name = env["FUF_TEST_MEMBER_NAME"]
    if env.get("FUF_TEST_MEMBER_ID"):
        rid = runner.reference(env["FUF_TEST_MEMBER_ID"])
        rows = [provider.get("member/" + rid + "/?query={id,contact_details{id,name,email}}")]
        runner.require(runner.reference(rows[0]) == rid, "configured_test_member_reference_mismatch")
    else:
        rows = provider.rows("member", search=name, query="{id,contact_details{id,name,email}}")
    matches = []
    for row in rows:
        contact = row.get("contact_details")
        if not isinstance(contact, dict) or not all(key in contact for key in ("id", "name", "email")):
            cid = runner.reference(contact)
            contact = provider.get("contact-details/" + cid + "/?query={id,name,email}")
            runner.require(runner.reference(contact) == cid, "test_contact_reference_mismatch")
        if (contact.get("name") == name and
                str(contact.get("email", "")).strip().lower() == "me@benna.me"):
            matches.append(runner.reference(row))
    runner.require(len(matches) == 1, "test_member_name_and_email_missing_or_ambiguous")
    return {**env, "FUF_TEST_MEMBER_ID": matches[0]}


def main():
    env, mode, run_id = prepare(os.environ)
    if mode == "run":
        provider = runner.Provider(env["EASYVEREIN_API_TOKEN"])
        env = resolve_calendar(env, provider)
        env = resolve_member(env, provider)
    directory = Path("/opt/fuf-calculator/api-contract/journals")
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    journal = directory / (run_id + ".json")
    # os.environ is private to this test process, never production configuration.
    os.environ.update(env)
    print("API test run: " + run_id + "; creates its own event" if mode == "run"
          else "API test cleanup: " + run_id)
    args = ["--run-live", "--journal", str(journal)]
    if mode == "cleanup":
        args.append("--cleanup-only")
    result = runner.main(args)
    if journal.exists():
        print("Cleanup required: select cleanup and run ID " + run_id, file=sys.stderr)
    return result


if __name__ == "__main__":
    try:
        sys.exit(main())
    except runner.CheckFailed as error:
        print("FAIL " + str(error), file=sys.stderr)
        sys.exit(1)
    except Exception:
        print("FAIL launcher configuration; secret values suppressed", file=sys.stderr)
        sys.exit(1)
