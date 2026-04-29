-- ============================================================
--  update_user_roles.sql
--  Update role untuk user yang sudah ada
-- ============================================================

USE labmineral;

<<<<<<< HEAD
ALTER TABLE pengguna
    MODIFY COLUMN role ENUM('admin','analis','klien','supervisor','client')
    DEFAULT 'analis';

=======
>>>>>>> 50a6e1905fa6bdd226ed3ae1eee9cc6feb2442e8
-- Cek user yang ada
SELECT id, nama, username, role, status FROM pengguna;

-- Update role existing users
UPDATE pengguna SET role = 'admin' WHERE username = 'admin';
UPDATE pengguna SET role = 'analis' WHERE username IN ('rani.d', 'budi.s');
<<<<<<< HEAD
UPDATE pengguna SET role = 'client' WHERE username IN ('aneka.tm', 'freeport');
=======
UPDATE pengguna SET role = 'klien' WHERE username IN ('aneka.tm', 'freeport');
>>>>>>> 50a6e1905fa6bdd226ed3ae1eee9cc6feb2442e8

-- Tambah user supervisor jika belum ada
INSERT INTO pengguna (nama, username, password, email, role, status) 
SELECT 'Dr. Siti Aminah, M.Sc', 'supervisor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siti@lab.com', 'supervisor', 'aktif'
WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'supervisor');

-- Pastikan semua user memiliki status aktif
UPDATE pengguna SET status = 'aktif' WHERE status IS NULL OR status = '';

-- Verifikasi hasil
<<<<<<< HEAD
SELECT id, nama, username, role, status FROM pengguna ORDER BY role, nama;
=======
SELECT id, nama, username, role, status FROM pengguna ORDER BY role, nama;
>>>>>>> 50a6e1905fa6bdd226ed3ae1eee9cc6feb2442e8
