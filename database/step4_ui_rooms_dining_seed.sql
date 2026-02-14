USE heartland_abode;

-- ============================================================
-- Step 4 Seed: Large Inventory + Dining Categories
-- Re-runnable script for realistic demo data.
-- ============================================================

-- 1) Keep room types flexible (avoid enum lock on name)
ALTER TABLE ROOM_TYPE
MODIFY COLUMN name VARCHAR(50) NOT NULL;

-- 2) Add menu_category to FOOD_DINING if missing
SET @has_menu_category := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'FOOD_DINING'
      AND COLUMN_NAME = 'menu_category'
);
SET @sql_menu_category := IF(
    @has_menu_category = 0,
    'ALTER TABLE FOOD_DINING ADD COLUMN menu_category VARCHAR(30) NOT NULL DEFAULT ''Main Course'' AFTER food_type',
    'SELECT 1'
);
PREPARE stmt_menu_category FROM @sql_menu_category;
EXECUTE stmt_menu_category;
DEALLOCATE PREPARE stmt_menu_category;

-- 3) Ensure required room types exist
INSERT INTO ROOM_TYPE (name, description)
SELECT 'Standard', 'Comfort room for short and business stays'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Standard');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Deluxe', 'Upgraded room with enhanced comfort and services'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Deluxe');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Suite', 'Spacious suite suitable for premium travelers'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Suite');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Family', 'Large room optimized for group and family stays'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Family');

INSERT INTO ROOM_TYPE (name, description)
SELECT 'Premium Suite', 'High-end suite with VIP-grade amenities'
WHERE NOT EXISTS (SELECT 1 FROM ROOM_TYPE WHERE name = 'Premium Suite');

SET @type_standard := (SELECT room_type_id FROM ROOM_TYPE WHERE name = 'Standard' ORDER BY room_type_id LIMIT 1);
SET @type_deluxe := (SELECT room_type_id FROM ROOM_TYPE WHERE name = 'Deluxe' ORDER BY room_type_id LIMIT 1);
SET @type_suite := (SELECT room_type_id FROM ROOM_TYPE WHERE name = 'Suite' ORDER BY room_type_id LIMIT 1);
SET @type_family := (SELECT room_type_id FROM ROOM_TYPE WHERE name = 'Family' ORDER BY room_type_id LIMIT 1);
SET @type_premium_suite := (SELECT room_type_id FROM ROOM_TYPE WHERE name = 'Premium Suite' ORDER BY room_type_id LIMIT 1);

-- 4) Number helper table (1..50)
DROP TEMPORARY TABLE IF EXISTS tmp_seed_numbers;
CREATE TEMPORARY TABLE tmp_seed_numbers (n INT PRIMARY KEY);
INSERT INTO tmp_seed_numbers (n) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),
(11),(12),(13),(14),(15),(16),(17),(18),(19),(20),
(21),(22),(23),(24),(25),(26),(27),(28),(29),(30),
(31),(32),(33),(34),(35),(36),(37),(38),(39),(40),
(41),(42),(43),(44),(45),(46),(47),(48),(49),(50);

-- 5) Upsert floor-wise room inventory (120 rooms)
-- Some schema variants include ROOMS.hotel_id, some don't. Seed safely via a staging table + dynamic insert.
SET @has_rooms_hotel_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ROOMS'
      AND COLUMN_NAME = 'hotel_id'
);
SET @hotel_id := (SELECT COALESCE(MIN(hotel_id), 1) FROM HOTEL);

DROP TEMPORARY TABLE IF EXISTS tmp_rooms_seed;
CREATE TEMPORARY TABLE tmp_rooms_seed (
    room_no VARCHAR(10) PRIMARY KEY,
    room_type_id INT NOT NULL,
    tier INT NOT NULL,
    rent DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    description TEXT,
    image_path VARCHAR(255) NULL
);

-- Floor 1 -> 101..120 (Standard)
INSERT INTO tmp_rooms_seed (room_no, room_type_id, tier, rent, status, description, image_path)
SELECT
    LPAD(100 + n, 3, '0') AS room_no,
    @type_standard,
    3,
    ROUND(2800 + (n * 45), 2),
    CASE
        WHEN MOD(n, 10) = 0 THEN 'Reserved'
        WHEN MOD(n, 7) = 0 THEN 'Occupied'
        ELSE 'Available'
    END,
    CONCAT('[UI-SEED] Floor 1 Standard room ', LPAD(100 + n, 3, '0'), ' with practical amenities and work-ready setup.'),
    NULL
FROM tmp_seed_numbers
WHERE n BETWEEN 1 AND 20;

-- Floor 2 -> 201..220 (Deluxe)
INSERT INTO tmp_rooms_seed (room_no, room_type_id, tier, rent, status, description, image_path)
SELECT
    LPAD(200 + n, 3, '0') AS room_no,
    @type_deluxe,
    2,
    ROUND(4300 + (n * 58), 2),
    CASE
        WHEN MOD(n, 9) = 0 THEN 'Reserved'
        WHEN MOD(n, 8) = 0 THEN 'Occupied'
        ELSE 'Available'
    END,
    CONCAT('[UI-SEED] Floor 2 Deluxe room ', LPAD(200 + n, 3, '0'), ' with balcony view and upgraded furnishings.'),
    NULL
FROM tmp_seed_numbers
WHERE n BETWEEN 1 AND 20;

-- Floor 3 -> 301..320 (Suite)
INSERT INTO tmp_rooms_seed (room_no, room_type_id, tier, rent, status, description, image_path)
SELECT
    LPAD(300 + n, 3, '0') AS room_no,
    @type_suite,
    CASE WHEN MOD(n, 5) = 0 THEN 1 ELSE 2 END,
    ROUND(6700 + (n * 82), 2),
    CASE
        WHEN MOD(n, 11) = 0 THEN 'Reserved'
        WHEN MOD(n, 6) = 0 THEN 'Occupied'
        ELSE 'Available'
    END,
    CONCAT('[UI-SEED] Floor 3 Suite ', LPAD(300 + n, 3, '0'), ' with lounge space and premium stay package.'),
    NULL
FROM tmp_seed_numbers
WHERE n BETWEEN 1 AND 20;

-- Floor 4 -> 401..430 (Family / VIP)
INSERT INTO tmp_rooms_seed (room_no, room_type_id, tier, rent, status, description, image_path)
SELECT
    LPAD(400 + n, 3, '0') AS room_no,
    CASE WHEN MOD(n, 4) = 0 THEN @type_suite ELSE @type_family END,
    CASE WHEN MOD(n, 4) = 0 THEN 1 ELSE 2 END,
    ROUND(7600 + (n * 94) + CASE WHEN MOD(n, 4) = 0 THEN 700 ELSE 0 END, 2),
    CASE
        WHEN MOD(n, 12) = 0 THEN 'Reserved'
        WHEN MOD(n, 7) = 0 THEN 'Occupied'
        ELSE 'Available'
    END,
    CONCAT('[UI-SEED] Floor 4 Family/VIP room ', LPAD(400 + n, 3, '0'), ' optimized for family groups and executive guests.'),
    NULL
FROM tmp_seed_numbers
WHERE n BETWEEN 1 AND 30;

-- Floor 5 -> 501..530 (Premium Suites)
INSERT INTO tmp_rooms_seed (room_no, room_type_id, tier, rent, status, description, image_path)
SELECT
    LPAD(500 + n, 3, '0') AS room_no,
    @type_premium_suite,
    1,
    ROUND(9800 + (n * 145), 2),
    CASE
        WHEN MOD(n, 10) = 0 THEN 'Reserved'
        WHEN MOD(n, 8) = 0 THEN 'Occupied'
        ELSE 'Available'
    END,
    CONCAT('[UI-SEED] Floor 5 Premium Suite ', LPAD(500 + n, 3, '0'), ' with signature luxury amenities and skyline view.'),
    NULL
FROM tmp_seed_numbers
WHERE n BETWEEN 1 AND 30;

SET @sql_rooms_insert := IF(
    @has_rooms_hotel_id > 0,
    'INSERT INTO ROOMS (room_no, hotel_id, room_type_id, tier, rent, status, description, image_path)
     SELECT room_no, @hotel_id, room_type_id, tier, rent, status, description, image_path
     FROM tmp_rooms_seed
     ON DUPLICATE KEY UPDATE
        hotel_id = VALUES(hotel_id),
        room_type_id = VALUES(room_type_id),
        tier = VALUES(tier),
        rent = VALUES(rent),
        status = VALUES(status),
        description = VALUES(description),
        image_path = VALUES(image_path)',
    'INSERT INTO ROOMS (room_no, room_type_id, tier, rent, status, description, image_path)
     SELECT room_no, room_type_id, tier, rent, status, description, image_path
     FROM tmp_rooms_seed
     ON DUPLICATE KEY UPDATE
        room_type_id = VALUES(room_type_id),
        tier = VALUES(tier),
        rent = VALUES(rent),
        status = VALUES(status),
        description = VALUES(description),
        image_path = VALUES(image_path)'
);
PREPARE stmt_rooms_insert FROM @sql_rooms_insert;
EXECUTE stmt_rooms_insert;
DEALLOCATE PREPARE stmt_rooms_insert;

-- 6) Ensure useful room feature labels exist
INSERT INTO ROOM_FEATURES (name)
SELECT 'WiFi' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'WiFi');
INSERT INTO ROOM_FEATURES (name)
SELECT 'TV' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'TV');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Mini Bar' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Mini Bar');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Balcony' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Balcony');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Room Service' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Room Service');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Safe' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Safe');
INSERT INTO ROOM_FEATURES (name)
SELECT 'AC' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'AC');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Double Bed' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Double Bed');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Living Area' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Living Area');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Bathtub' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Bathtub');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Work Desk' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Work Desk');
INSERT INTO ROOM_FEATURES (name)
SELECT 'Tea/Coffee Maker' WHERE NOT EXISTS (SELECT 1 FROM ROOM_FEATURES WHERE name = 'Tea/Coffee Maker');

-- Rebuild mappings for seeded floor ranges
DELETE rfm
FROM ROOM_FEATURES_MAP rfm
JOIN ROOMS r ON r.room_id = rfm.room_id
WHERE CAST(r.room_no AS UNSIGNED) BETWEEN 101 AND 120
   OR CAST(r.room_no AS UNSIGNED) BETWEEN 201 AND 220
   OR CAST(r.room_no AS UNSIGNED) BETWEEN 301 AND 320
   OR CAST(r.room_no AS UNSIGNED) BETWEEN 401 AND 430
   OR CAST(r.room_no AS UNSIGNED) BETWEEN 501 AND 530;

INSERT IGNORE INTO ROOM_FEATURES_MAP (room_id, feature_id)
SELECT r.room_id, f.room_feature_id
FROM ROOMS r
JOIN ROOM_FEATURES f ON f.name IN ('AC', 'WiFi', 'TV', 'Safe')
WHERE CAST(r.room_no AS UNSIGNED) BETWEEN 101 AND 120;

INSERT IGNORE INTO ROOM_FEATURES_MAP (room_id, feature_id)
SELECT r.room_id, f.room_feature_id
FROM ROOMS r
JOIN ROOM_FEATURES f ON f.name IN ('AC', 'WiFi', 'TV', 'Mini Bar', 'Balcony', 'Work Desk')
WHERE CAST(r.room_no AS UNSIGNED) BETWEEN 201 AND 220;

INSERT IGNORE INTO ROOM_FEATURES_MAP (room_id, feature_id)
SELECT r.room_id, f.room_feature_id
FROM ROOMS r
JOIN ROOM_FEATURES f ON f.name IN ('AC', 'WiFi', 'TV', 'Mini Bar', 'Room Service', 'Living Area', 'Tea/Coffee Maker')
WHERE CAST(r.room_no AS UNSIGNED) BETWEEN 301 AND 320;

INSERT IGNORE INTO ROOM_FEATURES_MAP (room_id, feature_id)
SELECT r.room_id, f.room_feature_id
FROM ROOMS r
JOIN ROOM_FEATURES f ON f.name IN ('AC', 'WiFi', 'TV', 'Double Bed', 'Balcony', 'Room Service')
WHERE CAST(r.room_no AS UNSIGNED) BETWEEN 401 AND 430;

INSERT IGNORE INTO ROOM_FEATURES_MAP (room_id, feature_id)
SELECT r.room_id, f.room_feature_id
FROM ROOMS r
JOIN ROOM_FEATURES f ON f.name IN ('AC', 'WiFi', 'TV', 'Mini Bar', 'Living Area', 'Bathtub', 'Room Service', 'Tea/Coffee Maker')
WHERE CAST(r.room_no AS UNSIGNED) BETWEEN 501 AND 530;

-- 7) Seed 40+ category-based dining items (48 items)
DELETE FROM FOOD_DINING WHERE description LIKE '[UI-SEED] %';

INSERT INTO FOOD_DINING (title, description, price, food_type, menu_category, is_available, image_path) VALUES
-- Starters
('Veg Spring Rolls', '[UI-SEED] Crispy rolls with julienned vegetables and sweet chili dip', 180.00, 'VEG', 'Starters', 1, NULL),
('Paneer Tikka Skewers', '[UI-SEED] Char-grilled cottage cheese cubes in smoky marinade', 260.00, 'VEG', 'Starters', 1, NULL),
('Stuffed Mushrooms', '[UI-SEED] Herb cheese stuffed mushrooms baked with garlic butter', 240.00, 'VEG', 'Starters', 1, NULL),
('Hara Bhara Kebab', '[UI-SEED] Spinach and pea patties with mint yogurt', 210.00, 'VEG', 'Starters', 1, NULL),
('Chicken Tikka Bites', '[UI-SEED] Boneless chicken tikka with chef special masala', 290.00, 'NON-VEG', 'Starters', 1, NULL),
('Prawn Tempura', '[UI-SEED] Lightly battered prawns with spicy aioli', 340.00, 'NON-VEG', 'Starters', 1, NULL),

-- Soups
('Tomato Basil Soup', '[UI-SEED] Velvety tomato soup finished with basil oil', 150.00, 'VEG', 'Soups', 1, NULL),
('Sweet Corn Veg Soup', '[UI-SEED] Sweet corn and vegetable broth with cracked pepper', 140.00, 'VEG', 'Soups', 1, NULL),
('Hot and Sour Soup', '[UI-SEED] Spicy Indo-Chinese broth with vegetables', 155.00, 'VEG', 'Soups', 1, NULL),
('Cream of Mushroom', '[UI-SEED] Creamy mushroom soup with toasted croutons', 170.00, 'VEG', 'Soups', 1, NULL),
('Chicken Clear Soup', '[UI-SEED] Light chicken broth with herbs and julienned veggies', 185.00, 'NON-VEG', 'Soups', 1, NULL),
('Seafood Chowder', '[UI-SEED] Rich chowder with fish, shrimp and cream', 230.00, 'NON-VEG', 'Soups', 1, NULL),

-- Main Course
('Paneer Butter Masala', '[UI-SEED] Paneer cubes in buttery tomato gravy', 320.00, 'VEG', 'Main Course', 1, NULL),
('Dal Tadka', '[UI-SEED] Yellow lentils tempered with cumin and garlic', 230.00, 'VEG', 'Main Course', 1, NULL),
('Veg Kofta Curry', '[UI-SEED] Vegetable dumplings in rich onion-tomato gravy', 300.00, 'VEG', 'Main Course', 1, NULL),
('Kadai Vegetable', '[UI-SEED] Seasonal vegetables cooked in kadai masala', 280.00, 'VEG', 'Main Course', 1, NULL),
('Butter Chicken', '[UI-SEED] Classic butter chicken in creamy makhani sauce', 390.00, 'NON-VEG', 'Main Course', 1, NULL),
('Mutton Rogan Josh', '[UI-SEED] Kashmiri style mutton curry slow-cooked with spices', 460.00, 'NON-VEG', 'Main Course', 1, NULL),
('Fish Curry Coastal', '[UI-SEED] Tangy coastal fish curry with coconut base', 420.00, 'NON-VEG', 'Main Course', 1, NULL),
('Chicken Chettinad', '[UI-SEED] South Indian peppery chicken curry', 410.00, 'NON-VEG', 'Main Course', 1, NULL),

-- Breads
('Butter Naan', '[UI-SEED] Soft tandoor naan brushed with butter', 65.00, 'VEG', 'Breads', 1, NULL),
('Garlic Naan', '[UI-SEED] Tandoor naan topped with roasted garlic', 75.00, 'VEG', 'Breads', 1, NULL),
('Tandoori Roti', '[UI-SEED] Whole wheat tandoor flatbread', 45.00, 'VEG', 'Breads', 1, NULL),
('Lachha Paratha', '[UI-SEED] Layered flaky paratha with ghee', 70.00, 'VEG', 'Breads', 1, NULL),
('Kulcha', '[UI-SEED] Soft stuffed kulcha bread', 80.00, 'VEG', 'Breads', 1, NULL),
('Roomali Roti', '[UI-SEED] Thin hand-tossed roomali roti', 55.00, 'VEG', 'Breads', 1, NULL),

-- Rice
('Steamed Basmati Rice', '[UI-SEED] Fluffy long-grain basmati rice', 130.00, 'VEG', 'Rice', 1, NULL),
('Jeera Rice', '[UI-SEED] Cumin-flavored aromatic rice', 170.00, 'VEG', 'Rice', 1, NULL),
('Veg Pulao', '[UI-SEED] Fragrant rice cooked with vegetables and herbs', 220.00, 'VEG', 'Rice', 1, NULL),
('Paneer Biryani', '[UI-SEED] Layered biryani with paneer and saffron notes', 320.00, 'VEG', 'Rice', 1, NULL),
('Chicken Biryani', '[UI-SEED] Dum-style biryani with tender chicken pieces', 360.00, 'NON-VEG', 'Rice', 1, NULL),
('Mutton Biryani', '[UI-SEED] Rich biryani with slow-cooked mutton', 440.00, 'NON-VEG', 'Rice', 1, NULL),

-- Desserts
('Gulab Jamun', '[UI-SEED] Warm syrup-soaked dumplings', 140.00, 'VEG', 'Desserts', 1, NULL),
('Rasmalai', '[UI-SEED] Soft cottage cheese patties in saffron milk', 170.00, 'VEG', 'Desserts', 1, NULL),
('Chocolate Brownie', '[UI-SEED] Dense chocolate brownie with sauce', 190.00, 'VEG', 'Desserts', 1, NULL),
('Fruit Custard', '[UI-SEED] Seasonal fruit medley in chilled custard', 150.00, 'VEG', 'Desserts', 1, NULL),
('Cheesecake Slice', '[UI-SEED] Classic baked cheesecake with berry glaze', 210.00, 'VEG', 'Desserts', 1, NULL),
('Kulfi Falooda', '[UI-SEED] Traditional kulfi served with falooda strands', 180.00, 'VEG', 'Desserts', 1, NULL),

-- Beverages
('Masala Chai', '[UI-SEED] Spiced Indian tea with milk', 80.00, 'VEG', 'Beverages', 1, NULL),
('Filter Coffee', '[UI-SEED] South Indian filter coffee', 95.00, 'VEG', 'Beverages', 1, NULL),
('Fresh Lime Soda', '[UI-SEED] Sweet or salted lime soda', 110.00, 'VEG', 'Beverages', 1, NULL),
('Mango Smoothie', '[UI-SEED] Thick mango smoothie with yogurt', 160.00, 'VEG', 'Beverages', 1, NULL),
('Cold Coffee', '[UI-SEED] Chilled coffee with creamy froth', 170.00, 'VEG', 'Beverages', 1, NULL),
('Fresh Orange Juice', '[UI-SEED] Freshly squeezed orange juice', 180.00, 'VEG', 'Beverages', 1, NULL),

-- Combos
('Executive Veg Combo', '[UI-SEED] Starter + main course + bread + dessert (veg)', 540.00, 'VEG', 'Combos', 1, NULL),
('North Indian Veg Thali', '[UI-SEED] Rotis, dal, sabzi, rice and sweet dish', 460.00, 'VEG', 'Combos', 1, NULL),
('Family Veg Meal', '[UI-SEED] Sharing platter with veg curries, breads and rice', 1290.00, 'VEG', 'Combos', 1, NULL),
('Chicken Grill Combo', '[UI-SEED] Grilled chicken + rice + soup + beverage', 680.00, 'NON-VEG', 'Combos', 1, NULL),
('Seafood Combo Meal', '[UI-SEED] Fish curry, rice, salad and dessert', 760.00, 'NON-VEG', 'Combos', 1, NULL),
('Family Non-Veg Feast', '[UI-SEED] Sharing menu with chicken, mutton, breads and biryani', 1680.00, 'NON-VEG', 'Combos', 1, NULL);

-- 8) Refresh room count at hotel level
UPDATE HOTEL
SET rooms_count = (
    SELECT COUNT(*) FROM ROOMS WHERE hotel_id = @hotel_id
)
WHERE hotel_id = @hotel_id;

-- 9) Verification summary
SELECT 'STEP4_UI_SEED_SUMMARY' AS summary,
       (SELECT COUNT(*) FROM ROOMS WHERE CAST(room_no AS UNSIGNED) BETWEEN 101 AND 120) AS floor1_rooms,
       (SELECT COUNT(*) FROM ROOMS WHERE CAST(room_no AS UNSIGNED) BETWEEN 201 AND 220) AS floor2_rooms,
       (SELECT COUNT(*) FROM ROOMS WHERE CAST(room_no AS UNSIGNED) BETWEEN 301 AND 320) AS floor3_rooms,
       (SELECT COUNT(*) FROM ROOMS WHERE CAST(room_no AS UNSIGNED) BETWEEN 401 AND 430) AS floor4_rooms,
       (SELECT COUNT(*) FROM ROOMS WHERE CAST(room_no AS UNSIGNED) BETWEEN 501 AND 530) AS floor5_rooms,
       (SELECT COUNT(*) FROM FOOD_DINING WHERE description LIKE '[UI-SEED] %') AS seeded_food_items,
       (SELECT COUNT(*) FROM FOOD_DINING WHERE is_available = 1) AS total_available_food_items;
