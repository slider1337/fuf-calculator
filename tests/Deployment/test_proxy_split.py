from pathlib import Path
from unittest import TestCase
import yaml

ROOT = Path(__file__).resolve().parents[2]

class ProxySplitTest(TestCase):
    def test_only_web_container_is_discovered_and_requests_a_certificate(self):
        cfg = yaml.safe_load((ROOT / 'deploy/docker-compose.prod.yml').read_text())
        app = cfg['services']['app']
        self.assertIn('80', app.get('expose', []))
        self.assertIsInstance(app['environment'], dict)
        env = app['environment']
        self.assertEqual(env['VIRTUAL_HOST'], '${FUF_VIRTUAL_HOST:-fuf-calc.benna.me}')
        self.assertEqual(env['ACME_HOST'], env['VIRTUAL_HOST'])
        self.assertEqual(env['VIRTUAL_PORT'], '80')
        for name in ['worker', 'migrate']:
            other = cfg['services'][name]['environment']
            keys = set(other) if isinstance(other, dict) else {line.split('=', 1)[0] for line in other}
            self.assertFalse(keys & {'VIRTUAL_HOST', 'VIRTUAL_PORT', 'ACME_HOST', 'LETSENCRYPT_HOST'})

    def test_calculator_cannot_publish_proxy_ports_or_own_certificates(self):
        cfg = yaml.safe_load((ROOT / 'deploy/docker-compose.prod.yml').read_text())
        self.assertEqual(set(cfg['services']), {'app', 'worker', 'migrate'})
        for service in cfg['services'].values():
            self.assertNotIn('ports', service)
            self.assertFalse(any('/data' == mount.split(':')[1] or '/config' == mount.split(':')[1]
                                 for mount in service.get('volumes', [])))
        self.assertEqual(cfg['networks']['benna-web'], {'external': True, 'name': 'benna-web'})
        self.assertEqual(cfg['services']['app']['networks']['benna-web']['aliases'], ['fuf-calculator'])
        self.assertIs(cfg['volumes']['sqlite-data']['external'], True)
        self.assertIn(':?', cfg['volumes']['sqlite-data']['name'])
        self.assertIn(':?', cfg['name'])
        self.assertNotIn('benna-web', cfg['services']['worker'].get('networks', []))
        self.assertNotIn('benna-web', cfg['services']['migrate'].get('networks', []))

    def test_deploy_checks_approval_before_copy_and_only_starts_app_services(self):
        workflow = (ROOT / '.github/workflows/deploy.yml').read_text()
        self.assertLess(workflow.index('test -f /opt/fuf-calculator/.proxy-migration-approved'), workflow.index('uses: appleboy/scp-action'))
        self.assertNotIn('image prune', workflow)
        self.assertNotIn('remove-orphans', workflow)
        commands = [line.strip() for line in workflow.splitlines() if 'docker compose ' in line]
        self.assertTrue(commands)
        for command in commands:
            self.assertIn('--env-file .env --env-file proxy.env', command)
            if ' up ' in command:
                self.assertTrue(command.endswith('up -d app worker'))

    def test_internal_http_and_explicit_trusted_network(self):
        caddy = (ROOT / 'deploy/Caddyfile').read_text()
        self.assertIn(':80 {', caddy)
        self.assertIn('trusted_proxies static {$FUF_PROXY_CIDR}', caddy)
        self.assertIn('php_server', caddy)
        self.assertNotIn('fuf-calc.benna.me {', caddy)
