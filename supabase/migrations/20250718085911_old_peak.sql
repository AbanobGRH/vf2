-- VITA Health Platform Database Schema for MariaDB/phpMyAdmin
-- Compatible with MariaDB 10.x and phpMyAdmin

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `vita_health` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vita_health`;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `email` varchar(255) NOT NULL,
    `full_name` varchar(255) NOT NULL,
    `date_of_birth` date DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `blood_type` varchar(10) DEFAULT NULL,
    `medical_conditions` json DEFAULT NULL,
    `allergies` json DEFAULT NULL,
    `emergency_contact_name` varchar(255) DEFAULT NULL,
    `emergency_contact_phone` varchar(20) DEFAULT NULL,
    `primary_physician` varchar(255) DEFAULT NULL,
    `insurance_info` text DEFAULT NULL,
    `breakfast_time` time DEFAULT '08:00:00',
    `lunch_time` time DEFAULT '12:00:00',
    `dinner_time` time DEFAULT '18:00:00',
    `snack_times` json DEFAULT NULL,
    `dietary_restrictions` json DEFAULT NULL,
    `preferred_meal_size` enum('small','medium','large') DEFAULT 'medium',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Health readings table (removed blood pressure as requested)
CREATE TABLE IF NOT EXISTS `health_readings` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `heart_rate` int(11) DEFAULT NULL,
    `spo2` int(11) DEFAULT NULL,
    `glucose_level` int(11) DEFAULT NULL,
    `reading_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
    `device_id` varchar(100) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_health_readings_user_timestamp` (`user_id`, `reading_timestamp` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locations table
CREATE TABLE IF NOT EXISTS `locations` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `latitude` decimal(10,8) DEFAULT NULL,
    `longitude` decimal(11,8) DEFAULT NULL,
    `accuracy` decimal(5,2) DEFAULT NULL,
    `speed` decimal(5,2) DEFAULT NULL,
    `location_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
    `is_safe_zone` tinyint(1) DEFAULT 0,
    `zone_name` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_locations_user_timestamp` (`user_id`, `location_timestamp` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medications table
CREATE TABLE IF NOT EXISTS `medications` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `name` varchar(255) NOT NULL,
    `dosage` varchar(100) NOT NULL,
    `frequency` varchar(100) NOT NULL,
    `times` json NOT NULL,
    `condition_for` varchar(255) DEFAULT NULL,
    `instructions` text DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medication reminders table
CREATE TABLE IF NOT EXISTS `medication_reminders` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `medication_id` varchar(36) NOT NULL,
    `reminder_time` timestamp NOT NULL,
    `is_taken` tinyint(1) DEFAULT 0,
    `audio_file_path` varchar(500) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `taken_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_medication_reminders_time` (`reminder_time`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medication_id`) REFERENCES `medications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alerts table
CREATE TABLE IF NOT EXISTS `alerts` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `alert_type` varchar(100) NOT NULL,
    `message` text NOT NULL,
    `severity` enum('low','medium','high') DEFAULT 'medium',
    `is_read` tinyint(1) DEFAULT 0,
    `metadata` json DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `read_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_alerts_user_created` (`user_id`, `created_at` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Family members table
CREATE TABLE IF NOT EXISTS `family_members` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `relationship` varchar(100) NOT NULL,
    `access_level` enum('basic','medical','full') DEFAULT 'basic',
    `permissions` json DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safe zones table
CREATE TABLE IF NOT EXISTS `safe_zones` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `name` varchar(255) NOT NULL,
    `latitude` decimal(10,8) NOT NULL,
    `longitude` decimal(11,8) NOT NULL,
    `radius` int(11) DEFAULT 50,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fall detection events
CREATE TABLE IF NOT EXISTS `fall_events` (
    `id` varchar(36) NOT NULL DEFAULT (UUID()),
    `user_id` varchar(36) NOT NULL,
    `latitude` decimal(10,8) DEFAULT NULL,
    `longitude` decimal(11,8) DEFAULT NULL,
    `acceleration_data` json DEFAULT NULL,
    `confidence_level` decimal(3,2) DEFAULT NULL,
    `is_confirmed` tinyint(1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `confirmed_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO `users` (`id`, `email`, `full_name`, `date_of_birth`, `phone`, `address`, `blood_type`, `medical_conditions`, `allergies`, `emergency_contact_name`, `emergency_contact_phone`, `primary_physician`, `breakfast_time`, `lunch_time`, `dinner_time`, `snack_times`, `dietary_restrictions`, `preferred_meal_size`) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 'margaret.thompson@email.com', 'Margaret Thompson', '1952-03-15', '(555) 123-4567', '123 Oak Street, Springfield, IL 62701', 'O+', '["Hypertension", "Type 2 Diabetes", "High Cholesterol"]', '["Penicillin", "Shellfish"]', 'Sarah Thompson', '(555) 987-6543', 'Dr. Michael Chen', '07:30:00', '12:30:00', '18:30:00', '["10:00:00", "15:30:00"]', '["Low Sodium", "Diabetic Friendly"]', 'small')
ON DUPLICATE KEY UPDATE `email` = `email`;

-- Insert sample medications
INSERT INTO `medications` (`user_id`, `name`, `dosage`, `frequency`, `times`, `condition_for`, `instructions`) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 'Metformin', '500mg', 'Twice daily', '["08:00:00", "20:00:00"]', 'Type 2 Diabetes', 'Take with food'),
('550e8400-e29b-41d4-a716-446655440000', 'Atorvastatin', '20mg', 'Once daily', '["20:00:00"]', 'High Cholesterol', 'Take in the evening')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Insert sample health readings for testing
INSERT INTO `health_readings` (`user_id`, `heart_rate`, `spo2`, `glucose_level`, `device_id`) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 72, 98, 95, 'ESP32_VITA_001'),
('550e8400-e29b-41d4-a716-446655440000', 75, 97, 92, 'ESP32_VITA_001'),
('550e8400-e29b-41d4-a716-446655440000', 68, 99, 88, 'ESP32_VITA_001');

-- Insert sample location data
INSERT INTO `locations` (`user_id`, `latitude`, `longitude`, `accuracy`, `speed`, `is_safe_zone`, `zone_name`) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 39.7392, -104.9903, 3.0, 0.0, 1, 'Home'),
('550e8400-e29b-41d4-a716-446655440000', 39.7390, -104.9905, 4.0, 2.5, 1, 'Home');

-- Insert sample safe zone
INSERT INTO `safe_zones` (`user_id`, `name`, `latitude`, `longitude`, `radius`) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 'Home', 39.7392, -104.9903, 50);

COMMIT;