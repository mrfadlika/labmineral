USE labmineral;

-- Sinkronkan enum role dengan kode aplikasi yang sudah mengenal role supervisor.
ALTER TABLE pengguna
<<<<<<< HEAD
    MODIFY COLUMN role ENUM('admin','analis','klien','supervisor','client')
=======
    MODIFY COLUMN role ENUM('admin','analis','klien','supervisor')
>>>>>>> 50a6e1905fa6bdd226ed3ae1eee9cc6feb2442e8
    DEFAULT 'analis';
