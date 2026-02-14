CREATE DATABASE IF NOT EXISTS heartland_abode;
USE heartland_abode;

-- Deterministic image path mapping based on food name/title.
-- Note: this SQL sets expected JPG paths. To validate file existence and fallback automatically,
-- run Admin > Food & Dining > Auto Map Images button after import.

SET @name_col := (
    SELECT CASE
        WHEN EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FOOD_DINING' AND COLUMN_NAME = 'title'
        ) THEN 'title'
        WHEN EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FOOD_DINING' AND COLUMN_NAME = 'food_name'
        ) THEN 'food_name'
        ELSE 'name'
    END
);

SET @image_col := (
    SELECT CASE
        WHEN EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FOOD_DINING' AND COLUMN_NAME = 'image_path'
        ) THEN 'image_path'
        ELSE 'image'
    END
);

SET @sql := CONCAT(
    'UPDATE FOOD_DINING ',
    'SET ', @image_col, ' = CONCAT(''assets/images/food/'', ', @name_col, ', ''.jpg'')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
