# Installing PhaiERP

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer with `pdo_mysql`, `mbstring` (and `curl` for the AI assistant) |
| Database | MySQL 8.0+ or MariaDB 10.6+ |
| Web server | Apache (with `mod_rewrite`) or Nginx |

No Composer, npm or build step.

## 1. Put the files in place

Download the latest zip from [phai-erp.monrak.net](https://phai-erp.monrak.net/) and unzip it: you get a `phai-erp` folder. Or `git clone https://github.com/kirati-ai/phai-erp.git`.

**Quick (Apache)** — copy the `phai-erp` folder under the web root, e.g. `htdocs/phai-erp`, and open `http://localhost/phai-erp/`.
The `.htaccess` in the main folder sends every request into `public/`, so `config.php`, `src/` and `database/` are never served.

**Recommended for production** — point the document root at `phai-erp/public`.

Nginx:

```nginx
server {
    listen 80;
    server_name erp.example.com;
    root /var/www/phai-erp/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

The web server must be able to write `storage/logs`, `storage/uploads` and (during installation) the main folder, to create `config.php`.

## 2. The installer

The first visit opens `/install`: five steps with **Next** / **Back**. Each step is checked before moving on, and nothing is created until you press Install on the last one.

1. **Server** — all requirements green.
2. **Database** — the connection is tested when you press Next; use a new or empty database (it is created if missing).
3. **Company** — name, tax ID, phone, address (pick province, district, sub-district; the postcode fills in — may be left incomplete) and **logo** (click or drop a PNG, JPG or WebP up to 1 MB). The logo appears on the login page and printed documents; address and logo can be changed later in **System › Company**.
4. **Administrator** — password at least 8 characters, typed twice.
5. **Confirm** — check the summary, tick **Add sample data to try it out** if you like (see below), press **Install**, then go to the sign-in page.

If `config.php` cannot be written, the installer shows its content to save as `config.php` in the main folder yourself.

To install again: delete `config.php` and the database, then open the site.

## 3. Sample data

Sample data for a shelf and table workshop, with two months of history (your own company details are not changed): every menu has data — parts, part groups and types, a price list, exchange rates, a second warehouse, bills of materials, vendors, customers (addresses in parts, made-up tax ids), a requisition, purchase orders, receipts (one waiting for its invoice, with landed cost), payables, vouchers, payments (a withholding certificate at two rates, 1% and 3%), work orders, QC, sales orders, shipments, invoices, a credit note, billing notes, cash receipts, journal entries, a bank reconciliation, an MRP run and a stock count still being counted.
Everything is created through the same API as the screens, so stock, costs and the ledger are exactly what real use would produce. All names are fictional.

To load it later (only into a system with no parts yet). Once in, it **stays** — for an empty system, install again (remove `config.php` and the database, then open the installer):

```bash
php bin/phai demo
```

## 4. config.php

| Key | Meaning |
|---|---|
| `app.url` | Site address (set by the installer) |
| `app.debug` | Always `false` in production — `true` shows error details |
| `app.timezone`, `app.locale` | Time zone, default language (`th` / `en`) |
| `db.*` | Database connection |
| `session.lifetime` | Idle minutes before signing in again |
| `ai.*` | AI provider, key, model, prices — also editable in **System › AI settings** |
| `pdf.browser` | Edge or Chrome used to make PDF files — empty = found automatically (without one, use "Save as PDF" in the print dialog) |

`config.php` holds the database password and the API key: never commit it or make it downloadable.

## 5. Production

- Use HTTPS (the session cookie becomes `Secure` automatically).
- Give PhaiERP its own database user with rights on its database only.
- Back up daily:

```bash
mysqldump --single-transaction --routines phaierp > phaierp-$(date +%F).sql
```

  plus `config.php` and `storage/uploads`.

- Upgrading: back up the database and files, replace all program files except `config.php` and `storage/`, and check [CHANGELOG.md](../CHANGELOG.md) for database changes.

## Command line

```bash
php bin/phai version
php bin/phai demo
php bin/phai password admin   # forgotten password: set a new one (also unlocks)
php tests/run.php
```
