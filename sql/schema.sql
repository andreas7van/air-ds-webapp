-- Air DS — database schema
-- Drops and recreates the `air_ds` database.

DROP DATABASE IF EXISTS `air_ds`;
CREATE DATABASE `air_ds`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `air_ds`;

-- Registered users. Passwords are stored as bcrypt hashes (password_hash).
CREATE TABLE `users` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `first_name`  VARCHAR(50)  NOT NULL,
  `last_name`   VARCHAR(50)  NOT NULL,
  `username`    VARCHAR(50)  NOT NULL UNIQUE,
  `password`    VARCHAR(255) NOT NULL,
  `email`       VARCHAR(100) NOT NULL UNIQUE,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Airports served by the demo airline. `tax` is the per-passenger airport fee.
CREATE TABLE `airports` (
  `id`        INT AUTO_INCREMENT PRIMARY KEY,
  `name`      VARCHAR(100)  NOT NULL,
  `code`      VARCHAR(10)   NOT NULL UNIQUE,
  `latitude`  DECIMAL(10,6) NOT NULL,
  `longitude` DECIMAL(10,6) NOT NULL,
  `tax`       DECIMAL(6,2)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reservations. `seats` is a comma-separated list of seat ids (e.g. "1A,1B").
CREATE TABLE `reservations` (
  `id`                   INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`              INT           NOT NULL,
  `departure_airport_id` INT           NOT NULL,
  `arrival_airport_id`   INT           NOT NULL,
  `flight_date`          DATE          NOT NULL,
  `passengers_count`     INT           NOT NULL,
  `seats`                VARCHAR(255)  NOT NULL,
  `total_cost`           DECIMAL(10,2) NOT NULL,
  `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`)              REFERENCES `users`   (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`departure_airport_id`) REFERENCES `airports`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`arrival_airport_id`)   REFERENCES `airports`(`id`) ON DELETE RESTRICT,
  CHECK (`departure_airport_id` <> `arrival_airport_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
