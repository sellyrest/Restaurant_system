-- 3NF schema for restaurant_db
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `restaurant_db`;
USE `restaurant_db`;

DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_item_prices`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `payment_methods`;
DROP TABLE IF EXISTS `order_statuses`;
DROP TABLE IF EXISTS `tables_list`;
DROP TABLE IF EXISTS `table_statuses`;
DROP TABLE IF EXISTS `menu_prices`;
DROP TABLE IF EXISTS `menu`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `available` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_menu_category_id` (`category_id`),
  CONSTRAINT `fk_menu_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `menu_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `effective_from` timestamp NOT NULL DEFAULT current_timestamp(),
  `effective_to` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menu_prices_menu_id` (`menu_id`),
  CONSTRAINT `fk_menu_prices_menu_id` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `table_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_table_statuses_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tables_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_number` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tables_table_number` (`table_number`),
  KEY `idx_tables_status_id` (`status_id`),
  CONSTRAINT `fk_tables_status_id` FOREIGN KEY (`status_id`) REFERENCES `table_statuses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_statuses_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orders_table_id` (`table_id`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_status_id` (`status_id`),
  CONSTRAINT `fk_orders_table_id` FOREIGN KEY (`table_id`) REFERENCES `tables_list` (`id`),
  CONSTRAINT `fk_orders_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_orders_status_id` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_menu_id` (`menu_id`),
  CONSTRAINT `fk_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_menu_id` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order_item_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_item_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_item_prices_order_item_id` (`order_item_id`),
  CONSTRAINT `fk_order_item_prices_order_item_id` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_methods_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `method_id` int(11) NOT NULL,
  `paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payments_order_id` (`order_id`),
  KEY `idx_payments_method_id` (`method_id`),
  CONSTRAINT `fk_payments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_method_id` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`name`) VALUES
('Main'),
('Drink'),
('Dessert');

INSERT INTO `table_statuses` (`name`) VALUES
('available'),
('occupied');

INSERT INTO `order_statuses` (`name`) VALUES
('pending'),
('preparing'),
('served'),
('paid');

INSERT INTO `payment_methods` (`name`) VALUES
('cash'),
('card'),
('gopay'),
('ovo'),
('qris');

INSERT INTO `customers` (`name`, `phone`, `created_at`) VALUES
('Glory Kristy', NULL, '2026-04-13 04:42:05'),
('Selly Resty', '082363847', '2026-04-13 04:50:56'),
('Lala', NULL, '2026-04-13 05:19:26'),
('Mark', NULL, '2026-04-13 05:25:10'),
('awo', NULL, '2026-04-13 05:40:57'),
('FayMar', NULL, '2026-04-13 13:06:45');

INSERT INTO `menu` (`id`, `name`, `category_id`, `available`) VALUES
(1, 'Nasi Goreng', (SELECT id FROM categories WHERE name='Main'), 1),
(2, 'Mie Goreng', (SELECT id FROM categories WHERE name='Main'), 1),
(3, 'Ayam Bakar', (SELECT id FROM categories WHERE name='Main'), 1),
(4, 'Es Teh', (SELECT id FROM categories WHERE name='Drink'), 1),
(5, 'Jus Alpukat', (SELECT id FROM categories WHERE name='Drink'), 1),
(6, 'Lava Cake', (SELECT id FROM categories WHERE name='Dessert'), 1),
(7, 'Choco Pudding', (SELECT id FROM categories WHERE name='Dessert'), 1),
(8, 'Bebek Goreng Spesial', (SELECT id FROM categories WHERE name='Main'), 1);

INSERT INTO `menu_prices` (`menu_id`, `price`, `effective_from`, `effective_to`) VALUES
(1, 35000.00, '2026-04-13 00:00:00', NULL),
(2, 30000.00, '2026-04-13 00:00:00', NULL),
(3, 45000.00, '2026-04-13 00:00:00', NULL),
(4, 8000.00, '2026-04-13 00:00:00', NULL),
(5, 15000.00, '2026-04-13 00:00:00', NULL),
(6, 25000.00, '2026-04-13 00:00:00', NULL),
(7, 28000.00, '2026-04-13 00:00:00', NULL),
(8, 45000.00, '2026-04-13 00:00:00', NULL);

INSERT INTO `tables_list` (`id`, `table_number`, `capacity`, `status_id`) VALUES
(1, 'T1', 4, (SELECT id FROM table_statuses WHERE name='available')),
(2, 'T2', 4, (SELECT id FROM table_statuses WHERE name='available')),
(3, 'T3', 6, (SELECT id FROM table_statuses WHERE name='available')),
(4, 'T4', 2, (SELECT id FROM table_statuses WHERE name='available')),
(5, 'T5', 8, (SELECT id FROM table_statuses WHERE name='available'));

INSERT INTO `orders` (`id`, `table_id`, `customer_id`, `status_id`, `created_at`) VALUES
(1, 1, 1, (SELECT id FROM order_statuses WHERE name='paid'), '2026-04-13 04:42:05'),
(2, 4, 2, (SELECT id FROM order_statuses WHERE name='served'), '2026-04-13 04:45:49'),
(3, 3, 3, (SELECT id FROM order_statuses WHERE name='served'), '2026-04-13 05:19:26'),
(4, 3, 4, (SELECT id FROM order_statuses WHERE name='served'), '2026-04-13 05:25:10'),
(5, 4, 5, (SELECT id FROM order_statuses WHERE name='paid'), '2026-04-13 05:40:57'),
(6, 3, 6, (SELECT id FROM order_statuses WHERE name='paid'), '2026-04-13 13:06:45');

INSERT INTO `order_items` (`id`, `order_id`, `menu_id`, `quantity`) VALUES
(1, 1, 2, 1),
(2, 1, 4, 1),
(3, 2, 3, 1),
(4, 3, 5, 2),
(5, 3, 3, 1),
(6, 4, 6, 1),
(7, 4, 4, 1),
(8, 5, 8, 3),
(9, 5, 4, 1),
(10, 6, 2, 1),
(11, 6, 3, 1),
(12, 6, 5, 1);

INSERT INTO `order_item_prices` (`order_item_id`, `price`) VALUES
(1, 30000.00),
(2, 8000.00),
(3, 45000.00),
(4, 15000.00),
(5, 45000.00),
(6, 25000.00),
(7, 8000.00),
(8, 45000.00),
(9, 8000.00),
(10, 30000.00),
(11, 45000.00),
(12, 15000.00);

INSERT INTO `payments` (`order_id`, `method_id`, `paid`, `paid_at`) VALUES
(3, (SELECT id FROM payment_methods WHERE name='card'), 75000.00, '2026-04-13 05:20:07'),
(4, (SELECT id FROM payment_methods WHERE name='cash'), 33000.00, '2026-04-13 05:25:26'),
(2, (SELECT id FROM payment_methods WHERE name='cash'), 45000.00, '2026-04-13 05:37:28'),
(1, (SELECT id FROM payment_methods WHERE name='cash'), 38000.00, '2026-04-13 05:38:25'),
(5, (SELECT id FROM payment_methods WHERE name='qris'), 143000.00, '2026-04-13 05:41:17'),
(6, (SELECT id FROM payment_methods WHERE name='qris'), 90000.00, '2026-04-13 13:07:42');

COMMIT;