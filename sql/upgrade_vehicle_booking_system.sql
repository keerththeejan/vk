-- VK Vehicle Booking System migration
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicle_customers_email (email),
  INDEX idx_vehicle_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_drivers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  license_number VARCHAR(100) NOT NULL,
  availability ENUM('available','on_trip','off_duty') NOT NULL DEFAULT 'available',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicle_drivers_license (license_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_name VARCHAR(190) NOT NULL,
  vehicle_type ENUM('car','van','bike','lorry','bus') NOT NULL DEFAULT 'car',
  registration_number VARCHAR(64) NOT NULL,
  price_per_day DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  price_per_km DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  default_driver_charge DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  assigned_driver_id INT UNSIGNED DEFAULT NULL,
  status ENUM('available','booked','maintenance') NOT NULL DEFAULT 'available',
  image_path VARCHAR(512) DEFAULT NULL,
  seats INT UNSIGNED NOT NULL DEFAULT 4,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicles_reg (registration_number),
  INDEX idx_vehicles_type_status (vehicle_type, status),
  CONSTRAINT fk_vehicles_driver FOREIGN KEY (assigned_driver_id) REFERENCES vehicle_drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(40) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  booking_type ENUM('rental','hire') NOT NULL,
  vehicle_id INT UNSIGNED DEFAULT NULL,
  driver_id INT UNSIGNED DEFAULT NULL,
  status ENUM('pending','confirmed','ongoing','completed','cancelled') NOT NULL DEFAULT 'pending',
  pickup_location VARCHAR(255) NOT NULL,
  pickup_lat DECIMAL(10,7) DEFAULT NULL,
  pickup_lng DECIMAL(10,7) DEFAULT NULL,
  drop_location VARCHAR(255) DEFAULT NULL,
  drop_lat DECIMAL(10,7) DEFAULT NULL,
  drop_lng DECIMAL(10,7) DEFAULT NULL,
  pickup_at DATETIME NOT NULL,
  return_at DATETIME DEFAULT NULL,
  vehicle_type ENUM('car','van','bike','lorry','bus') NOT NULL DEFAULT 'car',
  passengers INT UNSIGNED NOT NULL DEFAULT 1,
  distance_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  rental_days INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  driver_charge DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  special_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicle_bookings_ref (booking_ref),
  INDEX idx_vehicle_bookings_customer (customer_id, created_at),
  INDEX idx_vehicle_bookings_status (status),
  INDEX idx_vehicle_bookings_type (booking_type),
  CONSTRAINT fk_vehicle_bookings_customer FOREIGN KEY (customer_id) REFERENCES vehicle_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_vehicle_bookings_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
  CONSTRAINT fk_vehicle_bookings_driver FOREIGN KEY (driver_id) REFERENCES vehicle_drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
