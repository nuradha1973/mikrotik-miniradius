
-- MINIRADIUS DATABASE SETUP SQL
-- FreeRADIUS 3.x + MikroTik RouterOS v7 Compatible Schema

-- 
-- Skema ini dirancang untuk bekerja dengan:
--   - FreeRADIUS 3.2.x (rlm_sql_mysql)
--   - MikroTik RouterOS v7.x (REST API)
--   - MiniRadius Web Panel (PHP)
--
-- Cara penggunaan:
--   mysql -u root -p radius < setup.sql
--


-- Gunakan database radius (buat jika belum ada)
CREATE DATABASE IF NOT EXISTS radius_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE radius_db;


-- TABEL 1: radcheck
-- Menyimpan kredensial dan atribut cek per-user
-- Digunakan oleh: FreeRADIUS (authorize), MiniRadius (CRUD user)

CREATE TABLE IF NOT EXISTS radcheck (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '==',
    value VARCHAR(253) NOT NULL DEFAULT '',
    KEY idx_radcheck_username (username(32)),
    KEY idx_radcheck_attr (username(32), attribute(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 2: radreply
-- Atribut balasan per-user (dikirim ke NAS saat auth berhasil)
-- Digunakan oleh: FreeRADIUS (post-auth)

CREATE TABLE IF NOT EXISTS radreply (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '=',
    value VARCHAR(253) NOT NULL DEFAULT '',
    KEY idx_radreply_username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 3: radgroupcheck
-- Atribut cek per-group (digunakan untuk validasi grup)
-- Digunakan oleh: FreeRADIUS (authorize)

CREATE TABLE IF NOT EXISTS radgroupcheck (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '==',
    value VARCHAR(253) NOT NULL DEFAULT '',
    KEY idx_radgroupcheck_groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 4: radgroupreply
-- Atribut balasan per-group (speed profile Mikrotik-Rate-Limit)
-- Digunakan oleh: FreeRADIUS (post-auth), MiniRadius (CRUD profile)

CREATE TABLE IF NOT EXISTS radgroupreply (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op VARCHAR(2) NOT NULL DEFAULT '=',
    value VARCHAR(253) NOT NULL DEFAULT '',
    KEY idx_radgroupreply_groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 5: radusergroup
-- Pemetaan user ke group (menentukan speed profile user)
-- Digunakan oleh: FreeRADIUS (authorize), MiniRadius (CRUD user)

CREATE TABLE IF NOT EXISTS radusergroup (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    priority INT(11) NOT NULL DEFAULT 1,
    KEY idx_radusergroup_username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 6: radacct
-- Log sesi akuntansi RADIUS (session tracking)
-- Digunakan oleh: FreeRADIUS (accounting), MiniRadius (dashboard & logs)
--
-- Query yang digunakan di MiniRadius:
--   - COUNT active PPPoE:  WHERE acctstoptime IS NULL AND framedprotocol IN ('PPPoE', 'PPP')
--   - COUNT active Hotspot: WHERE acctstoptime IS NULL AND (framedprotocol NOT IN ('PPPoE', 'PPP') OR framedprotocol IS NULL)
--   - Load logs:           ORDER BY acctstarttime DESC LIMIT 50

CREATE TABLE IF NOT EXISTS radacct (
    radacctid BIGINT(21) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    acctsessionid VARCHAR(64) NOT NULL DEFAULT '',
    acctuniqueid VARCHAR(32) NOT NULL DEFAULT '',
    username VARCHAR(64) NOT NULL DEFAULT '',
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    realm VARCHAR(64) DEFAULT '',
    nasipaddress VARCHAR(15) NOT NULL DEFAULT '',
    nasportid VARCHAR(32) DEFAULT NULL,
    nasporttype VARCHAR(32) DEFAULT NULL,
    acctstarttime DATETIME DEFAULT NULL,
    acctupdatetime DATETIME DEFAULT NULL,
    acctstoptime DATETIME DEFAULT NULL,
    acctinterval INT(12) DEFAULT NULL,
    acctsessiontime INT(12) UNSIGNED DEFAULT NULL,
    acctauthentic VARCHAR(32) DEFAULT NULL,
    connectinfo_start VARCHAR(128) DEFAULT NULL,
    connectinfo_stop VARCHAR(128) DEFAULT NULL,
    acctinputoctets BIGINT(20) DEFAULT NULL,
    acctoutputoctets BIGINT(20) DEFAULT NULL,
    calledstationid VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid VARCHAR(50) NOT NULL DEFAULT '',
    acctterminatecause VARCHAR(32) NOT NULL DEFAULT '',
    servicetype VARCHAR(32) DEFAULT NULL,
    framedprotocol VARCHAR(32) DEFAULT NULL,
    framedipaddress VARCHAR(15) NOT NULL DEFAULT '',
    framedipv6address VARCHAR(45) NOT NULL DEFAULT '',
    framedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    framedinterfaceid VARCHAR(44) NOT NULL DEFAULT '',
    delegatedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    class VARCHAR(64) DEFAULT NULL,
    KEY idx_radacct_username (username),
    KEY idx_radacct_acctsessionid (acctsessionid),
    KEY idx_radacct_acctuniqueid (acctuniqueid),
    KEY idx_radacct_acctstarttime (acctstarttime),
    KEY idx_radacct_acctstoptime (acctstoptime),
    KEY idx_radacct_nasipaddress (nasipaddress),
    KEY idx_radacct_framedipaddress (framedipaddress),
    KEY idx_radacct_framedprotocol (framedprotocol),
    KEY idx_radacct_active_sessions (acctstoptime, username, nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 7: radpostauth
-- Log autentikasi (berhasil/gagal) — berguna untuk audit
-- Digunakan oleh: FreeRADIUS (post-auth logging)

CREATE TABLE IF NOT EXISTS radpostauth (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    pass VARCHAR(64) NOT NULL DEFAULT '',
    reply VARCHAR(32) NOT NULL DEFAULT '',
    authdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    class VARCHAR(64) DEFAULT NULL,
    KEY idx_radpostauth_username (username),
    KEY idx_radpostauth_class (class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABEL 8: nas
-- Daftar NAS (Network Access Server) clients untuk FreeRADIUS
-- Digunakan oleh: FreeRADIUS (rlm_sql nas query)

CREATE TABLE IF NOT EXISTS nas (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nasname VARCHAR(128) NOT NULL,
    shortname VARCHAR(32),
    type VARCHAR(30) DEFAULT 'other',
    ports INT(5),
    secret VARCHAR(60) DEFAULT 'secret',
    server VARCHAR(64),
    community VARCHAR(50),
    description VARCHAR(200) DEFAULT 'RADIUS Client',
    UNIQUE KEY idx_nas_nasname (nasname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- TABEL 9: miniradius_user_status
-- Menyimpan status aktif/disabled user (HANYA untuk MiniRadius)
-- Tabel ini TIDAK digunakan oleh FreeRADIUS, sehingga aman
-- dari gangguan autentikasi.

CREATE TABLE IF NOT EXISTS miniradius_user_status (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- GRANT: Buat user MySQL 'radius' jika belum ada
-- Sesuaikan password dengan config.php (db_pass)

-- CREATE USER IF NOT EXISTS 'radius'@'localhost' IDENTIFIED BY 'homelab123';
-- GRANT ALL PRIVILEGES ON radius_db.* TO 'radius'@'localhost';
-- FLUSH PRIVILEGES;



-- SELESAI
-- Database siap digunakan. Semua tabel sudah dibuat tanpa data awal.

