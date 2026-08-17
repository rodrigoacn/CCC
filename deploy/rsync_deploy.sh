#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  rsync_deploy.sh — Sync code from dev machine to web nodes
#  Run from your LOCAL dev machine (Windows/Mac/Linux with WSL)
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Configuration ────────────────────────────────────────────────────────────
WEB_NODES=("VM2_USER@VM2_IP" "VM3_USER@VM3_IP")
REMOTE_DIR="/var/www/classexpress"
LOCAL_DIR="$(pwd)"

# Directories to exclude from sync
EXCLUDES=(
    ".git/"
    ".env"
    "deploy/"
    "node_modules/"
    "vendor/"
    "composer.lock"
    "tests/"
    ".agents/"
    ".aider*"
    ".devin/"
    ".replit"
    "scriptspy.py"
    "app.js"
    "*.pyc"
    "__pycache__/"
    "*.exe"
)

echo "=== ClassExpress Deploy ==="
echo "Local:  $LOCAL_DIR"
echo "Remote: ${WEB_NODES[0]}, ${WEB_NODES[1]}"
echo ""

# Build exclude flags
EXCLUDE_FLAGS=""
for ex in "${EXCLUDES[@]}"; do
    EXCLUDE_FLAGS="$EXCLUDE_FLAGS --exclude=$ex"
done

# Sync to each web node
for node in "${WEB_NODES[@]}"; do
    echo "--- Deploying to $node ---"
    rsync -avz --delete \
        $EXCLUDE_FLAGS \
        "$LOCAL_DIR/" \
        "$node:$REMOTE_DIR/"

    # Set permissions on remote
    ssh "$node" << 'REMOTE'
        chown -R www-data:www-data /var/www/classexpress
        find /var/www/classexpress -type d -exec chmod 755 {} \;
        find /var/www/classexpress -type f -exec chmod 644 {} \;
        # Keep the Go binaries executable (chmod 644 above strips the +x bit)
        chmod 755 /var/www/classexpress/go/bin/server /var/www/classexpress/go/bin/cron
        # Restart PHP-FPM to clear opcache
        systemctl reload php8.0-fpm 2>/dev/null || true
REMOTE

    echo "--- Done: $node ---"
    echo ""
done

echo "=== Deploy Complete ==="
