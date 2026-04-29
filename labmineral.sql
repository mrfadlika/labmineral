-- ============================================================
--  LabMineral Pro — Database Schema + Data Awal
--  MySQL 5.7+ / MariaDB 10.4+
--  Import via phpMyAdmin atau: mysql -u root labmineral < labmineral.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS labmineral
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE labmineral;

-- ------------------------------------------------------------
-- PENGGUNA
-- ------------------------------------------------------------
CREATE TABLE pengguna (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    email      VARCHAR(100),
    role       ENUM('admin','analis','klien','supervisor','client') DEFAULT 'analis',
    status     ENUM('aktif','nonaktif')       DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password semua akun default: password (admin123, analis123, etc are hashes here)
-- Note: The hash $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi is 'password' in many Laravel defaults
INSERT INTO pengguna (nama, username, password, email, role) VALUES
('Dr. Ahmad Fauzi',   'admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ahmad@lab.com',   'admin'),
('Rani Dewi, S.Si',   'rani.d',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rani@lab.com',    'analis'),
('Budi Santoso',      'budi.s',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'budi@lab.com',    'analis'),
('PT. Aneka Tambang', 'aneka.tm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'aneka@mail.com',  'client'),
('PT. Freeport Ind.', 'freeport', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fp@freeport.com', 'client'),
('Dr. Siti Aminah, M.Sc', 'supervisor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siti@lab.com', 'supervisor');

-- ------------------------------------------------------------
-- SAMPEL
-- ------------------------------------------------------------
CREATE TABLE sampel (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    penerimaan_id  INT,
    kode_sampel    VARCHAR(20)  NOT NULL UNIQUE,
    tanggal_masuk  DATE         NOT NULL,
    jenis_material VARCHAR(100) NOT NULL,
    berat_gram     DECIMAL(10,3),
    klien          VARCHAR(100),
    metode_uji     VARCHAR(50),
    keterangan     TEXT,
    status         ENUM('antrian','diuji','review','selesai','ditolak') DEFAULT 'antrian',
    dibuat_oleh    INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- ------------------------------------------------------------
-- HASIL UJI
-- ------------------------------------------------------------
CREATE TABLE hasil_uji (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    kode_uji    VARCHAR(20)   NOT NULL UNIQUE,
    sampel_id   INT           NOT NULL,
    no_referensi VARCHAR(50),
    parameter   VARCHAR(100)  NOT NULL,
    nilai       DECIMAL(12,4) NOT NULL,
    satuan      VARCHAR(20),
    batas_min   DECIMAL(12,4),
    batas_maks  DECIMAL(12,4),
    metode      VARCHAR(50),
    alat_id     INT,
    analis_id   INT,
    kesimpulan  ENUM('lulus','tidak_lulus','pending') DEFAULT 'pending',
    catatan     TEXT,
    tanggal_uji DATE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sampel_id)  REFERENCES sampel(id),
    FOREIGN KEY (analis_id)  REFERENCES pengguna(id)
);

-- ------------------------------------------------------------
-- BAHAN / REAGEN
-- ------------------------------------------------------------
CREATE TABLE bahan (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    kode_bahan           VARCHAR(20)   NOT NULL UNIQUE,
    nama                 VARCHAR(150)  NOT NULL,
    stok                 DECIMAL(10,3) NOT NULL DEFAULT 0,
    satuan               VARCHAR(20),
    stok_minimum         DECIMAL(10,3) DEFAULT 0,
    supplier             VARCHAR(100),
    tanggal_kadaluarsa   DATE,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- PERALATAN
-- ------------------------------------------------------------
CREATE TABLE peralatan (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    kode_alat               VARCHAR(20)  NOT NULL UNIQUE,
    nama                    VARCHAR(150) NOT NULL,
    lokasi                  VARCHAR(100),
    status                  ENUM('tersedia','digunakan','maintenance','rusak') DEFAULT 'tersedia',
    tanggal_kalibrasi       DATE,
    masa_berlaku_kalibrasi  DATE,
    jam_pakai               INT DEFAULT 0,
    tanggal_servis_terakhir DATE,
    jadwal_maintenance      DATE,
    pic                     VARCHAR(100),
    catatan                 TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- PENERIMAAN SAMPEL (BATCH)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS penerimaan_sampel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_penerimaan VARCHAR(50) NOT NULL UNIQUE,
    klien VARCHAR(100) NOT NULL,
    tanggal_terima DATE NOT NULL,
    jumlah_sampel INT DEFAULT 0,
    jenis_material VARCHAR(200),
    metode_uji VARCHAR(100),
    keterangan TEXT,
    status ENUM('diterima', 'diproses', 'selesai', 'dibatalkan') DEFAULT 'diterima',
    is_confirmed TINYINT(1) DEFAULT 0,
    dibuat_oleh INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- ------------------------------------------------------------
-- INVOICE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_invoice VARCHAR(50) NOT NULL UNIQUE,
    penerimaan_id INT,
    klien VARCHAR(100) NOT NULL,
    alamat_klien TEXT,
    tanggal_invoice DATE NOT NULL,
    tanggal_jatuh_tempo DATE,
    subtotal DECIMAL(15,2) DEFAULT 0,
    diskon_pct DECIMAL(5,2) DEFAULT 0,
    diskon_nominal DECIMAL(15,2) DEFAULT 0,
    ppn_pct DECIMAL(5,2) DEFAULT 11,
    ppn_nominal DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft', 'diterbitkan', 'lunas', 'dibatalkan') DEFAULT 'draft',
    catatan TEXT,
    dibuat_oleh INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_sampel(id),
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- ------------------------------------------------------------
-- LOG AKTIVITAS
-- ------------------------------------------------------------
CREATE TABLE log_aktivitas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pengguna_id INT,
    aksi        VARCHAR(200),
    modul       VARCHAR(50),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
);
