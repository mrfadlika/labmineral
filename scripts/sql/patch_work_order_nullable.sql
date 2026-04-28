USE labmineral;

-- Arsitektur baru memakai pivot work_order_sampel, jadi sampel_id legacy
-- perlu nullable untuk WO batch baru yang tidak mengisi sampel_id langsung.

SET @fk_name = (
    SELECT kcu.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE kcu
    WHERE kcu.TABLE_SCHEMA = DATABASE()
      AND kcu.TABLE_NAME = 'work_order'
      AND kcu.COLUMN_NAME = 'sampel_id'
      AND kcu.REFERENCED_TABLE_NAME = 'sampel'
    LIMIT 1
);

SET @sql = IF(
    @fk_name IS NOT NULL,
    CONCAT('ALTER TABLE work_order DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT ''FK work_order.sampel_id tidak ditemukan, dilewati'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE work_order
    MODIFY COLUMN sampel_id INT NULL
    COMMENT 'Legacy - akan di-drop setelah migrasi pivot selesai';

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'work_order'
      AND COLUMN_NAME = 'sampel_id'
      AND REFERENCED_TABLE_NAME = 'sampel'
);

SET @sql = IF(
    @fk_exists = 0,
    'ALTER TABLE work_order ADD CONSTRAINT fk_wo_sampel_legacy FOREIGN KEY (sampel_id) REFERENCES sampel(id) ON DELETE SET NULL',
    'SELECT ''FK legacy work_order.sampel_id sudah ada'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
