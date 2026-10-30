import json
import os
from pathlib import Path
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]


@unittest.skipUnless(os.environ.get('COMPOSE_BIN'), 'Set COMPOSE_BIN to a standalone Compose binary')
class ComposeDiscoveryTest(unittest.TestCase):
    def test_resolved_web_worker_environment_and_data_volume(self):
        env = dict(os.environ, FUF_COMPOSE_PROJECT='fuf-calculator',
                   FUF_SQLITE_VOLUME='fuf-calculator_sqlite-data', FUF_PROXY_CIDR='172.30.0.0/16',
                   FUF_VIRTUAL_HOST='fixture.example.invalid', APP_URL='https://fixture.example.invalid',
                   MAILER_DSN='null://null', MAIL_FROM='fixture@example.invalid', EASYVEREIN_API_TOKEN='fixture-only')
        result = subprocess.run([env['COMPOSE_BIN'], '-f', str(ROOT / 'deploy/docker-compose.prod.yml'),
                                 'config', '--format', 'json'], env=env, capture_output=True, text=True, check=True)
        cfg = json.loads(result.stdout)
        app, worker, migrate = [cfg['services'][k] for k in ['app', 'worker', 'migrate']]
        self.assertEqual(app['environment']['VIRTUAL_HOST'], 'fixture.example.invalid')
        self.assertEqual(app['environment']['ACME_HOST'], 'fixture.example.invalid')
        self.assertEqual(app['environment']['VIRTUAL_PORT'], '80')
        self.assertEqual(app['environment']['EASYVEREIN_API_TOKEN'], 'fixture-only')
        self.assertEqual(worker['environment']['EASYVEREIN_API_TOKEN'], 'fixture-only')
        self.assertEqual(worker['environment'], {k: v for k, v in app['environment'].items()
                                                if k not in ['VIRTUAL_HOST', 'VIRTUAL_PORT', 'ACME_HOST']})
        self.assertFalse(set(migrate['environment']) & {'VIRTUAL_HOST', 'VIRTUAL_PORT', 'ACME_HOST'})
        self.assertEqual(cfg['volumes']['sqlite-data']['name'], 'fuf-calculator_sqlite-data')
        for service in [app, worker, migrate]:
            self.assertFalse(service.get('ports'))
            self.assertEqual([v['source'] for v in service['volumes'] if v['target'] == '/app/database'], ['sqlite-data'])
