-- =========================================================
-- Business_only3 migration: Contacts rename + undo history
-- Date: 2026-01-18
-- =========================================================

-- 1) History table (used by ajax/contact_rename.php and ajax/contact_undo_rename.php)
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

-- 2) Align user_contacts so it can join users(id)
-- NOTE: Only run the following ALTER statements if your user_contacts schema
-- does not already match what the PHP code expects.

-- friend_user_id must be INT (so: LEFT JOIN users u ON u.id = uc.friend_user_id works)
ALTER TABLE `user_contacts`
  MODIFY `friend_user_id` int NOT NULL;

-- display_name can be empty (unknown codes are not auto-saved; renames are user-driven)
ALTER TABLE `user_contacts`
  MODIFY `display_name` varchar(255) NOT NULL DEFAULT '';

-- Ensure one contact row per owner+friend
ALTER TABLE `user_contacts`
  ADD UNIQUE KEY `uq_owner_friend_user` (`owner_user_id`,`friend_user_id`);
