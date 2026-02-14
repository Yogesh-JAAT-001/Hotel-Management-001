USE heartland_abode;

-- ============================================================
-- Step 7: Advanced Analytics & BI Seed (Re-runnable)
-- Purpose:
-- 1) Add realistic high-volume hotel operations data for BI dashboards
-- 2) Ensure staff performance/attendance structures exist
-- 3) Populate past + future reservations, food orders, payments, and costs
-- ============================================================

-- ---------- Safety prerequisites ----------
SET @today := CURDATE();
-- Seed data window: covers ~8 months back and ~5 months forward relative to today.
SET @seed_base := DATE_SUB(@today, INTERVAL 240 DAY);

-- Ensure operating costs table exists for economics module.
CREATE TABLE IF NOT EXISTS OPERATING_COSTS (
    cost_id INT AUTO_INCREMENT PRIMARY KEY,
    cost_month DATE NOT NULL,
    category ENUM('Staff', 'Electricity', 'Maintenance', 'Water') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_month_category (cost_month, category),
    INDEX idx_cost_month (cost_month),
    INDEX idx_cost_category (category)
);

-- Ensure menu category exists in FOOD_DINING.
SET @has_menu_category := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'FOOD_DINING'
      AND COLUMN_NAME = 'menu_category'
);
SET @sql_add_menu_category := IF(
    @has_menu_category = 0,
    'ALTER TABLE FOOD_DINING ADD COLUMN menu_category VARCHAR(30) NOT NULL DEFAULT ''Main Course'' AFTER food_type',
    'SELECT 1'
);
PREPARE stmt_menu_category FROM @sql_add_menu_category;
EXECUTE stmt_menu_category;
DEALLOCATE PREPARE stmt_menu_category;

UPDATE FOOD_DINING
SET menu_category = 'Main Course'
WHERE menu_category IS NULL OR TRIM(menu_category) = '';

-- Ensure room type names support BI labels across schema variants.
ALTER TABLE ROOM_TYPE
MODIFY COLUMN name VARCHAR(50) NOT NULL;

-- ---------- Staff schema extensions ----------
SET @has_role := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'role'
);
SET @sql_add_role := IF(
    @has_role = 0,
    'ALTER TABLE STAFF ADD COLUMN role ENUM(''Manager'',''Receptionist'',''Chef'',''Housekeeping'',''Finance'') NULL',
    'SELECT 1'
);
PREPARE stmt_role FROM @sql_add_role;
EXECUTE stmt_role;
DEALLOCATE PREPARE stmt_role;

SET @has_performance := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'performance_score'
);
SET @sql_add_performance := IF(
    @has_performance = 0,
    'ALTER TABLE STAFF ADD COLUMN performance_score DECIMAL(5,2) NOT NULL DEFAULT 75.00 AFTER role',
    'SELECT 1'
);
PREPARE stmt_performance FROM @sql_add_performance;
EXECUTE stmt_performance;
DEALLOCATE PREPARE stmt_performance;

SET @has_attendance_rate := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'attendance_rate'
);
SET @sql_add_attendance_rate := IF(
    @has_attendance_rate = 0,
    'ALTER TABLE STAFF ADD COLUMN attendance_rate DECIMAL(5,2) NOT NULL DEFAULT 90.00 AFTER performance_score',
    'SELECT 1'
);
PREPARE stmt_attendance_rate FROM @sql_add_attendance_rate;
EXECUTE stmt_attendance_rate;
DEALLOCATE PREPARE stmt_attendance_rate;

SET @has_last_activity := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'last_activity_at'
);
SET @sql_add_last_activity := IF(
    @has_last_activity = 0,
    'ALTER TABLE STAFF ADD COLUMN last_activity_at DATETIME NULL AFTER attendance_rate',
    'SELECT 1'
);
PREPARE stmt_last_activity FROM @sql_add_last_activity;
EXECUTE stmt_last_activity;
DEALLOCATE PREPARE stmt_last_activity;

-- Staff attendance/activity simulation table.
CREATE TABLE IF NOT EXISTS STAFF_ATTENDANCE (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Late', 'Leave', 'Off') NOT NULL DEFAULT 'Present',
    hours_worked DECIMAL(4,2) NOT NULL DEFAULT 8.00,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_staff_day (staff_id, attendance_date),
    INDEX idx_attendance_date (attendance_date),
    INDEX idx_attendance_status (status),
    CONSTRAINT fk_attendance_staff FOREIGN KEY (staff_id) REFERENCES STAFF(staff_id) ON DELETE CASCADE
);

-- ---------- Base entities ----------
SET @dept_name_col := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'DEPARTMENT' AND COLUMN_NAME = 'name') > 0,
    'name',
    'dep_name'
);
SET @has_dept_description := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'DEPARTMENT' AND COLUMN_NAME = 'description'
);

DROP TEMPORARY TABLE IF EXISTS tmp_department_seed;
CREATE TEMPORARY TABLE tmp_department_seed (
    dept_name VARCHAR(80),
    dept_description VARCHAR(255)
);

INSERT INTO tmp_department_seed (dept_name, dept_description)
VALUES
('Management', 'Executive and strategic operations'),
('Reception', 'Front office and guest check-in operations'),
('Food & Beverage', 'Kitchen and dining operations'),
('Housekeeping', 'Room readiness and cleanliness'),
('Finance', 'Revenue accounting and financial controls'),
('Maintenance', 'Engineering and utility services');

SET @sql_insert_departments := IF(
    @has_dept_description > 0,
    CONCAT(
        'INSERT INTO DEPARTMENT (', @dept_name_col, ', description) ',
        'SELECT t.dept_name, t.dept_description FROM tmp_department_seed t ',
        'WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT d WHERE LOWER(d.', @dept_name_col, ') = LOWER(t.dept_name))'
    ),
    CONCAT(
        'INSERT INTO DEPARTMENT (', @dept_name_col, ') ',
        'SELECT t.dept_name FROM tmp_department_seed t ',
        'WHERE NOT EXISTS (SELECT 1 FROM DEPARTMENT d WHERE LOWER(d.', @dept_name_col, ') = LOWER(t.dept_name))'
    )
);
PREPARE stmt_insert_departments FROM @sql_insert_departments;
EXECUTE stmt_insert_departments;
DEALLOCATE PREPARE stmt_insert_departments;

-- Ensure enough guest records for broad booking history.
DROP TEMPORARY TABLE IF EXISTS tmp_seed_seq;
CREATE TEMPORARY TABLE tmp_seed_seq (n INT PRIMARY KEY);
INSERT INTO tmp_seed_seq (n)
SELECT a.i + b.i * 10 + c.i * 100 + 1 AS n
FROM (
    SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) a
CROSS JOIN (
    SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) b
CROSS JOIN (
    SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2
) c
WHERE a.i + b.i * 10 + c.i * 100 + 1 <= 220;

INSERT INTO GUEST (name, email, phone_no, address, password)
SELECT
    CONCAT('Analytics Guest ', LPAD(n, 3, '0')),
    CONCAT('analytics.guest', LPAD(n, 3, '0'), '@heartlandabode.local'),
    CONCAT('98', LPAD(10000000 + n, 8, '0')),
    CONCAT('Sector ', (n % 25) + 1, ', New Delhi'),
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
FROM tmp_seed_seq
WHERE n <= 60
  AND NOT EXISTS (
      SELECT 1 FROM GUEST g
      WHERE g.email = CONCAT('analytics.guest', LPAD(n, 3, '0'), '@heartlandabode.local')
  );

-- Ensure room type labels used by BI are present.
INSERT INTO ROOM_TYPE (name, description)
SELECT 'Standard', 'Royal Standard Wing inventory'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Standard');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Deluxe', 'Deluxe Heritage Wing inventory'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Deluxe');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Executive', 'Executive Wing inventory'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Executive');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Royal', 'Royal Wing inventory'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Royal');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Presidential', 'Presidential Wing inventory'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Presidential');

-- ---------- Admin bootstrap (ensure at least one admin account) ----------
-- Admin login uses email + password_verify(), so we seed a bcrypt password.
SET @has_admin_users := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
);
SET @has_admin_username := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'username'
);
SET @has_admin_full_name := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'full_name'
);
SET @has_admin_role := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'role'
);
SET @has_admin_is_active := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'is_active'
);

SET @admin_name_column := CASE
    WHEN @has_admin_username > 0 THEN 'username'
    WHEN @has_admin_full_name > 0 THEN 'full_name'
    ELSE ''
END;

SET @admin_role_column_type := (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admin_users'
      AND COLUMN_NAME = 'role'
    LIMIT 1
);
SET @admin_role_value := CASE
    WHEN @admin_role_column_type IS NULL OR @admin_role_column_type = '' THEN 'SuperAdmin'
    WHEN LOWER(@admin_role_column_type) LIKE '%superadmin%' THEN 'SuperAdmin'
    WHEN LOWER(@admin_role_column_type) LIKE '%admin%' THEN 'Admin'
    WHEN LOWER(@admin_role_column_type) LIKE '%manager%' THEN 'Manager'
    ELSE 'Manager'
END;
SET @admin_insert_columns := CONCAT(
    IF(@admin_name_column <> '', CONCAT(@admin_name_column, ', '), ''),
    'email, password',
    IF(@has_admin_role > 0, ', role', ''),
    IF(@has_admin_is_active > 0, ', is_active', '')
);
SET @admin_insert_values := CONCAT(
    IF(@admin_name_column <> '', '''Yogesh Admin'', ', ''),
    '''yogeshkumar@heartlandabode.com'', ''$2y$10$brK0YkVjVXeTUZot3Oy/FOTP17OspHUIKVJ0M4p6isBZXzqWpwV2u''',
    IF(@has_admin_role > 0, CONCAT(', ', QUOTE(@admin_role_value)), ''),
    IF(@has_admin_is_active > 0, ', 1', '')
);

SET @sql_seed_admin := IF(
    @has_admin_users > 0,
    CONCAT(
        'INSERT INTO admin_users (', @admin_insert_columns, ') ',
        'SELECT ', @admin_insert_values, ' ',
        'WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE LOWER(email) = LOWER(''yogeshkumar@heartlandabode.com'') LIMIT 1)'
    ),
    'SELECT 1'
);
PREPARE stmt_seed_admin FROM @sql_seed_admin;
EXECUTE stmt_seed_admin;
DEALLOCATE PREPARE stmt_seed_admin;

-- ---------- Staff seed (25 records, includes Rhode names) ----------
SET @dept_pk := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'DEPARTMENT' AND COLUMN_NAME = 'dept_id') > 0,
    'dept_id',
    'dep_id'
);
SET @dept_name := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'DEPARTMENT' AND COLUMN_NAME = 'name') > 0,
    'name',
    'dep_name'
);

DROP TEMPORARY TABLE IF EXISTS tmp_dept_lookup;
SET @sql_dept_lookup := CONCAT(
    'CREATE TEMPORARY TABLE tmp_dept_lookup AS ',
    'SELECT ', @dept_pk, ' AS dept_id, LOWER(', @dept_name, ') AS dept_name ',
    'FROM DEPARTMENT'
);
PREPARE stmt_dept_lookup FROM @sql_dept_lookup;
EXECUTE stmt_dept_lookup;
DEALLOCATE PREPARE stmt_dept_lookup;

SET @fallback_dept := (SELECT MIN(dept_id) FROM tmp_dept_lookup);
SET @dept_management := COALESCE((SELECT dept_id FROM tmp_dept_lookup WHERE dept_name = 'management' LIMIT 1), @fallback_dept);
SET @dept_reception := COALESCE((SELECT dept_id FROM tmp_dept_lookup WHERE dept_name = 'reception' LIMIT 1), @fallback_dept);
SET @dept_fnb := COALESCE((SELECT dept_id FROM tmp_dept_lookup WHERE dept_name = 'food & beverage' LIMIT 1), @fallback_dept);
SET @dept_housekeeping := COALESCE((SELECT dept_id FROM tmp_dept_lookup WHERE dept_name = 'housekeeping' LIMIT 1), @fallback_dept);
SET @dept_finance := COALESCE((SELECT dept_id FROM tmp_dept_lookup WHERE dept_name = 'finance' LIMIT 1), @fallback_dept);
SET @dept_maintenance := COALESCE((SELECT dept_id FROM tmp_dept_lookup WHERE dept_name = 'maintenance' LIMIT 1), @fallback_dept);

SET @has_staff_position := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'position'
);
SET @has_staff_phone_no := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'phone_no'
);
SET @has_staff_gender := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'gender'
);
SET @has_staff_status := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'status'
);
SET @has_staff_dept_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'STAFF' AND COLUMN_NAME = 'dept_id'
);

DROP TEMPORARY TABLE IF EXISTS tmp_staff_seed_data;
CREATE TEMPORARY TABLE tmp_staff_seed_data (
    name VARCHAR(120),
    email VARCHAR(255),
    phone VARCHAR(20),
    position VARCHAR(80),
    role VARCHAR(40),
    dept_id INT,
    salary DECIMAL(12,2),
    hire_date DATE,
    status VARCHAR(20),
    performance_score DECIMAL(5,2),
    attendance_rate DECIMAL(5,2),
    last_activity_at DATETIME,
    gender VARCHAR(10)
);

INSERT INTO tmp_staff_seed_data (name, email, phone, position, role, dept_id, salary, hire_date, status, performance_score, attendance_rate, last_activity_at, gender)
VALUES
('Rhode Mathews', 'rhode.mathews@heartlandabode.com', '9810001111', 'Senior Manager', 'Manager', @dept_management, 95000.00, '2023-01-11', 'Active', 92.50, 96.20, NOW() - INTERVAL 2 HOUR, 'Male'),
('Rhode Fernandes', 'rhode.fernandes@heartlandabode.com', '9810001112', 'Front Office Manager', 'Receptionist', @dept_reception, 68000.00, '2023-03-21', 'Active', 89.10, 94.40, NOW() - INTERVAL 5 HOUR, 'Female'),
('Aarav Mehta', 'aarav.mehta@heartlandabode.com', '9810001113', 'Finance Manager', 'Finance', @dept_finance, 82000.00, '2022-12-15', 'Active', 90.80, 95.00, NOW() - INTERVAL 1 DAY, 'Male'),
('Meera Iyer', 'meera.iyer@heartlandabode.com', '9810001114', 'Executive Chef', 'Chef', @dept_fnb, 78000.00, '2023-02-10', 'Active', 93.20, 97.10, NOW() - INTERVAL 3 HOUR, 'Female'),
('Karan Sood', 'karan.sood@heartlandabode.com', '9810001115', 'Sous Chef', 'Chef', @dept_fnb, 62000.00, '2023-05-01', 'Active', 86.40, 92.30, NOW() - INTERVAL 8 HOUR, 'Male'),
('Nisha Roy', 'nisha.roy@heartlandabode.com', '9810001116', 'Reception Executive', 'Receptionist', @dept_reception, 42000.00, '2024-01-18', 'Active', 83.60, 91.80, NOW() - INTERVAL 4 HOUR, 'Female'),
('Vikram Joshi', 'vikram.joshi@heartlandabode.com', '9810001117', 'Guest Relations', 'Receptionist', @dept_reception, 44500.00, '2023-10-09', 'Active', 85.90, 93.20, NOW() - INTERVAL 6 HOUR, 'Male'),
('Sneha Kulkarni', 'sneha.kulkarni@heartlandabode.com', '9810001118', 'Housekeeping Lead', 'Housekeeping', @dept_housekeeping, 47000.00, '2023-06-14', 'Active', 88.30, 95.60, NOW() - INTERVAL 1 HOUR, 'Female'),
('Harish Patil', 'harish.patil@heartlandabode.com', '9810001119', 'Housekeeping Associate', 'Housekeeping', @dept_housekeeping, 36000.00, '2024-02-03', 'Active', 80.70, 89.40, NOW() - INTERVAL 9 HOUR, 'Male'),
('Aisha Khan', 'aisha.khan@heartlandabode.com', '9810001120', 'Housekeeping Associate', 'Housekeeping', @dept_housekeeping, 35500.00, '2024-04-18', 'Active', 79.90, 88.60, NOW() - INTERVAL 12 HOUR, 'Female'),
('Rohit Sen', 'rohit.sen@heartlandabode.com', '9810001121', 'Maintenance Engineer', 'Housekeeping', @dept_maintenance, 52000.00, '2023-07-07', 'Active', 84.20, 93.90, NOW() - INTERVAL 14 HOUR, 'Male'),
('Divya Nair', 'divya.nair@heartlandabode.com', '9810001122', 'Accounts Executive', 'Finance', @dept_finance, 51000.00, '2023-08-16', 'Active', 87.70, 94.80, NOW() - INTERVAL 10 HOUR, 'Female'),
('Pranav Shah', 'pranav.shah@heartlandabode.com', '9810001123', 'Cost Controller', 'Finance', @dept_finance, 59000.00, '2022-11-30', 'Active', 91.60, 96.50, NOW() - INTERVAL 7 HOUR, 'Male'),
('Ishita Rao', 'ishita.rao@heartlandabode.com', '9810001124', 'Banquet Chef', 'Chef', @dept_fnb, 54000.00, '2024-01-09', 'Active', 82.30, 90.40, NOW() - INTERVAL 16 HOUR, 'Female'),
('Rahul Bedi', 'rahul.bedi@heartlandabode.com', '9810001125', 'Night Receptionist', 'Receptionist', @dept_reception, 39500.00, '2024-05-10', 'Active', 78.80, 87.10, NOW() - INTERVAL 18 HOUR, 'Male'),
('Pooja Arora', 'pooja.arora@heartlandabode.com', '9810001126', 'Guest Experience Manager', 'Manager', @dept_management, 70500.00, '2023-09-26', 'Active', 90.20, 95.30, NOW() - INTERVAL 4 HOUR, 'Female'),
('Sahil Verma', 'sahil.verma@heartlandabode.com', '9810001127', 'Commis Chef', 'Chef', @dept_fnb, 33000.00, '2024-06-21', 'Active', 76.40, 86.70, NOW() - INTERVAL 20 HOUR, 'Male'),
('Ananya Ghosh', 'ananya.ghosh@heartlandabode.com', '9810001128', 'Finance Analyst', 'Finance', @dept_finance, 57500.00, '2024-03-13', 'Active', 85.50, 92.80, NOW() - INTERVAL 11 HOUR, 'Female'),
('Rhode Wellington', 'rhode.wellington@heartlandabode.com', '9810001129', 'Operations Manager', 'Manager', @dept_management, 92000.00, '2023-04-05', 'Active', 91.30, 95.70, NOW() - INTERVAL 3 HOUR, 'Male'),
('Kabir Malhotra', 'kabir.malhotra@heartlandabode.com', '9810001130', 'Front Desk Executive', 'Receptionist', @dept_reception, 41000.00, '2024-07-09', 'Active', 81.70, 90.50, NOW() - INTERVAL 6 HOUR, 'Male'),
('Leena Kapoor', 'leena.kapoor@heartlandabode.com', '9810001131', 'Front Desk Executive', 'Receptionist', @dept_reception, 43000.00, '2024-08-14', 'Active', 82.90, 91.20, NOW() - INTERVAL 9 HOUR, 'Female'),
('Mohan Das', 'mohan.das@heartlandabode.com', '9810001132', 'Chef de Partie', 'Chef', @dept_fnb, 48500.00, '2023-11-20', 'Active', 84.10, 92.40, NOW() - INTERVAL 7 HOUR, 'Male'),
('Tanuja Singh', 'tanuja.singh@heartlandabode.com', '9810001133', 'Housekeeping Associate', 'Housekeeping', @dept_housekeeping, 34200.00, '2024-09-01', 'Active', 79.60, 88.90, NOW() - INTERVAL 15 HOUR, 'Female'),
('Ritesh Malhotra', 'ritesh.malhotra@heartlandabode.com', '9810001134', 'Revenue Accountant', 'Finance', @dept_finance, 56000.00, '2023-12-08', 'Active', 88.40, 94.10, NOW() - INTERVAL 12 HOUR, 'Male'),
('Neeraj Gill', 'neeraj.gill@heartlandabode.com', '9810001135', 'Maintenance Technician', 'Housekeeping', @dept_maintenance, 50500.00, '2024-10-10', 'Active', 83.30, 92.00, NOW() - INTERVAL 2 DAY, 'Male');

DELETE s
FROM STAFF s
JOIN tmp_staff_seed_data t ON LOWER(s.email) = LOWER(t.email);

SET @dept_col := IF(@has_staff_dept_id > 0, 'dept_id', 'dep_id');
SET @phone_col := IF(@has_staff_phone_no > 0, 'phone_no', 'phone');

SET @insert_cols := CONCAT(
    'name, email, ', @phone_col,
    IF(@has_staff_position > 0, ', position', ''),
    ', role, ', @dept_col, ', salary, hire_date',
    IF(@has_staff_status > 0, ', status', ''),
    ', performance_score, attendance_rate, last_activity_at',
    IF(@has_staff_gender > 0, ', gender', '')
);

SET @insert_values := CONCAT(
    'name, email, phone',
    IF(@has_staff_position > 0, ', position', ''),
    ', role, dept_id, salary, hire_date',
    IF(@has_staff_status > 0, ', status', ''),
    ', performance_score, attendance_rate, last_activity_at',
    IF(@has_staff_gender > 0, ', gender', '')
);

SET @sql_insert_staff := CONCAT(
    'INSERT INTO STAFF (', @insert_cols, ') ',
    'SELECT ', @insert_values, ' FROM tmp_staff_seed_data'
);
PREPARE stmt_insert_staff FROM @sql_insert_staff;
EXECUTE stmt_insert_staff;
DEALLOCATE PREPARE stmt_insert_staff;

-- ---------- Attendance/activity simulation (90 days) ----------
DROP TEMPORARY TABLE IF EXISTS tmp_staff_seed;
SET @staff_row := 0;
CREATE TEMPORARY TABLE tmp_staff_seed AS
SELECT
    (@staff_row := @staff_row + 1) AS idx,
    s.staff_id,
    s.role
FROM STAFF s
WHERE s.email LIKE '%@heartlandabode.com'
ORDER BY s.staff_id;

DROP TEMPORARY TABLE IF EXISTS tmp_day_seq;
CREATE TEMPORARY TABLE tmp_day_seq AS
SELECT n - 1 AS day_no
FROM tmp_seed_seq
WHERE n <= 90;

INSERT INTO STAFF_ATTENDANCE (staff_id, attendance_date, status, hours_worked, notes)
SELECT
    st.staff_id,
    DATE_SUB(@today, INTERVAL d.day_no DAY) AS attendance_date,
    CASE
        WHEN WEEKDAY(DATE_SUB(@today, INTERVAL d.day_no DAY)) = 6 THEN 'Off'
        WHEN MOD(st.staff_id + d.day_no, 19) = 0 THEN 'Leave'
        WHEN MOD(st.staff_id + d.day_no, 7) = 0 THEN 'Late'
        ELSE 'Present'
    END AS attendance_status,
    CASE
        WHEN WEEKDAY(DATE_SUB(@today, INTERVAL d.day_no DAY)) = 6 THEN 0.00
        WHEN MOD(st.staff_id + d.day_no, 19) = 0 THEN 0.00
        WHEN MOD(st.staff_id + d.day_no, 7) = 0 THEN 7.00
        ELSE 8.50
    END AS hours_worked,
    CONCAT('[ADV-BI-SEED] Duty log for ', DATE_SUB(@today, INTERVAL d.day_no DAY))
FROM tmp_staff_seed st
JOIN tmp_day_seq d
WHERE st.idx <= 25
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    hours_worked = VALUES(hours_worked),
    notes = VALUES(notes),
    updated_at = CURRENT_TIMESTAMP;

-- Refresh attendance rate / last activity roll-up.
UPDATE STAFF s
LEFT JOIN (
    SELECT
        staff_id,
        ROUND(
            100 * SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) /
            NULLIF(SUM(CASE WHEN status <> 'Off' THEN 1 ELSE 0 END), 0),
            2
        ) AS attendance_pct,
        MAX(CONCAT(attendance_date, ' 18:00:00')) AS last_activity
    FROM STAFF_ATTENDANCE
    WHERE attendance_date >= DATE_SUB(@today, INTERVAL 60 DAY)
    GROUP BY staff_id
) a ON a.staff_id = s.staff_id
SET
    s.attendance_rate = COALESCE(a.attendance_pct, s.attendance_rate),
    s.last_activity_at = COALESCE(a.last_activity, s.last_activity_at);

-- ---------- Reservation, payment, and food order seed ----------
-- Remove previous Step 7 seeded rows first.
DELETE rf
FROM RESERVATION_FOOD rf
JOIN RESERVATION r ON r.res_id = rf.res_id
WHERE r.special_requests LIKE '[ADV-BI-SEED]%';

DELETE p
FROM PAYMENTS p
JOIN RESERVATION r ON r.res_id = p.res_id
WHERE r.special_requests LIKE '[ADV-BI-SEED]%';

DELETE FROM RESERVATION
WHERE special_requests LIKE '[ADV-BI-SEED]%';

-- Build room and guest lookup with sequence indexes.
DROP TEMPORARY TABLE IF EXISTS tmp_guest_lookup;
SET @gidx := 0;
CREATE TEMPORARY TABLE tmp_guest_lookup AS
SELECT (@gidx := @gidx + 1) AS idx, g.guest_id
FROM GUEST g
ORDER BY g.guest_id
LIMIT 200;

DROP TEMPORARY TABLE IF EXISTS tmp_room_lookup;
SET @ridx := 0;
CREATE TEMPORARY TABLE tmp_room_lookup AS
SELECT
    (@ridx := @ridx + 1) AS idx,
    r.room_id,
    r.room_no,
    r.rent,
    rt.name AS room_type
FROM ROOMS r
JOIN ROOM_TYPE rt ON rt.room_type_id = r.room_type_id
ORDER BY r.room_no
LIMIT 300;

SELECT COUNT(*) INTO @guest_count FROM tmp_guest_lookup;
SELECT COUNT(*) INTO @room_count FROM tmp_room_lookup;

INSERT INTO RESERVATION_TYPE (name, payment_rule)
SELECT 'Standard', 'Pay on arrival'
WHERE NOT EXISTS (SELECT 1 FROM RESERVATION_TYPE WHERE LOWER(name) = 'standard');

INSERT INTO RESERVATION_TYPE (name, payment_rule)
SELECT 'Premium', 'Advance'
WHERE NOT EXISTS (SELECT 1 FROM RESERVATION_TYPE WHERE LOWER(name) = 'premium');

SELECT COALESCE(MIN(reservation_type_id), 1) INTO @reservation_type_default FROM RESERVATION_TYPE;

DROP TEMPORARY TABLE IF EXISTS tmp_seed_reservations;
CREATE TEMPORARY TABLE tmp_seed_reservations (
    seed_no INT PRIMARY KEY,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    reservation_type_id INT NOT NULL,
    r_date DATE NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    special_requests VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

INSERT INTO tmp_seed_reservations (
    seed_no, guest_id, room_id, reservation_type_id, r_date, check_in, check_out,
    total_price, status, special_requests, created_at, updated_at
)
SELECT
    seq.n AS seed_no,
    gl.guest_id,
    rl.room_id,
    @reservation_type_default,
    DATE_SUB(DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY), INTERVAL (seq.n % 18) DAY) AS r_date,
    DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY) AS check_in,
    DATE_ADD(DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY), INTERVAL 1 + (seq.n % 5) DAY) AS check_out,
    ROUND(
        (
            rl.rent * (1 + (seq.n % 4) * 0.06) * (1 + (seq.n % 5))
        ) + ((seq.n % 3) * 550),
        2
    ) AS total_price,
    CASE
        WHEN MOD(seq.n, 23) = 0 THEN 'Cancelled'
        WHEN DATE_ADD(DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY), INTERVAL 1 + (seq.n % 5) DAY) < @today THEN 'Checked-out'
        WHEN DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY) <= @today
             AND DATE_ADD(DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY), INTERVAL 1 + (seq.n % 5) DAY) >= @today THEN 'Checked-in'
        WHEN MOD(seq.n, 7) = 0 THEN 'Pending'
        ELSE 'Confirmed'
    END AS status,
    CONCAT('[ADV-BI-SEED] Booking #', LPAD(seq.n, 3, '0')),
    DATE_SUB(DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY), INTERVAL (5 + (seq.n % 20)) DAY),
    DATE_SUB(DATE_ADD(@seed_base, INTERVAL seq.n * 3 DAY), INTERVAL (5 + (seq.n % 20)) DAY)
FROM tmp_seed_seq seq
JOIN tmp_guest_lookup gl ON gl.idx = ((seq.n - 1) MOD @guest_count) + 1
JOIN tmp_room_lookup rl ON rl.idx = ((seq.n * 3 - 1) MOD @room_count) + 1
WHERE seq.n <= 130;

-- Insert into RESERVATION with schema-compat handling (r_date may or may not exist).
SET @has_r_date := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'RESERVATION' AND COLUMN_NAME = 'r_date'
);

SET @sql_insert_res := IF(
    @has_r_date > 0,
    'INSERT INTO RESERVATION (guest_id, room_id, reservation_type_id, r_date, check_in, check_out, total_price, status, special_requests, created_at, updated_at)
     SELECT guest_id, room_id, reservation_type_id, r_date, check_in, check_out, total_price, status, special_requests, created_at, updated_at
     FROM tmp_seed_reservations',
    'INSERT INTO RESERVATION (guest_id, room_id, reservation_type_id, check_in, check_out, total_price, status, special_requests, created_at, updated_at)
     SELECT guest_id, room_id, reservation_type_id, check_in, check_out, total_price, status, special_requests, created_at, updated_at
     FROM tmp_seed_reservations'
);
PREPARE stmt_insert_res FROM @sql_insert_res;
EXECUTE stmt_insert_res;
DEALLOCATE PREPARE stmt_insert_res;

-- Seed food orders (90 rows) tied to seeded reservations.
DROP TEMPORARY TABLE IF EXISTS tmp_seed_res_lookup;
SET @sridx := 0;
CREATE TEMPORARY TABLE tmp_seed_res_lookup AS
SELECT (@sridx := @sridx + 1) AS idx, r.res_id, r.check_in
FROM RESERVATION r
WHERE r.special_requests LIKE '[ADV-BI-SEED]%'
ORDER BY r.res_id;

DROP TEMPORARY TABLE IF EXISTS tmp_food_lookup;
SET @fidx := 0;
CREATE TEMPORARY TABLE tmp_food_lookup AS
SELECT
    (@fidx := @fidx + 1) AS idx,
    fd.food_id,
    fd.price,
    fd.menu_category,
    fd.food_type
FROM FOOD_DINING fd
WHERE fd.is_available = 1
ORDER BY fd.food_id;

SELECT COUNT(*) INTO @seed_res_count FROM tmp_seed_res_lookup;
SELECT COUNT(*) INTO @food_count FROM tmp_food_lookup;

DROP TEMPORARY TABLE IF EXISTS tmp_seed_food_orders;
CREATE TEMPORARY TABLE tmp_seed_food_orders (
    seed_no INT PRIMARY KEY,
    res_id INT NOT NULL,
    food_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL,
    created_at DATETIME NOT NULL
);

INSERT INTO tmp_seed_food_orders (seed_no, res_id, food_id, quantity, unit_price, created_at)
SELECT
    seq.n,
    srl.res_id,
    fl.food_id,
    1 + (seq.n % 3) AS quantity,
    ROUND(fl.price * (1 + (seq.n % 2) * 0.04), 2) AS unit_price,
    DATE_ADD(srl.check_in, INTERVAL ((seq.n % 11) + 8) HOUR)
FROM tmp_seed_seq seq
JOIN tmp_seed_res_lookup srl ON srl.idx = ((seq.n - 1) MOD @seed_res_count) + 1
JOIN tmp_food_lookup fl ON fl.idx = ((seq.n * 5 - 1) MOD @food_count) + 1
WHERE seq.n <= 90;

SET @has_qty_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'RESERVATION_FOOD' AND COLUMN_NAME = 'qty'
);
SET @has_quantity_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'RESERVATION_FOOD' AND COLUMN_NAME = 'quantity'
);

SET @sql_insert_food := IF(
    @has_qty_column > 0,
    'INSERT INTO RESERVATION_FOOD (res_id, food_id, qty, price, created_at)
     SELECT res_id, food_id, quantity, unit_price, created_at
     FROM tmp_seed_food_orders',
    IF(
        @has_quantity_column > 0,
        'INSERT INTO RESERVATION_FOOD (res_id, food_id, quantity, price, created_at)
         SELECT res_id, food_id, quantity, unit_price, created_at
         FROM tmp_seed_food_orders',
        'INSERT INTO RESERVATION_FOOD (res_id, food_id, price, created_at)
         SELECT res_id, food_id, unit_price, created_at
         FROM tmp_seed_food_orders'
    )
);
PREPARE stmt_insert_food FROM @sql_insert_food;
EXECUTE stmt_insert_food;
DEALLOCATE PREPARE stmt_insert_food;

-- Update reservation totals to include food totals (matches live booking flow).
SET @rf_qty_expr := IF(
    @has_qty_column > 0,
    'COALESCE(rf.qty, 1)',
    IF(@has_quantity_column > 0, 'COALESCE(rf.quantity, 1)', '1')
);
SET @sql_update_seed_totals := CONCAT(
    'UPDATE RESERVATION r ',
    'JOIN (',
    '  SELECT rf.res_id, SUM(', @rf_qty_expr, ' * COALESCE(rf.price, fd.price, 0)) AS food_total ',
    '  FROM RESERVATION_FOOD rf ',
    '  JOIN FOOD_DINING fd ON fd.food_id = rf.food_id ',
    '  JOIN RESERVATION res ON res.res_id = rf.res_id ',
    '  WHERE res.special_requests LIKE ''[ADV-BI-SEED]%'' ',
    '  GROUP BY rf.res_id',
    ') x ON x.res_id = r.res_id ',
    'SET r.total_price = ROUND(r.total_price + COALESCE(x.food_total, 0), 2) ',
    'WHERE r.special_requests LIKE ''[ADV-BI-SEED]%'''
);
PREPARE stmt_update_seed_totals FROM @sql_update_seed_totals;
EXECUTE stmt_update_seed_totals;
DEALLOCATE PREPARE stmt_update_seed_totals;

-- Payment records mapped to seeded reservations (after totals update).
INSERT INTO PAYMENTS (res_id, amount, payment_method, status, txn_id, created_at)
SELECT
    r.res_id,
    r.total_price,
    CASE (MOD(r.res_id, 4))
        WHEN 0 THEN 'Card'
        WHEN 1 THEN 'UPI'
        WHEN 2 THEN 'Net Banking'
        ELSE 'Cash'
    END AS payment_method,
    CASE
        WHEN r.status IN ('Checked-out', 'Checked-in', 'Confirmed') THEN 'Success'
        WHEN r.status = 'Cancelled' THEN 'Refunded'
        ELSE 'Pending'
    END AS payment_status,
    CONCAT('ADVBIPAY-', r.res_id),
    DATE_ADD(r.check_in, INTERVAL 8 HOUR)
FROM RESERVATION r
WHERE r.special_requests LIKE '[ADV-BI-SEED]%';

-- ---------- Monthly cost trend seed (18 months) ----------
SET @cost_base := DATE_SUB(DATE_FORMAT(@today, '%Y-%m-01'), INTERVAL 17 MONTH);
DROP TEMPORARY TABLE IF EXISTS tmp_month_seq;
CREATE TEMPORARY TABLE tmp_month_seq AS
SELECT n - 1 AS m
FROM tmp_seed_seq
WHERE n <= 18;

INSERT INTO OPERATING_COSTS (cost_month, category, amount, description)
SELECT
    DATE_FORMAT(DATE_ADD(@cost_base, INTERVAL ms.m MONTH), '%Y-%m-01') AS cost_month,
    c.category,
    CASE c.category
        WHEN 'Staff' THEN ROUND(245000 + ms.m * 4200 + (ms.m % 3) * 3800, 2)
        WHEN 'Electricity' THEN ROUND(98000 + ms.m * 1800 + CASE WHEN MONTH(DATE_ADD(@cost_base, INTERVAL ms.m MONTH)) IN (5,6,7,8) THEN 22000 ELSE 7000 END, 2)
        WHEN 'Maintenance' THEN ROUND(62000 + ms.m * 1100 + (ms.m % 4) * 3000, 2)
        WHEN 'Water' THEN ROUND(28000 + ms.m * 650 + (ms.m % 2) * 2400, 2)
    END AS amount,
    CONCAT('[ADV-BI-SEED] ', c.category, ' cost for ', DATE_FORMAT(DATE_ADD(@cost_base, INTERVAL ms.m MONTH), '%b %Y'))
FROM tmp_month_seq ms
JOIN (
    SELECT 'Staff' AS category
    UNION ALL SELECT 'Electricity'
    UNION ALL SELECT 'Maintenance'
    UNION ALL SELECT 'Water'
) c
ON 1 = 1
ON DUPLICATE KEY UPDATE
    amount = VALUES(amount),
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;

-- ---------- Post-seed quality adjustments ----------
-- Keep room statuses aligned with upcoming active reservations.
UPDATE ROOMS r
JOIN (
    SELECT room_id, MAX(check_out) AS latest_checkout
    FROM RESERVATION
    WHERE status IN ('Pending', 'Confirmed', 'Checked-in')
      AND check_out >= @today
      AND room_id IS NOT NULL
    GROUP BY room_id
) x ON x.room_id = r.room_id
SET r.status = 'Reserved'
WHERE r.status = 'Available';

-- Promote known categories where missing labels are still generic.
UPDATE FOOD_DINING
SET menu_category = CASE
    WHEN LOWER(title) LIKE '%soup%' THEN 'Soups'
    WHEN LOWER(title) LIKE '%naan%' OR LOWER(title) LIKE '%roti%' OR LOWER(title) LIKE '%paratha%' THEN 'Breads'
    WHEN LOWER(title) LIKE '%rice%' OR LOWER(title) LIKE '%biryani%' OR LOWER(title) LIKE '%pulao%' THEN 'Rice'
    WHEN LOWER(title) LIKE '%ice%' OR LOWER(title) LIKE '%cake%' OR LOWER(title) LIKE '%jamun%' OR LOWER(title) LIKE '%dessert%' THEN 'Desserts'
    WHEN LOWER(title) LIKE '%coffee%' OR LOWER(title) LIKE '%tea%' OR LOWER(title) LIKE '%juice%' OR LOWER(title) LIKE '%beverage%' THEN 'Beverages'
    ELSE menu_category
END
WHERE menu_category = 'Main Course';

-- ---------- Verification snapshots ----------
SELECT 'STEP7_COUNTS' AS metric,
       (SELECT COUNT(*) FROM RESERVATION WHERE special_requests LIKE '[ADV-BI-SEED]%') AS seeded_reservations,
       (SELECT COUNT(*) FROM RESERVATION_FOOD rf JOIN RESERVATION r ON r.res_id = rf.res_id WHERE r.special_requests LIKE '[ADV-BI-SEED]%') AS seeded_food_orders,
       (SELECT COUNT(*) FROM STAFF WHERE email LIKE '%@heartlandabode.com') AS staff_records,
       (SELECT COUNT(*) FROM STAFF WHERE name LIKE 'Rhode%') AS rhode_staff_count,
       (SELECT COUNT(*) FROM PAYMENTS WHERE txn_id LIKE 'ADVBIPAY-%') AS seeded_payments;

SELECT 'STEP7_BOOKING_WINDOW' AS metric,
       MIN(check_in) AS first_check_in,
       MAX(check_in) AS last_check_in,
       SUM(CASE WHEN check_in < @today THEN 1 ELSE 0 END) AS past_bookings,
       SUM(CASE WHEN check_in >= @today THEN 1 ELSE 0 END) AS future_bookings
FROM RESERVATION
WHERE special_requests LIKE '[ADV-BI-SEED]%';

SELECT 'STEP7_COST_WINDOW' AS metric,
       MIN(cost_month) AS first_cost_month,
       MAX(cost_month) AS last_cost_month,
       COUNT(*) AS cost_rows
FROM OPERATING_COSTS
WHERE description LIKE '[ADV-BI-SEED]%';
