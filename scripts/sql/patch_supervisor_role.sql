USE labmineral;

-- Sinkronkan enum role dengan kode aplikasi yang sudah mengenal role supervisor.
ALTER TABLE pengguna
    MODIFY COLUMN role ENUM('admin','analis','klien','supervisor')
    DEFAULT 'analis';
