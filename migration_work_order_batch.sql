-- ============================================================
--  migration_work_order_batch.sql
--  Migrasi work_order dari relasi 1:1 sampel → 1:N via pivot
--  Jalankan SEKALI pada database labmineral
-- ============================================================

-- ── STEP 1: Tambah kolom baru ke tabel work_order ────────────
ALTER TABLE work_order
    ADD COLUMN penerimaan_id  INT          NULL     AFTER nomor_wo,
    ADD COLUMN lingkup_batch  TINYINT(1)   NOT NULL DEFAULT 0
                              COMMENT '1=batch per penerimaan, 0=single/pilihan manual'
                              AFTER penerimaan_id,
    ADD COLUMN selesai_at     DATETIME     NULL     AFTER status;

-- Foreign key ke penerimaan_sampel
ALTER TABLE work_order
    ADD CONSTRAINT fk_wo_penerimaan
    FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_sampel(id)
    ON DELETE SET NULL;

-- ── STEP 2: Buat tabel pivot work_order_sampel ───────────────
CREATE TABLE IF NOT EXISTS work_order_sampel (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    wo_id       INT NOT NULL,
    sampel_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_wo_sampel (wo_id, sampel_id),

    CONSTRAINT fk_wos_wo
        FOREIGN KEY (wo_id) REFERENCES work_order(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_wos_sampel
        FOREIGN KEY (sampel_id) REFERENCES sampel(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── STEP 3: Migrasi data lama ─────────────────────────────────
-- Pindahkan sampel_id dari work_order ke tabel pivot
INSERT IGNORE INTO work_order_sampel (wo_id, sampel_id)
SELECT id, sampel_id
FROM work_order
WHERE sampel_id IS NOT NULL;

-- Isi penerimaan_id dari sampel yang sudah ada
UPDATE work_order w
JOIN sampel s ON w.sampel_id = s.id
SET w.penerimaan_id = s.penerimaan_id,
    w.lingkup_batch = CASE WHEN s.penerimaan_id IS NOT NULL THEN 1 ELSE 0 END
WHERE w.sampel_id IS NOT NULL;

-- ── STEP 4: Verifikasi sebelum drop kolom lama ───────────────
-- Jalankan query ini untuk memastikan data sudah terpindah:
--
-- SELECT
--     (SELECT COUNT(*) FROM work_order WHERE sampel_id IS NOT NULL) AS wo_dengan_sampel_id,
--     (SELECT COUNT(*) FROM work_order_sampel)                      AS baris_di_pivot;
--
-- Keduanya harus sama sebelum melanjutkan ke STEP 5.

-- ── STEP 5: Hapus kolom sampel_id lama (setelah verifikasi) ──
-- PENTING: Uncomment dan jalankan HANYA setelah verifikasi STEP 4 sukses.
--
-- ALTER TABLE work_order
--     DROP FOREIGN KEY fk_wo_sampel,   -- ganti nama FK sesuai database kamu
--     DROP COLUMN sampel_id;

-- ── INDEX tambahan untuk performa ────────────────────────────
CREATE INDEX idx_wos_wo_id     ON work_order_sampel(wo_id);
CREATE INDEX idx_wos_sampel_id ON work_order_sampel(sampel_id);
CREATE INDEX idx_wo_penerimaan ON work_order(penerimaan_id);
CREATE INDEX idx_wo_status     ON work_order(status);
