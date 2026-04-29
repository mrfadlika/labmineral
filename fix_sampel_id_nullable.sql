-- ============================================================
--  fix_sampel_id_nullable.sql
--  Perbaikan error 1452: FK constraint fails pada work_order.sampel_id
--
--  Root cause:
--  Kolom sampel_id di work_order masih NOT NULL (dari schema lama).
--  INSERT batch baru tidak mengisi sampel_id (by design),
--  sehingga FK constraint gagal.
--
--  Solusi: ubah sampel_id menjadi NULL-able karena arsitektur baru
--  sudah menggunakan pivot work_order_sampel sebagai relasi utama.
--  sampel_id lama tetap dipertahankan untuk backward-compat
--  data historis hingga siap di-drop.
-- ============================================================

USE labmineral;

-- ── STEP 1: Cari nama FK constraint untuk sampel_id ──────────
-- Jalankan ini dulu untuk konfirmasi nama constraint:
SELECT CONSTRAINT_NAME
FROM   information_schema.KEY_COLUMN_USAGE
WHERE  TABLE_SCHEMA           = DATABASE()
  AND  TABLE_NAME             = 'work_order'
  AND  COLUMN_NAME            = 'sampel_id'
  AND  REFERENCED_TABLE_NAME IS NOT NULL;

-- ── STEP 2: Drop FK lama, ubah kolom jadi NULL-able ──────────
-- MySQL tidak bisa ALTER kolom yang punya FK aktif,
-- jadi FK harus di-drop dulu lalu dibuat ulang.

SET FOREIGN_KEY_CHECKS = 0;

-- Ubah sampel_id: NOT NULL → NULL (pertahankan FK, hanya ubah nullable)
-- Nama constraint default dari modul_baru.sql adalah 'work_order_ibfk_1'
-- Sesuaikan jika nama FK berbeda (lihat hasil STEP 1 di atas)
ALTER TABLE work_order
    DROP FOREIGN KEY work_order_ibfk_1;

ALTER TABLE work_order
    MODIFY COLUMN sampel_id INT NULL
    COMMENT 'Legacy — akan di-drop setelah migrasi pivot selesai';

-- Buat ulang FK dengan ON DELETE SET NULL
ALTER TABLE work_order
    ADD CONSTRAINT fk_wo_sampel_legacy
    FOREIGN KEY (sampel_id) REFERENCES sampel(id)
    ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ── STEP 3: Verifikasi ────────────────────────────────────────
SELECT
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'work_order'
  AND COLUMN_NAME  IN ('sampel_id', 'penerimaan_id', 'lingkup_batch');
