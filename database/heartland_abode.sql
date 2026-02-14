-- The Heartland Abode Hotel Management System Database
-- Created: October 2024

CREATE DATABASE IF NOT EXISTS heartland_abode;
USE heartland_abode;

-- Drop tables if they exist (for clean setup)
DROP TABLE IF EXISTS PAYMENTS;
DROP TABLE IF EXISTS RESERVATION_FOOD;
DROP TABLE IF EXISTS FOOD_DINING;
DROP TABLE IF EXISTS RESERVATION;
DROP TABLE IF EXISTS RESERVATION_TYPE;
DROP TABLE IF EXISTS ROOM_FEATURES_MAP;
DROP TABLE IF EXISTS ROOM_FEATURES;
DROP TABLE IF EXISTS ROOMS;
DROP TABLE IF EXISTS ROOM_TYPE;
DROP TABLE IF EXISTS pricing_seasons;
DROP TABLE IF EXISTS pricing_settings;
DROP TABLE IF EXISTS GUEST;
DROP TABLE IF EXISTS STAFF;
DROP TABLE IF EXISTS DEPARTMENT;
DROP TABLE IF EXISTS HOTEL;
DROP TABLE IF EXISTS COUPON;
DROP TABLE IF EXISTS admin_users;

-- HOTEL Table
CREATE TABLE HOTEL (
    hotel_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    gstin VARCHAR(15),
    location TEXT,
    star_rating INT DEFAULT 5,
    rooms_count INT DEFAULT 0,
    contact_info JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- DEPARTMENT Table
CREATE TABLE DEPARTMENT (
    dep_id INT PRIMARY KEY AUTO_INCREMENT,
    dep_name VARCHAR(100) NOT NULL,
    manager_staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- STAFF Table
CREATE TABLE STAFF (
    staff_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    salary DECIMAL(10,2) NOT NULL,
    dep_id INT,
    phone VARCHAR(15),
    email VARCHAR(255),
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dep_id) REFERENCES DEPARTMENT(dep_id) ON DELETE SET NULL
);

-- Add foreign key constraint for manager_staff_id after STAFF table is created
ALTER TABLE DEPARTMENT ADD FOREIGN KEY (manager_staff_id) REFERENCES STAFF(staff_id) ON DELETE SET NULL;

-- GUEST Table
CREATE TABLE GUEST (
    guest_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    age INT,
    in_id VARCHAR(20), -- ID proof number
    phone_no VARCHAR(15) NOT NULL,
    email VARCHAR(255),
    address TEXT,
    password VARCHAR(255), -- For guest login
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ROOM_TYPE Table
CREATE TABLE ROOM_TYPE (
    room_type_id INT PRIMARY KEY AUTO_INCREMENT,
    name ENUM('1 TIER', '2 TIER', '3 TIER') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ROOMS Table
CREATE TABLE ROOMS (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_no VARCHAR(10) NOT NULL UNIQUE,
    hotel_id INT NOT NULL,
    room_type_id INT NOT NULL,
    tier INT NOT NULL,
    rent DECIMAL(10,2) NOT NULL,
    status ENUM('Available', 'Occupied', 'Maintenance', 'Reserved') DEFAULT 'Available',
    description TEXT,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES HOTEL(hotel_id) ON DELETE CASCADE,
    FOREIGN KEY (room_type_id) REFERENCES ROOM_TYPE(room_type_id) ON DELETE CASCADE
);

-- ROOM_FEATURES Table
CREATE TABLE ROOM_FEATURES (
    room_feature_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ROOM_FEATURES_MAP Table (Many-to-Many relationship)
CREATE TABLE ROOM_FEATURES_MAP (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    feature_id INT NOT NULL,
    FOREIGN KEY (room_id) REFERENCES ROOMS(room_id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES ROOM_FEATURES(room_feature_id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_feature (room_id, feature_id)
);

-- RESERVATION_TYPE Table
CREATE TABLE RESERVATION_TYPE (
    reservation_type_id INT PRIMARY KEY AUTO_INCREMENT,
    name ENUM('Premium', 'Standard') NOT NULL,
    payment_rule ENUM('Advance', 'Pay on arrival') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RESERVATION Table
CREATE TABLE RESERVATION (
    res_id INT PRIMARY KEY AUTO_INCREMENT,
    guest_id INT NOT NULL,
    room_id INT,
    r_date DATE NOT NULL, -- Reservation date
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    reservation_type_id INT NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled') DEFAULT 'Pending',
    total_price DECIMAL(10,2) NOT NULL,
    special_requests TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES GUEST(guest_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES ROOMS(room_id) ON DELETE SET NULL,
    FOREIGN KEY (reservation_type_id) REFERENCES RESERVATION_TYPE(reservation_type_id) ON DELETE CASCADE
);

-- FOOD_DINING Table
CREATE TABLE FOOD_DINING (
    food_id INT PRIMARY KEY AUTO_INCREMENT,
    order_no VARCHAR(20),
    title VARCHAR(255) NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    food_type ENUM('VEG', 'NON-VEG') NOT NULL,
    description TEXT,
    image_path VARCHAR(500),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- RESERVATION_FOOD Table (Many-to-Many relationship)
CREATE TABLE RESERVATION_FOOD (
    id INT PRIMARY KEY AUTO_INCREMENT,
    res_id INT NOT NULL,
    food_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    price DECIMAL(8,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (res_id) REFERENCES RESERVATION(res_id) ON DELETE CASCADE,
    FOREIGN KEY (food_id) REFERENCES FOOD_DINING(food_id) ON DELETE CASCADE
);

-- COUPON Table
CREATE TABLE COUPON (
    code VARCHAR(20) PRIMARY KEY,
    type ENUM('Flat', 'Percent') NOT NULL,
    value DECIMAL(8,2) NOT NULL,
    expiry DATE NOT NULL,
    usage_limit INT DEFAULT 1,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dynamic Pricing Settings Table
CREATE TABLE pricing_settings (
    id TINYINT PRIMARY KEY,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    min_multiplier DECIMAL(5,2) NOT NULL DEFAULT 0.70,
    max_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.80,
    occupancy_low_threshold DECIMAL(5,2) NOT NULL DEFAULT 0.40,
    occupancy_high_threshold DECIMAL(5,2) NOT NULL DEFAULT 0.75,
    occupancy_low_adjustment DECIMAL(5,2) NOT NULL DEFAULT -0.10,
    occupancy_high_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.15,
    demand_window_days INT NOT NULL DEFAULT 7,
    demand_low_threshold INT NOT NULL DEFAULT 2,
    demand_high_threshold INT NOT NULL DEFAULT 8,
    demand_low_adjustment DECIMAL(5,2) NOT NULL DEFAULT -0.05,
    demand_high_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.10,
    lead_time_last_minute_days INT NOT NULL DEFAULT 3,
    lead_time_early_bird_days INT NOT NULL DEFAULT 30,
    lead_time_last_minute_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.12,
    lead_time_early_bird_adjustment DECIMAL(5,2) NOT NULL DEFAULT -0.08,
    manual_global_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Dynamic Pricing Seasons Table
CREATE TABLE pricing_seasons (
    season_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    start_mmdd CHAR(5) NOT NULL,
    end_mmdd CHAR(5) NOT NULL,
    multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    description VARCHAR(255),
    priority TINYINT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- PAYMENTS Table
CREATE TABLE PAYMENTS (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    res_id INT NOT NULL,
    gateway VARCHAR(50),
    txn_id VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Success', 'Failed', 'Refunded') DEFAULT 'Pending',
    payment_method ENUM('Card', 'UPI', 'Net Banking', 'Cash') DEFAULT 'Card',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (res_id) REFERENCES RESERVATION(res_id) ON DELETE CASCADE
);

-- Admin Users Table (for admin panel access)
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('SuperAdmin', 'Manager', 'Receptionist', 'Housekeeping', 'Chef') DEFAULT 'Manager',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Sample Data
-- Hotel Information
INSERT INTO HOTEL (name, gstin, location, star_rating, rooms_count, contact_info) VALUES 
('The Heartland Abode', '22AAAAA0000A1Z5', 'New Delhi, India', 5, 50, '{"phone": "+91-11-12345678", "email": "info@heartlandabode.com", "website": "www.heartlandabode.com"}');

-- Departments
INSERT INTO DEPARTMENT (dep_id, dep_name) VALUES 
(1, 'Management'),
(2, 'Housekeeping'),
(3, 'Reception'),
(4, 'Food & Beverage'),
(5, 'Maintenance');

-- Staff (including the required records)
INSERT INTO STAFF (staff_id, name, gender, salary, dep_id, phone, email, hire_date) VALUES 
(101, 'Arjun Sharma', 'Male', 75000, 1, '+91-9876543210', 'arjun@heartlandabode.com', '2023-01-15'),
(102, 'Priya Singh', 'Female', 45000, 3, '+91-9876543211', 'priya@heartlandabode.com', '2023-02-01'),
(103, 'Rohit Kumar', 'Male', 38000, 5, '+91-9876543212', 'rohit@heartlandabode.com', '2023-03-10'),
(104, 'Meena', 'Female', 52000, 2, '+91-9876543213', 'meena@heartlandabode.com', '2023-01-20'),
(105, 'Rajesh', 'Male', 48000, 3, '+91-9876543214', 'rajesh@heartlandabode.com', '2023-02-15');

-- Update department managers
UPDATE DEPARTMENT SET manager_staff_id = 101 WHERE dep_id = 1;
UPDATE DEPARTMENT SET manager_staff_id = 104 WHERE dep_id = 2;
UPDATE DEPARTMENT SET manager_staff_id = 105 WHERE dep_id = 3;

-- Room Types
INSERT INTO ROOM_TYPE (name, description) VALUES 
('1 TIER', 'Luxury Suite with premium amenities'),
('2 TIER', 'Deluxe Room with modern facilities'),
('3 TIER', 'Standard Room with essential amenities');

-- Room Features
INSERT INTO ROOM_FEATURES (name, icon) VALUES 
('AC', 'fas fa-snowflake'),
('Fan', 'fas fa-fan'),
('Double Bed', 'fas fa-bed'),
('Single Bed', 'fas fa-bed'),
('WiFi', 'fas fa-wifi'),
('TV', 'fas fa-tv'),
('Mini Bar', 'fas fa-glass-martini'),
('Balcony', 'fas fa-door-open'),
('Room Service', 'fas fa-concierge-bell'),
('Safe', 'fas fa-lock');

-- Sample Rooms
INSERT INTO ROOMS (room_no, hotel_id, room_type_id, tier, rent, status, description) VALUES 
('101', 1, 1, 1, 8500.00, 'Available', 'Luxury suite with city view and premium amenities'),
('102', 1, 1, 1, 8500.00, 'Available', 'Luxury suite with garden view'),
('201', 1, 2, 2, 5500.00, 'Available', 'Deluxe room with modern facilities'),
('202', 1, 2, 2, 5500.00, 'Occupied', 'Deluxe room with balcony'),
('301', 1, 3, 3, 3500.00, 'Available', 'Standard room with essential amenities');

-- Room Features Mapping
INSERT INTO ROOM_FEATURES_MAP (room_id, feature_id) VALUES 
-- Room 101 (Luxury)
(1, 1), (1, 3), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10),
-- Room 102 (Luxury)
(2, 1), (2, 3), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10),
-- Room 201 (Deluxe)
(3, 1), (3, 3), (3, 5), (3, 6), (3, 8), (3, 9),
-- Room 202 (Deluxe)
(4, 1), (4, 3), (4, 5), (4, 6), (4, 8), (4, 9),
-- Room 301 (Standard)
(5, 2), (5, 4), (5, 5), (5, 6);

-- Reservation Types
INSERT INTO RESERVATION_TYPE (name, payment_rule, description) VALUES 
('Premium', 'Advance', 'Premium booking with advance payment required'),
('Standard', 'Pay on arrival', 'Standard booking with payment on arrival');

-- Sample Guest
INSERT INTO GUEST (name, gender, age, in_id, phone_no, email, address, password) VALUES 
('Amit Patel', 'Male', 32, 'AADHAAR123456789', '+91-9876543220', 'amit.patel@email.com', 'Mumbai, Maharashtra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Sneha Gupta', 'Female', 28, 'AADHAAR987654321', '+91-9876543221', 'sneha.gupta@email.com', 'Bangalore, Karnataka', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample Reservation
INSERT INTO RESERVATION (guest_id, room_id, r_date, check_in, check_out, reservation_type_id, status, total_price) VALUES 
(1, 4, '2024-10-10', '2024-10-15', '2024-10-18', 1, 'Confirmed', 16500.00);

-- Food & Dining
INSERT INTO FOOD_DINING (title, price, food_type, description, is_available) VALUES 
('Butter Chicken', 450.00, 'NON-VEG', 'Creamy tomato-based chicken curry with aromatic spices', TRUE),
('Paneer Makhani', 380.00, 'VEG', 'Rich and creamy cottage cheese curry', TRUE),
('Biryani (Chicken)', 520.00, 'NON-VEG', 'Fragrant basmati rice with tender chicken pieces', TRUE),
('Dal Makhani', 320.00, 'VEG', 'Creamy black lentils cooked overnight', TRUE),
('Fish Curry', 480.00, 'NON-VEG', 'Traditional coastal fish curry with coconut', TRUE),
('Vegetable Pulao', 280.00, 'VEG', 'Aromatic rice with mixed vegetables', TRUE);

-- Sample Food Order
INSERT INTO RESERVATION_FOOD (res_id, food_id, qty, price) VALUES 
(1, 1, 2, 450.00),
(1, 4, 1, 320.00);

-- Coupons
INSERT INTO COUPON (code, type, value, expiry, usage_limit) VALUES 
('WELCOME10', 'Percent', 10.00, '2024-12-31', 100),
('FLAT500', 'Flat', 500.00, '2024-11-30', 50),
('PREMIUM15', 'Percent', 15.00, '2024-12-31', 25);

-- Dynamic Pricing Configuration
INSERT INTO pricing_settings (
    id, is_enabled, min_multiplier, max_multiplier,
    occupancy_low_threshold, occupancy_high_threshold,
    occupancy_low_adjustment, occupancy_high_adjustment,
    demand_window_days, demand_low_threshold, demand_high_threshold,
    demand_low_adjustment, demand_high_adjustment,
    lead_time_last_minute_days, lead_time_early_bird_days,
    lead_time_last_minute_adjustment, lead_time_early_bird_adjustment,
    manual_global_adjustment
) VALUES (
    1, 1, 0.70, 1.80,
    0.40, 0.75,
    -0.10, 0.15,
    7, 2, 8,
    -0.05, 0.10,
    3, 30,
    0.12, -0.08,
    0.00
);

INSERT INTO pricing_seasons (name, start_mmdd, end_mmdd, multiplier, description, priority, is_active) VALUES
('Peak Summer', '04-01', '06-30', 1.20, 'High travel demand during summer season', 3, 1),
('Festive Demand', '12-15', '01-10', 1.30, 'Festive and year-end travel peak', 4, 1),
('Monsoon Saver', '07-01', '09-15', 0.90, 'Promotional season for low-demand period', 2, 1);

-- Sample Payment
INSERT INTO PAYMENTS (res_id, gateway, txn_id, amount, status, payment_method) VALUES 
(1, 'Razorpay', 'pay_123456789', 16500.00, 'Success', 'Card');

-- Admin Users
INSERT INTO admin_users (username, email, password, role) VALUES 
('yogeshkumar', 'yogeshkumar@heartlandabode.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SuperAdmin'),
('manager', 'manager@heartlandabode.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager'),
('reception', 'reception@heartlandabode.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Receptionist');

-- Create indexes for better performance
CREATE INDEX idx_reservation_dates ON RESERVATION(check_in, check_out);
CREATE INDEX idx_reservation_status ON RESERVATION(status);
CREATE INDEX idx_room_status ON ROOMS(status);
CREATE INDEX idx_guest_email ON GUEST(email);
CREATE INDEX idx_payment_status ON PAYMENTS(status);
CREATE INDEX idx_pricing_seasons_active_priority ON pricing_seasons(is_active, priority);

-- Views for common queries
CREATE VIEW room_availability AS
SELECT 
    r.room_id,
    r.room_no,
    r.tier,
    r.rent,
    rt.name as room_type,
    r.status,
    GROUP_CONCAT(rf.name) as features
FROM ROOMS r
JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
LEFT JOIN ROOM_FEATURES_MAP rfm ON r.room_id = rfm.room_id
LEFT JOIN ROOM_FEATURES rf ON rfm.feature_id = rf.room_feature_id
GROUP BY r.room_id;

CREATE VIEW reservation_details AS
SELECT 
    res.res_id,
    g.name as guest_name,
    g.phone_no,
    g.email,
    r.room_no,
    res.check_in,
    res.check_out,
    res.status,
    res.total_price,
    rt.name as reservation_type,
    p.status as payment_status
FROM RESERVATION res
JOIN GUEST g ON res.guest_id = g.guest_id
LEFT JOIN ROOMS r ON res.room_id = r.room_id
JOIN RESERVATION_TYPE rt ON res.reservation_type_id = rt.reservation_type_id
LEFT JOIN PAYMENTS p ON res.res_id = p.res_id;
