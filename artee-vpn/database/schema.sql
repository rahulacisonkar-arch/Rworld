-- ============================================================
--  ARTEE VPN — MySQL Database Schema
--  Compatible with MySQL 5.7+
--  Run this once to initialize the database
-- ============================================================

CREATE DATABASE IF NOT EXISTS arteevpn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arteevpn;

-- ─── Users Table ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(191) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,         -- bcrypt hash
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    status      ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
    avatar      VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── VPN Peers Table ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS peers (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    peer_id       VARCHAR(64) NOT NULL UNIQUE,  -- NetBird peer ID
    name          VARCHAR(100) NOT NULL,
    hostname      VARCHAR(255) DEFAULT NULL,
    ip_address    VARCHAR(45) DEFAULT NULL,      -- Assigned VPN IP (IPv4 or IPv6)
    os            VARCHAR(100) DEFAULT NULL,
    os_version    VARCHAR(100) DEFAULT NULL,
    version       VARCHAR(50) DEFAULT NULL,      -- NetBird client version
    country_code  VARCHAR(10) DEFAULT NULL,
    city          VARCHAR(100) DEFAULT NULL,
    status        ENUM('online','offline') NOT NULL DEFAULT 'offline',
    last_seen     DATETIME DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_peer_id (peer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Setup Keys Table ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS setup_keys (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    key_name     VARCHAR(100) NOT NULL,
    setup_key    VARCHAR(64) NOT NULL UNIQUE,
    key_type     ENUM('one-off','reusable') NOT NULL DEFAULT 'reusable',
    usage_count  INT UNSIGNED NOT NULL DEFAULT 0,
    max_usage    INT UNSIGNED DEFAULT NULL,       -- NULL = unlimited
    expires_at   DATETIME DEFAULT NULL,
    revoked      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_key (setup_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Access Policies Table ────────────────────────────────────
CREATE TABLE IF NOT EXISTS policies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    enabled     TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Activity Log Table ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED DEFAULT NULL,
    peer_id     INT UNSIGNED DEFAULT NULL,
    event_type  VARCHAR(100) NOT NULL,           -- e.g. 'peer.connected', 'user.login'
    description TEXT DEFAULT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (peer_id) REFERENCES peers(id) ON DELETE SET NULL,
    INDEX idx_event (event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Network Routes Table ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS routes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    network       VARCHAR(50) NOT NULL,          -- CIDR e.g. 10.0.0.0/24
    gateway_peer  INT UNSIGNED DEFAULT NULL,     -- Peer acting as router
    metric        INT UNSIGNED NOT NULL DEFAULT 9999,
    enabled       TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gateway_peer) REFERENCES peers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Sessions Table ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
    id          VARCHAR(128) PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  TEXT DEFAULT NULL,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Default Admin User ───────────────────────────────────────
-- Password: Admin@123  (CHANGE THIS immediately after first login!)
INSERT INTO users (name, email, password, role, status) VALUES (
    'Artee Admin',
    'admin',
    '$2y$10$V3XCk9FU2A4fPEzt7r0PVuklO0O5xGboGcscrVmmLWZ.2w1dRlZLy',
    'admin',
    'active'
);
