-- Hotel maintenance PoC: database schema (feature db-schema).
-- Drop-and-create: re-running it deletes every row (accepted for the PoC, the seed reloads data).
-- It names no database. Run it against the database given on the command line, from the repo root:
--   /c/xampp/mysql/bin/mysql.exe --defaults-extra-file=config/mysql.cnf hm_db_schema < db/schema.sql
-- Format rule (tests/lib/db.php splits this file): every statement ends with a semicolon at the
-- end of a line, comments are full lines starting with two dashes, no semicolons inside comments
-- or string literals, no DELIMITER.
-- Status and priority live in the lookup tables statuses and priorities. Their rows are fixed
-- domain values (exact Spanish names of CONTEXT.md) with fixed ids that the seed and the API rely
-- on. This file is the only writer of those rows: it inserts them right after creating each table.
-- work_orders.status_id, work_orders.priority_id, work_order_status_history.status_id and
-- level3_typologies.default_priority_id are integer foreign keys to them. Foreign keys and NOT NULL
-- hold regardless of the non-strict server sql_mode. Application code refers to them by id.
-- The name columns keep the table collation utf8mb4_unicode_ci on purpose: it is case-, accent-
-- and trailing-space-insensitive, so the unique index rejects look-alikes such as 'pendiente',
-- 'En resolucion' or 'Pendiente ' (a binary collation would let them coexist).
-- Status transition rules are not in the database: the API enforces them in PHP.
-- Tables are dropped in reverse dependency order, so the order is right even without the
-- FOREIGN_KEY_CHECKS wrapper.

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ai_prefill_log;
DROP TABLE IF EXISTS work_order_status_history;
DROP TABLE IF EXISTS work_orders;
DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS level3_typologies;
DROP TABLE IF EXISTS level2_typologies;
DROP TABLE IF EXISTS level1_typologies;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS location_types;
DROP TABLE IF EXISTS priorities;
DROP TABLE IF EXISTS statuses;
SET FOREIGN_KEY_CHECKS = 1;

-- Work order statuses: fixed rows, ids equal sort_order. is_closed is 1 only for Resuelto and
-- Cancelado (data for the API, not a database rule).
CREATE TABLE statuses (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(20) NOT NULL,
    is_closed TINYINT(1) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    CONSTRAINT uq_statuses_name UNIQUE (name),
    CONSTRAINT uq_statuses_sort_order UNIQUE (sort_order),
    CONSTRAINT chk_statuses_is_closed CHECK (is_closed IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO statuses (id, name, is_closed, sort_order) VALUES
    (1, 'Pendiente', 0, 1),
    (2, 'Asignado', 0, 2),
    (3, 'En resolución', 0, 3),
    (4, 'Resuelto', 1, 4),
    (5, 'Cancelado', 1, 5);

-- Work order priorities: fixed rows, ids equal sort_order.
CREATE TABLE priorities (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(10) NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    CONSTRAINT uq_priorities_name UNIQUE (name),
    CONSTRAINT uq_priorities_sort_order UNIQUE (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO priorities (id, name, sort_order) VALUES
    (1, 'Baja', 1),
    (2, 'Media', 2),
    (3, 'Alta', 3);

-- Location types (e.g. Habitación, Zona común, Zona técnica).
CREATE TABLE location_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    CONSTRAINT uq_location_types_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locations: flat list, each with one location type.
CREATE TABLE locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location_type_id INT UNSIGNED NOT NULL,
    CONSTRAINT uq_locations_name UNIQUE (name),
    CONSTRAINT fk_locations_location_type_id FOREIGN KEY (location_type_id)
        REFERENCES location_types (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typology tree: level 1 (Tipología 1), level 2 (Tipología 2), level 3 (Tipología 3).
CREATE TABLE level1_typologies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    CONSTRAINT uq_level1_typologies_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE level2_typologies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    level1_typology_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    CONSTRAINT uq_level2_typologies_parent_name UNIQUE (level1_typology_id, name),
    CONSTRAINT fk_level2_typologies_level1_typology_id FOREIGN KEY (level1_typology_id)
        REFERENCES level1_typologies (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Level 3 carries the default priority used when a work order is created.
CREATE TABLE level3_typologies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    level2_typology_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    default_priority_id INT UNSIGNED NOT NULL,
    CONSTRAINT uq_level3_typologies_parent_name UNIQUE (level2_typology_id, name),
    CONSTRAINT fk_level3_typologies_level2_typology_id FOREIGN KEY (level2_typology_id)
        REFERENCES level2_typologies (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_level3_typologies_default_priority_id FOREIGN KEY (default_priority_id)
        REFERENCES priorities (id) ON DELETE RESTRICT ON UPDATE RESTRICT
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users: technicians and the MCP service account (is_service_account = 1).
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_names VARCHAR(150) NOT NULL DEFAULT '',
    is_service_account TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT uq_users_username UNIQUE (username),
    CONSTRAINT chk_users_is_service_account CHECK (is_service_account IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auth tokens: only the hex SHA-256 of the token is stored, never the raw token.
-- Logout deletes the row (no revoked_at column).
CREATE TABLE auth_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT uq_auth_tokens_token_hash UNIQUE (token_hash),
    INDEX ix_auth_tokens_expires_at (expires_at),
    CONSTRAINT fk_auth_tokens_user_id FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Work orders (Partes de trabajo). Level 1 and 2 typologies are derived from the level 3 one.
CREATE TABLE work_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creator_user_id INT UNSIGNED NOT NULL,
    assignee_user_id INT UNSIGNED NULL,
    status_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    level3_typology_id INT UNSIGNED NOT NULL,
    priority_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    INDEX ix_work_orders_created_at (created_at),
    CONSTRAINT fk_work_orders_status_id FOREIGN KEY (status_id)
        REFERENCES statuses (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_work_orders_priority_id FOREIGN KEY (priority_id)
        REFERENCES priorities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_work_orders_creator_user_id FOREIGN KEY (creator_user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_work_orders_assignee_user_id FOREIGN KEY (assignee_user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_work_orders_location_id FOREIGN KEY (location_id)
        REFERENCES locations (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_work_orders_level3_typology_id FOREIGN KEY (level3_typology_id)
        REFERENCES level3_typologies (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Status history: one row per status a work order entered, ordered by changed_at, id.
CREATE TABLE work_order_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT UNSIGNED NOT NULL,
    status_id INT UNSIGNED NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    changed_by_user_id INT UNSIGNED NOT NULL,
    INDEX ix_work_order_status_history_order (work_order_id, changed_at, id),
    CONSTRAINT fk_work_order_status_history_work_order_id FOREIGN KEY (work_order_id)
        REFERENCES work_orders (id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_work_order_status_history_status_id FOREIGN KEY (status_id)
        REFERENCES statuses (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_work_order_status_history_changed_by_user_id FOREIGN KEY (changed_by_user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Prefill log: one row per AI Prefill request. tool_calls, validated_result and prefill_notes
-- hold JSON text. raw_output is free text because the model may return non-JSON.
CREATE TABLE ai_prefill_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id INT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    tool_calls LONGTEXT NULL,
    raw_output LONGTEXT NULL,
    validated_result LONGTEXT NULL,
    prefill_notes LONGTEXT NULL,
    error TEXT NULL,
    latency_ms INT UNSIGNED NULL,
    input_tokens INT UNSIGNED NULL,
    output_tokens INT UNSIGNED NULL,
    CONSTRAINT chk_ai_prefill_log_tool_calls CHECK (tool_calls IS NULL OR JSON_VALID(tool_calls)),
    CONSTRAINT chk_ai_prefill_log_validated_result CHECK (validated_result IS NULL OR JSON_VALID(validated_result)),
    CONSTRAINT chk_ai_prefill_log_prefill_notes CHECK (prefill_notes IS NULL OR JSON_VALID(prefill_notes)),
    CONSTRAINT fk_ai_prefill_log_user_id FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
