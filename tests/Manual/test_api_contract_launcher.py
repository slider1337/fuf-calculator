"""Offline configuration checks: no SSH, HTTP or provider writes."""
import importlib.util
import json
from pathlib import Path
import sys
import unittest

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

    def test_missing_calendar_is_reported_without_creating_anything(self):
        env = self.config()
        del env["FUF_TEST_CALENDAR_ID"]
        with self.assertRaisesRegex(launcher.runner.CheckFailed, "missing_FUF_TEST_CALENDAR_ID"):
            launcher.prepare(env)

    def test_multiple_noncurrent_keys_require_explicit_selection(self):
        env = self.config()
        services = json.loads(env["FUF_INTEGRATION_SERVICES"])
        services["website"]["keys"] = {"old": "x" * 32, "new": "y" * 32}
        env["FUF_INTEGRATION_SERVICES"] = json.dumps(services)
        with self.assertRaisesRegex(launcher.runner.CheckFailed, "set_FUF_TEST_KEY_ID"):
            launcher.prepare(env)
        env["FUF_TEST_KEY_ID"] = "new"
        self.assertEqual("y" * 32, launcher.prepare(env)[0]["FUF_TEST_SIGNING_KEY"])


if __name__ == "__main__":
    unittest.main()
