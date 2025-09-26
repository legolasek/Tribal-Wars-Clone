-- Tworzenie tabel związanych z systemem walk i ataków

-- Tabela ataków
CREATE TABLE IF NOT EXISTS `attacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_village_id` int(11) NOT NULL,
  `target_village_id` int(11) NOT NULL,
  `attack_type` enum('attack','raid','support') NOT NULL DEFAULT 'attack',
  `start_time` datetime NOT NULL,
  `arrival_time` datetime NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `is_canceled` tinyint(1) NOT NULL DEFAULT 0,
  `report_id` INT(11) NULL COMMENT 'ID of the related battle report in the general reports table',
  `target_building` VARCHAR(50) DEFAULT NULL COMMENT 'Cel dla katapult (internal_name)',
  PRIMARY KEY (`id`),
  KEY `source_village_id` (`source_village_id`),
  KEY `target_village_id` (`target_village_id`),
  KEY `arrival_time` (`arrival_time`),
  KEY `is_completed` (`is_completed`),
  KEY `is_canceled` (`is_canceled`),
  KEY `report_id` (`report_id`),
  FOREIGN KEY (`source_village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`target_village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela jednostek wysłanych w ataku
CREATE TABLE IF NOT EXISTS `attack_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attack_id` int(11) NOT NULL,
  `unit_type_id` int(11) NOT NULL,
  `count` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attack_id` (`attack_id`),
  KEY `unit_type_id` (`unit_type_id`),
  FOREIGN KEY (`attack_id`) REFERENCES `attacks` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nowa definicja tabeli battle_reports używająca report_id jako klucza głównego/obcego
-- Nowa definicja tabeli battle_reports
CREATE TABLE IF NOT EXISTS `battle_reports` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `attack_id` int(11) NOT NULL,
  `source_village_id` int(11) NOT NULL,
  `target_village_id` int(11) NOT NULL,
  `battle_time` datetime NOT NULL,
  `attacker_user_id` int(11) NOT NULL,
  `defender_user_id` int(11) NOT NULL,
  `attacker_won` tinyint(1) NOT NULL COMMENT '1 jeśli atakujący wygrał, 0 jeśli obrońca wygrał',
  `report_data` text NOT NULL COMMENT 'Dane raportu w formacie JSON (zawiera straty, łupy, itp.)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `attack_id` (`attack_id`),
  KEY `source_village_id` (`source_village_id`),
  KEY `target_village_id` (`target_village_id`),
  KEY `attacker_user_id` (`attacker_user_id`),
  KEY `defender_user_id` (`defender_user_id`),
  FOREIGN KEY (`attack_id`) REFERENCES `attacks` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`source_village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`target_village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`attacker_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`defender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela szczegółów jednostek w raportach (powiązanie z battle_reports przez report_id)
CREATE TABLE IF NOT EXISTS `battle_report_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL COMMENT 'References battle_reports.report_id',
  `unit_type_id` int(11) NOT NULL,
  `side` enum('attacker','defender') NOT NULL,
  `initial_count` int(11) NOT NULL,
  `lost_count` int(11) NOT NULL,
  `remaining_count` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `unit_type_id` (`unit_type_id`),
  CONSTRAINT `fk_battle_report_units_report_id` FOREIGN KEY (`report_id`) REFERENCES `battle_reports` (`report_id`) ON DELETE CASCADE,
  FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela logów działań AI/ProBot
CREATE TABLE IF NOT EXISTS `ai_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `village_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `village_id` (`village_id`),
  FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 