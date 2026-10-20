"""Offline tests of the live runner. No requests to either real API."""
import base64
import hashlib
import hmac
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location(
    "api_contract_test", Path(__file__).resolve().parents[2] / "scripts/manual/api_contract_test.py")
runner = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(runner)


class FakeProvider:
    def __init__(self):
        self.data = {"event": {}, "price-group": {}, "participation": {}}
        self.deleted = []
        self.next_id = 10
        self.fail_delete = None
        self.fail_post = None
        self.fail_patch = False

    def call(self, method, path, body=None, expected=(200,)):
        resource, *tail = path.split("?", 1)[0].strip("/").split("/")
        rid = tail[0] if tail else None
        if method == "POST":
            self.next_id += 1
            rid = str(self.next_id)
            self.data[resource][rid] = {"id": rid, **body}
            if resource == "participation":
                self.data[resource][rid].setdefault("is_companion", False)
            if self.fail_post == resource:
                raise runner.CheckFailed("http_outcome_unknown")
            return 201, dict(self.data[resource][rid])
        if method == "PATCH":
            if self.fail_patch:
                raise runner.CheckFailed("injected_patch_failure")
            self.data[resource][rid].update(body)
            return 200, dict(self.data[resource][rid])
        if method == "DELETE":
            self.deleted.append((resource, rid))
            if self.fail_delete == rid:
                raise runner.CheckFailed("injected_delete_failure")
            self.data[resource].pop(rid, None)
            return 204, None
        row = self.data[resource].get(rid)
        if row is None:
            if 404 in expected:
                return 404, None
            raise runner.CheckFailed("unexpected_http_status_404")
        return 200, dict(row)

    def get(self, path):
        if path.startswith("member/"):
            return {"id": 1, "contact_details": {"id": 2}}
        if path.startswith("contact-details/"):
            return {"id": 2, "primary_email": "me@benna.me"}
        if path.startswith("calendar/"):
            return {"id": 3}
        return self.call("GET", path)[1]

    def rows(self, resource, **query):
        for row in list(self.data[resource].values()):
            if "participation_event" in query and str(row["participation_event"]) != str(query["participation_event"]):
                continue
            if "event" in query and str(row["event"]) != str(query["event"]):
                continue
            yield dict(row)


class FakeCalculator:
    def __init__(self):
        self.calls = []

    def call(self, method, path, **kwargs):
        self.calls.append((method, path, kwargs))
        return kwargs.get("expected", (200,))[0], {"route": "legacy", "contractVersion": "1.7.0"}


class RunnerTest(unittest.TestCase):
    def setUp(self):
        # These are fake runs; do not emit live-looking PASS messages into CI logs.
        output = patch("sys.stdout", new=io.StringIO())
        output.start()
        self.addCleanup(output.stop)
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.path = Path(self.temp.name) / "run.json"
        self.journal = runner.Journal(self.path, "https://calc.example/")
        self.provider = FakeProvider()
        self.calculator = FakeCalculator()
        self.env = {"FUF_TEST_MEMBER_ID": "1", "FUF_TEST_CALENDAR_ID": "3",
                    "FUF_TEST_SERVICE_ID": "website", "FUF_TEST_KEY_ID": "current",
                    "FUF_TEST_ISSUER": "https://website.example", "FUF_INTEGRATION_AUDIENCE": "test",
                    "FUF_TEST_SIGNING_KEY": "x" * 32}
        self.check = runner.LiveCheck(self.provider, self.calculator, self.journal, self.env)

    def test_full_run_and_cleanup_deletes_guest_before_parent_and_verifies_absence(self):
        self.check.run()
        people = list(self.provider.data["participation"].values())
        self.assertEqual([0, 0], [person["state"] for person in people])
        self.check.cleanup()
        self.assertFalse(self.path.exists())
        self.assertTrue(all(not rows for rows in self.provider.data.values()))
        self.assertEqual([("participation", str(people[1]["id"])),
                          ("participation", str(people[0]["id"]))], self.provider.deleted[:2])

    def test_partial_run_failure_still_cleans_created_event(self):
        self.provider.fail_patch = True
        with self.assertRaises(runner.CheckFailed):
            self.check.run()
        self.check.cleanup()
        self.assertEqual({}, self.provider.data["event"])

    def test_lost_event_post_response_recovers_exact_marker_without_reposting(self):
        self.provider.fail_post = "event"
        with self.assertRaises(runner.CheckFailed):
            self.check.run()
        self.assertIsNone(self.journal.data["event"])
        resumed = runner.Journal(self.path, "https://calc.example/", existing=True)
        runner.LiveCheck(self.provider, self.calculator, resumed, self.env).cleanup()
        self.assertEqual(11, self.provider.next_id)
        self.assertFalse(self.path.exists())

    def test_lost_child_post_response_is_recovered_by_event_and_marker(self):
        self.provider.fail_post = "participation"
        with self.assertRaises(runner.CheckFailed):
            self.check.run()
        self.check.cleanup()
        self.assertTrue(all(not rows for rows in self.provider.data.values()))

    def test_failed_cleanup_keeps_journal_attempts_other_people_and_can_resume(self):
        self.check.run()
        guest = list(self.provider.data["participation"])[-1]
        self.provider.fail_delete = guest
        with self.assertRaisesRegex(runner.CheckFailed, "cleanup_participations_failed"):
            self.check.cleanup()
        self.assertTrue(self.path.exists())
        self.assertEqual(2, len(self.provider.deleted))
        self.assertTrue(self.provider.data["event"])
        self.provider.fail_delete = None
        self.check.cleanup()
        self.assertFalse(self.path.exists())

    def test_foreign_child_prevents_deletion(self):
        self.check.run()
        self.provider.data["participation"]["99"] = {
            "id": 99, "participation_event": self.journal.data["event"], "description": "foreign"}
        with self.assertRaisesRegex(runner.CheckFailed, "foreign_child"):
            self.check.cleanup()
        self.assertEqual([], self.provider.deleted)
        self.assertTrue(self.path.exists())

    def test_foreign_event_prevents_deletion(self):
        self.check.run()
        self.provider.data["event"][self.journal.data["event"]]["note"] = "foreign"
        with self.assertRaisesRegex(runner.CheckFailed, "ownership_unproven"):
            self.check.cleanup()
        self.assertEqual([], self.provider.deleted)

    def test_unknown_post_with_no_proven_event_keeps_journal(self):
        self.journal.data["event_post_pending"] = True
        self.journal.save()
        with self.assertRaisesRegex(runner.CheckFailed, "outcome_unresolved"):
            self.check.cleanup()
        self.assertTrue(self.path.exists())

    def test_delegation_matches_php_contract_and_replay_reuses_token(self):
        self.check.check_calculator("42")
        calls = self.calculator.calls
        self.assertEqual((401,), calls[0][2]["expected"])
        token = calls[1][2]["headers"]["Authorization"].removeprefix("FuF ")
        encoded, signature = token.split(".")
        self.assertEqual(hmac.new(b"x" * 32, encoded.encode(), hashlib.sha256).hexdigest(), signature)
        claims = json.loads(base64.urlsafe_b64decode(encoded + "=" * (-len(encoded) % 4)))
        self.assertEqual("/integration/v1/events/42/route", claims["target"])
        self.assertEqual(hashlib.sha256(b"").hexdigest(), claims["bodyHash"])
        self.assertEqual(calls[1][2]["headers"], calls[2][2]["headers"])
        self.assertNotEqual(calls[1][2]["headers"], calls[3][2]["headers"])

    def test_wrong_contact_email_aborts_before_writes(self):
        original = self.provider.get
        self.provider.get = lambda path: {"id": 2, "primary_email": "other@example.invalid"} if path.startswith("contact-details/") else original(path)
        with self.assertRaisesRegex(runner.CheckFailed, "email_must_be"):
            self.check.run()
        self.assertEqual(10, self.provider.next_id)

    def test_manual_flag_required_before_any_http_client_is_created(self):
        with patch.object(runner, "Http") as http:
            with self.assertRaisesRegex(runner.CheckFailed, "manual_run_live_flag_required"):
                runner.main(["--journal", str(self.path)])
            http.assert_not_called()

    def test_main_failure_automatically_cleans_and_returns_failure(self):
        env = {**self.env, "EASYVEREIN_API_TOKEN": "offline",
               "FUF_TEST_CALCULATOR_URL": "https://calc.example"}
        self.provider.fail_patch = True
        path = Path(self.temp.name) / "main.json"
        with patch.dict(runner.os.environ, env, clear=True), \
                patch.object(runner, "Provider", return_value=self.provider), \
                patch.object(runner, "Http", return_value=self.calculator), \
                patch("sys.stderr", new=io.StringIO()):
            self.calculator.base = "https://calc.example/"
            result = runner.main(["--run-live", "--journal", str(path)])
        self.assertEqual(1, result)
        self.assertFalse(path.exists())
        self.assertEqual({}, self.provider.data["event"])

    def test_main_signal_interruption_still_runs_cleanup(self):
        env = {**self.env, "EASYVEREIN_API_TOKEN": "offline",
               "FUF_TEST_CALCULATOR_URL": "https://calc.example"}
        path = Path(self.temp.name) / "interrupted.json"
        def interrupt():
            runner.signal.raise_signal(runner.signal.SIGTERM)
        with patch.dict(runner.os.environ, env, clear=True), \
                patch.object(runner, "Provider", return_value=self.provider), \
                patch.object(runner, "Http", return_value=self.calculator), \
                patch.object(runner.LiveCheck, "run", side_effect=interrupt), \
                patch("sys.stderr", new=io.StringIO()):
            self.calculator.base = "https://calc.example/"
            result = runner.main(["--run-live", "--journal", str(path)])
        self.assertEqual(1, result)
        self.assertFalse(path.exists())

    def test_journal_cannot_overwrite_an_existing_run(self):
        with self.assertRaises(FileExistsError):
            runner.Journal(self.path, "https://calc.example/")

    def test_refuse_redirects_and_insecure_bases(self):
        for base in ("http://calc.example", "https://secret@calc.example", "https://calc.example?token=x"):
            with self.assertRaises(runner.CheckFailed):
                runner.Http(base)
        self.assertIsNone(runner.NoRedirect().redirect_request(None, None, 302, "", {}, "https://other.example"))

    def test_provider_pagination_retains_filters_and_rejects_widening(self):
        provider = runner.Provider("offline")
        next_url = "https://easyverein.com/api/v3.0/event/?search=marker&offset=100"
        with patch.object(provider, "get", side_effect=[
                {"results": [{"id": 1}], "next": next_url},
                {"results": [{"id": 2}], "next": None}]):
            self.assertEqual([1, 2], [row["id"] for row in provider.rows("event", search="marker")])
        for next_url in ("https://evil.example/?search=marker", "https://easyverein.com/api/v3.0/event/?offset=100"):
            with patch.object(provider, "get", return_value={"results": [], "next": next_url}):
                with self.assertRaises(runner.CheckFailed):
                    list(provider.rows("event", search="marker"))

    def test_http_failure_names_resource_without_query_or_response_body(self):
        body = io.BytesIO(b'{"secret":"private-response-body"}')
        error = runner.HTTPError("https://easyverein.com/api/v3.0/member/", 400, "bad", {}, body)
        http = runner.Http("https://easyverein.com/api/v3.0/")
        with patch.object(http.opener, "open", side_effect=error):
            with self.assertRaises(runner.CheckFailed) as failure:
                http.call("GET", "member/?search=private-member-name")
        self.assertEqual("unexpected_http_status_400_GET_member", str(failure.exception))
        self.assertNotIn("private", str(failure.exception))

    def test_contact_preflight_requests_primary_email(self):
        original = self.provider.get
        paths = []
        def checked(path):
            paths.append(path)
            return original(path)
        self.provider.get = checked
        self.check.run()
        self.assertIn("contact-details/2/?query={id,primary_email}", paths)
        self.check.cleanup()

    def test_price_group_read_explicitly_requests_fields(self):
        original = self.provider.get
        def limited_default(path):
            row = original(path)
            if path.startswith("price-group/") and "query=" not in path:
                return {"id": row["id"]}
            return row
        self.provider.get = limited_default
        self.check.run()
        self.check.cleanup()

    def test_name_update_failure_remains_failure_but_does_not_skip_participation_checks(self):
        original = self.provider.call
        def ignore_rename(method, path, body=None, expected=(200,)):
            if method == "PATCH" and path.startswith("price-group/"):
                return original("GET", path)
            return original(method, path, body, expected)
        self.provider.call = ignore_rename
        with self.assertRaisesRegex(runner.CheckFailed, "price_group_update_not_persisted"):
            self.check.run()
        self.assertEqual(2, len(self.provider.data["participation"]))
        self.assertTrue(all(row["state"] == 0 for row in self.provider.data["participation"].values()))
        self.check.cleanup()
        self.assertFalse(self.path.exists())

    def test_provider_decimal_string_quantity_is_accepted_exactly(self):
        original = self.provider.get
        def decimal_quantity(path):
            row = original(path)
            if path.startswith("participation/") and "participation_price_groups" in row:
                row = {**row, "participation_price_groups": [
                    {**assignment, "pieces": "1.000000"}
                    for assignment in row["participation_price_groups"]]}
            return row
        self.provider.get = decimal_quantity
        self.check.run()
        self.check.cleanup()

    def test_decimal_comparison_does_not_accept_invalid_or_inexact_values(self):
        for value in (1, 1.0, "1", "1.000000"):
            self.assertTrue(runner.decimal_equals(value, "1"), repr(value))
        for value in (True, None, "NaN", "Infinity", "1e0", "1.0000000001", "2", [], {}):
            self.assertFalse(runner.decimal_equals(value, "1"), repr(value))

    def test_price_group_check_updates_price_with_label_like_calculator(self):
        self.check.run()
        group = next(iter(self.provider.data["price-group"].values()))
        self.assertEqual("API Test geändert", group["name"])
        self.assertEqual(1.75, group["selling_price"])
        self.check.cleanup()

    def test_readback_requests_bypass_http_cache_like_calculator(self):
        http = runner.Http("https://easyverein.com/api/v3.0/")
        with patch.object(http.opener, "open") as opened:
            response = opened.return_value
            response.code = 200
            response.read.return_value = b'{}'
            http.call("GET", "price-group/1/?query={id,name}")
            request = opened.call_args.args[0]
            self.assertEqual("no-cache", request.get_header("Cache-control"))

    def test_bad_assignment_stays_failure_but_both_cancellations_are_tested(self):
        original = self.provider.get
        def wrong_quantity(path):
            row = original(path)
            if path.startswith("participation/") and "participation_price_groups" in row:
                row = {**row, "participation_price_groups": [
                    {**assignment, "pieces": "2.000000"}
                    for assignment in row["participation_price_groups"]]}
            return row
        self.provider.get = wrong_quantity
        with self.assertRaisesRegex(runner.CheckFailed, "participation_price_group_contract_member.*guest"):
            self.check.run()
        self.assertEqual(2, len(self.provider.data["participation"]))
        self.assertTrue(all(row["state"] == 0 for row in self.provider.data["participation"].values()))
        self.check.cleanup()
        self.assertFalse(self.path.exists())


if __name__ == "__main__":
    unittest.main()
