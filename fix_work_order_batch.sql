-- ============================================================
--  fix_work_order_batch.sql
--  Perbaikan error: Table 'work_order_sampel' doesn't exist
--
--  Root cause:
--  modul_baru.sql gagal di tengah karena:
--  1. ALTER TABLE work_order ADD COLUMN penerimaan_id — error jika
--     kolom sudah ada (dari migration sebelumnya)
--  2. Akibatnya CREATE TABLE work_order_sampel tidak tereksekusi
--
--  Script ini IDEMPOTENT — aman dijalankan berulang kali.
--  Gunakan IF NOT EXISTS dan IF EXISTS di semua operasi.
-- ============================================================

USE labmineral;

-- ============================================================
-- STEP 1 — Pastikan kolom penerimaan_id ada di work_order
--          (mungkin sudah ada dari modul_baru.sql, mungkin belum)
-- ============================================================

-- Tambah penerimaan_id jika belum ada
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'work_order'
      AND COLUMN_NAME  = 'penerimaan_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE work_order ADD COLUMN penerimaan_id INT NULL AFTER nomor_wo',
    'SELECT ''penerimaan_id sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tambah lingkup_batch jika belum ada
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'work_order'
      AND COLUMN_NAME  = 'lingkup_batch'
);
SET @sql = IF(@col_exists = 0,
    "ALTER TABLE work_order ADD COLUMN lingkup_batch TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=batch per penerimaan, 0=single' AFTER penerimaan_id",
    'SELECT ''lingkup_batch sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tambah selesai_at jika belum ada
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'work_order'
      AND COLUMN_NAME  = 'selesai_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE work_order ADD COLUMN selesai_at DATETIME NULL AFTER status',
    'SELECT ''selesai_at sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tambah FK penerimaan_id → penerimaan_sampel jika belum ada
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA        = DATABASE()
      AND TABLE_NAME          = 'work_order'
      AND COLUMN_NAME         = 'penerimaan_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE work_order ADD CONSTRAINT fk_wo_penerimaan FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_sampel(id) ON DELETE SET NULL',
    'SELECT ''FK penerimaan_id sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- STEP 2 — Buat tabel pivot work_order_sampel
--          Ini yang menyebabkan error 1146
-- ============================================================
CREATE TABLE IF NOT EXISTS work_order_sampel (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    wo_id       INT NOT NULL,
    sampel_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY  uq_wo_sampel (wo_id, sampel_id),

    CONSTRAINT  fk_wos_wo
        FOREIGN KEY (wo_id) REFERENCES work_order(id)
        ON DELETE CASCADE,

    CONSTRAINT  fk_wos_sampel
        FOREIGN KEY (sampel_id) REFERENCES sampel(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index tambahan untuk performa query JOIN
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'work_order_sampel'
      AND INDEX_NAME = 'idx_wos_wo_id'
);
SET @sql = IF(
    @idx_exists = 0,
    'CREATE INDEX idx_wos_wo_id ON work_order_sampel(wo_id)',
    'SELECT ''Index idx_wos_wo_id sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'work_order_sampel'
      AND INDEX_NAME = 'idx_wos_sampel_id'
);
SET @sql = IF(
    @idx_exists = 0,
    'CREATE INDEX idx_wos_sampel_id ON work_order_sampel(sampel_id)',
    'SELECT ''Index idx_wos_sampel_id sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'work_order'
      AND INDEX_NAME = 'idx_wo_penerimaan'
);
SET @sql = IF(
    @idx_exists = 0,
    'CREATE INDEX idx_wo_penerimaan ON work_order(penerimaan_id)',
    'SELECT ''Index idx_wo_penerimaan sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- STEP 3 — Migrasi data: isi pivot dari sampel_id lama
--          Hanya untuk WO yang belum ada di pivot
-- ============================================================
INSERT IGNORE INTO work_order_sampel (wo_id, sampel_id)
SELECT id, sampel_id
FROM   work_order
WHERE  sampel_id IS NOT NULL;

-- Isi penerimaan_id dari relasi sampel yang sudah ada
UPDATE work_order w
JOIN   sampel s ON w.sampel_id = s.id
SET    w.penerimaan_id  = s.penerimaan_id,
       w.lingkup_batch  = CASE WHEN s.penerimaan_id IS NOT NULL THEN 1 ELSE 0 END
WHERE  w.penerimaan_id IS NULL
  AND  s.penerimaan_id IS NOT NULL;

-- ============================================================
-- STEP 4 — Verifikasi hasil
-- ============================================================
SELECT
    'work_order'         AS tabel,
    COUNT(*)             AS total_baris,
    SUM(CASE WHEN penerimaan_id IS NOT NULL THEN 1 ELSE 0 END) AS dengan_penerimaan,
    SUM(lingkup_batch)   AS batch_flag
FROM work_order

UNION ALL

SELECT
    'work_order_sampel'  AS tabel,
    COUNT(*)             AS total_baris,
    NULL                 AS dengan_penerimaan,
    NULL                 AS batch_flag
FROM work_order_sampel;

-- ============================================================
-- STEP 5 — (OPSIONAL) Hapus kolom sampel_id lama
--          Uncomment setelah verifikasi STEP 4 sukses
--          dan angka 'work_order' = 'work_order_sampel'
-- ============================================================
-- SET FOREIGN_KEY_CHECKS = 0;
-- ALTER TABLE work_order DROP FOREIGN KEY <nama_fk_sampel_id>;
-- ALTER TABLE work_order DROP COLUMN sampel_id;
-- SET FOREIGN_KEY_CHECKS = 1;

-- Cara cari nama FK untuk sampel_id:
-- SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_order'
-- AND COLUMN_NAME = 'sampel_id' AND REFERENCED_TABLE_NAME IS NOT NULL;
