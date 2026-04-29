-- ============================================================
--  sql/update_invoice.sql
--  Jalankan setelah update_modul_baru.sql
-- ============================================================
USE labmineral;

-- ── Tarif pengujian per parameter/metode ────────────────────
CREATE TABLE IF NOT EXISTS tarif_pengujian (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(150) NOT NULL,
    metode      VARCHAR(50),
    parameter   VARCHAR(100),
    harga       DECIMAL(12,0) NOT NULL DEFAULT 0
                COMMENT 'Harga dalam Rupiah',
    satuan      VARCHAR(50)   DEFAULT 'per parameter'
                COMMENT 'per parameter / per sampel / per batch',
    aktif       TINYINT(1)    DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO tarif_pengujian (nama, metode, parameter, harga, satuan) VALUES
('AAS — Logam tunggal',        'AAS',        NULL,         250000, 'per parameter'),
('XRF — Oksida mayor',         'XRF',        NULL,         300000, 'per parameter'),
('ICP-OES — Multi elemen',     'ICP-OES',    NULL,         350000, 'per parameter'),
('Gravimetri',                 'Gravimetri', NULL,         200000, 'per parameter'),
('Fire Assay — Au',            'Fire Assay', 'Au (Emas)',  500000, 'per sampel'),
('Fire Assay — Ag',            'Fire Assay', 'Ag (Perak)', 400000, 'per sampel'),
('Preparasi Destruksi Asam',   NULL,         NULL,         150000, 'per sampel'),
('Preparasi Fusion',           NULL,         NULL,         200000, 'per sampel');

-- ── Invoice ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoice (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nomor_invoice       VARCHAR(30) NOT NULL UNIQUE
                        COMMENT 'Format: INV-YYMM-NNN',
    penerimaan_id       INT
                        COMMENT 'Terkait batch penerimaan',
    klien               VARCHAR(150) NOT NULL,
    alamat_klien        TEXT,
    tanggal_invoice     DATE NOT NULL,
    tanggal_jatuh_tempo DATE,
    subtotal            DECIMAL(14,0) DEFAULT 0,
    diskon_pct          DECIMAL(5,2)  DEFAULT 0.00,
    diskon_nominal      DECIMAL(14,0) DEFAULT 0,
    ppn_pct             DECIMAL(5,2)  DEFAULT 11.00,
    ppn_nominal         DECIMAL(14,0) DEFAULT 0,
    total               DECIMAL(14,0) DEFAULT 0,
    status              ENUM('draft','diterbitkan','lunas','dibatalkan') DEFAULT 'draft',
    catatan             TEXT,
    dibuat_oleh         INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_sampel(id) ON DELETE SET NULL,
    FOREIGN KEY (dibuat_oleh)   REFERENCES pengguna(id)
);

-- ── Item invoice ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoice_item (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT NOT NULL,
    deskripsi   VARCHAR(250) NOT NULL,
    sampel_id   INT,
    tarif_id    INT,
    qty         INT           DEFAULT 1,
    harga_satuan DECIMAL(12,0) DEFAULT 0,
    subtotal    DECIMAL(14,0)  DEFAULT 0,
    catatan     VARCHAR(200),
    FOREIGN KEY (invoice_id) REFERENCES invoice(id) ON DELETE CASCADE,
    FOREIGN KEY (sampel_id)  REFERENCES sampel(id)  ON DELETE SET NULL,
    FOREIGN KEY (tarif_id)   REFERENCES tarif_pengujian(id) ON DELETE SET NULL
);

-- ── Trigger: hitung total invoice otomatis ───────────────────
DELIMITER $$

CREATE TRIGGER trg_inv_item_insert
AFTER INSERT ON invoice_item FOR EACH ROW
BEGIN
    CALL recalc_invoice(NEW.invoice_id);
END$$

CREATE TRIGGER trg_inv_item_update
AFTER UPDATE ON invoice_item FOR EACH ROW
BEGIN
    CALL recalc_invoice(NEW.invoice_id);
END$$

CREATE TRIGGER trg_inv_item_delete
AFTER DELETE ON invoice_item FOR EACH ROW
BEGIN
    CALL recalc_invoice(OLD.invoice_id);
END$$

CREATE PROCEDURE recalc_invoice(IN inv_id INT)
BEGIN
    DECLARE v_sub   DECIMAL(14,0);
    DECLARE v_dis   DECIMAL(5,2);
    DECLARE v_ppn   DECIMAL(5,2);
    DECLARE v_dis_n DECIMAL(14,0);
    DECLARE v_ppn_n DECIMAL(14,0);
    DECLARE v_total DECIMAL(14,0);

    SELECT SUM(subtotal) INTO v_sub
    FROM invoice_item WHERE invoice_id = inv_id;

    SELECT diskon_pct, ppn_pct INTO v_dis, v_ppn
    FROM invoice WHERE id = inv_id;

    SET v_sub   = COALESCE(v_sub, 0);
    SET v_dis_n = ROUND(v_sub * v_dis / 100);
    SET v_ppn_n = ROUND((v_sub - v_dis_n) * v_ppn / 100);
    SET v_total = v_sub - v_dis_n + v_ppn_n;

    UPDATE invoice
    SET subtotal       = v_sub,
        diskon_nominal = v_dis_n,
        ppn_nominal    = v_ppn_n,
        total          = v_total
    WHERE id = inv_id;
END$$

DELIMITER ;
