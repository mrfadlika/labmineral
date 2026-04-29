-- Jalankan file ini dari root project dengan mysql client.
-- Contoh:
-- mysql -uroot < scripts/sql/setup_labmineral.sql

SOURCE scripts/sql/create_database_labmineral.sql;
SOURCE labmineral.sql;
SOURCE batch_sql.sql;
SOURCE fix.sql;
SOURCE modul_baru.sql;
SOURCE invoice.sql;
SOURCE fix_work_order_batch.sql;
SOURCE scripts/sql/patch_work_order_nullable.sql;
SOURCE scripts/sql/patch_supervisor_role.sql;
SOURCE update_user_roles.sql;
