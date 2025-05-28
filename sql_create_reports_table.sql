-- Create general reports table to store different types of reports
CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'The user who owns this report (either attacker or defender, or recipient)',
  `report_type` VARCHAR(50) NOT NULL COMMENT 'Type of report (e.g., battle, trade, support)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_read` TINYINT(1) DEFAULT 0,
  `is_archived` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0 COMMENT 'Flag to mark report as deleted by the user',
  `related_id` INT(11) NULL COMMENT 'ID of the related detailed report table (e.g., battle_reports.id)',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `report_type` (`report_type`),
  KEY `created_at` (`created_at`),
  KEY `is_read` (`is_read`),
  KEY `is_archived` (`is_archived`),
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add a foreign key to battle_reports table referencing the new reports table
-- This requires battle_reports table to exist and have a column for the report ID.
-- Assuming battle_reports already has an 'id' column and we need a new column to link to 'reports'
-- ALTER TABLE `battle_reports` ADD COLUMN `report_id` INT(11) NULL AFTER `id`;
-- ALTER TABLE `battle_reports` ADD CONSTRAINT `fk_battle_reports_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;

-- Alternative approach: Use report_id as the primary key for detailed tables
-- This would mean report_id in detailed tables IS the ID from the general reports table.
-- This requires creating the report entry in the general reports table FIRST.
-- Example for battle_reports:
-- DROP TABLE IF EXISTS `battle_reports`; -- Drop if exists from previous script
-- CREATE TABLE IF NOT EXISTS `battle_reports` (
--  `report_id` INT(11) NOT NULL COMMENT 'References reports.id',
--  `attacker_village_id` INT(11) NOT NULL,
--  `defender_village_id` INT(11) NOT NULL,
--  -- ... other battle specific columns ...
--  PRIMARY KEY (`report_id`),
--  CONSTRAINT `fk_battle_reports_report_id` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Decyzja: Przyjmę drugie podejście - report_id w tabelach szczegółowych (np. battle_reports) będzie kluczem głównym i kluczem obcym do reports.id.
-- Będę musiał ręcznie zmodyfikować sql_create_battle_tables.sql lub dodać ALTER TABLE.

-- Na razie tworzę tylko tabelę 'reports'. Modyfikacja istniejących tabel i ich skryptów tworzących będzie osobnym krokiem. 