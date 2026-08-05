#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  setup_lb.sh — Provision VM1 (Nginx Load Balancer + SSL)
#  Run as root on Ubuntu 22.04+ / Debian 12+
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

echo "=== ClassExpress Load Balancer Setup ==="

# 1. System packages
apt-get update -qq
apt-get install -y -qq nginx certbot python3-certbot-nginx ufw

# 2. Firewall
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# 3. Nginx config
cp /tmp/deploy/nginx.conf /etc/nginx/sites-available/classexpress
ln -sf /etc/nginx/sites-available/classexpress /etc/nginx/sites-enabled/classexpress
rm -f /etc/nginx/sites-enabled/default

# Validate config
nginx -t

# 4. SSL certificate (Let's Encrypt)
# NOTE: DNS must point to this VM's IP before running certbot
certbot --nginx -d classexpress.online -d www.classexpress.online \
    --non-interactive --agree-tos --email admin@classexpress.online

# Auto-renew cron
echo "0 0,12 * * * root certbot renew --quiet --post-hook 'systemctl reload nginx'" > /etc/cron.d/certbot-renew

# 5. Copy static files for direct serving
mkdir -p /var/www/classexpress
# These are served directly by Nginx (not proxied)
# Copy from dev machine:
# scp C:\xampp\htdocs\CCC\styles.css root@THIS_VM:/var/www/classexpress/
# scp C:\xampp\htdocs\CCC\favico.svg root@THIS_VM:/var/www/classexpress/

# 6. Systemd reload
systemctl reload nginx
systemctl enable nginx

echo "=== Load Balancer Setup Complete ==="
echo ""
echo "NEXT STEPS:"
echo "1. Update nginx.conf with actual VM2/VM3 IPs"
echo "2. Point DNS A record for classexpress.online to this VM's public IP"
echo "3. Run certbot after DNS propagates"
echo "4. Test: curl -I https://classexpress.online"
