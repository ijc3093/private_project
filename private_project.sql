-- Cleaned + normalized schema for `private_project`
-- Generated: 2026-01-17
-- Notes:
-- 1) Uses InnoDB everywhere (required for FKs).
-- 2) Removes duplicate redundant UNIQUE keys from the dump (keeps one per logical constraint).
-- 3) Adds foreign keys AFTER cleanup checks to avoid error #1452.
-- 4) Includes optional "contact alias" support using existing `user_contacts` table.

SET SQL_MODE = 'STRICT_ALL_TABLES';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `private_project`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `private_project`;

-- ----------------------------
-- ROLE (single source of truth)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `role` (
  `idrole` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `inherits_from` int DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`idrole`),
  UNIQUE KEY `uq_role_name` (`name`),
  KEY `idx_role_inherits` (`inherits_from`),
  CONSTRAINT `fk_role_inherits`
    FOREIGN KEY (`inherits_from`) REFERENCES `role` (`idrole`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- USERS
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `friend_code` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` varchar(50) NOT NULL,
  `mobile` varchar(50) NOT NULL,
  `designation` varchar(255) NOT NULL DEFAULT '',
  `role` int NOT NULL DEFAULT 4,
  `image` varchar(100) NOT NULL DEFAULT 'default.jpg',
  `image_blob` longblob,
  `image_type` varchar(100) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_friend_code` (`friend_code`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_last_seen` (`last_seen`),
  CONSTRAINT `fk_users_role`
    FOREIGN KEY (`role`) REFERENCES `role` (`idrole`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- ADMIN
-- ----------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `idadmin` int NOT NULL AUTO_INCREMENT,
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
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `force_password_change` tinyint(1) DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `failed_login_attempts` int NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `reset_request_count` int NOT NULL DEFAULT 0,
  `reset_request_window_start` datetime DEFAULT NULL,
  `reset_last_requested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`idadmin`),
  UNIQUE KEY `uq_admin_email` (`email`),
  UNIQUE KEY `uq_admin_username` (`username`),
  UNIQUE KEY `uq_admin_friend_code` (`friend_code`),
  KEY `idx_admin_role` (`role`),
  CONSTRAINT `fk_admin_role`
    FOREIGN KEY (`role`) REFERENCES `role` (`idrole`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- FEEDBACK (legacy chat table)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender` varchar(100) NOT NULL,
  `receiver` varchar(100) NOT NULL,
  `channel` varchar(30) NOT NULL DEFAULT 'user_admin',
  `title` varchar(150) NOT NULL,
  `feedbackdata` text NOT NULL,
  `attachment` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_receiver_created` (`receiver`,`created_at`),
  KEY `idx_feedback_receiver_read` (`receiver`,`is_read`,`created_at`),
  KEY `idx_feedback_sender_receiver` (`sender`,`receiver`,`created_at`),
  KEY `idx_feedback_channel_receiver` (`channel`,`receiver`,`created_at`),
  KEY `idx_feedback_channel_read` (`channel`,`receiver`,`is_read`,`created_at`),
  KEY `idx_delivered_at` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- CHAT (id-based chat tables)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `feedbackdata` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pair_time` (`sender_id`,`receiver_id`,`created_at`),
  KEY `idx_receiver_read` (`receiver_id`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_typing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_code` varchar(20) DEFAULT NULL,
  `receiver_code` varchar(20) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_typing` (`sender_code`,`receiver_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- CONTACTS + REQUESTS
-- ----------------------------
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `contact_user_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contacts_pair` (`user_id`,`contact_user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_contact` (`contact_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_user_id` int NOT NULL,
  `to_user_id` int NOT NULL,
  `status` enum('pending','accepted','declined','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_request_pair` (`from_user_id`,`to_user_id`),
  KEY `idx_to_status` (`to_user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing alias/contact list table (your dump)
CREATE TABLE IF NOT EXISTS `user_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_user_id` int NOT NULL,
  `friend_user_id` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `contact_user_id` int DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_owner_contact_user` (`owner_user_id`,`contact_user_id`),
  UNIQUE KEY `uq_owner_contact_email` (`owner_user_id`,`contact_email`),
  KEY `idx_owner` (`owner_user_id`),
  KEY `idx_contact_user` (`contact_user_id`),
  KEY `idx_contact_email` (`contact_email`),
  KEY `idx_friend_user_id` (`friend_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- CONVERSATIONS (alternative chat model)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `type` enum('user','support') NOT NULL DEFAULT 'user',
  `created_by_user_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_uuid` (`uuid`),
  KEY `idx_type` (`type`),
  KEY `idx_creator` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_participants` (
  `conversation_id` int NOT NULL,
  `user_id` int NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`,`user_id`),
  KEY `idx_cp_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `body` text,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv_time` (`conversation_id`,`created_at`),
  KEY `idx_sender` (`sender_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `message_reads` (
  `message_id` int NOT NULL,
  `user_id` int NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`,`user_id`),
  KEY `idx_mr_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- NOTIFICATIONS
-- ----------------------------
CREATE TABLE IF NOT EXISTS `notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notiuser` varchar(100) NOT NULL,
  `notireceiver` varchar(100) NOT NULL,
  `notitype` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_receiver_read` (`notireceiver`,`is_read`),
  KEY `idx_notification_receiver_created` (`notireceiver`,`created_at`),
  KEY `idx_notification_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- PASSWORD RESET TOKENS
-- ----------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_type` enum('user','admin') NOT NULL,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_username` (`username`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_account` (`account_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- SECURITY LOGS (kept)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `security_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `meta` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(190) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `meta` text,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`),
  KEY `idx_success` (`success`),
  KEY `idx_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_security_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(190) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `meta` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asl_email` (`email`),
  KEY `idx_asl_admin_id` (`admin_id`),
  KEY `idx_asl_action` (`action`),
  KEY `idx_asl_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- OTHER TABLES FROM YOUR DUMP
-- ----------------------------
CREATE TABLE IF NOT EXISTS `admin_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_admin_id` int NOT NULL,
  `friend_admin_id` int NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_owner_friend` (`owner_admin_id`,`friend_admin_id`),
  KEY `idx_owner_admin` (`owner_admin_id`),
  KEY `idx_friend_admin` (`friend_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `deleteduser` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deleteduser_email` (`email`),
  KEY `idx_deleteduser_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `inherits_from` int DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`),
  KEY `idx_roles_inherits` (`inherits_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_chat_matrix` (
  `from_role` int NOT NULL,
  `to_role` int NOT NULL,
  `allowed` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`from_role`,`to_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int NOT NULL,
  `perm` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`,`perm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- DATA SEEDS (your known roles)
-- ----------------------------
INSERT INTO `role` (`idrole`,`name`,`inherits_from`,`status`) VALUES
(1,'Admin',NULL,1),
(2,'Manager',NULL,1),
(3,'Gospel',NULL,1),
(4,'Staff',NULL,1),
(5,'Coach',2,1),
(6,'Teacher',NULL,1),
(7,'Technician',2,1)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `inherits_from`=VALUES(`inherits_from`),
  `status`=VALUES(`status`);

-- ----------------------------
-- FIX for FK error #1452 (IMPORTANT)
-- If chat_messages already has rows with sender_id/receiver_id NOT IN users.id,
-- foreign keys will fail. You must fix (delete/repair) orphan rows first.
-- ----------------------------

-- Option A (recommended): delete orphan rows
DELETE cm
FROM chat_messages cm
LEFT JOIN users u ON u.id = cm.sender_id
WHERE u.id IS NULL;

DELETE cm
FROM chat_messages cm
LEFT JOIN users u ON u.id = cm.receiver_id
WHERE u.id IS NULL;

-- ----------------------------
-- FOREIGN KEYS (add after cleanup)
-- ----------------------------
ALTER TABLE `contacts`
  ADD CONSTRAINT `fk_c_user`    FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_c_contact` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `contact_requests`
  ADD CONSTRAINT `fk_cr_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cr_to`   FOREIGN KEY (`to_user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `conversation_participants`
  ADD CONSTRAINT `fk_cp_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`)         REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conv`   FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `message_reads`
  ADD CONSTRAINT `fk_mr_msg`  FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mr_user` FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_sender`   FOREIGN KEY (`sender_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- OPTIONAL: one alias per friend_code per owner (store friend_code in friend_user_id)
-- ALTER TABLE user_contacts
--   ADD UNIQUE KEY uq_owner_friend_code (owner_user_id, friend_user_id);
