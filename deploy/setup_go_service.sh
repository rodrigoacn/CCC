#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  setup_go_service.sh — Install/enable the Go server + cron systemd units
#  Run as root on each web node (VM2/VM3) AFTER deploying the Go code.
#
#  Self-contained: writes the systemd units itself, so it does not depend on
#  files under deploy/ (which rsync_deploy.sh excludes).
#
#  Requires: rsync_deploy.sh already pushed go/bin/server + go/bin/cron
#  (Linux builds) and /var/www/classexpress/.env is in place.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SYSTEMD_DIR="/etc/systemd/system"

echo "=== ClassExpress Go service setup ==="

if [ ! -x /var/www/classexpress/go/bin/server ]; then
    echo "ERROR: /var/www/classexpress/go/bin/server not found or not executable." >&2
    echo "Build Linux binaries first and re-run rsync_deploy.sh." >&2
    exit 1
fi

# ── 1. Write systemd units ───────────────────────────────────────────────
cat > "$SYSTEMD_DIR/classexpress-server.service" << 'UNIT'
[Unit]
Description=ClassExpress Go web server (web + mobile API + WebSocket sala)
After=network-online.target mysql.service redis-server.service
Wants=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/classexpress/go
ExecStart=/var/www/classexpress/go/bin/server
Restart=on-failure
RestartSec=3
TimeoutStopSec=15

# Security hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/www/classexpress/uploads
ProtectKernelTunables=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true

# Logging (journald)
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
UNIT

cat > "$SYSTEMD_DIR/classexpress-cron.service" << 'UNIT'
[Unit]
Description=ClassExpress hourly cleanup (go/bin/cron)

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/classexpress/go
ExecStart=/var/www/classexpress/go/bin/cron
TimeoutStartSec=120
UNIT

cat > "$SYSTEMD_DIR/classexpress-cron.timer" << 'UNIT'
[Unit]
Description=ClassExpress hourly cleanup timer

[Timer]
OnCalendar=hourly
Persistent=true
RandomizedDelaySec=300

[Install]
WantedBy=timers.target
UNIT

# ── 2. Reload and enable ─────────────────────────────────────────────────
systemctl daemon-reload

systemctl enable --now classexpress-server.service
systemctl enable --now classexpress-cron.timer

# ── 3. Status summary ────────────────────────────────────────────────────
systemctl --no-pager status classexpress-server.service || true
systemctl --no-pager status classexpress-cron.timer || true

echo "=== Go service setup complete ==="
echo "Server health: curl -s http://127.0.0.1:8080/health"
echo "Cron next run: systemctl list-timers classexpress-cron.timer"
