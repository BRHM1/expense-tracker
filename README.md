# Expense Tracker

A small PHP application to record and manage expenses. It is meant to show a basic
**PHP → MySQL → Apache HTTP Server** deployment with nothing else in the way:
no framework, no authentication, no Docker.

```text
Browser
   ↓  HTTP
Apache HTTP Server   (mod_php or PHP-FPM)
   ↓
PHP Application      (plain PHP 8.3 + PDO)
   ↓  TCP 3306 / socket
MySQL 8              (database: expense_tracker)
```

## Features

| Page | File | What it does |
|------|------|--------------|
| Dashboard | `index.php` | Total number of expenses, total spent, spending this month, 5 most recent expenses |
| Expenses | `expenses.php` | Table of all expenses with Edit / Delete buttons |
| Add | `add.php` | Form with server-side and Bootstrap client-side validation |
| Edit | `edit.php` | Same form, pre-filled |
| Delete | `delete.php` | Confirmation page; the delete itself is a POST with a CSRF token |
| Health check | `health.php` | Plain-text PHP → MySQL connectivity check |

All SQL goes through **PDO prepared statements**. Database credentials live only in
`config/.env` (or in Apache environment variables), never in the PHP code.

## Project structure

```text
expense-tracker/
├── index.php              Dashboard
├── expenses.php           Expense list
├── add.php                Add expense
├── edit.php               Edit expense
├── delete.php             Delete confirmation + delete
├── health.php             Connectivity check
├── .htaccess              Fallback protection if AllowOverride is enabled
├── config/
│   ├── database.php       Reads config, creates the PDO connection
│   ├── .env.example       Template for credentials (copy to .env)
│   └── .htaccess          Deny all
├── includes/
│   ├── functions.php      Helpers: escaping, flash messages, CSRF, validation
│   ├── header.php         Bootstrap layout + navbar + flash messages
│   ├── footer.php
│   └── expense_form.php   Shared Add/Edit form
├── css/
│   └── style.css
├── database/
│   └── schema.sql         Creates DB, table, sample data, app user
├── deploy/
│   └── expense-tracker.conf   Apache Virtual Host example
├── README.md
└── DEPLOYMENT.md          Step-by-step Apache deployment guide
```

---

## 1. Prerequisites

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.3 | Extensions: `pdo`, `pdo_mysql`, `mbstring`, `session` |
| MySQL | 8.0.16+ | 8.0.16+ is needed for the `CHECK` constraint |
| Apache HTTP Server | 2.4 | Modules: see below |
| Browser | any modern | Bootstrap 5 is loaded from the jsDelivr CDN |

### Required PHP extensions

| Extension | Why | Ubuntu package |
|-----------|-----|----------------|
| `pdo` + `pdo_mysql` | Database access | `php8.3-mysql` |
| `mbstring` | `mb_strlen()` in validation | `php8.3-mbstring` |
| `session` | Flash messages, CSRF token | built in |
| `ctype`, `filter` | Input handling | built in |

### Required Apache modules

| Module | Why |
|--------|-----|
| `mod_php` (`php8.3`) **or** `proxy_fcgi` + `setenvif` | Runs PHP |
| `mod_dir` | `DirectoryIndex index.php` |
| `mod_authz_core` / `mod_authz_host` | `Require all denied` for internal folders |
| `mod_env` | Optional, for `SetEnv DB_*` |
| `mod_headers` | Optional, for security headers |

## 2. PHP installation

**Ubuntu 22.04 / 24.04** (PHP 8.3 is the default on 24.04; on 22.04, add `ppa:ondrej/php` first):

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-mysql php8.3-mbstring libapache2-mod-php8.3
php -v
php -m | grep -E 'pdo_mysql|mbstring'
```

**Rocky / RHEL 9:**

```bash
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y && sudo dnf module enable php:remi-8.3 -y
sudo dnf install -y php php-cli php-mysqlnd php-mbstring
```

**Windows:** download the *Thread Safe* x64 zip of PHP 8.3 from <https://windows.php.net/download>,
extract it to `C:\php`, copy `php.ini-production` to `php.ini`, and enable:

```ini
extension_dir = "ext"
extension=pdo_mysql
extension=mbstring
```

### PHP configuration requirements (`php.ini`)

| Setting | Recommended value | Why |
|---------|-------------------|-----|
| `display_errors` | `Off` (production), `On` (development) | Don't leak errors to users |
| `log_errors` | `On` | Errors go to the Apache/PHP error log |
| `error_reporting` | `E_ALL` | |
| `date.timezone` | e.g. `UTC` or `Africa/Cairo` | Decides what "current month" means |
| `session.cookie_httponly` | `1` | |
| `session.use_strict_mode` | `1` | |
| `session.save_path` | writable by the Apache user | Sessions are used for flash messages and CSRF |

Apache reads the `php.ini` for its own SAPI. On Ubuntu that is `/etc/php/8.3/apache2/php.ini`
(mod_php) or `/etc/php/8.3/fpm/php.ini` (FPM), not the CLI one.

## 3. Apache installation and configuration

**Ubuntu:**

```bash
sudo apt install -y apache2
sudo a2enmod php8.3 dir env headers   # mod_php
sudo systemctl enable --now apache2
```

If you use **PHP-FPM** instead of mod_php:

```bash
sudo apt install -y php8.3-fpm
sudo a2dismod php8.3 mpm_prefork
sudo a2enmod mpm_event proxy_fcgi setenvif
sudo a2enconf php8.3-fpm
sudo systemctl restart php8.3-fpm apache2
```

**Rocky / RHEL:** `sudo dnf install -y httpd && sudo systemctl enable --now httpd`
(PHP runs through PHP-FPM by default there).

**Windows:** install Apache 2.4 from <https://www.apachelounge.com/download/> into `C:\Apache24`, then add to
`C:\Apache24\conf\httpd.conf`:

```apache
LoadModule php_module "C:/php/php8apache2_4.dll"
AddHandler application/x-httpd-php .php
PHPIniDir "C:/php"
DirectoryIndex index.php index.html
```

### Virtual Host

A full example is in [`deploy/expense-tracker.conf`](deploy/expense-tracker.conf). The parts that matter:

```apache
<VirtualHost *:80>
    ServerName expense-tracker.local
    DocumentRoot /var/www/expense-tracker
    DirectoryIndex index.php

    <Directory /var/www/expense-tracker>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    <DirectoryMatch "^/var/www/expense-tracker/(config|includes|database|deploy)">
        Require all denied
    </DirectoryMatch>

    <FilesMatch "^\.|\.(sql|ini|log|bak|example|md|conf)$">
        Require all denied
    </FilesMatch>

    ErrorLog  ${APACHE_LOG_DIR}/expense-tracker-error.log
    CustomLog ${APACHE_LOG_DIR}/expense-tracker-access.log combined
</VirtualHost>
```

`${APACHE_LOG_DIR}` exists on Debian/Ubuntu only. On RHEL or Windows, use `logs/expense-tracker-error.log`.

The project also includes `.htaccess` files with the same deny rules. They only take effect if you set
`AllowOverride All`. The vhost above doesn't need them.

## 4. MySQL database setup

Run the schema script as a MySQL admin user. It creates the `expense_tracker` database, the `expenses`
table, 7 sample expenses, and an `expense_user` account limited to `SELECT, INSERT, UPDATE, DELETE`.

**Change `your_password` in `database/schema.sql` before running it.**

```bash
mysql -u root -p < database/schema.sql
```

Windows (PowerShell):

```powershell
Get-Content database\schema.sql | & "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p
```

Table definition:

```sql
CREATE TABLE expenses (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    description  VARCHAR(255)  NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,          -- CHECK (amount > 0)
    category     ENUM('Food','Transportation','Shopping','Bills','Other') NOT NULL,
    expense_date DATE          NOT NULL
);
```

Running the script a second time adds the sample rows again. To start over, run
`DROP DATABASE expense_tracker;` first.

### Alternative: MySQL in a Docker container (local development)

```powershell
docker run -d --name expense-tracker-mysql -p 127.0.0.1:3307:3306 `
  -e MYSQL_ROOT_PASSWORD=<root_password> `
  -v expense-tracker-mysql-data:/var/lib/mysql --restart unless-stopped mysql:8.0
```

Connections from the host reach the container through Docker's network, not from `localhost`. Before
importing, change `'expense_user'@'localhost'` to `'expense_user'@'%'` in the schema, then run:

```powershell
Get-Content database\schema.sql | docker exec -i expense-tracker-mysql mysql -uroot -p<root_password>
```

In `config/.env`, use `DB_HOST=127.0.0.1` and `DB_PORT=3307`. Port 3307 avoids a clash with a local MySQL on 3306.

### MySQL configuration notes

- **Same server as Apache:** use `DB_HOST=localhost`. MySQL's default settings work as they are.
- **Separate DB server:** set `bind-address = 0.0.0.0` (or the server's private IP) in `mysqld.cnf` / `my.ini`,
  open port 3306 only to the web server, and create the user for the web server's host, not `localhost`:
  ```sql
  CREATE USER 'expense_user'@'10.0.0.5' IDENTIFIED BY '...';
  GRANT SELECT, INSERT, UPDATE, DELETE ON expense_tracker.* TO 'expense_user'@'10.0.0.5';
  ```
- The app connects with `charset=utf8mb4`. The database is created with `utf8mb4_unicode_ci`.
- `localhost` makes PHP use the Unix socket. `127.0.0.1` forces TCP. If one doesn't work, try the other.

## 5. Application configuration

Credentials are read by `config/database.php`, in this order:

1. **Environment variables** `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   (for example `SetEnv` lines in the vhost)
2. **`config/.env`**

```bash
cp config/.env.example config/.env
nano config/.env
```

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=expense_tracker
DB_USER=expense_user
DB_PASSWORD=your_password
```

`config/.env` is listed in `.gitignore` and Apache blocks HTTP requests for it.

Also set `date.timezone` in `php.ini`. The dashboard's "current month" figure depends on it.

## 6. Run locally (without Apache)

PHP's built-in server is enough for a quick test:

```bash
cd expense-tracker
php -S localhost:8000
```

Open <http://localhost:8000>.

The built-in server ignores `.htaccess`. Use it for development only.

## 7. Deploy to Apache

> **Full guide:** [DEPLOYMENT.md](DEPLOYMENT.md) covers Ubuntu, RHEL, Windows, MySQL in Docker, HTTPS,
> a verification checklist, hardening, updates and rollback, and backups.

Ubuntu, quick version:

```bash
# 1. Copy the code
sudo mkdir -p /var/www/expense-tracker
sudo cp -r expense-tracker/. /var/www/expense-tracker/

# 2. Configure credentials
sudo cp /var/www/expense-tracker/config/.env.example /var/www/expense-tracker/config/.env
sudo nano /var/www/expense-tracker/config/.env

# 3. Directory permissions: root owns the code, Apache can only read it
sudo chown -R root:www-data /var/www/expense-tracker
sudo find /var/www/expense-tracker -type d -exec chmod 755 {} \;
sudo find /var/www/expense-tracker -type f -exec chmod 644 {} \;
sudo chmod 640 /var/www/expense-tracker/config/.env    # secret: readable by www-data, not by others

# 4. Database (if not done yet)
mysql -u root -p < /var/www/expense-tracker/database/schema.sql

# 5. Enable the site
sudo cp /var/www/expense-tracker/deploy/expense-tracker.conf /etc/apache2/sites-available/
sudo a2ensite expense-tracker
sudo a2dissite 000-default          # optional
sudo apache2ctl configtest          # must print "Syntax OK"
sudo systemctl reload apache2

# 6. Local name resolution (for testing)
echo "127.0.0.1 expense-tracker.local" | sudo tee -a /etc/hosts
```

Open <http://expense-tracker.local>.

### Directory permissions summary

| Path | Owner:Group | Mode | Reason |
|------|-------------|------|--------|
| All directories | `root:www-data` | `755` | Apache can traverse but not modify |
| All files | `root:www-data` | `644` | Apache can read but not modify |
| `config/.env` | `root:www-data` | `640` | Only root and Apache can read the credentials |

The app never writes to its own directory. Only PHP's session directory needs to be writable,
and the PHP package sets that up. On RHEL the group is `apache`, not `www-data`. With SELinux enforcing, also run:

```bash
sudo restorecon -Rv /var/www/expense-tracker
sudo setsebool -P httpd_can_network_connect_db 1   # only if MySQL is on another host
```

### Windows (Apache Lounge)

1. Copy `expense-tracker` to `C:\Apache24\htdocs\expense-tracker`.
2. Create `config\.env`.
3. Adapt `deploy/expense-tracker.conf`: use Windows paths, use `logs/...` for log files, and remove the
   `<IfModule mod_php.c>` block or point it at `php_module`. Include it from `httpd.conf`.
4. Add `127.0.0.1 expense-tracker.local` to `C:\Windows\System32\drivers\etc\hosts`.
5. Run `C:\Apache24\bin\httpd.exe -t`, then `httpd.exe -k restart`.

## 8. Verify PHP → MySQL connectivity

**From the browser:** open <http://expense-tracker.local/health.php>:

```text
PHP version:   8.3.x
Server API:    apache2handler
pdo_mysql:     loaded
MySQL:         connected (server 8.0.x)
Expenses rows: 7
Status:        OK
```

It returns HTTP 200 on success and 500 on failure, so you can use it for monitoring:

```bash
curl -i http://expense-tracker.local/health.php
```

`health.php` reveals the PHP and MySQL versions. On a public server, delete it after verification or restrict it
with `<Files health.php> Require ip 127.0.0.1 </Files>`.

**From the command line** (tests the same credentials):

```bash
cd /var/www/expense-tracker
php -r 'require "config/database.php"; echo getDb()->query("SELECT VERSION()")->fetchColumn(), PHP_EOL;'
mysql -u expense_user -p -h localhost expense_tracker -e "SELECT COUNT(*) FROM expenses;"
```

**Functional check:** the dashboard shows non-zero totals, and adding, editing and deleting an expense each
show a green success message.

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Browser shows raw PHP source | PHP isn't wired into Apache | `a2enmod php8.3` (or set up FPM), then restart Apache |
| Browser downloads `index.php` | Same as above | Same as above |
| `403 Forbidden` on `/` | Missing `DirectoryIndex index.php`, or `Require all granted` absent | Check the vhost `<Directory>` block |
| `404` on every page | Wrong `DocumentRoot`, or the default site is answering | `apache2ctl -S`, `a2dissite 000-default` |
| "Could not load data from the database" | Wrong credentials, or MySQL is down | Check `health.php` and the Apache error log |
| Log: `could not find driver` | `pdo_mysql` not installed or enabled | `apt install php8.3-mysql`, then restart Apache (`php -m` only checks the CLI) |
| Log: `Access denied for user` | Password mismatch or wrong host in the grant | Compare `config/.env` with `CREATE USER`, check `SELECT user,host FROM mysql.user;` |
| Log: `Unknown database 'expense_tracker'` | Schema not imported | `mysql -u root -p < database/schema.sql` |
| Log: `No such file or directory` / `Connection refused` | `localhost` socket vs TCP mismatch, or MySQL stopped | Try `DB_HOST=127.0.0.1`, `systemctl status mysql` |
| "Your session expired" on every submit | Session directory not writable | Check `session.save_path` permissions |
| Blank white page | Fatal error with `display_errors=Off` | Read the error log |
| Changes in `php.ini` have no effect | Edited the CLI ini | Edit `/etc/php/8.3/apache2/php.ini` (or `fpm/`), then restart |

Useful commands:

```bash
sudo tail -f /var/log/apache2/expense-tracker-error.log   # app + PHP errors
sudo apache2ctl configtest                                # config syntax
sudo apache2ctl -S                                        # which vhost answers
apache2ctl -M | grep -E 'php|proxy_fcgi|headers|env'      # loaded modules
php -m | grep pdo_mysql                                   # CLI extensions
systemctl status apache2 mysql                            # service state
```

On Windows, logs are in `C:\Apache24\logs\`. Use `httpd.exe -t` and `httpd.exe -M`.
