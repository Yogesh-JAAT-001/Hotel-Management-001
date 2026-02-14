USE heartland_abode;

-- ============================================================
-- Step 5 Media Mapping Seed
-- Purpose: assign category-specific, high-resolution image URLs for
-- room and dining records. Re-runnable and non-destructive.
-- ============================================================

-- Ensure menu_category exists for FOOD_DINING classification.
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
PREPARE stmt_add_menu_category FROM @sql_add_menu_category;
EXECUTE stmt_add_menu_category;
DEALLOCATE PREPARE stmt_add_menu_category;

-- Normalize menu_category null/blank values.
UPDATE FOOD_DINING
SET menu_category = 'Main Course'
WHERE menu_category IS NULL OR TRIM(menu_category) = '';

-- ------------------------------------------------------------
-- ROOMS: assign distinct image URL per room number/category.
-- Uses source.unsplash.com with unique sig values.
-- ------------------------------------------------------------
UPDATE ROOMS r
JOIN ROOM_TYPE rt ON rt.room_type_id = r.room_type_id
SET r.image_path = CASE
    WHEN CAST(r.room_no AS UNSIGNED) BETWEEN 101 AND 120 THEN
        CONCAT('https://source.unsplash.com/1920x1080/?modern,clean,hotel,room,interior&sig=', CAST(r.room_no AS UNSIGNED) + 1100)

    WHEN CAST(r.room_no AS UNSIGNED) BETWEEN 201 AND 220 THEN
        CONCAT('https://source.unsplash.com/1920x1080/?deluxe,luxury,hotel,room,warm,lighting&sig=', CAST(r.room_no AS UNSIGNED) + 2200)

    WHEN CAST(r.room_no AS UNSIGNED) BETWEEN 301 AND 320 THEN
        CONCAT('https://source.unsplash.com/1920x1080/?suite,luxury,hotel,lounge,interior&sig=', CAST(r.room_no AS UNSIGNED) + 3300)

    WHEN CAST(r.room_no AS UNSIGNED) BETWEEN 401 AND 430
         AND (LOWER(rt.name) LIKE '%suite%' OR LOWER(rt.name) LIKE '%vip%' OR LOWER(rt.name) LIKE '%premium%') THEN
        CONCAT('https://source.unsplash.com/1920x1080/?vip,suite,palace,hotel,luxury,gold&sig=', CAST(r.room_no AS UNSIGNED) + 4400)

    WHEN CAST(r.room_no AS UNSIGNED) BETWEEN 401 AND 430 THEN
        CONCAT('https://source.unsplash.com/1920x1080/?family,hotel,room,multiple,beds&sig=', CAST(r.room_no AS UNSIGNED) + 4500)

    WHEN CAST(r.room_no AS UNSIGNED) BETWEEN 501 AND 550 THEN
        CONCAT('https://source.unsplash.com/1920x1080/?premium,vip,suite,palace,panoramic,view&sig=', CAST(r.room_no AS UNSIGNED) + 5500)

    WHEN LOWER(rt.name) LIKE '%premium%' OR LOWER(rt.name) LIKE '%vip%' OR LOWER(rt.name) LIKE '%presidential%' THEN
        CONCAT('https://source.unsplash.com/1920x1080/?premium,vip,suite,palace,interior&sig=', r.room_id + 6500)

    WHEN LOWER(rt.name) LIKE '%family%' THEN
        CONCAT('https://source.unsplash.com/1920x1080/?family,hotel,room,interior,comfort&sig=', r.room_id + 6600)

    WHEN LOWER(rt.name) LIKE '%suite%' THEN
        CONCAT('https://source.unsplash.com/1920x1080/?luxury,suite,hotel,interior,lounge&sig=', r.room_id + 6700)

    WHEN LOWER(rt.name) LIKE '%deluxe%' THEN
        CONCAT('https://source.unsplash.com/1920x1080/?deluxe,hotel,room,warm,interior&sig=', r.room_id + 6800)

    ELSE
        CONCAT('https://source.unsplash.com/1920x1080/?standard,hotel,room,interior&sig=', r.room_id + 6900)
END;

-- ------------------------------------------------------------
-- FOOD_DINING: assign distinct image URL per dish and category.
-- ------------------------------------------------------------
UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?plated,appetizer,fine,dining&sig=', food_id + 10000)
WHERE menu_category = 'Starters';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?soup,bowl,steam,restaurant,food&sig=', food_id + 11000)
WHERE menu_category = 'Soups';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?main,course,thali,restaurant,food&sig=', food_id + 12000)
WHERE menu_category = 'Main Course';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?naan,bread,indian,food,restaurant&sig=', food_id + 13000)
WHERE menu_category = 'Breads';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?rice,biryani,pulao,indian,food&sig=', food_id + 14000)
WHERE menu_category = 'Rice';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?dessert,pastry,icecream,gulab,jamun&sig=', food_id + 15000)
WHERE menu_category = 'Desserts';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?coffee,cocktail,juice,beverage,restaurant&sig=', food_id + 16000)
WHERE menu_category = 'Beverages';

UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?meal,set,combo,platter,restaurant&sig=', food_id + 17000)
WHERE menu_category = 'Combos';

-- Fallback category handling.
UPDATE FOOD_DINING
SET image_path = CONCAT('https://source.unsplash.com/1920x1080/?restaurant,food,plated,main,course&sig=', food_id + 18000)
WHERE menu_category NOT IN ('Starters', 'Soups', 'Main Course', 'Breads', 'Rice', 'Desserts', 'Beverages', 'Combos');

-- ------------------------------------------------------------
-- Validation summaries
-- ------------------------------------------------------------
SELECT 'STEP5_ROOM_IMAGE_AUDIT' AS summary,
       COUNT(*) AS total_rooms,
       SUM(CASE WHEN image_path IS NULL OR TRIM(image_path) = '' THEN 1 ELSE 0 END) AS missing_image_path,
       COUNT(DISTINCT image_path) AS distinct_image_paths
FROM ROOMS;

SELECT 'STEP5_FOOD_IMAGE_AUDIT' AS summary,
       COUNT(*) AS total_food,
       SUM(CASE WHEN image_path IS NULL OR TRIM(image_path) = '' THEN 1 ELSE 0 END) AS missing_image_path,
       COUNT(DISTINCT image_path) AS distinct_image_paths
FROM FOOD_DINING;

-- Mapping extracts
SELECT r.room_id, r.room_no, rt.name AS room_type, r.image_path
FROM ROOMS r
JOIN ROOM_TYPE rt ON rt.room_type_id = r.room_type_id
ORDER BY CAST(r.room_no AS UNSIGNED), r.room_id;

SELECT food_id, title, menu_category, image_path
FROM FOOD_DINING
ORDER BY menu_category, food_id;
