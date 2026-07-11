-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2026 at 01:04 AM
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
-- Database: `library_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `authors`
--

CREATE TABLE `authors` (
  `author_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `birth_year` year(4) DEFAULT NULL,
  `death_year` year(4) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `authors`
--

INSERT INTO `authors` (`author_id`, `name`, `birth_year`, `death_year`, `nationality`, `created_at`, `updated_at`) VALUES
(1, 'J.K. Rowling', '1965', '2007', 'British', '2025-12-11 11:22:06', '2025-12-11 13:30:33'),
(2, 'George Orwell', '1903', '1950', 'British', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(3, 'Jane Austen', '1967', NULL, 'British', '2025-12-11 11:22:06', '2025-12-11 11:25:44'),
(4, 'Stephen King', '1947', NULL, 'American', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(5, 'Agatha Christie', '1903', '1976', 'British', '2025-12-11 11:22:06', '2025-12-11 11:25:56'),
(8, 'Ernest Hemingway', '1914', '1961', 'American', '2025-12-11 11:22:06', '2025-12-11 11:24:47'),
(9, 'William Shakespeare', '1904', '1964', 'British', '2025-12-11 11:22:06', '2025-12-11 11:24:28'),
(10, 'Isaac Asimov', '1920', '1992', 'American', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(11, 'Hamdy Tamer Hamdy', '0000', NULL, 'American', '2025-12-11 22:44:17', '2025-12-11 22:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `isbn` varchar(20) NOT NULL,
  `publication_year` year(4) DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `total_copies` int(11) DEFAULT 1,
  `available_copies` int(11) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `title`, `author_id`, `category_id`, `location_id`, `isbn`, `publication_year`, `publisher`, `total_copies`, `available_copies`, `description`, `created_at`, `updated_at`) VALUES
(2, 'Pride and Prejudice', 5, 7, 4, '9780618260233', '2007', 'Allen', 10, 10, 'A classic novel of manners set in the English countryside.', '2025-12-11 12:23:50', '2025-12-28 21:55:25'),
(3, 'The Two Towers', 2, 10, 6, '90086547822', NULL, 'Secker & Warburg', 1, 0, 'The second part of The Lord of the Rings trilogy.', '2025-12-11 12:27:45', '2026-07-11 22:30:29'),
(4, 'The Fellowship of the Ring', 10, 1, 7, '98977393011', '2010', 'Warburg', 20, 20, 'The first volume in the epic high-fantasy series, The Lord of the Rings.', '2025-12-11 13:49:13', '2025-12-28 21:54:40'),
(5, 'Human & Healthy Lifestyle ', 1, 2, 2, '909743272918233', '2008', 'Allen & Unwin', 30, 26, 'Being in a good health by having a suitable diet and doing some exercises daily ', '2025-12-11 15:18:47', '2026-07-11 23:03:55'),
(6, 'QQQQQ', 9, 6, 7, '9788573839437', '2007', 'Allen & Unwin', 16, 16, 'HU', '2025-12-11 16:45:50', '2026-07-11 22:31:49'),
(7, 'RRRRRR', 5, 8, 9, '987650991254', '1999', NULL, 14, 11, 'rgrogrotr', '2025-12-11 18:08:56', '2026-07-11 22:32:01');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Fantasy & Fiction', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(2, 'Science Fiction', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(3, 'Mystery & Thriller', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(4, 'Romance', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(5, 'Science & Technology', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(6, 'History & Biography', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(7, 'Arts & Literature', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(8, 'Self-Help & Psychology', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(9, 'Business & Economics', '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(10, 'Travel & Adventure', '2025-12-11 11:22:06', '2025-12-11 11:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_code` varchar(20) NOT NULL,
  `position` enum('librarian','assistant_librarian','library_assistant','technician','manager','director','admin') NOT NULL,
  `hire_date` date NOT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contract') DEFAULT 'full_time',
  `is_currently_employed` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `user_id`, `employee_code`, `position`, `hire_date`, `salary`, `employment_type`, `is_currently_employed`, `created_at`, `updated_at`) VALUES
(1, 7, 'EMP000001', 'admin', '2025-12-01', 5000.00, 'part_time', 1, '2025-12-11 10:49:47', '2025-12-11 16:22:03'),
(14, 15, 'EMP000005', 'admin', '2025-12-28', 5000.00, 'full_time', 1, '2025-12-28 21:50:31', '2025-12-28 21:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--

CREATE TABLE `fines` (
  `fine_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fine_date` date NOT NULL,
  `reason` enum('overdue','damage','lost') DEFAULT 'overdue',
  `status` enum('pending','paid','waived') DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `days_overdue` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fines`
--

INSERT INTO `fines` (`fine_id`, `member_id`, `transaction_id`, `amount`, `fine_date`, `reason`, `status`, `paid_date`, `days_overdue`) VALUES
(5, 1, 11, 9.50, '2026-07-12', 'overdue', 'pending', NULL, 19);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `location_code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `floor` int(11) DEFAULT NULL CHECK (`floor` between 1 and 4),
  `shelf` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `location_code`, `name`, `floor`, `shelf`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'FL1-A1', 'Fantasy & Fiction Section', 1, 'A1-A10', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(2, 'FL1-A2', 'Science Fiction Section', 1, 'B1-B10', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(3, 'FL2-B1', 'Science & Technology', 2, 'A1-A12', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(4, 'FL2-B2', 'History & Biography', 2, 'B1-B15', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(5, 'FL3-C1', 'Arts & Literature', 4, 'A1-A20', 1, '2025-12-11 11:22:06', '2025-12-11 13:18:40'),
(6, 'FL3-C2', 'Classics Section', 3, 'B1-B12', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(7, 'FL4-D1', 'Children Section', 4, 'A1-A15', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(8, 'REF-001', 'Reference Section', 1, 'REF1-REF10', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06'),
(9, 'NEW-001', 'New Arrivals Display', 1, 'NEW1-NEW5', 1, '2025-12-11 11:22:06', '2025-12-11 11:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `member_code` varchar(20) NOT NULL,
  `total_books_borrowed` int(11) DEFAULT 0,
  `current_fines` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `user_id`, `member_code`, `total_books_borrowed`, `current_fines`, `created_at`, `updated_at`) VALUES
(1, 3, 'MEM000003', 12, 9.50, '2025-12-11 09:52:20', '2026-07-11 23:03:55'),
(3, 4, 'MEM000004', 0, 0.00, '2025-12-11 10:21:25', '2025-12-11 10:21:25'),
(11, 15, 'MEM000015', 0, 0.00, '2025-12-28 21:49:22', '2025-12-28 21:49:22'),
(12, 19, 'MEM000019', 0, 0.00, '2026-06-09 11:04:14', '2026-06-09 11:04:14'),
(13, 20, 'MEM000020', 1, 0.00, '2026-07-11 23:02:30', '2026-07-11 23:02:57');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `reservation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','fulfilled','cancelled','expired') DEFAULT 'active',
  `expiry_date` date DEFAULT NULL,
  `notification_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `transaction_type` enum('borrow','return','renew') NOT NULL,
  `borrow_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `renew_count` int(11) DEFAULT 0,
  `status` enum('active','completed','overdue') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `member_id`, `book_id`, `transaction_type`, `borrow_date`, `due_date`, `return_date`, `renew_count`, `status`, `created_at`) VALUES
(2, 1, 3, 'borrow', '2025-12-11', '2025-12-25', '2025-12-11', 0, 'completed', '2025-12-11 20:59:46'),
(3, 1, 3, 'borrow', '2025-12-11', '2025-12-25', '2025-12-11', 0, 'completed', '2025-12-11 21:18:10'),
(4, 1, 7, 'borrow', '2025-12-11', '2025-12-25', '2025-12-28', 0, 'completed', '2025-12-11 21:18:54'),
(5, 1, 6, 'borrow', '2025-12-11', '2025-12-25', '2025-12-28', 0, 'completed', '2025-12-11 21:19:08'),
(6, 1, 4, 'borrow', '2025-12-11', '2025-12-25', '2025-12-28', 0, 'completed', '2025-12-11 21:19:20'),
(7, 1, 2, 'borrow', '2025-12-11', '2025-12-25', '2025-12-28', 0, 'completed', '2025-12-11 21:19:43'),
(8, 1, 3, 'borrow', '2025-12-11', '2025-12-25', '2025-12-11', 0, 'completed', '2025-12-11 21:46:56'),
(11, 1, 5, 'borrow', '2026-06-09', '2026-06-23', '2026-07-12', 0, 'completed', '2026-06-09 10:59:47'),
(12, 1, 3, 'borrow', '2026-07-12', '2026-07-26', NULL, 0, 'active', '2026-07-11 22:30:29'),
(13, 1, 6, 'borrow', '2026-07-12', '2026-07-26', '2026-07-12', 0, 'completed', '2026-07-11 22:31:13'),
(14, 1, 7, 'borrow', '2026-07-12', '2026-07-26', NULL, 0, 'active', '2026-07-11 22:32:01'),
(15, 13, 5, 'borrow', '2026-07-12', '2026-07-26', NULL, 0, 'active', '2026-07-11 23:02:57'),
(16, 1, 5, 'borrow', '2026-07-12', '2026-07-26', NULL, 0, 'active', '2026-07-11 23:03:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'images/user_image.jpg',
  `role` enum('user','employee') DEFAULT 'user',
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `gender`, `phone_number`, `profile_image`, `role`, `registration_date`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Hamdy Tamer Hamdy', 'H.Tamer2280@nu.edu.eg', '$2y$10$d0w3.Kn0xrJokhycCwJdLezghy2Vd98JzCHX4mUdWxY8gsoXPV15i', 'Male', '01006772908', 'uploads/profile_images/profile_693a93dd2b6765.71793493.jpg', 'user', '2025-12-11 09:49:20', 1, '2025-12-11 09:49:20', '2026-07-11 22:30:48'),
(4, 'HamdyTamerXXX', 'hamdytamer253@gmail.com', '$2y$10$eeaUXn1zs26zC1DNvGJlPes8iaMSAAqPZcHeJy1fLtZ0adBXnSJ5C', 'Male', '01024091545', 'uploads/profile_images/user_4_1765485656.jpg', 'user', '2025-12-11 10:21:16', 1, '2025-12-11 10:21:16', '2025-12-11 20:41:13'),
(7, 'Mark Sam', 'employee777@gmail.com', '$2y$10$dX8Rcai3MXNZBIn8eSO.yOe61SZySs7rHkDK8lyIwvsMiNywLd7LW', 'Male', '0100909777', 'uploads/profile_images/employee_7_1765832920.jpg', 'employee', '2025-12-11 10:48:11', 1, '2025-12-11 10:48:11', '2025-12-15 21:08:40'),
(15, 'Ali TamerWWW', 'employee999@gmail.com', '$2y$10$jSz8DzCqfsd8Bdx39xwxzuYMgJbn/XMoE9m1OJ23r7GWYSy44bd7y', 'Male', '01026578998', 'images/user_image.jpg', 'employee', '2025-12-28 21:49:12', 1, '2025-12-28 21:49:12', '2025-12-28 21:51:38'),
(16, 'Mai Mabrouk', 'MsiMabrouk@gmail.com', '$2y$10$H3R.8c.powpvZRQW2wh4mO67sSRov9tBsQ.a7i5mJaEumav2VumGm', 'Female', '01005894720', 'images/user_image.jpg', 'user', '2026-06-09 11:01:35', 1, '2026-06-09 11:01:35', '2026-06-09 11:01:35'),
(19, 'Mai', 'MaiMabrouk@gmail.com', '$2y$10$Qgw2uTF/42uld8/40Z6/O.F3GB5LCJFav5XrLCLH4cABSx3cU11vG', 'Female', '01006589034', 'images/user_image.jpg', 'user', '2026-06-09 11:04:02', 1, '2026-06-09 11:04:02', '2026-06-09 11:04:02'),
(20, 'Ali Tamer', 'alitamer616@gmail.com', '$2y$10$sBT6zAx2SGsllG/vCzLmHu7mXyG5bAfW/cYXcXLP5Nobtpv16p3O2', 'Male', '01005672099', 'assets/uploads/profile_6a52cb65525b74.90990780.jpg', 'user', '2026-07-11 23:01:57', 1, '2026-07-11 23:01:57', '2026-07-11 23:01:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`author_id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`),
  ADD UNIQUE KEY `isbn` (`isbn`),
  ADD KEY `idx_title` (`title`),
  ADD KEY `idx_author_id` (`author_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_location_id` (`location_id`),
  ADD KEY `idx_publication_year` (`publication_year`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `idx_employee_code` (`employee_code`),
  ADD KEY `idx_position` (`position`);

--
-- Indexes for table `fines`
--
ALTER TABLE `fines`
  ADD PRIMARY KEY (`fine_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_fine_date` (`fine_date`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`),
  ADD UNIQUE KEY `location_code` (`location_code`),
  ADD KEY `idx_location_code` (`location_code`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_floor` (`floor`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD KEY `idx_member_code` (`member_code`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_book_id` (`book_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_book_id` (`book_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_number` (`phone_number`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone_number` (`phone_number`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `authors`
--
ALTER TABLE `authors`
  MODIFY `author_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `fines`
--
ALTER TABLE `fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `authors` (`author_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `books_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `books_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `fines`
--
ALTER TABLE `fines`
  ADD CONSTRAINT `fines_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fines_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
