USE labmineral;

-- Role baru: client. Role lama "klien" tetap dibiarkan sementara
-- agar data lama tetap terbaca oleh aplikasi.
ALTER TABLE pengguna
    MODIFY COLUMN role ENUM('admin','analis','klien','supervisor','client')
    DEFAULT 'analis';

UPDATE pengguna SET role = 'client' WHERE role = 'klien';

-- Tabel submission publik. Beberapa file aplikasi sudah memakai tabel ini,
-- patch ini membuat setup database baru menjadi lengkap.
CREATE TABLE IF NOT EXISTS submission_sampel (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nomor_submission  VARCHAR(30)  NOT NULL UNIQUE,
    klien             VARCHAR(150) NOT NULL,
    kontak_person     VARCHAR(150),
    email             VARCHAR(150) NOT NULL,
    telepon           VARCHAR(50),
    alamat            TEXT,
    po_referensi      VARCHAR(100),
    instruksi_khusus  TEXT,
    catatan           TEXT,
    status            ENUM('pending','diterima','diproses','ditolak') DEFAULT 'pending',
    tanggal_submit    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE submission_sampel
    MODIFY COLUMN status ENUM('pending','diterima','diproses','ditolak')
    DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS submission_sampel_detail (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    submission_id   INT NOT NULL,
    jenis_material  VARCHAR(100) NOT NULL,
    berat_gram      DECIMAL(10,3),
    metode_uji      VARCHAR(50),
    parameter       VARCHAR(100),
    keterangan      TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_detail_submission
        FOREIGN KEY (submission_id) REFERENCES submission_sampel(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relasi akun client sementara ke submission/penerimaan.
CREATE TABLE IF NOT EXISTS client_access (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    pengguna_id    INT NOT NULL,
    submission_id  INT NULL,
    penerimaan_id  INT NULL,
    kode_akses     VARCHAR(40)  NOT NULL UNIQUE,
    klien          VARCHAR(150) NOT NULL,
    email          VARCHAR(150),
    status         ENUM('aktif','selesai','ditutup') DEFAULT 'aktif',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_client_access_pengguna (pengguna_id),
    KEY idx_client_access_submission (submission_id),
    KEY idx_client_access_penerimaan (penerimaan_id),

    CONSTRAINT fk_client_access_pengguna
        FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_client_access_submission
        FOREIGN KEY (submission_id) REFERENCES submission_sampel(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_client_access_penerimaan
        FOREIGN KEY (penerimaan_id) REFERENCES penerimaan_sampel(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
