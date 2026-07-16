<?php

namespace App\Http\Controllers;

/**
 * Emits the one-command node installer:
 *   curl -fsSL <master>/install/node.sh | sudo bash -s -- --master <url> --token <t>
 *
 * The node it sets up: pulls the full valid-license set from this panel on a
 * timer (full replication), caches it locally, and runs a tiny local validation
 * API that verifies license signatures offline against the panel's public key.
 */
class NodeInstallerController extends Controller
{
    public function script()
    {
        $master = rtrim(config('app.url'), '/');
        $brand = config('brand.name', 'LicenseManager');
        $script = $this->render($master, $brand);

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function render(string $master, string $brand): string
    {
        // heredoc: $VARS expand here; \$VARS are literal (run on the node).
        return <<<SH
#!/usr/bin/env bash
# {$brand} — license verification node installer
set -euo pipefail

MASTER="{$master}"
TOKEN=""
PORT="8787"
INTERVAL="2min"
DIR="/opt/licensenode"

while [ \$# -gt 0 ]; do
  case "\$1" in
    --master) MASTER="\$2"; shift 2;;
    --token)  TOKEN="\$2"; shift 2;;
    --port)   PORT="\$2"; shift 2;;
    *) echo "Unknown option: \$1"; exit 1;;
  esac
done

if [ -z "\$TOKEN" ]; then echo "ERROR: --token <enrollment-token> is required"; exit 1; fi
if [ "\$(id -u)" != "0" ]; then echo "ERROR: run as root (sudo)"; exit 1; fi

command -v curl >/dev/null || { echo "Installing curl..."; (apt-get update -y && apt-get install -y curl) || yum install -y curl; }
command -v php  >/dev/null || { echo "Installing php-cli..."; (apt-get install -y php-cli) || yum install -y php-cli; }

echo "==> Installing {$brand} node into \$DIR"
mkdir -p "\$DIR"

cat > "\$DIR/config.env" <<CFG
MASTER=\$MASTER
TOKEN=\$TOKEN
PORT=\$PORT
CFG
chmod 600 "\$DIR/config.env"

# --- sync.sh: pull the full valid-license set + public key from the panel ---
cat > "\$DIR/sync.sh" <<'SYNC'
#!/usr/bin/env bash
set -euo pipefail
DIR="/opt/licensenode"; source "\$DIR/config.env"
tmp="\$(mktemp)"
code=\$(curl -fsS -o "\$tmp" -w '%{http_code}' -H "Authorization: Bearer \$TOKEN" "\$MASTER/api/node/v1/sync" || echo 000)
if [ "\$code" = "200" ]; then
  mv "\$tmp" "\$DIR/licenses.json"
  php -r '\$d=json_decode(file_get_contents("/opt/licensenode/licenses.json"),true); file_put_contents("/opt/licensenode/public.pem", \$d["public_key"]??"");'
  cnt=\$(php -r '\$d=json_decode(file_get_contents("/opt/licensenode/licenses.json"),true); echo (int)(\$d["count"]??0);')
  curl -fsS -X POST -H "Authorization: Bearer \$TOKEN" -d "license_count=\$cnt" "\$MASTER/api/node/v1/heartbeat" >/dev/null || true
  echo "synced \$cnt licenses"
else
  rm -f "\$tmp"; echo "sync failed (HTTP \$code)"; exit 1
fi
SYNC
chmod +x "\$DIR/sync.sh"

# --- validate.php: tiny local validation API (offline, signature-verified) ---
cat > "\$DIR/validate.php" <<'PHP'
<?php
// Local verification node. GET /health ; POST /validate {"key":"..."}
\$DIR = '/opt/licensenode';
\$uri = parse_url(\$_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
header('Content-Type: application/json');

if (\$uri === '/health') {
    \$d = @json_decode(@file_get_contents("\$DIR/licenses.json"), true) ?: [];
    echo json_encode(['ok' => true, 'count' => count(\$d['licenses'] ?? []), 'synced_at' => \$d['synced_at'] ?? null]);
    exit;
}
if (\$uri !== '/validate') { http_response_code(404); echo json_encode(['error' => 'not_found']); exit; }

\$in  = json_decode(file_get_contents('php://input'), true) ?: [];
\$key = \$_POST['key'] ?? (\$in['key'] ?? '');
\$data = @json_decode(@file_get_contents("\$DIR/licenses.json"), true) ?: ['licenses' => []];
\$pub = @file_get_contents("\$DIR/public.pem") ?: (\$data['public_key'] ?? '');

foreach (\$data['licenses'] as \$row) {
    if ((\$row['license']['key'] ?? null) !== \$key) continue;
    \$payload = \$row['license']; ksort(\$payload);
    \$canonical = json_encode(\$payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    \$ok = openssl_verify(\$canonical, base64_decode(\$row['signature'] ?? ''), \$pub, OPENSSL_ALGO_SHA256) === 1;
    echo json_encode(['valid' => \$ok && (\$row['license']['status'] ?? '') === 'active', 'license' => \$row['license'], 'verified_offline' => \$ok]);
    exit;
}
echo json_encode(['valid' => false, 'reason' => 'not_found']);
PHP

# --- systemd: periodic sync + always-on validation API ---
cat > /etc/systemd/system/licensenode-sync.service <<UNIT
[Unit]
Description={$brand} node license sync
[Service]
Type=oneshot
ExecStart=\$DIR/sync.sh
UNIT

cat > /etc/systemd/system/licensenode-sync.timer <<UNIT
[Unit]
Description=Run {$brand} node sync every \$INTERVAL
[Timer]
OnBootSec=30
OnUnitActiveSec=\$INTERVAL
[Install]
WantedBy=timers.target
UNIT

cat > /etc/systemd/system/licensenode-api.service <<UNIT
[Unit]
Description={$brand} node validation API
After=network.target
[Service]
ExecStart=/usr/bin/php -S 0.0.0.0:\$PORT \$DIR/validate.php
Restart=always
[Install]
WantedBy=multi-user.target
UNIT

echo "==> Initial sync"
"\$DIR/sync.sh" || echo "(will retry on timer)"

systemctl daemon-reload
systemctl enable --now licensenode-sync.timer licensenode-api.service

echo ""
echo "==> {$brand} node ready."
echo "    Validate:  curl -s -X POST http://<this-host>:\$PORT/validate -d '{\"key\":\"YOUR-KEY\"}'"
echo "    Health:    curl -s http://<this-host>:\$PORT/health"
SH;
    }
}
