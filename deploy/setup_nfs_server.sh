#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  setup_nfs_server.sh — Export uploads directory from VM4 (database node)
#  Run on VM4 to share uploads/ with web nodes via NFS
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

echo "=== NFS Server Setup (VM4) ==="

# 1. Install NFS server
apt-get update -qq
apt-get install -y -qq nfs-kernel-server

# 2. Create shared directory
mkdir -p /var/classexpress_uploads
chown nobody:nogroup /var/classexpress_exports
chmod 777 /var/classexpress_exports

# 3. Export (replace WEB_VM2_IP and WEB_VM3_IP with actual IPs)
cat >> /etc/exports << 'EXPORTS'
/var/classexpress_exports WEB_VM2_IP(rw,sync,no_subtree_check,no_root_squash)
/var/classexpress_exports WEB_VM3_IP(rw,sync,no_subtree_check,no_root_squash)
EXPORTS

# 4. Apply exports
exportfs -ra

# 5. Start NFS server
systemctl enable nfs-kernel-server
systemctl restart nfs-kernel-server

echo "=== NFS Server Setup Complete ==="
echo "Update /etc/exports with actual web node IPs, then run: exportfs -ra"
