# File Repository Web Application (eOffice & OspynDocs)

A centralised backup repository and document-retrieval portal for files
originating from two source applications — **eOffice** and **OspynDocs**.
Built with vanilla PHP 8 + PDO MySQL for standard LAMP shared hosting
(no root, no shell daemons, no Composer requirement).

> Single **Admin** role for this release. The schema is role-ready so an
> Uploader role and a Users-management screen can be added later.

## Tech stack

- **Backend:** PHP 8.x, PDO (prepared statements only — no concatenated SQL)
- **Database:** MySQL 8 / MariaDB, UTF8MB4
- **Frontend:** Bootstrap 5, vanilla JS + `fetch`
- **PDF:** vendored FPDF (server-side, streamed in-memory) + MIT QR generator
- **Spreadsheets:** self-contained XLSX reader/writer (zip + XML) + native CSV

## Project structure

```
/bin        CLI utilities (e.g. check_db.php)
/config     config.sample.php (committed) + config.php (git-ignored)
/public     web entry point(s) and assets  ← document root
/src        application classes (App\ namespace, PSR-4 autoloaded)
/storage    uploads / reports / tmp (git-ignored contents)
/views      page templates (added from Stage 2)
schema.sql  single-file database migration
```

## Setup

1. **Configure**

   ```bash
   cp config/config.sample.php config/config.php
   ```

   Edit `config/config.php` with your database credentials and a long random
   `security.app_key`. This file is git-ignored — never commit it.

2. **Create the database & schema**

   ```bash
   mysql -u <user> -p <database> < schema.sql
   ```

   This creates all tables and seeds one admin account:

   | Field    | Value                     |
   |----------|---------------------------|
   | Username | `admin`                   |
   | Email    | `admin@kdiscmis.org.in`   |
   | Password | `Admin@123`               |

   **Change the admin password immediately after first login.**

3. **Verify the wiring**

   ```bash
   php bin/check_db.php
   ```

   Confirms the connection works, all 9 tables exist, and the admin is seeded.
   Optionally populate demo data for the Dashboard:

   ```bash
   php bin/seed_demo.php          # seeds only if no files exist
   php bin/seed_demo.php --force  # seed regardless
   ```

4. **Document root**

   Point your web server / hosting `public_html` at the `/public` directory
   (or place the app in a sub-directory and set `app.base_url` accordingly).

## Deployment (cPanel Git Version Control)

The repository ships a `.cpanel.yml`. In cPanel **Git Version Control**, use
*Update from Remote* → *Deploy HEAD Commit* to publish.

One-time setup:

1. Edit `.cpanel.yml` and set `CHANGE_ME_CPANEL_USER` to your cPanel username
   (this sets `DEPLOYPATH`, the application directory in your home folder).
2. After the first deploy, edit `DEPLOYPATH/config/config.php` with your real
   database and email/SMTP credentials, then import `schema.sql`.
3. Point the domain's **Document Root** at `DEPLOYPATH/public`.

**Configuration is never overwritten by a deploy.** The deploy tasks only
replace the application code directories (`src`, `public`, `views`, `vendor`,
`bin`) and the `config.sample.php` template. They never copy over or delete:

- `config/config.php` — database + email/SMTP credentials and the app key.
  It is created from the sample **only on the first deploy**, then left
  untouched. To change credentials, edit it once on the server.
- `storage/uploads`, `storage/reports`, `storage/tmp` — user uploads and
  generated reports survive every deploy.
- `schema.sql` is published but **never executed automatically** — no
  automatic database migrations.

A defense-in-depth root `.htaccess` is also included: if the app is ever served
from the project root instead of `/public`, it blocks web access to `config`,
`src`, `storage`, `vendor`, `bin` and to sensitive files (`.sql`, `.yml`,
`config.sample.php`, dotfiles).

## Database schema (v2.0)

`users`, `files`, `eoffice_metadata`, `ospyndocs_metadata`,
`file_attachments`, `file_transaction_history` (immutable),
`file_update_log`, `bulk_import_batches`, `user_preferences`.

See `schema.sql` for full definitions, foreign keys, and indexes.

## Build status

Built in the stages from the spec (Section 7):

- [x] **Stage 1 — Foundation:** structure, config, DB connection, schema, seed admin
- [x] **Stage 2 — Auth + navbar shell:** login/logout, change password, profile,
      session/CSRF, front-controller routing, and the responsive horizontal navbar
- [x] **Stage 3 — Dashboard:** per-app stat cards, module entry panels, recent
      activity feed, server-side stats with AJAX refresh (optional demo seeder)
- [x] **Stage 4 — File List View:** AJAX keyword/date/multi-select filters with
      removable chips, single-column sort (session-persisted), column toggle
      and per-page (user_preferences), pagination, metadata edit modal with
      update logging, and soft-delete — generalised across both apps
- [x] **Stage 5 — File Work Area:** split-panel workspace with sticky top bar,
      rich-text note editor (Quill) with char/word count + expand toggle,
      Details/Attachments/History tabs (tab persisted in sessionStorage),
      real attachment upload/preview/download/soft-delete, immutable history
      with CSV export, and an Edit Metadata modal
- [x] **Stage 6 — PDF Generation:** options modal (Minimum/Standard/Detailed,
      attachments/history/QR toggles, download or preview), A4 portrait with
      per-page header/footer, vector QR code, streamed in memory (never stored)
- [x] **Stage 7 — Bulk Upload wizard:** 5-step wizard (select app, .xlsx/.csv
      template, upload + colour-coded validation preview, typed-confirm process,
      summary); per-record atomic INSERT/UPDATE/HISTORY_ONLY, deduplicated
      history, update logging with batch id, and a downloadable CSV result
      report — self-contained XLSX reader/writer (no PhpSpreadsheet)
- [x] **Stage 8 — Audit Log + polish:** unified, filterable event/update log
      across all files (by app, event type, source, keyword, date, and
      import_batch_id for full bulk-session traceability), with toasts and
      confirmation modals throughout

All eight build stages are complete.

## Security notes

- Session-based auth with CSRF tokens on all POST forms (Stage 2+)
- Passwords hashed with `password_hash()` (bcrypt)
- Upload MIME + extension whitelist, sanitised stored filenames
- Soft-deletes only (no hard deletes)
- `config/config.php` (DB + email/SMTP credentials, app key) git-ignored;
  secrets never committed and never overwritten by automated deploys
