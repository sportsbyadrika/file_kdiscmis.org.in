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
- **PDF:** mPDF/TCPDF (server-side, streamed in-memory) — added in a later stage
- **Spreadsheets:** PhpSpreadsheet (.xlsx) + native CSV — added in a later stage

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
- [ ] Stage 4 — File List View
- [ ] Stage 5 — File Work Area
- [ ] Stage 6 — PDF Generation
- [ ] Stage 7 — Bulk Upload wizard
- [ ] Stage 8 — Audit Log + polish

## Security notes

- Session-based auth with CSRF tokens on all POST forms (Stage 2+)
- Passwords hashed with `password_hash()` (bcrypt)
- Upload MIME + extension whitelist, sanitised stored filenames
- Soft-deletes only (no hard deletes)
- `config/config.php` git-ignored; secrets never committed
