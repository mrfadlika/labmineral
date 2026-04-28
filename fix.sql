-- ============================================================
--  sql/update_v2.sql
--  Jalankan di phpMyAdmin SETELAH update_batch.sql
-- ============================================================
USE labmineral;

-- ── FIX S-6: Tambah kolom no_referensi di hasil_uji ─────────
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hasil_uji'
      AND COLUMN_NAME = 'no_referensi'
);
SET @sql = IF(
    @col_exists = 0,
    "ALTER TABLE hasil_uji ADD COLUMN no_referensi VARCHAR(20) NULL COMMENT 'Nomor penerimaan batch (referensi)' AFTER sampel_id",
    'SELECT ''Kolom hasil_uji.no_referensi sudah ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Isi otomatis dari relasi yang sudah ada
UPDATE hasil_uji h
JOIN sampel s ON h.sampel_id = s.id
JOIN penerimaan_sampel p ON s.penerimaan_id = p.id
SET h.no_referensi = p.nomor_penerimaan
WHERE h.no_referensi IS NULL;

-- ── FIX K-1: Hapus kolom GENERATED di tabel bahan ──────────
-- (kolom STORED generated tidak perlu, pakai fungsi PHP saja
--  untuk konsistensi — hapus agar tidak ada dua sumber kebenaran)
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bahan'
      AND COLUMN_NAME = 'status'
);
SET @sql = IF(
    @col_exists > 0,
    'ALTER TABLE bahan DROP COLUMN status',
    'SELECT ''Kolom bahan.status tidak ada, dilewati'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── FIX S-4: Trigger sinkron jumlah_sampel ─────────────────
-- Hapus trigger lama jika ada
DROP TRIGGER IF EXISTS trg_sampel_after_insert;
DROP TRIGGER IF EXISTS trg_sampel_after_update;
DROP TRIGGER IF EXISTS trg_sampel_after_delete;

DELIMITER $$

-- Insert
CREATE TRIGGER trg_sampel_after_insert
AFTER INSERT ON sampel FOR EACH ROW
BEGIN
    IF NEW.penerimaan_id IS NOT NULL THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = NEW.penerimaan_id
        ) WHERE id = NEW.penerimaan_id;
    END IF;
END$$

-- Update (tangani perpindahan batch)
CREATE TRIGGER trg_sampel_after_update
AFTER UPDATE ON sampel FOR EACH ROW
BEGIN
    IF OLD.penerimaan_id IS NOT NULL THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = OLD.penerimaan_id
        ) WHERE id = OLD.penerimaan_id;
    END IF;
    IF NEW.penerimaan_id IS NOT NULL AND NEW.penerimaan_id != COALESCE(OLD.penerimaan_id, 0) THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = NEW.penerimaan_id
        ) WHERE id = NEW.penerimaan_id;
    END IF;
END$$

-- Delete
CREATE TRIGGER trg_sampel_after_delete
AFTER DELETE ON sampel FOR EACH ROW
BEGIN
    IF OLD.penerimaan_id IS NOT NULL THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = OLD.penerimaan_id
        ) WHERE id = OLD.penerimaan_id;
    END IF;
END$$

-- ── FIX S-5: Trigger auto-update status sampel ──────────────
-- Saat semua hasil_uji untuk sampel sudah bukan 'pending',
-- status sampel otomatis berubah ke 'selesai'
CREATE TRIGGER trg_hasil_uji_after_insert
AFTER INSERT ON hasil_uji FOR EACH ROW
BEGIN
    DECLARE total_param INT;
    DECLARE pending_param INT;
    SELECT COUNT(*) INTO total_param
    FROM hasil_uji WHERE sampel_id = NEW.sampel_id;

    SELECT COUNT(*) INTO pending_param
    FROM hasil_uji WHERE sampel_id = NEW.sampel_id AND kesimpulan = 'pending';

    -- Ubah ke 'diuji' saat ada hasil pertama
    IF total_param = 1 THEN
        UPDATE sampel SET status = 'diuji'
        WHERE id = NEW.sampel_id AND status = 'antrian';
    END IF;

    -- Ubah ke 'selesai' saat semua parameter tidak pending
    IF total_param > 0 AND pending_param = 0 THEN
        UPDATE sampel SET status = 'selesai'
        WHERE id = NEW.sampel_id AND status IN ('diuji', 'review');
    END IF;
END$$

CREATE TRIGGER trg_hasil_uji_after_update
AFTER UPDATE ON hasil_uji FOR EACH ROW
BEGIN
    DECLARE pending_param INT;
    SELECT COUNT(*) INTO pending_param
    FROM hasil_uji WHERE sampel_id = NEW.sampel_id AND kesimpulan = 'pending';

    IF pending_param = 0 THEN
        UPDATE sampel SET status = 'selesai'
        WHERE id = NEW.sampel_id AND status IN ('diuji', 'review');
    END IF;
END$$

DELIMITER ;

-- ── Sinkronisasi jumlah_sampel yang sudah ada ───────────────
UPDATE penerimaan_sampel p
SET jumlah_sampel = (
    SELECT COUNT(*) FROM sampel WHERE penerimaan_id = p.id
);
