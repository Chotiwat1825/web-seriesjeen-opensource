-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 28, 2026 at 06:42 PM
-- Server version: 10.6.23-MariaDB
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dogdaco_testweb`
--

-- --------------------------------------------------------

--
-- Table structure for table `key_codes`
--

CREATE TABLE `key_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `expires_at` datetime DEFAULT NULL,
  `hwid` varchar(255) DEFAULT NULL,
  `last_active_at` datetime DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `key_codes`
--

INSERT INTO `key_codes` (`id`, `code`, `role`, `expires_at`, `hwid`, `last_active_at`, `user_agent`, `ip_address`, `created_at`) VALUES
(1, 'SJ-ADMIN-9999', 'admin', NULL, NULL, NULL, NULL, NULL, '2026-05-28 09:15:57');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `is_visible`) VALUES
('contact_facebook', 'https://facebook.com/seriesjeen', 1),
('contact_line', '@seriesjeen', 1),
('contact_other', 'https://t.me/seriesjeen', 1),
('web_bg_color', '#000000', 1),
('web_footer_color', '#000000', 1),
('web_footer_text', 'seriesjeen.online', 1),
('web_footer_url', 'https://api.seriesjeen.online', 1),
('web_gradient_color', '#b48cd9', 1),
('web_login_description', 'หนังใหม่ใสเเล้วจ้า seriesjeen.online', 1),
('web_logo_url', 'https://rental.seriesjeen.online/mascot_no_bg_smooth.png', 1),
('web_logo_width', '32', 1),
('web_name', 'test', 1),
('web_navbar_color', '#000000', 1),
('web_theme_color', '#8e63f2', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `key_codes`
--
ALTER TABLE `key_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `key_codes`
--
ALTER TABLE `key_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
