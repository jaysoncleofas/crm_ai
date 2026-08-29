#!/usr/bin/env bash
# One-time: register a self-hosted GitHub Actions runner on this VPS.
#
# 1. GitHub → jaysoncleofas/crm_ai → Settings → Actions → Runners → New self-hosted runner
# 2. Copy the registration token (expires in ~1 hour)
# 3. On the server:
#      export RUNNER_REGISTRATION_TOKEN='paste-token-here'
#      bash deploy/scripts/install-github-runner.sh
#
# The runner label jayson-vps is shared with other Jayson apps on this host.
set -euo pipefail

RUNNER_VERSION="${RUNNER_VERSION:-2.323.0}"
RUNNER_HOME="${RUNNER_HOME:-/opt/github-runner/crm}"
RUNNER_LABELS="${RUNNER_LABELS:-self-hosted,linux,jayson-vps}"
RUNNER_NAME="${RUNNER_NAME:-jayson-vps-crm}"

if [ -z "${RUNNER_REGISTRATION_TOKEN:-}" ]; then
  echo "ERROR: set RUNNER_REGISTRATION_TOKEN from GitHub → Settings → Actions → Runners" >&2
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi

mkdir -p "$RUNNER_HOME"
cd "$RUNNER_HOME"

if [ ! -f ./config.sh ]; then
  curl -fsSL -o actions-runner.tar.gz \
    "https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/actions-runner-linux-x64-${RUNNER_VERSION}.tar.gz"
  tar xzf actions-runner.tar.gz
  rm actions-runner.tar.gz
fi

./config.sh uninstall --unattended || true
./config.sh \
  --url "https://github.com/jaysoncleofas/crm_ai" \
  --token "$RUNNER_REGISTRATION_TOKEN" \
  --name "$RUNNER_NAME" \
  --labels "$RUNNER_LABELS" \
  --work "_work" \
  --unattended \
  --replace

./svc.sh install
./svc.sh start
./svc.sh status

echo "Runner installed at $RUNNER_HOME (labels: $RUNNER_LABELS)"
