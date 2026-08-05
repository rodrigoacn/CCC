#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
#  setup_db.sh — Provision VM4 (MySQL + Redis)
#  Run as root on Ubuntu 22.04+ / Debian 12+
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

echo "=== ClassExpress Database Node Setup ==="

# 1. System packages
apt-get update -qq
apt-get install -y -qq mysql-server redis-server ufw

# 2. Firewall
ufw allow 22/tcp
ufw allow 3306/tcp    # MySQL (from web nodes only)
ufw allow 6379/tcp    # Redis (from web nodes only)
ufw --force enable

# 3. MySQL setup
systemctl enable mysql
systemctl start mysql

# Create database and user
mysql -u root << SQL
CREATE DATABASE IF NOT EXISTS classexpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'classexpress_app'@'%' IDENTIFIED BY 'CHANGE_ME_STRONG_DB_PASSWORD';
GRANT ALL PRIVILEGES ON classexpress.* TO 'classexpress_app'@'%';
FLUSH PRIVILEGES;
SQL

# Import existing database (if migrating)
# mysql -u root classexpress < /tmp/classexpress_backup.sql

# MySQL tuning for high-connection workload
cat >> /etc/mysql/mysql.conf.d/classexpress.cnf << 'MYSQL'
[mysqld]
# Connection pool
max_connections = 200
wait_timeout = 600
interactive_timeout = 600

# InnoDB tuning
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache (MySQL 5.7 only; removed in 8.0)
# query_cache_type = 1
# query_cache_size = 64M

# Slow query log (for debugging)
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
MYSQL

systemctl restart mysql

# 4. Redis setup
cp /tmp/deploy/redis.conf /etc/redis/classexpress.conf
chown redis:redis /etc/redis/classexpress.conf

# Create systemd override for Redis
mkdir -p /etc/systemd/system/redis-server@classexpress.service.d
cat > /etc/systemd/system/redis-server@classexpress.service.d/override.conf << 'SYSTEMD'
[Service]
ExecStart=
ExecStart=/usr/bin/redis-server /etc/redis/classexpress.conf
SYSTEMD

systemctl daemon-reload
systemctl enable redis-server@classexpress
systemctl start redis-server@classexpress

# 5. Create backup directory
mkdir -p /var/backups/classexpress
chmod 700 /var/backups/classexpress

# 6. Automated MySQL backup cron (daily at 3am)
cat > /etc/cron.d/classexpress-backup << 'CRON'
0 3 * * * root mysqldump -u root classexpress | gzip > /var/backups/classexpress/db_$(date +\%Y\%m\%d).sql.gz
0 3 * * * root find /var/backups/classexpress -name "*.sql.gz" -mtime +30 -delete
CRON

echo "=== Database Node Setup Complete ==="
echo "Next: Update MySQL root password and classexpress_app password"
echo "Next: Import existing database if migrating"
echo "Next: Update /etc/redis/classexpress.conf with strong password"
