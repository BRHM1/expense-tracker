# Deployment Guide: Expense Tracker on Apache HTTP Server

This guide takes Expense Tracker from source code to a running site on **Apache HTTP Server**, with **PHP 8.3**
and **MySQL 8**. Use it on a fresh server. For a quick local run, see the [README](README.md).

```text
Browser ──HTTP──▶ Apache HTTP Server ──▶ PHP 8.3 (mod_php or PHP-FPM) ──PDO──▶ MySQL 8
                  :80 / :443                 /var/www/expense-tracker            :3306
```

## Contents

1. [Choose a deployment target](#1-choose-a-deployment-target)
2. [Requirements checklist](#2-requirements-checklist)
3. [Ubuntu 24.04 deployment (recommended)](#3-ubuntu-2404-deployment-recommended)
4. [Rocky Linux / RHEL 9 differences](#4-rocky-linux--rhel-9-differences)
5. [Windows deployment (Apache Lounge)](#5-windows-deployment-apache-lounge)
6. [Variant: MySQL in Docker](#6-variant-mysql-in-docker)
7. [Enable HTTPS](#7-enable-https)
8. [Post-deployment verification](#8-post-deployment-verification)
9. [Security hardening checklist](#9-security-hardening-checklist)
10. [Updating and rolling back](#10-updating-and-rolling-back)
11. [Backup and restore](#11-backup-and-restore)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Choose a deployment target

| Target | PHP handler | When to use |
|--------|-------------|-------------|
| Ubuntu 24.04 + mod_php | `libapache2-mod-php8.3` | Simplest setup. Good for a single small app |
| Ubuntu 24.04 + PHP-FPM | `php8.3-fpm` + `proxy_fcgi` | Better performance; lets Apache use `mpm_event` |
| Rocky / RHEL 9 | PHP-FPM (the distribution default) | Red Hat environments |
| Windows + Apache Lounge | `php8apache2_4.dll` | Windows servers or a local demo |

MySQL can run on the same host, on a separate host, or in a Docker container. Only `config/.env` changes.

## 2. Requirements checklist

**Software**

- [ ] Apache HTTP Server 2.4
- [ ] PHP 8.3 with `pdo_mysql`, `mbstring` and `session`
- [ ] MySQL 8.0.16 or newer

**Apache modules**

- [ ] PHP handler: `php8.3`, or `proxy_fcgi` + `setenvif`
- [ ] `dir`
- [ ] `authz_core`
- [ ] `env` (optional, for `SetEnv`)
- [ ] `headers` (optional, for security headers)
- [ ] `ssl` (for HTTPS)

**Network**

- [ ] Inbound 80/443 open to users
- [ ] 3306 reachable from the web server only

**Values to prepare**

| Value | Example |
|-------|---------|
| Server name | `expense-tracker.example.com` |
| Install path | `/var/www/expense-tracker` |
| DB host / port | `localhost` / `3306` |
| DB name | `expense_tracker` |
| DB user / password | `expense_user` / a strong random password |
| Timezone | `Africa/Cairo` |

---

## 3. Ubuntu 24.04 deployment (recommended)

Run every command as a user with `sudo`.

### Step 1: Install packages

```bash
sudo apt update
sudo apt install -y apache2 mysql-server git \
    php8.3 php8.3-cli php8.3-mysql php8.3-mbstring libapache2-mod-php8.3
```

Check the installation:

```bash
php -v                                  # PHP 8.3.x
php -m | grep -E 'pdo_mysql|mbstring'   # both listed
apache2 -v                              # Apache/2.4.x
mysql --version                         # 8.0.x
```

On **Ubuntu 22.04**, PHP 8.3 isn't in the default repositories. Add it first:

```bash
sudo add-apt-repository ppa:ondrej/php && sudo apt update
```

### Step 2: Enable Apache modules

```bash
sudo a2enmod php8.3 dir env headers
sudo systemctl restart apache2
apache2ctl -M | grep -E 'php|dir|env|headers'
```

### Step 3: Configure PHP

Edit `/etc/php/8.3/apache2/php.ini`. This is the Apache SAPI file, not the CLI one.

```ini
display_errors = Off
log_errors = On
error_reporting = E_ALL
date.timezone = Africa/Cairo
session.cookie_httponly = 1
session.use_strict_mode = 1
expose_php = Off
```

### Step 4: Secure MySQL and create the database

```bash
sudo mysql_secure_installation
```

Pick a strong password for the application user. Put it into the schema script, then import it:

```bash
git clone https://github.com/BRHM1/expense-tracker.git /tmp/expense-tracker
APP_DB_PASSWORD='change-me-strong-password'
sed "s/'your_password'/'${APP_DB_PASSWORD}'/" /tmp/expense-tracker/database/schema.sql | sudo mysql
```

The script:

- creates the `expense_tracker` database (`utf8mb4`)
- creates the `expenses` table
- inserts 7 sample rows
- creates `expense_user@localhost` with `SELECT, INSERT, UPDATE, DELETE` only

Check the result:

```bash
mysql -u expense_user -p -e "SELECT COUNT(*) FROM expense_tracker.expenses;"
```

### Step 5: Install the application

```bash
sudo mkdir -p /var/www/expense-tracker
sudo cp -r /tmp/expense-tracker/. /var/www/expense-tracker/
sudo rm -rf /var/www/expense-tracker/.git
```

### Step 6: Configure the database connection

```bash
sudo cp /var/www/expense-tracker/config/.env.example /var/www/expense-tracker/config/.env
sudo nano /var/www/expense-tracker/config/.env
```

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=expense_tracker
DB_USER=expense_user
DB_PASSWORD=change-me-strong-password
```

Instead of `config/.env`, you can use `SetEnv DB_* ...` lines in the vhost.
Environment variables take precedence over `config/.env`.

### Step 7: Set ownership and permissions

```bash
sudo chown -R root:www-data /var/www/expense-tracker
sudo find /var/www/expense-tracker -type d -exec chmod 755 {} \;
sudo find /var/www/expense-tracker -type f -exec chmod 644 {} \;
sudo chmod 640 /var/www/expense-tracker/config/.env
```

| Path | Owner:Group | Mode | Reason |
|------|-------------|------|--------|
| Directories | `root:www-data` | `755` | Apache can read and traverse, but not write |
| Files | `root:www-data` | `644` | Apache can read, but not write |
| `config/.env` | `root:www-data` | `640` | Credentials are hidden from other local users |

The application never writes to its own directory. Only PHP's session directory must be writable, and the
package sets that up.

### Step 8: Create the Virtual Host

```bash
sudo cp /var/www/expense-tracker/deploy/expense-tracker.conf /etc/apache2/sites-available/
sudo nano /etc/apache2/sites-available/expense-tracker.conf   # set ServerName
sudo a2ensite expense-tracker
sudo a2dissite 000-default
sudo apache2ctl configtest      # must print: Syntax OK
sudo systemctl reload apache2
```

[`deploy/expense-tracker.conf`](deploy/expense-tracker.conf) does the following:

- sets `DocumentRoot` to `/var/www/expense-tracker` and `DirectoryIndex index.php`
- turns off directory listing (`Options -Indexes`) and `.htaccess` processing (`AllowOverride None`)
- denies HTTP access to `config/`, `includes/`, `database/` and `deploy/`
- denies hidden files and setup files (`.env`, `.sql`, `.md`, `.conf`, …)
- adds security headers and per-site PHP flags
- writes separate access and error logs

### Step 9: Open the firewall

```bash
sudo ufw allow 'Apache Full'    # 80 and 443
sudo ufw status
```

Don't open 3306 to the internet.

### Step 10: Verify

Go to [Section 8](#8-post-deployment-verification).

### Optional: use PHP-FPM instead of mod_php

```bash
sudo apt install -y php8.3-fpm
sudo a2dismod php8.3 mpm_prefork
sudo a2enmod mpm_event proxy_fcgi setenvif
sudo a2enconf php8.3-fpm
sudo systemctl restart php8.3-fpm apache2
```

With FPM, put the Step 3 settings in `/etc/php/8.3/fpm/php.ini`. The `php_flag` lines in the vhost are
ignored, because they sit inside `<IfModule mod_php.c>`.

---

## 4. Rocky Linux / RHEL 9 differences

```bash
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y && sudo dnf module enable php:remi-8.3 -y
sudo dnf install -y httpd php php-cli php-fpm php-mysqlnd php-mbstring mysql-server git
sudo systemctl enable --now httpd php-fpm mysqld
```

| Item | Ubuntu | RHEL / Rocky |
|------|--------|--------------|
| Service | `apache2` | `httpd` |
| Vhost location | `/etc/apache2/sites-available/` + `a2ensite` | `/etc/httpd/conf.d/expense-tracker.conf` |
| Apache group | `www-data` | `apache` |
| Log directory | `${APACHE_LOG_DIR}` | `logs/` (that is, `/var/log/httpd/`) |
| Config test | `apache2ctl configtest` | `apachectl configtest` |
| Firewall | `ufw allow 'Apache Full'` | `firewall-cmd --permanent --add-service={http,https} && firewall-cmd --reload` |
| php.ini | `/etc/php/8.3/apache2/php.ini` | `/etc/php.ini` (FPM) |

SELinux is enforcing by default:

```bash
sudo restorecon -Rv /var/www/expense-tracker
sudo setsebool -P httpd_can_network_connect_db 1   # only if MySQL is remote or in a container
```

---

## 5. Windows deployment (Apache Lounge)

1. **PHP.** Run `winget install PHP.PHP.8.3`, or extract the *Thread Safe* x64 zip to `C:\php`.
   Copy `php.ini-production` to `php.ini`, then set:
   ```ini
   extension_dir = "ext"
   extension=pdo_mysql
   extension=mbstring
   date.timezone = Africa/Cairo
   ```
2. **Apache.** Extract the Apache Lounge 2.4 VS17 build to `C:\Apache24`.
   Install the Visual C++ Redistributable.
3. **Load PHP.** Add to `C:\Apache24\conf\httpd.conf`:
   ```apache
   LoadModule php_module "C:/php/php8apache2_4.dll"
   AddHandler application/x-httpd-php .php
   PHPIniDir "C:/php"
   DirectoryIndex index.php index.html
   Include conf/extra/expense-tracker.conf
   ```
4. **Copy the app.** Copy the project to `C:\Apache24\htdocs\expense-tracker`.
5. **Vhost.** Create `conf/extra/expense-tracker.conf` from `deploy/expense-tracker.conf`:
   - use Windows paths with forward slashes
   - write logs to `logs/...`
   - change `<IfModule mod_php.c>` to `<IfModule php_module>`
6. **Database config.** Create `config\.env` (see Step 6 above).
7. **Hosts file.** Add `127.0.0.1 expense-tracker.local` to `C:\Windows\System32\drivers\etc\hosts`.
8. **Service.** Install and start Apache from an elevated prompt:
   ```powershell
   C:\Apache24\bin\httpd.exe -t          # Syntax OK
   C:\Apache24\bin\httpd.exe -k install
   C:\Apache24\bin\httpd.exe -k start
   ```

---

## 6. Variant: MySQL in Docker

This variant fits development, or a host that runs MySQL only in containers.

```bash
docker run -d --name expense-tracker-mysql \
  -p 127.0.0.1:3307:3306 \
  -e MYSQL_ROOT_PASSWORD='<root_password>' \
  -v expense-tracker-mysql-data:/var/lib/mysql \
  --restart unless-stopped mysql:8.0

# Wait until it reports "mysqld is alive"
docker exec expense-tracker-mysql mysqladmin ping -uroot -p'<root_password>'
```

Connections from the host arrive through Docker's bridge network, not from `localhost`. So create the user
for host `'%'`. The published port is bound to `127.0.0.1` only.

```bash
sed -e "s/'expense_user'@'localhost'/'expense_user'@'%'/" \
    -e "s/'your_password'/'${APP_DB_PASSWORD}'/" database/schema.sql \
  | docker exec -i expense-tracker-mysql mysql -uroot -p'<root_password>'
```

Then use these values in `config/.env`:

```ini
DB_HOST=127.0.0.1
DB_PORT=3307
```

On RHEL with SELinux, also run `setsebool -P httpd_can_network_connect_db 1`.

---

## 7. Enable HTTPS

Do this on any server that is reachable from the internet. The domain must already point to the server.

```bash
sudo apt install -y certbot python3-certbot-apache
sudo a2enmod ssl
sudo certbot --apache -d expense-tracker.example.com
sudo certbot renew --dry-run
```

Certbot creates the `:443` vhost and the HTTP-to-HTTPS redirect. After switching to HTTPS, add this to
`php.ini` so session cookies are only sent over TLS:

```ini
session.cookie_secure = 1
```

---

## 8. Post-deployment verification

| # | Check | Command / action | Expected |
|---|-------|------------------|----------|
| 1 | Apache config | `sudo apache2ctl configtest` | `Syntax OK` |
| 2 | Correct vhost | `sudo apache2ctl -S` | `expense-tracker.example.com` → `expense-tracker.conf` |
| 3 | PHP is executed | `curl -s http://<host>/ \| head -5` | HTML, not `<?php` |
| 4 | PHP → MySQL | `curl -i http://<host>/health.php` | `HTTP 200`, `MySQL: connected`, `Status: OK` |
| 5 | Secrets blocked | `curl -I http://<host>/config/.env` | `403 Forbidden` |
| 6 | SQL blocked | `curl -I http://<host>/database/schema.sql` | `403 Forbidden` |
| 7 | No listing | `curl -I http://<host>/css/` | `403 Forbidden` |
| 8 | CLI DB check | see below | MySQL version printed |
| 9 | Functional | Add, edit and delete an expense in the browser | Green success message each time |
| 10 | Logs are clean | `sudo tail /var/log/apache2/expense-tracker-error.log` | No new errors |

Command for check 8:

```bash
cd /var/www/expense-tracker && sudo -u www-data php -r 'require "config/database.php"; echo getDb()->query("SELECT VERSION()")->fetchColumn(), PHP_EOL;'
```

Once verification passes, **remove or restrict `health.php`** on public servers, because it shows the PHP and
MySQL versions. To restrict it, add this to the vhost:

```apache
<Files "health.php">
    Require ip 127.0.0.1 10.0.0.0/8
</Files>
```

---

## 9. Security hardening checklist

**Database**

- [ ] The application DB user has only `SELECT, INSERT, UPDATE, DELETE` on `expense_tracker.*`
- [ ] The DB password is strong and unique, and isn't the `your_password` placeholder
- [ ] MySQL is not reachable from the internet (`bind-address`, firewall, or a container port bound to `127.0.0.1`)

**Files and Apache**

- [ ] `config/.env` has mode `640`, and `curl` returns 403 for it
- [ ] `AllowOverride None` and `Options -Indexes` are set in the vhost
- [ ] `config/`, `includes/`, `database/` and `deploy/` are denied over HTTP
- [ ] `000-default` is disabled, or doesn't serve the app directory

**PHP**

- [ ] `display_errors = Off` and `expose_php = Off`

**Network**

- [ ] HTTPS is enabled and `session.cookie_secure = 1`
- [ ] `health.php` is removed or restricted

**Maintenance**

- [ ] OS packages are kept up to date (`unattended-upgrades`)

What the application already does:

- prepared statements for all SQL
- output escaping
- CSRF tokens on every form
- server-side validation
- delete only through POST

---

## 10. Updating and rolling back

**Update**

```bash
cd /tmp && rm -rf expense-tracker && git clone https://github.com/BRHM1/expense-tracker.git
sudo cp -a /var/www/expense-tracker /var/www/expense-tracker.bak-$(date +%F)   # snapshot
sudo rsync -a --delete --exclude 'config/.env' --exclude '.git' /tmp/expense-tracker/ /var/www/expense-tracker/
sudo chown -R root:www-data /var/www/expense-tracker
sudo systemctl reload apache2
```

**Roll back**

```bash
sudo rm -rf /var/www/expense-tracker
sudo mv /var/www/expense-tracker.bak-YYYY-MM-DD /var/www/expense-tracker
sudo systemctl reload apache2
```

Don't re-run `database/schema.sql` against a live database. It inserts the sample rows again.

---

## 11. Backup and restore

**Back up** (for example, daily from cron):

```bash
mysqldump -u root -p --single-transaction --routines expense_tracker | gzip > expense_tracker-$(date +%F).sql.gz
```

**Restore:**

```bash
gunzip -c expense_tracker-YYYY-MM-DD.sql.gz | mysql -u root -p expense_tracker
```

**Docker variant:**

```bash
docker exec expense-tracker-mysql mysqldump -uroot -p'<root_password>' --single-transaction expense_tracker > backup.sql
```

Also keep a copy of `config/.env`. It is not in Git.

---

## 12. Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| PHP source shown or downloaded | PHP handler not loaded | `a2enmod php8.3` (or set up FPM), then restart Apache |
| 403 on `/` | No `DirectoryIndex`, or `Require all granted` missing | Check the vhost `<Directory>` block |
| Default "It works!" page | `000-default` answers first | `a2dissite 000-default`, check `apache2ctl -S` |
| `health.php` → `connection FAILED` | DB unreachable or wrong credentials | Read the exact error in the Apache error log |
| `could not find driver` | `pdo_mysql` missing for Apache's SAPI | `apt install php8.3-mysql`, restart Apache |
| `Access denied for user` | Wrong password, or the user's host doesn't match | `SELECT user,host FROM mysql.user;` (Docker needs `'%'`) |
| `Connection refused` / `No such file or directory` | Socket vs TCP mismatch, or MySQL is down | Try `DB_HOST=127.0.0.1`, `systemctl status mysql` |
| `Permission denied` (RHEL) | SELinux | `restorecon -Rv`, `setsebool -P httpd_can_network_connect_db 1` |
| "Your session expired" on every form | Session directory not writable | Check `session.save_path` |
| Wrong "current month" total | Timezone not set | `date.timezone` in Apache's `php.ini` |

Useful commands:

```bash
sudo tail -f /var/log/apache2/expense-tracker-error.log
sudo apache2ctl -S
apache2ctl -M
sudo systemctl status apache2 mysql
```
