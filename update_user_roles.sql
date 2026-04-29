-- ============================================================
--  update_user_roles.sql
--  Update role untuk user yang sudah ada
-- ============================================================

USE labmineral;

-- Cek user yang ada
SELECT id, nama, username, role, status FROM pengguna;

-- Update role existing users
UPDATE pengguna SET role = 'admin' WHERE username = 'admin';
UPDATE pengguna SET role = 'analis' WHERE username IN ('rani.d', 'budi.s');
UPDATE pengguna SET role = 'klien' WHERE username IN ('aneka.tm', 'freeport');

-- Tambah user supervisor jika belum ada
INSERT INTO pengguna (nama, username, password, email, role, status) 
SELECT 'Dr. Siti Aminah, M.Sc', 'supervisor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siti@lab.com', 'supervisor', 'aktif'
WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'supervisor');

-- Pastikan semua user memiliki status aktif
UPDATE pengguna SET status = 'aktif' WHERE status IS NULL OR status = '';

-- Verifikasi hasil
SELECT id, nama, username, role, status FROM pengguna ORDER BY role, nama;