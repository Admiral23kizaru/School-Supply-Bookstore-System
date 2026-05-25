-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 03:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_supply_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL DEFAULT 'Unknown',
  `user_role` enum('admin','seller','customer','unknown') NOT NULL DEFAULT 'unknown',
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('Success','Failed') NOT NULL DEFAULT 'Success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `user_role`, `action_type`, `description`, `ip_address`, `status`, `created_at`) VALUES
(1, 2, 'ken', 'customer', 'LOGIN_SUCCESS', 'Customer logged in', '::1', 'Success', '2026-04-04 03:42:34'),
(2, 2, 'Customer #2', 'customer', 'ORDER_PLACED', 'Order #ORD-366A75 placed — Total: ₱22.50', '::1', 'Success', '2026-04-04 03:42:44'),
(3, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-04 03:42:56'),
(4, 2, 'ken', 'customer', 'LOGIN_FAILED', 'Failed login attempt — wrong password', '::1', 'Failed', '2026-04-04 04:24:30'),
(5, NULL, 'KEN@gmail.com', 'unknown', 'LOGIN_FAILED', 'Failed login attempt — email not found', '::1', 'Failed', '2026-04-04 04:24:30'),
(6, 2, 'ken', 'customer', 'LOGIN_SUCCESS', 'Customer logged in', '::1', 'Success', '2026-04-04 04:24:36'),
(7, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully (OTP verified)', '::1', 'Success', '2026-04-04 08:35:16'),
(8, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully (OTP verified)', '::1', 'Success', '2026-04-04 08:36:11'),
(9, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully (OTP verified)', '::1', 'Success', '2026-04-04 08:37:38'),
(10, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully (OTP verified)', '::1', 'Success', '2026-04-04 09:24:42'),
(11, 2, 'ken', 'customer', 'LOGIN_FAILED', 'Failed login attempt — wrong password', '::1', 'Failed', '2026-04-12 14:11:07'),
(12, NULL, 'KEN@gmail.com', 'unknown', 'LOGIN_FAILED', 'Failed login attempt — email not found', '::1', 'Failed', '2026-04-12 14:11:07'),
(13, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully (OTP verified)', '::1', 'Success', '2026-04-12 14:12:10'),
(14, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-13 01:38:19'),
(15, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-13 12:18:55'),
(16, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-13 13:13:47'),
(17, 2, 'Admin #2', 'admin', 'USER_APPROVED', 'Approved Seller \"saturn\" (ID 2)', '::1', 'Success', '2026-04-13 13:13:57'),
(18, 2, 'Admin #2', 'admin', 'APPROVAL_EMAIL_SENT', 'Approval email sent to Seller \"saturn\"', '::1', 'Success', '2026-04-13 13:14:02'),
(19, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-19 12:42:03'),
(20, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-19 13:18:34'),
(21, NULL, 'superaadmin@gmail.com', 'unknown', 'LOGIN_FAILED', 'Failed login attempt — email not found', '::1', 'Failed', '2026-04-19 13:21:28'),
(22, 3, 'Super Admin', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-19 13:21:56'),
(23, NULL, 'superaadmin@gmail.com', 'unknown', 'LOGIN_FAILED', 'Failed login attempt — email not found', '::1', 'Failed', '2026-04-19 13:31:55'),
(24, 2, 'alice', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-19 13:32:32'),
(25, 3, 'Super Admin', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-19 13:36:23'),
(26, 2, 'saturn', 'seller', 'LOGIN_SUCCESS', 'Seller logged in to portal', '::1', 'Success', '2026-04-19 13:43:17'),
(27, 3, 'Super Admin', 'admin', 'LOGIN_SUCCESS', 'Admin logged in successfully', '::1', 'Success', '2026-04-19 13:50:13');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image_url` varchar(255) DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `account_status` enum('Active','Suspended','Banned') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `first_name`, `last_name`, `email`, `password`, `created_at`, `profile_image_url`, `is_super_admin`, `account_status`) VALUES
(2, 'alice', NULL, NULL, 'jsaturn879@gmail.com', '$2y$10$sJ4I2sda4wlgHXnWB8eFPOZe3a8bHWhMy3.C43cD6XHGYqi/BYuzC', '2026-04-01 11:24:59', NULL, 0, 'Active'),
(3, 'Super Admin', NULL, NULL, 'superadmin@gmail.com', '$2y$10$Vfn9juy02ynbpYcrmoO9z.skkaFzP1knrou92QUn9EfJxlBc77gOa', '2026-04-19 13:20:19', NULL, 1, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_status` enum('Active','Suspended','Banned') NOT NULL DEFAULT 'Active',
  `profile_image_url` varchar(255) DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `approval_status` varchar(20) NOT NULL DEFAULT 'Approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `first_name`, `last_name`, `email`, `password`, `address`, `created_at`, `account_status`, `profile_image_url`, `verification_token`, `is_verified`, `approval_status`) VALUES
(2, 'ken', NULL, NULL, 'KEN@GMAIL.COM', '$2y$10$MfaXfJ.PPW8I5Wtt9rjCe.GpxMiQ2kUK7TS.R0RUTmaTE1TLR6TG6', NULL, '2026-03-22 11:36:09', 'Active', NULL, NULL, 0, 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Processing','Delivered','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(20) DEFAULT 'Cash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `total_amount`, `status`, `created_at`, `payment_method`) VALUES
('ORD-366A75', 2, 22.50, 'Pending', '2026-04-04 03:42:44', 'Cash'),
('ORD-5E0C2D', 2, 45.00, 'Pending', '2026-04-03 11:46:44', 'Cash'),
('ORD-C18D07', 2, 45.00, 'Delivered', '2026-03-22 12:54:13', 'Cash'),
('ORD-E61F59', 2, 22.50, 'Delivered', '2026-03-23 04:40:07', 'Cash');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 'ORD-C18D07', 1, 1, 45.00),
(2, 'ORD-E61F59', 6, 1, 22.50),
(3, 'ORD-5E0C2D', 1, 1, 45.00),
(4, 'ORD-366A75', 6, 1, 22.50);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `status` enum('In Stock','Low Stock','Out of Stock') DEFAULT 'In Stock',
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `category`, `description`, `price`, `stock`, `status`, `image_url`, `created_at`) VALUES
(1, NULL, 'Yellow Pad (80 leaves)', 'PAPERs', 'Unit: pc', 45.00, 118, 'In Stock', '/School Supply Bookstore System/assets/uploads/products/yellowpad.jpg', '2026-03-22 10:34:18'),
(2, NULL, 'Intermediate Pad', 'PAPER', 'High quality intermediate pad paper for writing.', 38.50, 85, 'In Stock', '/School Supply Bookstore System/assets/uploads/products/Intermediate Pad.webp', '2026-03-22 10:34:18'),
(3, NULL, 'Faber-Castell Pencil', 'WRITING', 'Classic No. 2 HB pencil, pre-sharpened.', 12.75, 9, 'Low Stock', '/School Supply Bookstore System/assets/uploads/products/Faber-Castell Pencil.jpg', '2026-03-22 10:34:18'),
(4, NULL, 'Ballpoint Pen (12pc)', 'WRITING', 'Box of 12 black ballpoint pens.', 29.00, 64, 'In Stock', '/School Supply Bookstore System/assets/uploads/products/Ballpoint Pen.jpg', '2026-03-22 10:34:18'),
(5, NULL, 'Scotch Tape (1 in)', 'SUPPLY', 'Clear transparent tape 1 inch wide.', 18.00, 200, 'In Stock', '/School Supply Bookstore System/assets/uploads/products/Scotch Tape.jpg', '2026-03-22 10:34:18'),
(6, NULL, 'Index Cards (100pc)', 'PAPER', 'White unruled index cards 3x5 size.', 22.50, 3, 'Low Stock', '/School Supply Bookstore System/assets/uploads/products/Index Cards.jpg', '2026-03-22 10:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `sellers`
--

CREATE TABLE `sellers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `store_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_status` enum('Active','Suspended','Banned') NOT NULL DEFAULT 'Active',
  `profile_image_url` varchar(255) DEFAULT NULL,
  `approval_status` varchar(20) NOT NULL DEFAULT 'Approved',
  `verification_token` varchar(64) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sellers`
--

INSERT INTO `sellers` (`id`, `name`, `first_name`, `last_name`, `store_name`, `email`, `password`, `created_at`, `account_status`, `profile_image_url`, `approval_status`, `verification_token`, `is_verified`) VALUES
(1, 'aaron', NULL, NULL, NULL, 'admin@fingerlings.com', '$2y$10$CbFZI1c/yRIBMFVoiEtGbO6p.WBlZKQIFBRvB3TeBM1tO0EgtBfiK', '2026-04-01 11:25:50', 'Active', NULL, 'Approved', NULL, 0),
(2, 'saturn', 'jenhu', 'pagara', NULL, 'aaronryo.gemang@nmsc.edu.ph', '$2y$10$5086NhIk2RilvZ/k56CKneUzIQLihmGUiRyjiCRZDh9iDRSSiwSSS', '2026-04-13 13:13:11', 'Active', NULL, 'Approved', NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `sellers`
--
ALTER TABLE `sellers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sellers`
--
ALTER TABLE `sellers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carts_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
