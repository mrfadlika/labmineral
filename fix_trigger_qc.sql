DELIMITER $$
DROP TRIGGER IF EXISTS trg_qc_recovery_insert$$
CREATE TRIGGER trg_qc_recovery_insert
BEFORE INSERT ON qc_sampel
FOR EACH ROW
BEGIN
    IF NEW.nilai_expected IS NOT NULL AND NEW.nilai_expected > 0 THEN
        SET NEW.persen_recovery = (NEW.nilai_qc / NEW.nilai_expected) * 100;
        IF ROUND(ABS(NEW.nilai_qc - NEW.nilai_expected), 4) <= 0.05 THEN
            SET NEW.flag = 'pass';
        ELSE
            SET NEW.flag = 'fail';
        END IF;
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_qc_recovery_update$$
CREATE TRIGGER trg_qc_recovery_update
BEFORE UPDATE ON qc_sampel
FOR EACH ROW
BEGIN
    IF NEW.nilai_expected IS NOT NULL AND NEW.nilai_expected > 0 THEN
        SET NEW.persen_recovery = (NEW.nilai_qc / NEW.nilai_expected) * 100;
        IF ROUND(ABS(NEW.nilai_qc - NEW.nilai_expected), 4) <= 0.05 THEN
            SET NEW.flag = 'pass';
        ELSE
            SET NEW.flag = 'fail';
        END IF;
    END IF;
END$$
DELIMITER ;
