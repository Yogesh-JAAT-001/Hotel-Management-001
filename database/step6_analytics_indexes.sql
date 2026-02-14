-- Step 6: Analytics indexes for faster BI queries (routine-free version)
-- This script avoids stored procedures so it can run on MariaDB instances
-- where mysql.proc metadata is out of sync.
-- Safe to re-run.

CREATE DATABASE IF NOT EXISTS heartland_abode;
USE heartland_abode;

SET @db_name = DATABASE();

-- ----------------------------
-- RESERVATION indexes
-- ----------------------------
SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND INDEX_NAME = 'idx_reservation_room_status_dates') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND COLUMN_NAME IN ('room_id','status','check_in','check_out')) = 4,
    'CREATE INDEX idx_reservation_room_status_dates ON RESERVATION (room_id, status, check_in, check_out)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND INDEX_NAME = 'idx_reservation_guest_dates') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND COLUMN_NAME IN ('guest_id','check_in','check_out')) = 3,
    'CREATE INDEX idx_reservation_guest_dates ON RESERVATION (guest_id, check_in, check_out)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND INDEX_NAME = 'idx_reservation_created_status') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND COLUMN_NAME IN ('created_at','status')) = 2,
    'CREATE INDEX idx_reservation_created_status ON RESERVATION (created_at, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND INDEX_NAME = 'idx_reservation_r_date_status') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION'
        AND COLUMN_NAME IN ('r_date','status')) = 2,
    'CREATE INDEX idx_reservation_r_date_status ON RESERVATION (r_date, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- RESERVATION_FOOD indexes
-- ----------------------------
SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION_FOOD') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION_FOOD'
        AND INDEX_NAME = 'idx_res_food_res_food') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION_FOOD'
        AND COLUMN_NAME IN ('res_id','food_id')) = 2,
    'CREATE INDEX idx_res_food_res_food ON RESERVATION_FOOD (res_id, food_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION_FOOD') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION_FOOD'
        AND INDEX_NAME = 'idx_res_food_created') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'RESERVATION_FOOD'
        AND COLUMN_NAME = 'created_at') = 1,
    'CREATE INDEX idx_res_food_created ON RESERVATION_FOOD (created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- FOOD_DINING indexes
-- ----------------------------
SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'FOOD_DINING') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'FOOD_DINING'
        AND INDEX_NAME = 'idx_food_menu_category') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'FOOD_DINING'
        AND COLUMN_NAME = 'menu_category') = 1,
    'CREATE INDEX idx_food_menu_category ON FOOD_DINING (menu_category)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'FOOD_DINING') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'FOOD_DINING'
        AND INDEX_NAME = 'idx_food_type_availability') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'FOOD_DINING'
        AND COLUMN_NAME IN ('food_type','is_available')) = 2,
    'CREATE INDEX idx_food_type_availability ON FOOD_DINING (food_type, is_available)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- ROOMS indexes
-- ----------------------------
SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ROOMS') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ROOMS'
        AND INDEX_NAME = 'idx_rooms_type_status') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ROOMS'
        AND COLUMN_NAME IN ('room_type_id','status')) = 2,
    'CREATE INDEX idx_rooms_type_status ON ROOMS (room_type_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ROOMS') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ROOMS'
        AND INDEX_NAME = 'idx_rooms_room_no') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'ROOMS'
        AND COLUMN_NAME = 'room_no') = 1,
    'CREATE INDEX idx_rooms_room_no ON ROOMS (room_no)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- PAYMENTS indexes
-- ----------------------------
SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'PAYMENTS') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'PAYMENTS'
        AND INDEX_NAME = 'idx_payments_created_status') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'PAYMENTS'
        AND COLUMN_NAME IN ('created_at','status')) = 2,
    'CREATE INDEX idx_payments_created_status ON PAYMENTS (created_at, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'PAYMENTS') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'PAYMENTS'
        AND INDEX_NAME = 'idx_payments_res_status') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'PAYMENTS'
        AND COLUMN_NAME IN ('res_id','status')) = 2,
    'CREATE INDEX idx_payments_res_status ON PAYMENTS (res_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- OPERATING_COSTS indexes
-- ----------------------------
SET @sql_idx := IF(
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'OPERATING_COSTS') > 0
    AND
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'OPERATING_COSTS'
        AND INDEX_NAME = 'idx_cost_month_category_amount') = 0
    AND
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'OPERATING_COSTS'
        AND COLUMN_NAME IN ('cost_month','category','amount')) = 3,
    'CREATE INDEX idx_cost_month_category_amount ON OPERATING_COSTS (cost_month, category, amount)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

