#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  setup_web.sh — Provision VM2 / VM3 (Web/API Node)
#  Run as root on Ubuntu 22.04+ / Debian 12+
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

echo "=== ClassExpress Web Node Setup ==="

# 1. System packages
apt-get update -qq
apt-get install -y -qq apache2 php8.0 php8.0-fpm php8.0-mysql php8.0-redis \
    php8.0-curl php8.0-mbstring php8.0-xml php8.0-gd php8.0-zip \
    php8.0-intl php8.0-bcmath git curl ufw

# 2. Firewall
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# 3. Create app directory
mkdir -p /var/www/classexpress
chown -R www-data:www-data /var/www/classexpress

# 4. PHP-FPM config
cp /tmp/deploy/php-fpm.conf /etc/php/8.0/fpm/pool.d/classexpress.conf
systemctl restart php8.0-fpm
systemctl enable php8.0-fpm

# 5. Apache config (reverse proxy for local use if no LB)
cat > /etc/apache2/sites-available/classexpress.conf << 'APACHE'
<VirtualHost *:80>
    ServerName classexpress.online
    DocumentRoot /var/www/classexpress

    <Directory /var/www/classexpress>
        AllowOverride All
        Require all granted
    </Directory>

    # Proxy to PHP-FPM via socket
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.0-fpm-classexpress.sock|fcgi://localhost"
    </FilesMatch>

    # Security
    ServerSignature Off
    TraceEnable Off

    # Timeouts
    ProxyTimeout 60
</VirtualHost>
APACHE

a2ensite classexpress.conf
a2dissite 000-default.conf
a2enmod proxy_fcgi rewrite headers
systemctl restart apache2
systemctl enable apache2

# 6. PHP production settings
sed -i 's/display_errors = On/display_errors = Off/' /etc/php/8.0/fpm/php.ini
sed -i 's/display_startup_errors = On/display_startup_errors = Off/' /etc/php/8.0/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/' /etc/php/8.0/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 12M/' /etc/php/8.0/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 60/' /etc/php/8.0/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 256M/' /etc/php/8.0/fpm/php.ini

# 7. Create uploads directory
mkdir -p /var/www/classexpress/uploads/avatars
chown -R www-data:www-data /var/www/classexpress/uploads

# 8. Deploy code (if rsync from local machine)
# scp -r C:\xampp\htdocs\CCC\* root@THIS_VM:/var/www/classexpress/
# OR
# rsync -avz --exclude='.env' /local/CCC/ root@THIS_VM:/var/www/classexpress/

# 9. Set permissions
chown -R www-data:www-data /var/www/classexpress
find /var/www/classexpress -type d -exec chmod 755 {} \;
find /var/www/classexpress -type f -exec chmod 644 {} \;

echo "=== Web Node Setup Complete ==="
echo "Next: Copy .env to /var/www/classexpress/.env"
echo "Next: Sync code from dev machine"
