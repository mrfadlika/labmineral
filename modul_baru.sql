-- ============================================================
--  sql/update_modul_baru.sql
--  P1 Preparasi · P2 QC & Validasi · P3 Work Order · P4 Faktor
--  Jalankan SETELAH update_v2.sql
-- ============================================================
USE labmineral;

-- ────────────────────────────────────────────────────────────
--  P3 — WORK ORDER
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS work_order (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nomor_wo        VARCHAR(20) NOT NULL UNIQUE
                    COMMENT 'Format: WO-YYMM-NNN',
    sampel_id       INT NOT NULL,
    analis_id       INT,
    peralatan_id    INT,
    parameter       VARCHAR(200)
                    COMMENT 'Parameter yang akan diuji (koma-pisah)',
    metode          VARCHAR(50),
    prioritas       ENUM('normal','tinggi','urgent') DEFAULT 'normal',
    jadwal_mulai    DATETIME,
    jadwal_selesai  DATETIME,
    status          ENUM('draft','aktif','selesai','dibatalkan') DEFAULT 'draft',
    catatan         TEXT,
    dibuat_oleh     INT,
    disetujui_oleh  INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sampel_id)      REFERENCES sampel(id),
    FOREIGN KEY (analis_id)      REFERENCES pengguna(id),
    FOREIGN KEY (peralatan_id)   REFERENCES peralatan(id),
    FOREIGN KEY (dibuat_oleh)    REFERENCES pengguna(id),
    FOREIGN KEY (disetujui_oleh) REFERENCES pengguna(id)
);

-- 1. Tambah kolom referensi ke penerimaan di work_order
ALTER TABLE work_order
    ADD COLUMN penerimaan_id INT NULL AFTER nomor_wo,
    ADD COLUMN lingkup_batch TINYINT(1) DEFAULT 0 COMMENT '1=batch, 0=single',
    ADD FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_sampel(id);

-- 2. Tabel pivot WO ↔ Sampel (many-to-many)
CREATE TABLE work_order_sampel (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    wo_id       INT NOT NULL,
    sampel_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wo_sampel (wo_id, sampel_id),
    FOREIGN KEY (wo_id)     REFERENCES work_order(id) ON DELETE CASCADE,
    FOREIGN KEY (sampel_id) REFERENCES sampel(id)
);

-- 3. Migrasi data lama: pindahkan sampel_id lama ke tabel pivot
INSERT INTO work_order_sampel (wo_id, sampel_id)
SELECT id, sampel_id FROM work_order WHERE sampel_id IS NOT NULL;

-- 4. Hapus kolom sampel_id lama (setelah migrasi aman)
-- ALTER TABLE work_order DROP FOREIGN KEY fk_wo_sampel;
-- ALTER TABLE work_order DROP COLUMN sampel_id;
-- (jalankan setelah verifikasi data)

-- ────────────────────────────────────────────────────────────
--  P1 — PREPARASI SAMPEL
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS preparasi_sampel (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id       INT,
    sampel_id           INT NOT NULL,
    metode_preparasi    ENUM('destruksi_asam','ekstraksi','pengenceran',
                             'fusion','lainnya') NOT NULL,
    prosedur            TEXT
                        COMMENT 'Langkah-langkah preparasi yang dilakukan',
    -- Pengenceran
    faktor_pengenceran  DECIMAL(10,4) DEFAULT 1.0000
                        COMMENT '1 = tidak diencerkan',
    volume_awal_ml      DECIMAL(8,3),
    volume_akhir_ml     DECIMAL(8,3),
    -- Reagen yang digunakan
    reagen_detail       TEXT
                        COMMENT 'JSON: [{bahan_id, nama, volume_ml, lot}]',
    -- QC preparasi
    blanko_disiapkan    TINYINT(1) DEFAULT 0,
    standar_disiapkan   TINYINT(1) DEFAULT 0,
    spike_disiapkan     TINYINT(1) DEFAULT 0,
    duplikat_disiapkan  TINYINT(1) DEFAULT 0,
    -- Kondisi lingkungan
    suhu_ruang          DECIMAL(5,2)  COMMENT 'Celcius',
    kelembaban          DECIMAL(5,2)  COMMENT 'Persen',
    catatan             TEXT,
    analis_id           INT,
    tanggal_preparasi   DATE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_order_id) REFERENCES work_order(id) ON DELETE SET NULL,
    FOREIGN KEY (sampel_id)     REFERENCES sampel(id),
    FOREIGN KEY (analis_id)     REFERENCES pengguna(id)
);

-- ────────────────────────────────────────────────────────────
--  P2 — QC SAMPEL
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS qc_sampel (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    hasil_uji_id    INT,              -- NULL jika QC berdiri sendiri
    preparasi_id    INT,
    sampel_id       INT NOT NULL,
    tipe_qc         ENUM('blanko','standar','spike','duplikat') NOT NULL,
    parameter       VARCHAR(100),
    -- Nilai
    nilai_qc        DECIMAL(12,4),    -- Nilai yang terukur
    nilai_expected  DECIMAL(12,4),    -- Nilai yang seharusnya
    satuan          VARCHAR(20),
    -- Evaluasi
    persen_recovery DECIMAL(8,4)
                    COMMENT '(nilai_qc / nilai_expected) * 100',
    batas_min_pct   DECIMAL(6,2) DEFAULT 85.00
                    COMMENT 'Batas bawah % recovery (default 85%)',
    batas_maks_pct  DECIMAL(6,2) DEFAULT 115.00
                    COMMENT 'Batas atas % recovery (default 115%)',
    flag            ENUM('pass','fail','warning') DEFAULT 'pass',
    -- Review
    status_qc       ENUM('pending','disetujui','ditolak') DEFAULT 'pending',
    reviewer_id     INT,
    catatan_review  TEXT,
    tanggal_uji     DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hasil_uji_id)  REFERENCES hasil_uji(id) ON DELETE SET NULL,
    FOREIGN KEY (preparasi_id)  REFERENCES preparasi_sampel(id) ON DELETE SET NULL,
    FOREIGN KEY (sampel_id)     REFERENCES sampel(id),
    FOREIGN KEY (reviewer_id)   REFERENCES pengguna(id)
);

-- ────────────────────────────────────────────────────────────
--  P4 — ALTER hasil_uji: faktor pengenceran & konversi satuan
-- ────────────────────────────────────────────────────────────
ALTER TABLE hasil_uji
    ADD COLUMN faktor_pengenceran  DECIMAL(10,4) DEFAULT 1.0000
        COMMENT 'Faktor pengenceran saat preparasi (≥1)'
        AFTER nilai,
    ADD COLUMN nilai_terkoreksi    DECIMAL(12,4) DEFAULT NULL
        COMMENT 'nilai × faktor_pengenceran × faktor_konversi'
        AFTER faktor_pengenceran,
    ADD COLUMN satuan_asli         VARCHAR(20)   DEFAULT NULL
        COMMENT 'Satuan langsung dari instrumen'
        AFTER satuan,
    ADD COLUMN satuan_laporan      VARCHAR(20)   DEFAULT NULL
        COMMENT 'Satuan yang dilaporkan ke klien'
        AFTER satuan_asli,
    ADD COLUMN faktor_konversi     DECIMAL(14,8) DEFAULT 1.00000000
        COMMENT 'Faktor konversi satuan asli → satuan laporan'
        AFTER satuan_laporan,
    ADD COLUMN preparasi_id        INT           DEFAULT NULL
        AFTER no_referensi,
    ADD FOREIGN KEY fk_hu_prep (preparasi_id)
        REFERENCES preparasi_sampel(id) ON DELETE SET NULL;

-- Isi nilai_terkoreksi untuk data existing (semua faktor = 1)
UPDATE hasil_uji
SET nilai_terkoreksi = nilai,
    satuan_asli      = satuan,
    satuan_laporan   = satuan
WHERE nilai_terkoreksi IS NULL;

-- Trigger: hitung nilai_terkoreksi otomatis
DELIMITER $$

CREATE TRIGGER trg_hitung_koreksi_insert
BEFORE INSERT ON hasil_uji FOR EACH ROW
BEGIN
    SET NEW.nilai_terkoreksi =
        NEW.nilai
        * COALESCE(NEW.faktor_pengenceran, 1.0)
        * COALESCE(NEW.faktor_konversi,   1.0);
    IF NEW.satuan_laporan IS NULL THEN
        SET NEW.satuan_laporan = NEW.satuan;
    END IF;
    IF NEW.satuan_asli IS NULL THEN
        SET NEW.satuan_asli = NEW.satuan;
    END IF;
END$$

CREATE TRIGGER trg_hitung_koreksi_update
BEFORE UPDATE ON hasil_uji FOR EACH ROW
BEGIN
    SET NEW.nilai_terkoreksi =
        NEW.nilai
        * COALESCE(NEW.faktor_pengenceran, 1.0)
        * COALESCE(NEW.faktor_konversi,   1.0);
END$$

-- Trigger: hitung persen_recovery di qc_sampel otomatis
CREATE TRIGGER trg_qc_recovery_insert
BEFORE INSERT ON qc_sampel FOR EACH ROW
BEGIN
    IF NEW.nilai_expected IS NOT NULL AND NEW.nilai_expected > 0 THEN
        SET NEW.persen_recovery = (NEW.nilai_qc / NEW.nilai_expected) * 100;
        IF NEW.persen_recovery < NEW.batas_min_pct
           OR NEW.persen_recovery > NEW.batas_maks_pct THEN
            SET NEW.flag = 'fail';
        ELSEIF NEW.persen_recovery < (NEW.batas_min_pct + 5)
            OR NEW.persen_recovery > (NEW.batas_maks_pct - 5) THEN
            SET NEW.flag = 'warning';
        ELSE
            SET NEW.flag = 'pass';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_qc_recovery_update
BEFORE UPDATE ON qc_sampel FOR EACH ROW
BEGIN
    IF NEW.nilai_expected IS NOT NULL AND NEW.nilai_expected > 0 THEN
        SET NEW.persen_recovery = (NEW.nilai_qc / NEW.nilai_expected) * 100;
        IF NEW.persen_recovery < NEW.batas_min_pct
           OR NEW.persen_recovery > NEW.batas_maks_pct THEN
            SET NEW.flag = 'fail';
        ELSEIF NEW.persen_recovery < (NEW.batas_min_pct + 5)
            OR NEW.persen_recovery > (NEW.batas_maks_pct - 5) THEN
            SET NEW.flag = 'warning';
        ELSE
            SET NEW.flag = 'pass';
        END IF;
    END IF;
END$$

DELIMITER ;

-- ────────────────────────────────────────────────────────────
--  Data contoh work order
-- ────────────────────────────────────────────────────────────
INSERT INTO work_order (nomor_wo, sampel_id, analis_id, peralatan_id,
    parameter, metode, prioritas, jadwal_mulai, jadwal_selesai,
    status, dibuat_oleh)
VALUES
('WO-2603-001', 4, 2, 1, 'Au (Emas), Ag (Perak)', 'Fire Assay',
 'normal', '2026-03-13 08:00:00', '2026-03-13 16:00:00', 'selesai', 1),
('WO-2603-002', 5, 3, 4, 'Ni (Nikel), Fe (Besi)', 'ICP-OES',
 'tinggi', '2026-03-14 08:00:00', '2026-03-14 14:00:00', 'aktif',  1);
