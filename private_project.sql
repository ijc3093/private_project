-- =========================================================
-- Clean Schema for `social_media`
-- MySQL 8.x / phpMyAdmin
-- =========================================================

-- SET NAMES utf8mb4;
-- SET time_zone = "+00:00";
-- SET foreign_key_checks = 0;

-- (Optional) Create DB
-- CREATE DATABASE IF NOT EXISTS social_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE social_media;

-- =========================================================
-- DROP TABLES (optional if rebuilding)
-- =========================================================
DROP TABLE IF EXISTS message_reads;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversation_participants;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS contact_requests;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS chat_typing;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS user_contacts;
DROP TABLE IF EXISTS notification;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS admin_security_log;
DROP TABLE IF EXISTS admin_contacts;
DROP TABLE IF EXISTS deleteduser;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS role_chat_matrix;
DROP TABLE IF EXISTS role;

-- =========================================================
-- ROLE TABLES
-- =========================================================
CREATE TABLE role (
  idrole INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  inherits_from INT DEFAULT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (idrole),
  UNIQUE KEY uq_role_name (name),
  KEY idx_role_inherits (inherits_from),
  CONSTRAINT fk_role_inherits
    FOREIGN KEY (inherits_from) REFERENCES role(idrole)
    ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE roles (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  inherits_from INT DEFAULT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_name (name),
  KEY idx_roles_inherits (inherits_from)
);

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  perm VARCHAR(50) NOT NULL,
  PRIMARY KEY (role_id, perm),
  CONSTRAINT fk_rp_role
    FOREIGN KEY (role_id) REFERENCES role(idrole)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE role_chat_matrix (
  from_role INT NOT NULL,
  to_role INT NOT NULL,
  allowed TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (from_role, to_role),
  CONSTRAINT fk_rcm_from
    FOREIGN KEY (from_role) REFERENCES role(idrole)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rcm_to
    FOREIGN KEY (to_role) REFERENCES role(idrole)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- Seed roles
INSERT INTO role (idrole, name, inherits_from, status) VALUES
(1, 'Admin', NULL, 1),
(2, 'Manager', NULL, 1),
(3, 'Gospel', NULL, 1),
(4, 'Staff', NULL, 1),
(5, 'Coach', 2, 1),
(6, 'Teacher', NULL, 1),
(7, 'Technician', 2, 1);

INSERT INTO roles (id, name, inherits_from, status, created_at) VALUES
(1, 'Coach', 4, 1, '2026-01-10 11:45:33'),
(3, 'Admin', NULL, 1, '2026-01-10 14:41:08'),
(4, 'Manager', NULL, 1, '2026-01-10 14:41:08'),
(5, 'Gospel', NULL, 1, '2026-01-10 14:41:08'),
(6, 'Staff', NULL, 1, '2026-01-10 14:41:08'),
(13, 'Teacher', NULL, 1, '2026-01-10 19:48:02');

-- =========================================================
-- USERS
-- =========================================================
CREATE TABLE users (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) DEFAULT NULL,
  friend_code VARCHAR(20) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  gender VARCHAR(50) NOT NULL,
  mobile VARCHAR(50) NOT NULL,
  designation VARCHAR(255) NOT NULL DEFAULT '',
  role INT NOT NULL DEFAULT 4,
  image VARCHAR(100) NOT NULL DEFAULT 'default.jpg',
  image_blob LONGBLOB,
  image_type VARCHAR(100) DEFAULT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen TIMESTAMP NULL DEFAULT NULL, -- online/offline support
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_friend_code (friend_code),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_role (role),
  KEY idx_last_seen (last_seen),
  CONSTRAINT fk_users_role
    FOREIGN KEY (role) REFERENCES role(idrole)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

-- =========================================================
-- ADMIN
-- =========================================================
CREATE TABLE admin (
  idadmin INT NOT NULL AUTO_INCREMENT,
  fullname VARCHAR(20) DEFAULT NULL,
  username VARCHAR(100) NOT NULL,
  friend_code VARCHAR(20) DEFAULT NULL,
  email VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  gender VARCHAR(50) DEFAULT NULL,
  mobile VARCHAR(50) DEFAULT NULL,
  designation VARCHAR(50) DEFAULT NULL,
  role INT NOT NULL,
  image VARCHAR(100) NOT NULL DEFAULT 'default.jpg',
  image_blob LONGBLOB,
  image_type VARCHAR(100) DEFAULT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  force_password_change TINYINT(1) DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  failed_login_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  reset_token_hash VARCHAR(64) DEFAULT NULL,
  reset_token_expires DATETIME DEFAULT NULL,
  reset_request_count INT NOT NULL DEFAULT 0,
  reset_request_window_start DATETIME DEFAULT NULL,
  reset_last_requested_at DATETIME DEFAULT NULL,
  PRIMARY KEY (idadmin),
  UNIQUE KEY uq_admin_email (email),
  UNIQUE KEY uq_admin_username (username),
  UNIQUE KEY uq_admin_friend_code (friend_code),
  KEY idx_admin_role (role),
  CONSTRAINT fk_admin_role
    FOREIGN KEY (role) REFERENCES role(idrole)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE admin_contacts (
  id INT NOT NULL AUTO_INCREMENT,
  owner_admin_id INT NOT NULL,
  friend_admin_id INT NOT NULL,
  display_name VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_owner_friend (owner_admin_id, friend_admin_id),
  KEY idx_owner (owner_admin_id),
  KEY idx_friend (friend_admin_id),
  CONSTRAINT fk_ac_owner
    FOREIGN KEY (owner_admin_id) REFERENCES admin(idadmin)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ac_friend
    FOREIGN KEY (friend_admin_id) REFERENCES admin(idadmin)
    ON DELETE CASCADE ON UPDATE CASCADE
);

--
-- Table structure for table `user_contacts`
--

CREATE TABLE `user_contacts` (
  `id` int NOT NULL,
  `owner_user_id` int NOT NULL,
  `friend_user_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_user_id` int DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_security_log (
  id INT NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) DEFAULT NULL,
  admin_id INT DEFAULT NULL,
  action VARCHAR(50) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  meta TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_asl_email (email),
  KEY idx_asl_admin (admin_id),
  KEY idx_asl_action (action),
  KEY idx_asl_created (created_at)
);

CREATE TABLE deleteduser (
  id INT NOT NULL AUTO_INCREMENT,
  email VARCHAR(100) NOT NULL,
  deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_deleteduser_email (email),
  KEY idx_deleteduser_deleted_at (deleted_at)
);

-- =========================================================
-- CHAT (user_id based)
-- =========================================================
CREATE TABLE chat_messages (
  id INT NOT NULL AUTO_INCREMENT,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  feedbackdata TEXT NOT NULL,
  attachment VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME DEFAULT NULL,
  delivered_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pair_time (sender_id, receiver_id, created_at),
  KEY idx_receiver_read (receiver_id, is_read, created_at),
  KEY idx_delivered_at (delivered_at)
);

CREATE TABLE chat_typing (
  id INT NOT NULL AUTO_INCREMENT,
  sender_code VARCHAR(20) DEFAULT NULL,
  receiver_code VARCHAR(20) DEFAULT NULL,
  is_typing TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_typing (sender_code, receiver_code)
);

-- =========================================================
-- CONTACTS
-- =========================================================
CREATE TABLE contacts (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  contact_user_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contacts_pair (user_id, contact_user_id),
  KEY idx_user (user_id),
  KEY idx_contact (contact_user_id),
  CONSTRAINT fk_c_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_c_contact
    FOREIGN KEY (contact_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE contact_requests (
  id INT NOT NULL AUTO_INCREMENT,
  from_user_id INT NOT NULL,
  to_user_id INT NOT NULL,
  status ENUM('pending','accepted','declined','blocked') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_request_pair (from_user_id, to_user_id),
  KEY idx_to_status (to_user_id, status),
  CONSTRAINT fk_cr_from
    FOREIGN KEY (from_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cr_to
    FOREIGN KEY (to_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =========================================================
-- CONVERSATIONS (optional system)
-- =========================================================
CREATE TABLE conversations (
  id INT NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  type ENUM('user','support') NOT NULL DEFAULT 'user',
  created_by_user_id INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conv_uuid (uuid),
  KEY idx_type (type),
  KEY idx_creator (created_by_user_id),
  CONSTRAINT fk_conv_creator
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE conversation_participants (
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  KEY idx_cp_user (user_id),
  CONSTRAINT fk_cp_conv
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cp_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE messages (
  id INT NOT NULL AUTO_INCREMENT,
  conversation_id INT NOT NULL,
  sender_user_id INT NOT NULL,
  body TEXT,
  attachment VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conv_time (conversation_id, created_at),
  KEY idx_sender (sender_user_id),
  CONSTRAINT fk_msg_conv
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_msg_sender
    FOREIGN KEY (sender_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE message_reads (
  message_id INT NOT NULL,
  user_id INT NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id),
  KEY idx_mr_user (user_id),
  CONSTRAINT fk_mr_msg
    FOREIGN KEY (message_id) REFERENCES messages(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mr_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =========================================================
-- FEEDBACK (your existing system)
-- delivered_at + read_at exist already
-- =========================================================
CREATE TABLE feedback (
  id INT NOT NULL AUTO_INCREMENT,
  sender VARCHAR(100) NOT NULL,
  receiver VARCHAR(100) NOT NULL,
  channel VARCHAR(30) NOT NULL DEFAULT 'user_admin',
  title VARCHAR(150) NOT NULL,
  feedbackdata TEXT NOT NULL,
  attachment VARCHAR(150) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at TIMESTAMP NULL DEFAULT NULL,
  delivered_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_feedback_receiver_created (receiver, created_at),
  KEY idx_feedback_receiver_read (receiver, is_read, created_at),
  KEY idx_feedback_sender_receiver (sender, receiver, created_at),
  KEY idx_feedback_channel_receiver (channel, receiver, created_at),
  KEY idx_feedback_channel_read (channel, receiver, is_read, created_at),
  KEY idx_feedback_channel_sender_receiver (channel, sender, receiver, created_at),
  KEY idx_delivered_at (delivered_at)
);

-- =========================================================
-- NOTIFICATION
-- =========================================================
CREATE TABLE notification (
  id INT NOT NULL AUTO_INCREMENT,
  notiuser VARCHAR(100) NOT NULL,
  notireceiver VARCHAR(100) NOT NULL,
  notitype VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_notification_receiver_read (notireceiver, is_read),
  KEY idx_notification_receiver_created (notireceiver, created_at),
  KEY idx_notification_created (created_at)
);

-- =========================================================
-- PASSWORD RESET TOKENS
-- =========================================================
CREATE TABLE password_reset_tokens (
  id INT NOT NULL AUTO_INCREMENT,
  account_type ENUM('user','admin') NOT NULL,
  user_id INT DEFAULT NULL,
  admin_id INT DEFAULT NULL,
  username VARCHAR(100) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  ip VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_token_hash (token_hash),
  KEY idx_username (username),
  KEY idx_expires (expires_at),
  KEY idx_account (account_type),
  KEY idx_user_id (user_id),
  KEY idx_admin_id (admin_id)
);

-- =========================================================
-- ADD CHAT FOREIGN KEYS SAFELY
-- =========================================================
-- If you are importing into an existing database with chat_messages data,
-- the FK may fail if sender_id/receiver_id values don't exist in users.
-- Run this BEFORE enabling FKs if needed:
--   DELETE cm FROM chat_messages cm
--   LEFT JOIN users u1 ON u1.id = cm.sender_id
--   LEFT JOIN users u2 ON u2.id = cm.receiver_id
--   WHERE u1.id IS NULL OR u2.id IS NULL;

ALTER TABLE chat_messages
  ADD CONSTRAINT fk_chat_sender FOREIGN KEY (sender_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_chat_receiver FOREIGN KEY (receiver_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

SET foreign_key_checks = 1;


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
-- AUTO_INCREMENT for table `user_contacts`
--
ALTER TABLE `user_contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;



-- --------------------------------------------------------
-- Contact rename history (for Undo rename)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_contact_name_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_user_id` int NOT NULL,
  `friend_user_id` int NOT NULL,
  `old_name` varchar(255) NOT NULL,
  `new_name` varchar(255) NOT NULL,
  `action` varchar(20) NOT NULL DEFAULT 'rename',
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner_friend` (`owner_user_id`,`friend_user_id`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
