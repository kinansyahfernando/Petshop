-- ============================================================
--  Pet Shop — petshop_db  (MySQL 5.7+ / MariaDB 10.3+ / Laragon)
--  Cara pakai: lihat README.md (import via HeidiSQL atau terminal)
--  Akun default:
--    Admin : admin@petshop.com / admin123
--    Kasir : kasir / kasir123
--  GANTI password setelah login pertama!
-- ============================================================

CREATE DATABASE IF NOT EXISTS `petshop_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `petshop_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `transaction_items`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `price`      INT NOT NULL DEFAULT 0,
  `category`   VARCHAR(50)  NOT NULL DEFAULT 'Lainnya',
  `stock`      INT NOT NULL DEFAULT 0,
  `image`      VARCHAR(10)  NOT NULL DEFAULT '📦',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transactions` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `transaction_code` VARCHAR(50) NOT NULL,
  `user_id`          INT DEFAULT NULL,
  `total_amount`     INT NOT NULL DEFAULT 0,
  `payment_amount`   INT NOT NULL DEFAULT 0,
  `change_amount`    INT NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trx_code` (`transaction_code`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_trans_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transaction_items` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `transaction_id` INT NOT NULL,
  `product_id`     INT DEFAULT NULL,
  `quantity`       INT NOT NULL DEFAULT 1,
  `price`          INT NOT NULL DEFAULT 0,
  `subtotal`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_trx` (`transaction_id`),
  KEY `idx_prod` (`product_id`),
  CONSTRAINT `fk_ti_trans`   FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ti_product` FOREIGN KEY (`product_id`)     REFERENCES `products`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Akun default (hash bcrypt)
INSERT INTO `users` (`username`,`email`,`password`,`role`) VALUES
('admin','admin@petshop.com','$2y$10$4y1tJW2qcxoitT9av43mI.uTf8BwdFdq4ieUaBvp22KmVP0Blya4W','admin'),
('kasir', NULL,             '$2y$10$DOS0yqzU7LIV0CuNDLh5eupqip5d/cScvLfEYOme6SGAvRsyl6aEO','kasir');

-- Produk contoh
INSERT INTO `products` (`name`,`price`,`category`,`stock`,`image`) VALUES
('Dog Food Premium',   150000,'Makanan',    25,'🦴'),
('Cat Food Whiskas',    85000,'Makanan',    30,'🟠'),
('Bird Seed Mix',       45000,'Makanan',    40,'🌾'),
('Dog Collar',          65000,'Aksesoris',  15,'🎀'),
('Cat Litter 5kg',      95000,'Perawatan',  20,'🪣'),
('Pet Shampoo',         55000,'Perawatan',  35,'🧴'),
('Dog Toy Ball',        35000,'Mainan',     50,'⚽'),
('Cat Scratching Post',125000,'Mainan',     10,'🪵'),
('Fish Tank Filter',   175000,'Aksesoris',   8,'💧'),
('Pet Vitamin',         75000,'Kesehatan',  22,'💊'),
('Dog Leash',           45000,'Aksesoris',  18,'🦮'),
('Pet Carrier',        285000,'Aksesoris',   5,'🧳');
