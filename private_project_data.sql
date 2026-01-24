-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Jan 24, 2026 at 10:50 AM
-- Server version: 8.0.44
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `private_project`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `idadmin` int NOT NULL,
  `fullname` varchar(20) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `friend_code` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `role` int NOT NULL,
  `image` varchar(100) NOT NULL DEFAULT 'default.jpg',
  `image_blob` longblob,
  `image_type` varchar(100) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `force_password_change` tinyint(1) DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `failed_login_attempts` int NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `reset_request_count` int NOT NULL DEFAULT '0',
  `reset_request_window_start` datetime DEFAULT NULL,
  `reset_last_requested_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_contacts`
--

CREATE TABLE `admin_contacts` (
  `id` int NOT NULL,
  `owner_admin_id` int NOT NULL,
  `friend_admin_id` int NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_security_log`
--

CREATE TABLE `admin_security_log` (
  `id` int NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `meta` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `feedbackdata` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_typing`
--

CREATE TABLE `chat_typing` (
  `id` int NOT NULL,
  `sender_code` varchar(20) DEFAULT NULL,
  `receiver_code` varchar(20) DEFAULT NULL,
  `is_typing` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `chat_typing`
--

INSERT INTO `chat_typing` (`id`, `sender_code`, `receiver_code`, `is_typing`, `updated_at`) VALUES
(1, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 0, '2026-01-22 17:01:28'),
(24, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 0, '2026-01-23 03:39:33'),
(232, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 0, '2026-01-22 21:50:23'),
(242, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 0, '2026-01-22 23:45:20');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `contact_user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_requests`
--

CREATE TABLE `contact_requests` (
  `id` int NOT NULL,
  `from_user_id` int NOT NULL,
  `to_user_id` int NOT NULL,
  `status` enum('pending','accepted','declined','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int NOT NULL,
  `uuid` char(36) NOT NULL,
  `type` enum('user','support') NOT NULL DEFAULT 'user',
  `created_by_user_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_participants`
--

CREATE TABLE `conversation_participants` (
  `conversation_id` int NOT NULL,
  `user_id` int NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deleteduser`
--

CREATE TABLE `deleteduser` (
  `id` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int NOT NULL,
  `sender` varchar(100) NOT NULL,
  `receiver` varchar(100) NOT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'user_admin',
  `title` varchar(150) NOT NULL,
  `feedbackdata` text NOT NULL,
  `attachment` varchar(150) DEFAULT NULL,
  `attachment_type` varchar(20) DEFAULT NULL,
  `attachment_original` varchar(255) DEFAULT NULL,
  `attachment_url` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `sender`, `receiver`, `channel`, `title`, `feedbackdata`, `attachment`, `attachment_type`, `attachment_original`, `attachment_url`, `created_at`, `is_read`, `read_at`, `delivered_at`) VALUES
(1, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'Hello how are you doing?', NULL, NULL, NULL, NULL, '2026-01-21 21:43:16', 1, '2026-01-21 21:43:49', NULL),
(2, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'who is this?', NULL, NULL, NULL, NULL, '2026-01-21 21:50:49', 1, '2026-01-21 23:52:21', NULL),
(3, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hello', NULL, NULL, NULL, NULL, '2026-01-21 23:52:27', 1, '2026-01-21 23:52:27', NULL),
(4, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'how are you', NULL, NULL, NULL, NULL, '2026-01-21 23:53:27', 1, '2026-01-21 23:53:27', NULL),
(5, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 00:02:38', 1, '2026-01-22 00:02:38', NULL),
(6, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 00:07:11', 1, '2026-01-22 02:57:21', NULL),
(7, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 02:46:10', 1, '2026-01-22 02:57:21', NULL),
(8, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'Yeah how are you doing?', NULL, NULL, NULL, NULL, '2026-01-22 02:57:46', 1, '2026-01-22 02:57:57', NULL),
(9, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'I am good', NULL, NULL, NULL, NULL, '2026-01-22 02:58:06', 1, '2026-01-22 02:58:06', NULL),
(10, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 03:16:44', 1, '2026-01-22 03:16:53', NULL),
(11, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 03:17:04', 1, '2026-01-22 03:17:04', NULL),
(12, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'Hello John', NULL, NULL, NULL, NULL, '2026-01-22 04:52:13', 1, '2026-01-22 04:52:28', NULL),
(13, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 07:04:44', 1, '2026-01-22 07:04:44', NULL),
(14, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 07:05:16', 1, '2026-01-22 07:05:24', NULL),
(15, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hiiiiiiiiiiiiiiiiiii', NULL, NULL, NULL, NULL, '2026-01-22 19:47:25', 1, '2026-01-22 19:47:42', NULL),
(16, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 19:47:47', 1, '2026-01-22 19:47:56', NULL),
(17, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 19:47:56', 1, '2026-01-22 19:48:10', NULL),
(18, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'just chilling', NULL, NULL, NULL, NULL, '2026-01-22 19:48:10', 1, '2026-01-22 19:48:24', NULL),
(19, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'ok good', NULL, NULL, NULL, NULL, '2026-01-22 19:48:24', 1, '2026-01-22 19:50:04', NULL),
(20, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hiiiiiiiiiiiiiiiiiiiiii', NULL, NULL, NULL, NULL, '2026-01-22 19:55:54', 1, '2026-01-22 19:56:06', NULL),
(21, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'hiiiii', NULL, NULL, NULL, NULL, '2026-01-22 19:56:06', 1, '2026-01-22 20:02:26', NULL),
(22, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'hiiiiii', NULL, NULL, NULL, NULL, '2026-01-22 19:56:56', 1, '2026-01-22 20:02:26', NULL),
(23, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hiiiiiiiiiiiiiiiii', NULL, NULL, NULL, NULL, '2026-01-22 20:33:18', 1, '2026-01-22 20:35:02', NULL),
(24, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 21:49:58', 1, '2026-01-22 21:52:03', NULL),
(25, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 21:50:23', 1, '2026-01-22 21:52:03', NULL),
(26, 'USR-944C-SJC2', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-22 23:15:25', 1, '2026-01-22 23:17:42', NULL),
(27, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 00:15:45', 1, '2026-01-23 00:15:45', NULL),
(28, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 00:16:26', 1, '2026-01-23 00:16:34', NULL),
(29, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 01:42:29', 1, '2026-01-23 01:42:55', NULL),
(30, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 01:43:05', 1, '2026-01-23 01:43:05', NULL),
(31, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '😊😊😊😊😊😊😊😊😊', NULL, NULL, NULL, NULL, '2026-01-23 02:56:15', 1, '2026-01-23 02:56:15', NULL),
(32, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 07:12:10', 1, '2026-01-23 07:12:10', NULL),
(33, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', 'baseball-1769154151-a3608ad1.png', NULL, NULL, NULL, '2026-01-23 07:42:31', 1, '2026-01-23 07:42:31', NULL),
(34, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 08:05:42', 0, NULL, NULL),
(35, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', 'baseball-1769156929-ea186e29.png', NULL, NULL, NULL, '2026-01-23 08:28:49', 1, '2026-01-23 08:28:49', NULL),
(36, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-23 10:09:40', 1, '2026-01-23 10:09:41', NULL),
(37, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '👍', NULL, NULL, NULL, NULL, '2026-01-23 10:10:33', 1, '2026-01-23 10:10:33', NULL),
(38, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769185128-ed4a8783.png', NULL, NULL, NULL, '2026-01-23 16:18:48', 1, '2026-01-23 16:18:48', NULL),
(39, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'README-1769185209-c463f14d.txt', NULL, NULL, NULL, '2026-01-23 16:20:09', 1, '2026-01-23 16:20:09', NULL),
(40, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', 'README-1769185289-e7a7ae45.txt', NULL, NULL, NULL, '2026-01-23 16:21:29', 1, '2026-01-23 16:21:29', NULL),
(41, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', 'baseball-1769186408-6848cb58.png', NULL, NULL, NULL, '2026-01-23 16:40:08', 1, '2026-01-23 16:40:08', NULL),
(42, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '😊 hi', NULL, NULL, NULL, NULL, '2026-01-23 16:42:36', 1, '2026-01-23 16:42:36', NULL),
(43, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769187514-3e7f98aa.png', NULL, NULL, NULL, '2026-01-23 16:58:34', 1, '2026-01-23 16:58:34', NULL),
(44, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'README-1769187531-d28738f1.txt', NULL, NULL, NULL, '2026-01-23 16:58:51', 1, '2026-01-23 16:58:51', NULL),
(45, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hello', 'baseball-1769201628-788ee6d1.png', NULL, NULL, NULL, '2026-01-23 20:53:48', 1, '2026-01-23 20:53:48', NULL),
(46, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', '😊', 'baseball-1769201664-e3a2a838.png', NULL, NULL, NULL, '2026-01-23 20:54:24', 0, NULL, NULL),
(47, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', '', 'baseball-1769207200-8dbdf83a.png', NULL, NULL, NULL, '2026-01-23 22:26:40', 0, NULL, NULL),
(48, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', '', 'baseball-1769207388-18393b90.png', NULL, NULL, NULL, '2026-01-23 22:29:48', 0, NULL, NULL),
(49, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', '😊', 'baseball-1769207562-d17e6da6.png', NULL, NULL, NULL, '2026-01-23 22:32:42', 0, NULL, NULL),
(50, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', '😊 hi', NULL, NULL, NULL, NULL, '2026-01-23 22:33:54', 0, NULL, NULL),
(51, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hi', 'baseball-1769213177-7705b237.png', NULL, NULL, NULL, '2026-01-24 00:06:17', 0, NULL, NULL),
(52, 'USR-B1TQ-SRI7', 'USR-944C-SJC2', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-24 00:11:20', 0, NULL, NULL),
(53, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', 'baseball-1769223937-e954dc9f.png', NULL, NULL, NULL, '2026-01-24 03:05:37', 1, '2026-01-24 03:05:37', NULL),
(54, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', '👍', NULL, NULL, NULL, NULL, '2026-01-24 03:07:45', 1, '2026-01-24 03:07:45', NULL),
(55, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', '👍', NULL, NULL, NULL, NULL, '2026-01-24 03:07:58', 1, '2026-01-24 03:08:07', NULL),
(56, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', '', 'baseball-1769224112-68339a6d.png', NULL, NULL, NULL, '2026-01-24 03:08:32', 1, '2026-01-24 03:08:32', NULL),
(57, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', '', 'baseball-1769224195-f9b7992a.png', NULL, NULL, NULL, '2026-01-24 03:09:55', 1, '2026-01-24 03:09:55', NULL),
(58, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769225741-58bd90bc.png', NULL, NULL, NULL, '2026-01-24 03:35:41', 1, '2026-01-24 03:35:41', NULL),
(59, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769226193-bc675d43.png', NULL, NULL, NULL, '2026-01-24 03:43:13', 1, '2026-01-24 03:43:14', NULL),
(60, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769227605-2c453c69.png', NULL, NULL, NULL, '2026-01-24 04:06:45', 1, '2026-01-24 04:06:45', NULL),
(61, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769227892-18968bbb.png', NULL, NULL, NULL, '2026-01-24 04:11:32', 1, '2026-01-24 04:11:32', NULL),
(62, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769246792-3d08d6fc.png', NULL, NULL, NULL, '2026-01-24 09:26:32', 1, '2026-01-24 09:26:32', NULL),
(63, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769247410-7ae711fe.png', NULL, NULL, NULL, '2026-01-24 09:36:50', 1, '2026-01-24 09:36:50', NULL),
(64, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769247758-ac76dce0.png', NULL, NULL, NULL, '2026-01-24 09:42:38', 1, '2026-01-24 09:42:38', NULL),
(65, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769248372-840c6d99.png', NULL, NULL, NULL, '2026-01-24 09:52:52', 1, '2026-01-24 09:52:52', NULL),
(66, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769249461-72071102.png', NULL, NULL, NULL, '2026-01-24 10:11:01', 1, '2026-01-24 10:11:01', NULL),
(67, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769249476-fcd24115.png', NULL, NULL, NULL, '2026-01-24 10:11:16', 1, '2026-01-24 10:11:16', NULL),
(68, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-24 10:33:39', 1, '2026-01-24 10:33:40', NULL),
(69, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '😊', NULL, NULL, NULL, NULL, '2026-01-24 10:33:49', 1, '2026-01-24 10:33:49', NULL),
(70, 'USR-B1TQ-SRI7', 'USR-2JS7-VP8L', 'user_user', '', '', 'baseball-1769250839-0ae345b6.png', NULL, NULL, NULL, '2026-01-24 10:33:59', 1, '2026-01-24 10:33:59', NULL),
(71, 'USR-2JS7-VP8L', 'USR-B1TQ-SRI7', 'user_user', '', 'hi', NULL, NULL, NULL, NULL, '2026-01-24 10:45:07', 1, '2026-01-24 10:45:18', NULL),
(72, 'USR-4OUZ-VZJM', 'USR-B1TQ-SRI7', 'user_user', '', 'Hello John', NULL, NULL, NULL, NULL, '2026-01-24 10:49:16', 1, '2026-01-24 10:49:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `conversation_id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `body` text,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_reads`
--

CREATE TABLE `message_reads` (
  `message_id` int NOT NULL,
  `user_id` int NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `id` int NOT NULL,
  `notiuser` varchar(100) NOT NULL,
  `notireceiver` varchar(100) NOT NULL,
  `notitype` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`id`, `notiuser`, `notireceiver`, `notitype`, `created_at`, `is_read`, `read_at`) VALUES
(1, 'john_k@gmail.com', 'Admin', 'Create Account', '2026-01-21 21:37:18', 0, NULL),
(2, 'rachel_v@gmail.com', 'Admin', 'Create Account', '2026-01-21 21:39:21', 0, NULL),
(3, 'jide@gmail.com', 'Admin', 'Create Account', '2026-01-22 04:35:17', 0, NULL),
(4, 'akin_t@gmail.com', 'Admin', 'Create Account', '2026-01-24 10:47:14', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int NOT NULL,
  `account_type` enum('user','admin') NOT NULL,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `idrole` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `inherits_from` int DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`idrole`, `name`, `inherits_from`, `status`) VALUES
(1, 'Admin', NULL, 1),
(2, 'Manager', NULL, 1),
(3, 'Gospel', NULL, 1),
(4, 'Staff', NULL, 1),
(5, 'Coach', 2, 1),
(6, 'Teacher', NULL, 1),
(7, 'Technician', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `inherits_from` int DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `inherits_from`, `status`, `created_at`) VALUES
(1, 'Coach', 4, 1, '2026-01-10 17:45:33'),
(3, 'Admin', NULL, 1, '2026-01-10 20:41:08'),
(4, 'Manager', NULL, 1, '2026-01-10 20:41:08'),
(5, 'Gospel', NULL, 1, '2026-01-10 20:41:08'),
(6, 'Staff', NULL, 1, '2026-01-10 20:41:08'),
(13, 'Teacher', NULL, 1, '2026-01-11 01:48:02');

-- --------------------------------------------------------

--
-- Table structure for table `role_chat_matrix`
--

CREATE TABLE `role_chat_matrix` (
  `from_role` int NOT NULL,
  `to_role` int NOT NULL,
  `allowed` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `perm` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `friend_code` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` varchar(50) NOT NULL,
  `mobile` varchar(50) NOT NULL,
  `designation` varchar(255) NOT NULL DEFAULT '',
  `role` int NOT NULL DEFAULT '4',
  `image` varchar(100) NOT NULL DEFAULT 'default.jpg',
  `image_blob` longblob,
  `image_type` varchar(100) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `friend_code`, `email`, `password`, `gender`, `mobile`, `designation`, `role`, `image`, `image_blob`, `image_type`, `status`, `created_at`, `last_seen`) VALUES
(1, 'John K', 'John_K', 'USR-B1TQ-SRI7', 'john_k@gmail.com', '$2y$10$SWNX6pLVgii.5BywY49F/OwD3sR8Ste0jSd1pJs8UN69bCbk33SF2', 'Male', '6789032899', '', 4, 'default.jpg', NULL, NULL, 1, '2026-01-21 21:37:18', '2026-01-24 10:50:54'),
(2, 'Rachel V', 'Rachel_V', 'USR-2JS7-VP8L', 'rachel_v@gmail.com', '$2y$10$s5WFXdOQilrWhTFPEcJh5OzU9F1tYu/afWOIGLE83gWRBAoIQZnJ6', 'Female', '8903457890', '', 4, 'default.jpg', NULL, NULL, 1, '2026-01-21 21:39:21', '2026-01-24 10:45:45'),
(3, 'Jide A', 'jide_a', 'USR-944C-SJC2', 'jide@gmail.com', '$2y$10$I8YU4Bid.29WCTuMBmP2RewdOlyVhy5XU217HI3f83YqW12AvPN1G', 'Male', '7896543245', '', 4, 'default.jpg', NULL, NULL, 1, '2026-01-22 04:35:17', '2026-01-23 00:05:00'),
(4, 'Akin T', 'akin_t', 'USR-4OUZ-VZJM', 'akin_t@gmail.com', '$2y$10$d5Ikoo2WSXDB.9TtRs.tpurDdTbndoLLjSW1Nmky9El9JIb9O1M9.', 'Male', '7896543234', '', 4, 'default.jpg', NULL, NULL, 1, '2026-01-24 10:47:14', '2026-01-24 10:50:53');

-- --------------------------------------------------------

--
-- Table structure for table `user_contacts`
--

CREATE TABLE `user_contacts` (
  `id` int NOT NULL,
  `owner_user_id` int NOT NULL,
  `friend_user_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_user_id` int DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_contacts`
--

INSERT INTO `user_contacts` (`id`, `owner_user_id`, `friend_user_id`, `display_name`, `contact_user_id`, `contact_email`, `contact_name`, `created_at`) VALUES
(10, 2, '1', 'John K', NULL, NULL, NULL, '2026-01-21 21:41:42'),
(11, 1, '2', 'Rachel V', NULL, NULL, NULL, '2026-01-22 04:26:06'),
(12, 1, '3', 'Jide', NULL, NULL, NULL, '2026-01-22 04:52:47'),
(13, 4, '1', 'John K', NULL, NULL, NULL, '2026-01-24 10:48:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_contact_name_history`
--

CREATE TABLE `user_contact_name_history` (
  `id` int NOT NULL,
  `owner_user_id` int NOT NULL,
  `friend_user_id` int NOT NULL,
  `old_name` varchar(255) NOT NULL,
  `new_name` varchar(255) NOT NULL,
  `action` varchar(20) NOT NULL DEFAULT 'rename',
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_contact_name_history`
--

INSERT INTO `user_contact_name_history` (`id`, `owner_user_id`, `friend_user_id`, `old_name`, `new_name`, `action`, `changed_at`) VALUES
(1, 2, 1, 'John K', 'John S', 'rename', '2026-01-21 15:42:17'),
(2, 2, 1, 'John S', 'John K', 'rename', '2026-01-21 15:42:42'),
(3, 2, 1, 'John K', 'John', 'rename', '2026-01-21 22:02:53'),
(4, 2, 1, 'John', 'John K', 'rename', '2026-01-21 22:03:55'),
(5, 2, 1, 'John K', 'John', 'undo', '2026-01-21 22:04:05'),
(6, 1, 3, 'Jide A', 'Jide A', 'rename', '2026-01-22 15:29:10'),
(7, 1, 3, 'Jide A', 'Jide', 'rename', '2026-01-23 19:52:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`idadmin`),
  ADD UNIQUE KEY `uq_admin_email` (`email`),
  ADD UNIQUE KEY `uq_admin_username` (`username`),
  ADD UNIQUE KEY `uq_admin_friend_code` (`friend_code`),
  ADD KEY `idx_admin_role` (`role`);

--
-- Indexes for table `admin_contacts`
--
ALTER TABLE `admin_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_owner_friend` (`owner_admin_id`,`friend_admin_id`),
  ADD KEY `idx_owner` (`owner_admin_id`),
  ADD KEY `idx_friend` (`friend_admin_id`);

--
-- Indexes for table `admin_security_log`
--
ALTER TABLE `admin_security_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asl_email` (`email`),
  ADD KEY `idx_asl_admin` (`admin_id`),
  ADD KEY `idx_asl_action` (`action`),
  ADD KEY `idx_asl_created` (`created_at`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pair_time` (`sender_id`,`receiver_id`,`created_at`),
  ADD KEY `idx_receiver_read` (`receiver_id`,`is_read`,`created_at`),
  ADD KEY `idx_delivered_at` (`delivered_at`);

--
-- Indexes for table `chat_typing`
--
ALTER TABLE `chat_typing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_typing` (`sender_code`,`receiver_code`),
  ADD UNIQUE KEY `uniq_sender_receiver` (`sender_code`,`receiver_code`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_contacts_pair` (`user_id`,`contact_user_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_contact` (`contact_user_id`);

--
-- Indexes for table `contact_requests`
--
ALTER TABLE `contact_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_request_pair` (`from_user_id`,`to_user_id`),
  ADD KEY `idx_to_status` (`to_user_id`,`status`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conv_uuid` (`uuid`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_creator` (`created_by_user_id`);

--
-- Indexes for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD PRIMARY KEY (`conversation_id`,`user_id`),
  ADD KEY `idx_cp_user` (`user_id`);

--
-- Indexes for table `deleteduser`
--
ALTER TABLE `deleteduser`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleteduser_email` (`email`),
  ADD KEY `idx_deleteduser_deleted_at` (`deleted_at`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_receiver_created` (`receiver`,`created_at`),
  ADD KEY `idx_feedback_receiver_read` (`receiver`,`is_read`,`created_at`),
  ADD KEY `idx_feedback_sender_receiver` (`sender`,`receiver`,`created_at`),
  ADD KEY `idx_feedback_channel_receiver` (`channel`,`receiver`,`created_at`),
  ADD KEY `idx_feedback_channel_read` (`channel`,`receiver`,`is_read`,`created_at`),
  ADD KEY `idx_feedback_channel_sender_receiver` (`channel`,`sender`,`receiver`,`created_at`),
  ADD KEY `idx_delivered_at` (`delivered_at`),
  ADD KEY `idx_feedback_chat` (`sender`,`receiver`,`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv_time` (`conversation_id`,`created_at`),
  ADD KEY `idx_sender` (`sender_user_id`);

--
-- Indexes for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD PRIMARY KEY (`message_id`,`user_id`),
  ADD KEY `idx_mr_user` (`user_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_receiver_read` (`notireceiver`,`is_read`),
  ADD KEY `idx_notification_receiver_created` (`notireceiver`,`created_at`),
  ADD KEY `idx_notification_created` (`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_account` (`account_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`idrole`),
  ADD UNIQUE KEY `uq_role_name` (`name`),
  ADD KEY `idx_role_inherits` (`inherits_from`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`),
  ADD KEY `idx_roles_inherits` (`inherits_from`);

--
-- Indexes for table `role_chat_matrix`
--
ALTER TABLE `role_chat_matrix`
  ADD PRIMARY KEY (`from_role`,`to_role`),
  ADD KEY `fk_rcm_to` (`to_role`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`perm`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_friend_code` (`friend_code`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_last_seen` (`last_seen`);

--
-- Indexes for table `user_contacts`
--
ALTER TABLE `user_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_owner_contact_user` (`owner_user_id`,`contact_user_id`),
  ADD UNIQUE KEY `uq_owner_contact_email` (`owner_user_id`,`contact_email`),
  ADD KEY `idx_owner` (`owner_user_id`),
  ADD KEY `idx_contact_user` (`contact_user_id`),
  ADD KEY `idx_contact_email` (`contact_email`);

--
-- Indexes for table `user_contact_name_history`
--
ALTER TABLE `user_contact_name_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_friend` (`owner_user_id`,`friend_user_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `idadmin` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_contacts`
--
ALTER TABLE `admin_contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_security_log`
--
ALTER TABLE `admin_security_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_typing`
--
ALTER TABLE `chat_typing`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1123;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_requests`
--
ALTER TABLE `contact_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deleteduser`
--
ALTER TABLE `deleteduser`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `idrole` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_contacts`
--
ALTER TABLE `user_contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_contact_name_history`
--
ALTER TABLE `user_contact_name_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_role` FOREIGN KEY (`role`) REFERENCES `role` (`idrole`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `admin_contacts`
--
ALTER TABLE `admin_contacts`
  ADD CONSTRAINT `fk_ac_friend` FOREIGN KEY (`friend_admin_id`) REFERENCES `admin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ac_owner` FOREIGN KEY (`owner_admin_id`) REFERENCES `admin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `fk_c_contact` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_c_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `contact_requests`
--
ALTER TABLE `contact_requests`
  ADD CONSTRAINT `fk_cr_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cr_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD CONSTRAINT `fk_cp_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD CONSTRAINT `fk_mr_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `role`
--
ALTER TABLE `role`
  ADD CONSTRAINT `fk_role_inherits` FOREIGN KEY (`inherits_from`) REFERENCES `role` (`idrole`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `role_chat_matrix`
--
ALTER TABLE `role_chat_matrix`
  ADD CONSTRAINT `fk_rcm_from` FOREIGN KEY (`from_role`) REFERENCES `role` (`idrole`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rcm_to` FOREIGN KEY (`to_role`) REFERENCES `role` (`idrole`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`idrole`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role`) REFERENCES `role` (`idrole`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
