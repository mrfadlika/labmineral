USE labmineral;

CREATE TABLE IF NOT EXISTS submission_sampel (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nomor_submission  VARCHAR(30) NOT NULL UNIQUE,
    klien             VARCHAR(100) NOT NULL,
    kontak_person     VARCHAR(100) DEFAULT NULL,
    email             VARCHAR(100) NOT NULL,
    telepon           VARCHAR(30) DEFAULT NULL,
    alamat            TEXT DEFAULT NULL,
    po_referensi      VARCHAR(100) DEFAULT NULL,
    instruksi_khusus  TEXT DEFAULT NULL,
    catatan           TEXT DEFAULT NULL,
    status            ENUM('pending','diterima','ditolak','diproses') DEFAULT 'pending',
    tanggal_submit    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS submission_sampel_detail (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    submission_id   INT NOT NULL,
    jenis_material  VARCHAR(100) NOT NULL,
    berat_gram      DECIMAL(10,3) DEFAULT NULL,
    metode_uji      VARCHAR(50) DEFAULT NULL,
    parameter       VARCHAR(255) DEFAULT NULL,
    keterangan      TEXT DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_submission_detail_submission_id (submission_id),
    CONSTRAINT fk_submission_detail_submission
        FOREIGN KEY (submission_id) REFERENCES submission_sampel(id)
        ON DELETE CASCADE
);
