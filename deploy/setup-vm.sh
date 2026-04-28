#!/usr/bin/env bash
# =============================================================================
# VM Bootstrap Script for fuf-calculator
# Run this ONCE on a fresh Debian 13 VM with Docker installed.
#
# Usage:
#   scp -r deploy/ user@vm:/opt/fuf-calculator/
#   ssh user@vm 'bash /opt/fuf-calculator/setup-vm.sh'
# =============================================================================

set -euo pipefail

APP_DIR="/opt/fuf-calculator"

echo "==> Verifying Docker is installed..."
if ! command -v docker &>/dev/null; then
    echo "ERROR: Docker is not installed. Please install Docker first."
    exit 1
fi

echo "==> Pulling latest image..."
docker pull ghcr.io/slider1337/fuf-calculator:latest

echo "==> Starting container..."
cd "$APP_DIR"
docker compose -f docker-compose.prod.yml up -d

echo "==> Opening firewall ports (if ufw is active)..."
if command -v ufw &>/dev/null && ufw status | grep -q "active"; then
    ufw allow 80/tcp
    ufw allow 443/tcp
    echo "    Firewall rules added."
else
    echo "    No active ufw detected, skipping."
fi

echo ""
echo "============================================="
echo " Deployment complete!"
echo " Site: https://fuf-calc.benna.me"
echo " Data: docker volume 'fuf-calculator_sqlite-data'"
echo ""
echo " To update later, just run:"
echo "   docker pull ghcr.io/slider1337/fuf-calculator:latest"
echo "   cd $APP_DIR && docker compose -f docker-compose.prod.yml up -d"
echo "============================================="
