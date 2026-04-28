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
    role       ENUM('admin','analis','klien') DEFAULT 'analis',
    status     ENUM('aktif','nonaktif')       DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password semua akun default: password
INSERT INTO pengguna (nama, username, password, email, role) VALUES
('Dr. Ahmad Fauzi',   'admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ahmad@lab.com',   'admin'),
('Rani Dewi, S.Si',   'rani.d',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rani@lab.com',    'analis'),
('Budi Santoso',      'budi.s',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'budi@lab.com',    'analis'),
('PT. Aneka Tambang', 'aneka.tm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'aneka@mail.com',  'klien'),
('PT. Freeport Ind.', 'freeport', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fp@freeport.com', 'klien');

-- ------------------------------------------------------------
-- SAMPEL
-- ------------------------------------------------------------
CREATE TABLE sampel (
    id             INT AUTO_INCREMENT PRIMARY KEY,
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

INSERT INTO sampel (kode_sampel, tanggal_masuk, jenis_material, berat_gram, klien, metode_uji, status, dibuat_oleh) VALUES
('S-2603-043', '2026-03-10', 'Bijih Besi',     250.500, 'PT. Krakatau Steel', 'Gravimetri', 'selesai', 2),
('S-2603-044', '2026-03-11', 'Bauksit',         180.200, 'PT. Antam',          'XRF',        'selesai', 2),
('S-2603-045', '2026-03-12', 'Tembaga Oksida',  120.750, 'CV. Mitra Logam',    'AAS',        'review',  3),
('S-2603-046', '2026-03-13', 'Bijih Emas',       95.300, 'PT. Freeport',       'Fire Assay', 'diuji',   2),
('S-2603-047', '2026-03-14', 'Nikel Laterit',   310.000, 'PT. Aneka Tambang',  'ICP-OES',    'antrian', 3),
('S-2603-048', '2026-03-15', 'Timbal/Seng',     145.600, 'PT. Timah',          'AAS',        'antrian', 3);

-- ------------------------------------------------------------
-- HASIL UJI
-- ------------------------------------------------------------
CREATE TABLE hasil_uji (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    kode_uji    VARCHAR(20)   NOT NULL UNIQUE,
    sampel_id   INT           NOT NULL,
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

INSERT INTO hasil_uji (kode_uji, sampel_id, parameter, nilai, satuan, batas_min, batas_maks, metode, analis_id, kesimpulan, tanggal_uji) VALUES
('U-001', 4, 'Au (Emas)',    8.4500, 'g/t',  0.5,  NULL, 'Fire Assay', 2, 'lulus',        '2026-03-13'),
('U-002', 4, 'Ag (Perak)',  12.3000, 'g/t',  1.0,  NULL, 'Fire Assay', 2, 'lulus',        '2026-03-13'),
('U-003', 3, 'Cu (Tembaga)', 0.2800, '%',    0.5,  NULL, 'AAS',        3, 'tidak_lulus',  '2026-03-12'),
('U-004', 2, 'Al2O3',       52.7000, '%',   45.0,  60.0, 'XRF',        2, 'lulus',        '2026-03-11'),
('U-005', 5, 'Ni (Nikel)',   1.8700, '%',    1.5,  NULL, 'ICP-OES',    3, 'lulus',        '2026-03-14'),
('U-006', 5, 'Fe (Besi)',   18.4000, '%',   NULL,  20.0, 'ICP-OES',    3, 'lulus',        '2026-03-14'),
('U-007', 1, 'Fe Total',    61.2000, '%',   58.0,  NULL, 'Gravimetri', 2, 'lulus',        '2026-03-10'),
('U-008', 1, 'SiO2',         4.8000, '%',   NULL,   5.0, 'XRF',        3, 'lulus',        '2026-03-10');

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

INSERT INTO bahan (kode_bahan, nama, stok, satuan, stok_minimum, supplier, tanggal_kadaluarsa) VALUES
('BH-001', 'Asam Nitrat (HNO3)',        0.500, 'Liter',  2.0, 'PT. Merck Indonesia',     '2026-12-31'),
('BH-002', 'Aqua Regia',                2.100, 'Liter',  3.0, 'PT. Brataco Chemika',     '2026-06-30'),
('BH-003', 'Asam Klorida (HCl)',         8.500, 'Liter',  2.0, 'PT. Merck Indonesia',     '2026-12-31'),
('BH-004', 'Asam Sulfat (H2SO4)',       12.000, 'Liter',  3.0, 'PT. Brataco Chemika',     '2027-01-31'),
('BH-005', 'NaOH (Natrium Hidroksida)',  0.800, 'kg',     2.0, 'PT. Sigma Aldrich',       '2026-09-30'),
('BH-006', 'Standard Au 1000 ppm',       3.000, 'Ampul',  5.0, 'PT. Inorganic Ventures',  '2026-08-15'),
('BH-007', 'Standard Ni 1000 ppm',      12.000, 'Ampul',  5.0, 'PT. Inorganic Ventures',  '2027-03-01'),
('BH-008', 'Flux (Na2B4O7)',             1.200, 'kg',     3.0, 'PT. Fluka Chemika',       '2027-06-30');

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

INSERT INTO peralatan (kode_alat, nama, lokasi, status, tanggal_kalibrasi, masa_berlaku_kalibrasi, jam_pakai, jadwal_maintenance, pic, catatan) VALUES
('AAS-01', 'AAS Shimadzu AA-7000',       'Lab Kimia Basah', 'tersedia',    '2026-01-15', '2026-04-12', 1240, NULL,         'Rani D.',     NULL),
('XRF-01', 'XRF PANalytical Axios',      'Lab Instrumen',   'tersedia',    '2026-02-10', '2026-05-05',  980, NULL,         'Rani D.',     NULL),
('XRF-02', 'XRF Analyzer Compact',       'Lab Instrumen',   'maintenance', '2025-12-01', '2026-03-12', 1540, '2026-03-16', 'Budi S.',     'Kalibrasi kadaluarsa, perlu kalibrasi ulang'),
('ICP-01', 'ICP-OES Agilent 5110',       'Lab Instrumen',   'tersedia',    '2025-12-20', '2026-03-29', 2100, NULL,         'Rani D.',     NULL),
('MF-01',  'Furnace Muffle 1200C',       'Lab Preparasi',   'digunakan',   '2025-09-01', '2026-06-20',  870, '2026-03-17', 'Teknisi Ext.', NULL),
('TB-01',  'Timbangan Analitik 0.1mg',   'Lab Preparasi',   'maintenance', '2025-11-01', '2026-03-22',  430, '2026-03-22', 'Budi S.',     'Sedang diservis'),
('CR-01',  'Mesin Crusher Rahang',        'Lab Preparasi',   'tersedia',    NULL,         NULL,           560, NULL,         'Budi S.',     NULL),
('BM-01',  'Ball Mill Planetary',         'Lab Preparasi',   'rusak',       NULL,         NULL,           320, NULL,         'Teknisi Ext.', 'Motor rusak, menunggu suku cadang'),
('PH-01',  'pH Meter Digital',            'Lab Kimia Basah', 'tersedia',    '2026-01-01', '2026-04-01',  180, NULL,         'Rani D.',     NULL);

-- ------------------------------------------------------------
-- LOG PENGGUNAAN BAHAN
-- ------------------------------------------------------------
CREATE TABLE log_bahan (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    bahan_id    INT NOT NULL,
    jenis       ENUM('masuk','keluar') NOT NULL,
    jumlah      DECIMAL(10,3) NOT NULL,
    keterangan  VARCHAR(200),
    pengguna_id INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bahan_id)    REFERENCES bahan(id),
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
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
