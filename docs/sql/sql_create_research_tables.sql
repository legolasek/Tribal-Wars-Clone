-- Create research_types table to store all available research options
CREATE TABLE IF NOT EXISTS `research_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internal_name` varchar(50) NOT NULL,
  `name_pl` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `building_type` varchar(50) NOT NULL,  -- Building where research can be performed (smithy, etc.)
  `required_building_level` int(11) NOT NULL DEFAULT 1,
  `cost_wood` int(11) NOT NULL DEFAULT 0,
  `cost_clay` int(11) NOT NULL DEFAULT 0,
  `cost_iron` int(11) NOT NULL DEFAULT 0,
  `research_time_base` int(11) NOT NULL DEFAULT 3600,  -- Base research time in seconds
  `research_time_factor` float NOT NULL DEFAULT 1.2,   -- Multiplier for each level
  `max_level` int(11) NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `prerequisite_research_id` int(11) DEFAULT NULL,     -- ID of research required before this one
  `prerequisite_research_level` int(11) DEFAULT NULL,  -- Required level of prerequisite research
  PRIMARY KEY (`id`),
  UNIQUE KEY `internal_name` (`internal_name`),
  KEY `building_type` (`building_type`),
  KEY `prerequisite_research_id` (`prerequisite_research_id`),
  CONSTRAINT `research_types_ibfk_1` FOREIGN KEY (`prerequisite_research_id`) REFERENCES `research_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create village_research table to store research levels for each village
CREATE TABLE IF NOT EXISTS `village_research` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `village_id` int(11) NOT NULL,
  `research_type_id` int(11) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `village_research_unique` (`village_id`, `research_type_id`),
  KEY `research_type_id` (`research_type_id`),
  CONSTRAINT `village_research_ibfk_1` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `village_research_ibfk_2` FOREIGN KEY (`research_type_id`) REFERENCES `research_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create research_queue table to store ongoing research tasks
CREATE TABLE IF NOT EXISTS `research_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `village_id` int(11) NOT NULL,
  `research_type_id` int(11) NOT NULL,
  `level_after` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `village_id` (`village_id`),
  KEY `research_type_id` (`research_type_id`),
  CONSTRAINT `research_queue_ibfk_1` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `research_queue_ibfk_2` FOREIGN KEY (`research_type_id`) REFERENCES `research_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample research types for smithy
INSERT INTO `research_types` (`internal_name`, `name_pl`, `description`, `building_type`, `required_building_level`, `cost_wood`, `cost_clay`, `cost_iron`, `research_time_base`, `max_level`) VALUES
('improved_axe', 'Ulepszona Siekiera', 'Zwiększa atak piechoty o 10% za każdy poziom.', 'smithy', 1, 180, 150, 220, 3600, 3),
('improved_armor', 'Ulepszona Zbroja', 'Zwiększa obronę piechoty o 10% za każdy poziom.', 'smithy', 2, 200, 180, 240, 4200, 3),
('improved_sword', 'Ulepszony Miecz', 'Zwiększa atak kawalerii o 10% za każdy poziom.', 'smithy', 3, 220, 200, 260, 4800, 3),
('horseshoe', 'Podkowy', 'Zwiększa szybkość kawalerii o 10% za każdy poziom.', 'smithy', 4, 240, 220, 280, 5400, 3),
('improved_catapult', 'Ulepszony Katapult', 'Zwiększa obrażenia katapult o 10% za każdy poziom.', 'smithy', 5, 300, 280, 350, 6000, 3);

-- Insert sample advanced research types for academy
INSERT INTO `research_types` (`internal_name`, `name_pl`, `description`, `building_type`, `required_building_level`, `cost_wood`, `cost_clay`, `cost_iron`, `research_time_base`, `max_level`) VALUES
('spying', 'Szpiegostwo', 'Pozwala na dokładniejsze raporty zwiadowcze.', 'academy', 1, 400, 600, 500, 7200, 3),
('improved_maps', 'Ulepszone Mapy', 'Zwiększa zasięg widoczności mapy.', 'academy', 2, 500, 700, 600, 8400, 3),
('military_tactics', 'Taktyka Wojenna', 'Zwiększa morale wojsk o 5% za każdy poziom.', 'academy', 3, 600, 800, 700, 9600, 3); 