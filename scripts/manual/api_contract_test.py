#!/usr/bin/env python3
"""Explicitly invoked live HTTP contract checks. No discovery by the normal suite."""
import argparse
import base64
from datetime import datetime, timedelta, timezone
import hashlib
import hmac
import json
import os
from pathlib import Path
import re
import signal
import sys
import time
import uuid
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, urlencode, urlsplit
from urllib.request import Request, build_opener, HTTPRedirectHandler


class CheckFailed(Exception):
    """Messages contain only controlled technical diagnostics, never response bodies."""


def require(condition, code):
    if not condition:
        raise CheckFailed(code)


def reference(value):
    if isinstance(value, dict):
        value = value.get("id")
    if isinstance(value, str) and value.startswith("https://easyverein.com/"):
        value = value.rstrip("/").rsplit("/", 1)[-1]
    require(not isinstance(value, bool) and re.fullmatch(r"[1-9][0-9]{0,18}", str(value)),
            "invalid_resource_reference")
    return str(value)


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Http:
    def __init__(self, base):
        url = urlsplit(base)
        require(url.scheme == "https" and url.hostname and not url.username
                and not url.password and not url.query and not url.fragment,
                "https_base_url_required")
        self.base = base.rstrip("/") + "/"
        self.opener = build_opener(NoRedirect())

    def call(self, method, path, body=None, headers=None, expected=(200,)):
        require(not path.startswith(("/", "http")) and ".." not in path,
                "invalid_request_path")
        # Only fixed resource/method labels may appear in diagnostics. Never IDs,
        # query values, names, delegated tokens or remote response bodies.
        resource = path.split("/", 1)[0].split("?", 1)[0]
        resource = resource if resource in {"calendar", "member", "contact-details", "event",
                    "price-group", "participation", "integration"} else "resource"
        method_label = method if method in {"GET", "POST", "PATCH", "PUT", "DELETE"} else "METHOD"
        context = method_label + "_" + resource
        raw = b"" if body is None else json.dumps(body, separators=(",", ":")).encode()
        request = Request(self.base + path, data=raw if body is not None else None,
                          method=method, headers={"Accept": "application/json",
                                                  "Content-Type": "application/json", **(headers or {})})
        try:
            response = self.opener.open(request, timeout=20)
        except HTTPError as error:
            response = error
        except (URLError, TimeoutError, OSError):
            raise CheckFailed("http_outcome_unknown_" + context) from None
        with response:
            status = response.code
            # Do not read or log error bodies: they may contain personal data or secrets.
            require(status in expected, "unexpected_http_status_" + str(status) + "_" + context)
            if method == "DELETE" or status in (204, 404) or status >= 400:
                return status, None
            data = response.read(4_194_305)
            require(len(data) <= 4_194_304, "response_too_large")
            try:
                return status, json.loads(data)
            except (ValueError, UnicodeError):
                raise CheckFailed("invalid_json_response") from None


class Provider:
    def __init__(self, token):
        self.http = Http("https://easyverein.com/api/v3.0/")
        self.headers = {"Authorization": "Bearer " + token}

    def call(self, method, path, body=None, expected=(200,)):
        return self.http.call(method, path, body, self.headers, expected)

    def get(self, path):
        return self.call("GET", path)[1]

    def rows(self, resource, **query):
        # Validate provider cursors and retain every original filter.
        original = dict(query)
        query = {**query, "limit": "100"}
        seen = set()
        pages = set()
        for _ in range(100):
            encoded = urlencode(query)
            require(encoded not in pages, "repeated_collection_cursor")
            pages.add(encoded)
            data = self.get(resource + "/?" + encoded)
            require(isinstance(data, dict) and "next" in data and isinstance(data.get("results"), list),
                    "invalid_collection")
            for row in data["results"]:
                rid = reference(row)
                require(rid not in seen, "duplicate_collection_page")
                seen.add(rid)
                yield row
            if not data.get("next"):
                return
            next_url = urlsplit(data["next"])
            require(next_url.scheme == "https" and next_url.netloc == "easyverein.com"
                    and next_url.path == "/api/v3.0/" + resource + "/" and not next_url.fragment,
                    "unsafe_collection_cursor")
            parsed = parse_qs(next_url.query, keep_blank_values=True)
            require(all(len(values) == 1 for values in parsed.values()), "ambiguous_collection_cursor")
            query = {key: values[0] for key, values in parsed.items()}
            require(all(query.get(key) == str(value) for key, value in original.items()),
                    "collection_filter_changed")
        raise CheckFailed("collection_page_limit")


class Journal:
    def __init__(self, path, calculator, existing=False):
        self.path = Path(path)
        if existing:
            self.data = json.loads(self.path.read_text())
            require(re.fullmatch(r"fuf-api-test-[a-f0-9]{32}", self.data.get("marker", "")),
                    "invalid_journal_marker")
            require(self.data.get("calculator") == calculator, "journal_target_mismatch")
        else:
            self.path.parent.mkdir(parents=True, exist_ok=True)
            # Refuse accidental reuse/overwrite, even on concurrent invocation.
            fd = os.open(self.path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
            os.close(fd)
            self.data = {"marker": "fuf-api-test-" + uuid.uuid4().hex,
                         "calculator": calculator, "event": None, "event_post_pending": False}
            self.save()

    def save(self):
        temporary = self.path.with_suffix(".tmp")
        with open(os.open(temporary, os.O_CREAT | os.O_TRUNC | os.O_WRONLY, 0o600), "w") as out:
            json.dump(self.data, out)
            out.flush()
            os.fsync(out.fileno())
        os.replace(temporary, self.path)


class LiveCheck:
    def __init__(self, provider, calculator, journal, env):
        self.provider, self.calculator, self.journal, self.env = provider, calculator, journal, env
        self.marker = journal.data["marker"]

    def signed_headers(self, method, path):
        now = int(time.time())
        claims = {"serviceId": self.env["FUF_TEST_SERVICE_ID"],
                  "kid": self.env["FUF_TEST_KEY_ID"], "iss": self.env["FUF_TEST_ISSUER"],
                  "aud": self.env["FUF_INTEGRATION_AUDIENCE"],
                  "memberId": self.env["FUF_TEST_MEMBER_ID"], "iat": now, "exp": now + 60,
                  "nonce": uuid.uuid4().hex, "method": method, "target": "/" + path,
                  "bodyHash": hashlib.sha256(b"").hexdigest()}
        encoded = base64.urlsafe_b64encode(json.dumps(claims, separators=(",", ":")).encode()).rstrip(b"=")
        signature = hmac.new(self.env["FUF_TEST_SIGNING_KEY"].encode(), encoded, hashlib.sha256).hexdigest()
        return {"Authorization": "FuF " + encoded.decode() + "." + signature}

    def check_calculator(self, event):
        path = "integration/v1/events/" + event + "/route"
        self.calculator.call("GET", path, expected=(401,))
        headers = self.signed_headers("GET", path)
        _, route = self.calculator.call("GET", path, headers=headers)
        require(isinstance(route, dict) and route.get("route") == "legacy"
                and isinstance(route.get("contractVersion"), str), "calculator_route_contract")
        # An event created only at the provider must not acquire a Calculator trip.
        self.calculator.call("GET", path, headers=headers, expected=(401,))
        path = "integration/v1/events/" + event + "/trip"
        self.calculator.call("GET", path, headers=self.signed_headers("GET", path), expected=(404,))
        print("PASS calculator: delegation, route, replay protection, unmapped event")

    def create(self, resource, body, endpoint=None):
        _, row = self.provider.call("POST", endpoint or resource + "/", body, (200, 201))
        return reference(row)

    def run(self):
        calendar = reference(self.env["FUF_TEST_CALENDAR_ID"])
        member = self.provider.get("member/" + reference(self.env["FUF_TEST_MEMBER_ID"]) + "/?query={id,contact_details{id}}")
        require(reference(member) == self.env["FUF_TEST_MEMBER_ID"], "test_member_mismatch")
        contact = reference(member.get("contact_details"))
        address = self.provider.get("contact-details/" + contact + "/?query={id,primary_email}")
        require(reference(address) == contact and str(address.get("primary_email", "")).strip().lower() == "me@benna.me",
                "test_contact_email_must_be_me_at_benna_me")
        require(reference(self.provider.get("calendar/" + calendar + "/?query={id}")) == calendar,
                "test_calendar_mismatch")
        start = datetime.now(timezone.utc) + timedelta(days=30)
        self.journal.data["event_post_pending"] = True
        self.journal.save()  # Persist intent before the potentially ambiguous POST.
        event = self.create("event", {"name": self.marker, "note": self.marker,
                            "calendar": int(calendar), "start": start.isoformat(),
                            "end": (start + timedelta(hours=1)).isoformat(), "access": 2,
                            "is_public": False, "show_memberarea": False, "max_participators": 4,
                            "allow_multiple_price_groups_per_participation": False})
        self.journal.data.update(event=event, event_post_pending=False)
        self.journal.save()
        self.check_calculator(event)
        self.provider.call("PATCH", "event/" + event + "/", {"prologue": "<p>" + self.marker + "</p>"})
        row = self.provider.get("event/" + event + "/?query={id,note,prologue}")
        require(row.get("note") == self.marker and self.marker in row.get("prologue", ""),
                "event_update_not_persisted")
        group = self.create("price-group", {"event": int(event), "name": "API Test Erwachsene",
                            "description": self.marker, "selling_price": 1.25, "pieces": -1,
                            "maximum_pieces_per_participation": 1})
        self.provider.call("PATCH", "price-group/" + group + "/", {"name": "API Test geändert"})
        require(self.provider.get("price-group/" + group + "/").get("name") == "API Test geändert",
                "price_group_update_not_persisted")
        body = {"participation_event": int(event), "participation_address": int(contact),
                "state": 1, "description": self.marker,
                "participation_price_groups": [{"price_group": int(group), "pieces": 1}]}
        endpoint = "participation/create-participation-with-price-groups/"
        parent = self.create("participation", body, endpoint)
        guest = self.create("participation", {**body, "is_companion": True,
                            "companion_name": self.marker, "parent": int(parent)}, endpoint)
        for rid, companion in ((parent, False), (guest, True)):
            row = self.provider.get("participation/" + rid + "/?query={id,participation_event,state,is_companion,parent,companion_name,participation_price_groups{price_group,pieces}}")
            require(reference(row.get("participation_event")) == event and row.get("state") == 1
                    and row.get("is_companion") is companion, "participation_contract")
            groups = row.get("participation_price_groups", [])
            require(len(groups) == 1 and reference(groups[0].get("price_group")) == group
                    and groups[0].get("pieces") == 1, "participation_price_group_contract")
            if companion:
                require(reference(row.get("parent")) == parent and row.get("companion_name") == self.marker,
                        "guest_parent_contract")
        for rid in (guest, parent):
            self.provider.call("PATCH", "participation/" + rid + "/", {"state": 0})
            require(self.provider.get("participation/" + rid + "/?query={id,state}").get("state") == 0,
                    "cancellation_not_persisted")
        print("PASS easyverein: event, description, price group, member, guest, cancellations")

    def delete(self, resource, rid):
        path = resource + "/" + reference(rid) + "/"
        self.provider.call("DELETE", path, expected=(200, 202, 204, 404))
        self.provider.call("GET", path, expected=(404,))

    def cleanup(self):
        data = self.journal.data
        event = data["event"]
        if event is None and data["event_post_pending"]:
            matches = [row for row in self.provider.rows("event", search=self.marker,
                       query="{id,note}") if row.get("note") == self.marker]
            require(len(matches) == 1, "event_post_outcome_unresolved_keep_journal")
            event = data["event"] = reference(matches[0])
            data["event_post_pending"] = False
            self.journal.save()
        if event is not None:
            status, row = self.provider.call("GET", "event/" + reference(event) + "/?query={id,note}",
                                             expected=(200, 404))
            if status != 404:
                require(reference(row) == event and row.get("note") == self.marker,
                        "cleanup_event_ownership_unproven")
                people = list(self.provider.rows("participation", participation_event=event,
                              query="{id,participation_event,description,is_companion}"))
                groups = list(self.provider.rows("price-group", event=event,
                              query="{id,event,description}"))
                require(all(row.get("description") == self.marker and
                            reference(row.get("participation_event")) == event for row in people)
                        and all(row.get("description") == self.marker and
                            reference(row.get("event")) == event for row in groups),
                        "cleanup_foreign_child_detected")
                errors = []
                for row in sorted(people, key=lambda row: not row.get("is_companion", False)):
                    try:
                        self.delete("participation", reference(row))
                    except CheckFailed as error:
                        errors.append(str(error))
                require(not errors, "cleanup_participations_failed")
                for row in groups:
                    try:
                        self.delete("price-group", reference(row))
                    except CheckFailed as error:
                        errors.append(str(error))
                require(not errors, "cleanup_price_groups_failed")
                self.delete("event", event)
        self.journal.path.unlink()
        print("PASS cleanup: own test data removed and absence verified")


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run-live", action="store_true", help="Required explicit manual invocation")
    parser.add_argument("--journal", required=True, help="Durable local path; must not exist on new runs")
    parser.add_argument("--cleanup-only", action="store_true", help="Resume cleanup from existing journal")
    args = parser.parse_args(argv)
    require(args.run_live, "manual_run_live_flag_required")
    keys = ["EASYVEREIN_API_TOKEN", "FUF_TEST_CALCULATOR_URL"]
    if not args.cleanup_only:
        keys += ["FUF_TEST_CALENDAR_ID", "FUF_TEST_MEMBER_ID", "FUF_TEST_SERVICE_ID",
                 "FUF_TEST_KEY_ID", "FUF_TEST_ISSUER", "FUF_INTEGRATION_AUDIENCE", "FUF_TEST_SIGNING_KEY"]
    require(all(os.environ.get(key) for key in keys), "required_environment_missing")
    env = dict(os.environ)
    calculator = Http(env["FUF_TEST_CALCULATOR_URL"])
    require(urlsplit(calculator.base).path == "/", "calculator_url_must_be_origin")
    if not args.cleanup_only:
        require(len(env["FUF_TEST_SIGNING_KEY"]) >= 32, "signing_key_too_short")
        reference(env["FUF_TEST_MEMBER_ID"])
        reference(env["FUF_TEST_CALENDAR_ID"])
    journal = Journal(args.journal, calculator.base, existing=args.cleanup_only)
    check = LiveCheck(Provider(env["EASYVEREIN_API_TOKEN"]), calculator, journal, env)
    def interrupted(signum, frame):
        raise CheckFailed("interrupted")
    previous = {sig: signal.signal(sig, interrupted) for sig in (signal.SIGTERM, signal.SIGINT)}
    failed = False
    try:
        if not args.cleanup_only:
            check.run()
    except CheckFailed as error:
        print("FAIL checks: " + str(error) + "; attempting cleanup", file=sys.stderr)
        failed = True
    except (Exception, KeyboardInterrupt):
        print("FAIL runner; attempting cleanup (details suppressed)", file=sys.stderr)
        failed = True
    finally:
        # Further normal termination signals must not interrupt cleanup itself.
        for sig in previous:
            signal.signal(sig, signal.SIG_IGN)
        try:
            check.cleanup()
        except CheckFailed as error:
            print("FAIL cleanup: " + str(error), file=sys.stderr)
            print("Keep journal and rerun with --cleanup-only", file=sys.stderr)
            failed = True
        except Exception:
            print("FAIL cleanup; keep journal and rerun with --cleanup-only", file=sys.stderr)
            failed = True
        finally:
            for sig, handler in previous.items():
                signal.signal(sig, handler)
    return 1 if failed else 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        print("FAIL configuration or journal; no secrets/response bodies logged", file=sys.stderr)
        sys.exit(1)
