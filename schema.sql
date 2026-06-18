-- =====================================================================
--  File Repository Web Application (eOffice & OspynDocs)
--  Schema v2.0  —  MySQL 8 / MariaDB, UTF8MB4
--
--  Single-file migration. Run with:
--     mysql -u <user> -p <database> < schema.sql
--
--  Notes:
--   * Single Admin role for this release; `users.role` is kept so that
--     additional roles (e.g. uploader) can be introduced later.
--   * All deletes are soft (is_deleted flag) — no hard deletes.
--   * file_transaction_history rows are immutable (audit integrity).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(100)  NOT NULL,
    email           VARCHAR(190)  NOT NULL,
    password_hash   VARCHAR(255)  NOT NULL,
    full_name       VARCHAR(150)  NOT NULL DEFAULT '',
    role            VARCHAR(30)   NOT NULL DEFAULT 'admin',
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- files  — core record shared by both source apps
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_app        ENUM('eoffice','ospyndocs') NOT NULL,
    reference_no      VARCHAR(190) NOT NULL,            -- File Ref No (eOffice) / Document ID (OspynDocs); match key
    status            VARCHAR(80)  NOT NULL DEFAULT '',
    file_note         LONGTEXT     NULL,                -- HTML rich text or pre-formatted plain text
    uploaded_by       INT UNSIGNED NULL,
    upload_date       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_updated_by   INT UNSIGNED NULL,
    last_updated_on   DATETIME     NULL,
    is_deleted        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_files_app_ref (source_app, reference_no),
    KEY idx_files_source_app (source_app),
    KEY idx_files_status (status),
    KEY idx_files_upload_date (upload_date),
    KEY idx_files_last_updated (last_updated_on),
    KEY idx_files_is_deleted (is_deleted),
    KEY idx_files_uploaded_by (uploaded_by),
    CONSTRAINT fk_files_uploaded_by   FOREIGN KEY (uploaded_by)     REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_files_updated_by    FOREIGN KEY (last_updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- eoffice_metadata  — app-specific fields keyed to files.id
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eoffice_metadata (
    file_id          INT UNSIGNED NOT NULL,
    file_ref_no      VARCHAR(190) NOT NULL,
    subject          VARCHAR(500) NOT NULL DEFAULT '',
    department       VARCHAR(190) NOT NULL DEFAULT '',
    file_category    VARCHAR(190) NOT NULL DEFAULT '',
    date_of_document DATE         NULL,
    tags             VARCHAR(500) NULL,
    remarks          TEXT         NULL,
    PRIMARY KEY (file_id),
    KEY idx_eoffice_department (department),
    KEY idx_eoffice_category (file_category),
    KEY idx_eoffice_doc_date (date_of_document),
    CONSTRAINT fk_eoffice_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ospyndocs_metadata  — app-specific fields keyed to files.id
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ospyndocs_metadata (
    file_id          INT UNSIGNED NOT NULL,
    document_id      VARCHAR(190) NOT NULL,
    document_name    VARCHAR(500) NOT NULL DEFAULT '',
    document_type    VARCHAR(190) NOT NULL DEFAULT '',
    project_module   VARCHAR(190) NULL,
    version          VARCHAR(50)  NULL,
    date_of_creation DATE         NULL,
    tags             VARCHAR(500) NULL,
    remarks          TEXT         NULL,
    PRIMARY KEY (file_id),
    KEY idx_ospyndocs_type (document_type),
    KEY idx_ospyndocs_project (project_module),
    KEY idx_ospyndocs_create_date (date_of_creation),
    CONSTRAINT fk_ospyndocs_file FOREIGN KEY (file_id) REFERENCES files (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- file_attachments  — multiple attachments per file
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS file_attachments (
    attachment_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_id           INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path       VARCHAR(500) NOT NULL,
    mime_type         VARCHAR(150) NOT NULL DEFAULT '',
    file_size_bytes   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by       INT UNSIGNED NULL,
    uploaded_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_deleted        TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (attachment_id),
    KEY idx_att_file_id (file_id),
    KEY idx_att_is_deleted (is_deleted),
    CONSTRAINT fk_att_file        FOREIGN KEY (file_id)     REFERENCES files (id) ON DELETE CASCADE,
    CONSTRAINT fk_att_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- file_transaction_history  — immutable audit trail of status changes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS file_transaction_history (
    history_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_id          INT UNSIGNED NOT NULL,
    history_date     DATE         NOT NULL,
    transaction_type VARCHAR(120) NOT NULL,
    from_status      VARCHAR(80)  NULL,
    to_status        VARCHAR(80)  NULL,
    note             TEXT         NULL,
    source           ENUM('BULK_IMPORT','USER_ACTION') NOT NULL DEFAULT 'USER_ACTION',
    performed_by     INT UNSIGNED NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    KEY idx_hist_file_id (file_id),
    KEY idx_hist_date (history_date),
    -- Dedup key for bulk import (file_id, history_date, transaction_type)
    UNIQUE KEY uq_hist_dedup (file_id, history_date, transaction_type),
    CONSTRAINT fk_hist_file         FOREIGN KEY (file_id)      REFERENCES files (id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_performed_by FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- file_update_log  — every manual/bulk metadata update
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS file_update_log (
    log_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_id         INT UNSIGNED NOT NULL,
    updated_by      INT UNSIGNED NULL,
    update_source   ENUM('BULK_IMPORT','MANUAL_EDIT') NOT NULL DEFAULT 'MANUAL_EDIT',
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fields_changed  JSON         NULL,                 -- { "field": {"old":..., "new":...}, ... }
    import_batch_id CHAR(36)     NULL,                 -- UUID; links to bulk_import_batches
    PRIMARY KEY (log_id),
    KEY idx_upd_file_id (file_id),
    KEY idx_upd_batch (import_batch_id),
    KEY idx_upd_source (update_source),
    KEY idx_upd_at (updated_at),
    CONSTRAINT fk_upd_file       FOREIGN KEY (file_id)    REFERENCES files (id) ON DELETE CASCADE,
    CONSTRAINT fk_upd_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- bulk_import_batches  — one row per bulk-upload session
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bulk_import_batches (
    batch_id     CHAR(36)     NOT NULL,                -- UUID
    source_app   ENUM('eoffice','ospyndocs') NOT NULL,
    imported_by  INT UNSIGNED NULL,
    imported_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_rows   INT UNSIGNED NOT NULL DEFAULT 0,
    inserted     INT UNSIGNED NOT NULL DEFAULT 0,
    updated      INT UNSIGNED NOT NULL DEFAULT 0,
    skipped      INT UNSIGNED NOT NULL DEFAULT 0,
    report_path  VARCHAR(500) NULL,
    PRIMARY KEY (batch_id),
    KEY idx_batch_source (source_app),
    KEY idx_batch_imported_at (imported_at),
    CONSTRAINT fk_batch_imported_by FOREIGN KEY (imported_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- user_preferences  — column visibility, sort, per-page, etc.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_preferences (
    preference_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    module           VARCHAR(80)  NOT NULL,            -- e.g. eoffice_list, ospyndocs_list
    preference_key   VARCHAR(80)  NOT NULL,            -- e.g. columns, sort, per_page
    preference_value TEXT         NULL,
    PRIMARY KEY (preference_id),
    UNIQUE KEY uq_pref (user_id, module, preference_key),
    CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed: one Admin account
--   username: admin
--   email:    admin@kdiscmis.org.in
--   password: Admin@123   (change immediately after first login)
-- ---------------------------------------------------------------------
INSERT INTO users (username, email, password_hash, full_name, role, is_active)
SELECT 'admin', 'admin@kdiscmis.org.in',
       '$2y$12$DeWf6rD6q3pfLgrlBrsnp.tGdi3auJuBvH5SVpOgO/z0vajjESEAK',
       'System Administrator', 'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');
