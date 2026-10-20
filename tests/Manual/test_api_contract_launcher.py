"""Offline configuration checks: no SSH, HTTP or provider writes."""
import importlib.util
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import Mock

DIRECTORY = Path(__file__).resolve().parents[2] / "scripts/manual"
sys.path.insert(0, str(DIRECTORY))
SPEC = importlib.util.spec_from_file_location("launch_api_contract", DIRECTORY / "launch_api_contract.py")
launcher = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(launcher)


class LauncherTest(unittest.TestCase):
    def config(self):
        return {"APP_URL": "https://calc.example", "EASYVEREIN_API_TOKEN": "offline",
                "FUF_API_RUN_ID": "123-1", "FUF_TEST_CALENDAR_ID": "3", "FUF_TEST_MEMBER_ID": "1",
                "FUF_INTEGRATION_AUDIENCE": "calc",
                "FUF_INTEGRATION_SERVICES": json.dumps({"website": {"issuer": "https://website.example",
                     "keys": {"current": "x" * 32}, "scopes": ["member.read"]}})}

    def test_reuses_existing_service_without_changing_original_config(self):
        original = self.config()
        env, mode, run_id = launcher.prepare(original)
        self.assertEqual(("run", "123-1"), (mode, run_id))
        self.assertEqual("x" * 32, env["FUF_TEST_SIGNING_KEY"])
        self.assertNotIn("FUF_TEST_SIGNING_KEY", original)
        self.assertEqual(original["APP_URL"], env["FUF_TEST_CALCULATOR_URL"])

    def test_cleanup_requires_no_member_or_integration_configuration(self):
        env, mode, run_id = launcher.prepare({"APP_URL": "https://calc.example", "EASYVEREIN_API_TOKEN": "offline",
                    "FUF_API_TEST_MODE": "cleanup", "FUF_API_CLEANUP_RUN_ID": "123-1"})
        self.assertEqual(("cleanup", "123-1"), (mode, run_id))

    def test_rejects_path_injection_in_cleanup_run_id(self):
        env = self.config()
        env.update(FUF_API_TEST_MODE="cleanup", FUF_API_CLEANUP_RUN_ID="../../other")
        with self.assertRaisesRegex(launcher.runner.CheckFailed, "invalid_run_id"):
            launcher.prepare(env)

    def test_missing_calendar_id_uses_user_selected_calendar_name(self):
        env = self.config()
        del env["FUF_TEST_CALENDAR_ID"]
        prepared = launcher.prepare(env)[0]
        self.assertEqual("FuF Verein", prepared["FUF_TEST_CALENDAR_NAME"])
        provider = Mock()
        provider.rows.return_value = [{"id": 10, "name": "FuF Teens"}, {"id": 3, "name": "FuF Verein"}]
        self.assertEqual("3", launcher.resolve_calendar(prepared, provider)["FUF_TEST_CALENDAR_ID"])
        provider.rows.assert_called_once_with("calendar", query="{id,name}")
        provider.call.assert_not_called()

    def test_missing_or_duplicate_calendar_names_abort_instead_of_guessing(self):
        env = launcher.prepare(self.config())[0]
        del env["FUF_TEST_CALENDAR_ID"]
        for rows in ([], [{"id": 3, "name": "FuF Verein"}, {"id": 4, "name": "FuF Verein"}]):
            provider = Mock()
            provider.rows.return_value = rows
            with self.assertRaisesRegex(launcher.runner.CheckFailed, "missing_or_ambiguous"):
                launcher.resolve_calendar(env, provider)
            provider.call.assert_not_called()

    def test_explicit_calendar_id_must_match_user_selected_name(self):
        env = launcher.prepare(self.config())[0]
        provider = Mock()
        provider.get.return_value = {"id": 3, "name": "FuF Teens"}
        with self.assertRaisesRegex(launcher.runner.CheckFailed, "not_FuF_Verein"):
            launcher.resolve_calendar(env, provider)
        provider.get.return_value = {"id": 3, "name": "FuF Verein"}
        self.assertEqual("3", launcher.resolve_calendar(env, provider)["FUF_TEST_CALENDAR_ID"])

    def test_multiple_noncurrent_keys_require_explicit_selection(self):
        env = self.config()
        services = json.loads(env["FUF_INTEGRATION_SERVICES"])
        services["website"]["keys"] = {"old": "x" * 32, "new": "y" * 32}
        env["FUF_INTEGRATION_SERVICES"] = json.dumps(services)
        with self.assertRaisesRegex(launcher.runner.CheckFailed, "set_FUF_TEST_KEY_ID"):
            launcher.prepare(env)
        env["FUF_TEST_KEY_ID"] = "new"
        self.assertEqual("y" * 32, launcher.prepare(env)[0]["FUF_TEST_SIGNING_KEY"])

    def test_resolve_selected_member_requires_name_and_email_not_name_alone(self):
        env = self.config()
        del env["FUF_TEST_MEMBER_ID"]
        env = launcher.prepare(env)[0]
        provider = Mock()
        provider.rows.return_value = [
            {"id": 8, "contact_details": {"id": 80, "name": "Benedikt Schaller", "primary_email": "other@example.invalid"}},
            {"id": 9, "contact_details": {"id": 90, "name": "Other Person", "primary_email": "me@benna.me"}},
            {"id": 1, "contact_details": {"id": 10, "name": "Benedikt Schaller", "primary_email": "me@benna.me"}}]
        self.assertEqual("1", launcher.resolve_member(env, provider)["FUF_TEST_MEMBER_ID"])
        provider.rows.assert_called_once_with("member", search="Benedikt Schaller",
                                              query="{id,contact_details{id,name,primary_email}}")
        provider.call.assert_not_called()

    def test_ambiguous_or_absent_selected_member_aborts(self):
        env = launcher.prepare(self.config())[0]
        del env["FUF_TEST_MEMBER_ID"]
        row = {"id": 1, "contact_details": {"id": 10, "name": "Benedikt Schaller", "primary_email": "me@benna.me"}}
        for rows in ([], [row, {**row, "id": 2}]):
            provider = Mock()
            provider.rows.return_value = rows
            with self.assertRaisesRegex(launcher.runner.CheckFailed, "missing_or_ambiguous"):
                launcher.resolve_member(env, provider)

    def test_explicit_member_id_does_not_bypass_selected_name_and_email(self):
        env = launcher.prepare(self.config())[0]
        provider = Mock()
        provider.get.return_value = {"id": 1, "contact_details": {"id": 10, "name": "Other Person", "primary_email": "me@benna.me"}}
        with self.assertRaisesRegex(launcher.runner.CheckFailed, "missing_or_ambiguous"):
            launcher.resolve_member(env, provider)

    def test_unexpanded_contact_is_checked_before_selecting_member(self):
        env = launcher.prepare(self.config())[0]
        provider = Mock()
        provider.get.side_effect = [{"id": 1, "contact_details": 10},
            {"id": 10, "name": "Benedikt Schaller", "primary_email": "me@benna.me"}]
        self.assertEqual("1", launcher.resolve_member(env, provider)["FUF_TEST_MEMBER_ID"])

    def test_provider_primary_email_field_is_used_for_member_lookup(self):
        env = launcher.prepare(self.config())[0]
        provider = Mock()
        contact = {"id": 10, "name": "Benedikt Schaller", "primary_email": "me@benna.me"}
        provider.get.side_effect = [{"id": 1, "contact_details": contact}, contact]
        self.assertEqual("1", launcher.resolve_member(env, provider)["FUF_TEST_MEMBER_ID"])
        self.assertIn("primary_email", provider.get.call_args_list[0].args[0])


if __name__ == "__main__":
    unittest.main()
