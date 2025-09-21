-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 21, 2025 at 12:46 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `manufacturing_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `bom_header`
--

CREATE TABLE `bom_header` (
  `id` int(11) NOT NULL,
  `bom_code` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `version` varchar(10) DEFAULT '1.0',
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bom_materials`
--

CREATE TABLE `bom_materials` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_required` decimal(10,3) NOT NULL,
  `wastage_percentage` decimal(5,2) DEFAULT 0.00,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bom_operations`
--

CREATE TABLE `bom_operations` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `operation_sequence` int(11) NOT NULL,
  `operation_name` varchar(100) NOT NULL,
  `work_center_id` int(11) NOT NULL,
  `setup_time_minutes` int(11) DEFAULT 0,
  `operation_time_minutes` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacturing_orders`
--

CREATE TABLE `manufacturing_orders` (
  `id` int(11) NOT NULL,
  `mo_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `quantity_to_produce` decimal(10,3) NOT NULL,
  `quantity_produced` decimal(10,3) DEFAULT 0.000,
  `scheduled_start_date` date NOT NULL,
  `scheduled_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `assignee_id` int(11) DEFAULT NULL,
  `status` enum('planned','in_progress','completed','cancelled') DEFAULT 'planned',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('raw_material','finished_good','semi_finished') NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `current_stock` decimal(10,3) DEFAULT 0.000,
  `minimum_stock` decimal(10,3) DEFAULT 0.000,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `product_name`, `product_type`, `unit_of_measure`, `unit_cost`, `current_stock`, `minimum_stock`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'RM001', 'Wooden Legs', 'raw_material', 'PCS', 5.50, 200.000, 50.000, NULL, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(2, 'RM002', 'Wooden Top', 'raw_material', 'PCS', 15.00, 100.000, 20.000, NULL, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(3, 'RM003', 'Screws', 'raw_material', 'PCS', 0.10, 5000.000, 1000.000, NULL, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(4, 'RM004', 'Varnish Bottle', 'raw_material', 'BTL', 8.00, 50.000, 10.000, NULL, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(5, 'FG001', 'Wooden Table', 'finished_good', 'PCS', 0.00, 0.000, 5.000, NULL, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(6, 'WP1001', 'woodplank', 'raw_material', 'kg', 1000.00, 10000.000, 500.000, 'its good', 1, '2025-09-20 22:40:25', '2025-09-20 22:40:25');

-- --------------------------------------------------------

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE `stock_ledger` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('in','out','adjustment') NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_value` decimal(12,2) DEFAULT 0.00,
  `reference_type` enum('mo','wo','adjustment','purchase','sale') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `balance_after` decimal(10,3) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','manager','operator','inventory') NOT NULL DEFAULT 'operator',
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@manufacturing.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', NULL, 1, '2025-09-20 22:19:23', '2025-09-20 22:19:23'),
(2, 'mrkrisshu', 'mrkrisshu@gmail.com', '$2y$10$HP3LVroJgpfup9WtthPSjeUP8JDF/i539NykWApHY1XOYyCeNG1ce', 'Krishna Bantola D', 'operator', '7022696385', 1, '2025-09-20 22:21:21', '2025-09-20 22:21:21');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 1 day),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `otp_code`, `otp_expires_at`, `expires_at`, `created_at`) VALUES
(1, 2, '361f7adbdf355309244f206f523abb88a7855a774d17721de3bd6f017f2338c6', NULL, NULL, '2025-09-20 17:51:32', '2025-09-20 22:21:32'),
(2, 2, 'd5e0f63b0e0dcb575916dc81499a8a80070e0b49cd0d4a888eb147b5130d2d89', NULL, NULL, '2025-09-20 17:59:37', '2025-09-20 22:29:37');

-- --------------------------------------------------------

--
-- Table structure for table `work_centers`
--

CREATE TABLE `work_centers` (
  `id` int(11) NOT NULL,
  `center_code` varchar(50) NOT NULL,
  `center_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `hourly_cost` decimal(8,2) DEFAULT 0.00,
  `capacity_per_hour` decimal(8,2) DEFAULT 1.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_centers`
--

INSERT INTO `work_centers` (`id`, `center_code`, `center_name`, `description`, `hourly_cost`, `capacity_per_hour`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ASM001', 'Assembly Line', 'Main assembly line for product assembly', 25.00, 10.00, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(2, 'PNT001', 'Paint Floor', 'Painting and finishing operations', 30.00, 8.00, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(3, 'PCK001', 'Packaging Line', 'Final packaging and quality check', 20.00, 15.00, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24'),
(4, 'CUT001', 'Cutting Station', 'Material cutting and preparation', 35.00, 5.00, 1, '2025-09-20 22:19:24', '2025-09-20 22:19:24');

-- --------------------------------------------------------

--
-- Table structure for table `work_orders`
--

CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL,
  `wo_number` varchar(50) NOT NULL,
  `mo_id` int(11) NOT NULL,
  `operation_id` int(11) NOT NULL,
  `work_center_id` int(11) NOT NULL,
  `operation_name` varchar(100) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `planned_start_time` datetime DEFAULT NULL,
  `planned_end_time` datetime DEFAULT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `status` enum('pending','started','paused','completed','cancelled') DEFAULT 'pending',
  `setup_time_minutes` int(11) DEFAULT 0,
  `operation_time_minutes` int(11) NOT NULL,
  `actual_time_minutes` int(11) DEFAULT 0,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bom_header`
--
ALTER TABLE `bom_header`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bom_code` (`bom_code`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bom_materials`
--
ALTER TABLE `bom_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `bom_operations`
--
ALTER TABLE `bom_operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `work_center_id` (`work_center_id`);

--
-- Indexes for table `manufacturing_orders`
--
ALTER TABLE `manufacturing_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mo_number` (`mo_number`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `assignee_id` (`assignee_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_mo_status` (`status`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `idx_products_code` (`product_code`);

--
-- Indexes for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_stock_ledger_product` (`product_id`),
  ADD KEY `idx_stock_ledger_date` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_username` (`username`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `work_centers`
--
ALTER TABLE `work_centers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `center_code` (`center_code`);

--
-- Indexes for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wo_number` (`wo_number`),
  ADD KEY `mo_id` (`mo_id`),
  ADD KEY `operation_id` (`operation_id`),
  ADD KEY `work_center_id` (`work_center_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_wo_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bom_header`
--
ALTER TABLE `bom_header`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bom_materials`
--
ALTER TABLE `bom_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bom_operations`
--
ALTER TABLE `bom_operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manufacturing_orders`
--
ALTER TABLE `manufacturing_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `work_centers`
--
ALTER TABLE `work_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `work_orders`
--
ALTER TABLE `work_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bom_header`
--
ALTER TABLE `bom_header`
  ADD CONSTRAINT `bom_header_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `bom_header_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `bom_materials`
--
ALTER TABLE `bom_materials`
  ADD CONSTRAINT `bom_materials_ibfk_1` FOREIGN KEY (`bom_id`) REFERENCES `bom_header` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bom_materials_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `bom_operations`
--
ALTER TABLE `bom_operations`
  ADD CONSTRAINT `bom_operations_ibfk_1` FOREIGN KEY (`bom_id`) REFERENCES `bom_header` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bom_operations_ibfk_2` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`);

--
-- Constraints for table `manufacturing_orders`
--
ALTER TABLE `manufacturing_orders`
  ADD CONSTRAINT `manufacturing_orders_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `manufacturing_orders_ibfk_2` FOREIGN KEY (`bom_id`) REFERENCES `bom_header` (`id`),
  ADD CONSTRAINT `manufacturing_orders_ibfk_3` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `manufacturing_orders_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `stock_ledger_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ledger_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD CONSTRAINT `work_orders_ibfk_1` FOREIGN KEY (`mo_id`) REFERENCES `manufacturing_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_orders_ibfk_2` FOREIGN KEY (`operation_id`) REFERENCES `bom_operations` (`id`),
  ADD CONSTRAINT `work_orders_ibfk_3` FOREIGN KEY (`work_center_id`) REFERENCES `work_centers` (`id`),
  ADD CONSTRAINT `work_orders_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
