#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  setup_nfs.sh — Mount shared uploads via NFS on web nodes
#  Run on VM2/VM3 (web nodes) to mount VM4's uploads directory
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

NFS_SERVER="${1:-VM4_IP}"
REMOTE_PATH="/var/backups/classexpress/uploads"
LOCAL_PATH="/var/www/classexpress/uploads"

echo "=== NFS Mount Setup ==="
echo "Server: $NFS_SERVER"
echo "Remote: $REMOTE_PATH"
echo "Local:  $LOCAL_PATH"

# 1. Install NFS client
apt-get update -qq
apt-get install -y -qq nfs-common

# 2. Create mount point
mkdir -p "$LOCAL_PATH"

# 3. Mount
mount -t nfs4 "$NFS_SERVER:$REMOTE_PATH" "$LOCAL_PATH"

# 4. Add to fstab for persistence
grep -q "$NFS_SERVER:$REMOTE_PATH" /etc/fstab || \
    echo "$NFS_SERVER:$REMOTE_PATH $LOCAL_PATH nfs4 defaults,_netdev 0 0" >> /etc/fstab

# 5. Verify
df -h "$LOCAL_PATH"
echo "=== NFS Mount Complete ==="
