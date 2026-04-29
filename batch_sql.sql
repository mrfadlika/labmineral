-- ============================================================
--  sql/update_batch.sql
--  Jalankan di phpMyAdmin pada database labmineral
--  SETELAH labmineral.sql sudah diimport
-- ============================================================

USE labmineral;

-- ------------------------------------------------------------
-- 1. TABEL PENERIMAAN SAMPEL (Batch)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS penerimaan_sampel (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nomor_penerimaan  VARCHAR(20)  NOT NULL UNIQUE,
    klien             VARCHAR(100) NOT NULL,
    tanggal_terima    DATE         NOT NULL,
    jumlah_sampel     INT          DEFAULT 0,
    jenis_material    VARCHAR(200),   -- ringkasan material dalam batch
    metode_uji        VARCHAR(200),   -- ringkasan metode dalam batch
    keterangan        TEXT,
    status            ENUM('diterima','diproses','selesai') DEFAULT 'diterima',
    dibuat_oleh       INT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- ------------------------------------------------------------
-- 2. TAMBAH KOLOM penerimaan_id KE TABEL sampel
-- ------------------------------------------------------------
ALTER TABLE sampel
    ADD COLUMN penerimaan_id INT NULL AFTER id,
    ADD FOREIGN KEY fk_sampel_penerimaan (penerimaan_id)
        REFERENCES penerimaan_sampel(id)
        ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 3. DATA CONTOH: buat 2 batch penerimaan
-- ------------------------------------------------------------
INSERT INTO penerimaan_sampel
    (nomor_penerimaan, klien, tanggal_terima, jumlah_sampel, jenis_material, metode_uji, status, dibuat_oleh)
VALUES
('REC-2603-001', 'PT. Freeport',       '2026-03-13', 1, 'Bijih Emas',                     'Fire Assay', 'diproses', 2),
('REC-2603-002', 'PT. Aneka Tambang',  '2026-03-14', 1, 'Nikel Laterit',                   'ICP-OES',    'diterima', 3),
('REC-2603-003', 'PT. Krakatau Steel', '2026-03-10', 1, 'Bijih Besi',                      'Gravimetri', 'selesai',  2),
('REC-2603-004', 'PT. Antam',          '2026-03-11', 1, 'Bauksit',                         'XRF',        'selesai',  2),
('REC-2603-005', 'CV. Mitra Logam',    '2026-03-12', 1, 'Tembaga Oksida',                  'AAS',        'diproses', 3);

-- ------------------------------------------------------------
-- 4. UPDATE sampel lama → tautkan ke batch
-- ------------------------------------------------------------
UPDATE sampel SET penerimaan_id = (SELECT id FROM penerimaan_sampel WHERE nomor_penerimaan='REC-2603-001') WHERE kode_sampel='S-2603-046';
UPDATE sampel SET penerimaan_id = (SELECT id FROM penerimaan_sampel WHERE nomor_penerimaan='REC-2603-002') WHERE kode_sampel='S-2603-047';
UPDATE sampel SET penerimaan_id = (SELECT id FROM penerimaan_sampel WHERE nomor_penerimaan='REC-2603-003') WHERE kode_sampel='S-2603-043';
UPDATE sampel SET penerimaan_id = (SELECT id FROM penerimaan_sampel WHERE nomor_penerimaan='REC-2603-004') WHERE kode_sampel='S-2603-044';
UPDATE sampel SET penerimaan_id = (SELECT id FROM penerimaan_sampel WHERE nomor_penerimaan='REC-2603-005') WHERE kode_sampel='S-2603-045';

-- ------------------------------------------------------------
-- 5. TRIGGER: auto-update jumlah_sampel di penerimaan
-- ------------------------------------------------------------
DELIMITER $$

CREATE TRIGGER trg_sampel_after_insert
AFTER INSERT ON sampel FOR EACH ROW
BEGIN
    IF NEW.penerimaan_id IS NOT NULL THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = NEW.penerimaan_id
        )
        WHERE id = NEW.penerimaan_id;
    END IF;
END$$

CREATE TRIGGER trg_sampel_after_update
AFTER UPDATE ON sampel FOR EACH ROW
BEGIN
    IF NEW.penerimaan_id IS NOT NULL THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = NEW.penerimaan_id
        )
        WHERE id = NEW.penerimaan_id;
    END IF;
END$$

CREATE TRIGGER trg_sampel_after_delete
AFTER DELETE ON sampel FOR EACH ROW
BEGIN
    IF OLD.penerimaan_id IS NOT NULL THEN
        UPDATE penerimaan_sampel
        SET jumlah_sampel = (
            SELECT COUNT(*) FROM sampel WHERE penerimaan_id = OLD.penerimaan_id
        )
        WHERE id = OLD.penerimaan_id;
    END IF;
END$$

DELIMITER ;
