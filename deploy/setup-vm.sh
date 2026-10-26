#!/usr/bin/env bash
# Shared proxy must be prepared separately. See docs/deployment/server-proxy.md.
set -euo pipefail
cd /opt/fuf-calculator
test -f .proxy-migration-approved
test -s proxy.env
docker network inspect benna-web >/dev/null
docker compose --env-file .env --env-file proxy.env -f docker-compose.prod.yml config --quiet
docker compose --env-file .env --env-file proxy.env -f docker-compose.prod.yml pull app worker migrate
docker compose --env-file .env --env-file proxy.env -f docker-compose.prod.yml up -d app worker
