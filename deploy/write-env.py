#!/usr/bin/env python3
"""Write the Compose environment without logging or shell-evaluating secret values."""
import os
import json
from pathlib import Path

KEYS = (
    'APP_URL', 'MAILER_DSN', 'MAIL_FROM', 'EASYVEREIN_CLIENT_ID',
    'EASYVEREIN_CLIENT_SECRET', 'EASYVEREIN_API_TOKEN', 'EASYVEREIN_WRITE_MODE',
    'EASYVEREIN_REQUESTS_PER_MINUTE', 'MAIL_DELIVERY_MODE',
    'FUF_INTEGRATION_AUDIENCE', 'FUF_INTEGRATION_SERVICES',
    'FUF_WORKER_BATCH', 'FUF_WORKER_INTERVAL',
    'FUF_RECONCILE_ACTOR_ID', 'FUF_RECONCILE_INTERVAL',
)
os.umask(0o077)
# Compose double quotes decode JSON-compatible escapes; $$ prevents variable expansion.
def literal(value):
    if '\x00' in value:
        raise ValueError('Invalid environment value')
    return json.dumps(value, ensure_ascii=False).replace('$', '$$')

Path('.env').write_text(''.join(f'{key}={literal(os.environ.get(key, ""))}\n' for key in KEYS))
