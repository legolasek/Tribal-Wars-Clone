-- Tworzenie tabeli typów jednostek (definicje jednostek)
CREATE TABLE IF NOT EXISTS `unit_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `internal_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `name_pl` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description_pl` text COLLATE utf8_unicode_ci,
  `building_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT 'barracks, stable, garage',
  `attack` int(11) NOT NULL DEFAULT 0,
  `defense` int(11) NOT NULL DEFAULT 0,
  `speed` int(11) NOT NULL DEFAULT 0,
  `carry_capacity` int(11) NOT NULL DEFAULT 0,
  `population` int(11) NOT NULL DEFAULT 1,
  `wood_cost` int(11) NOT NULL DEFAULT 0,
  `cost_clay` int(11) NOT NULL DEFAULT 0,
  `cost_iron` int(11) NOT NULL DEFAULT 0,
  `required_tech` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'internal_name of required research',
  `required_tech_level` int(11) NOT NULL DEFAULT 0,
  `required_building_level` int(11) NOT NULL DEFAULT 1,
  `training_time_base` int(11) NOT NULL DEFAULT 0 COMMENT 'Base training time in seconds',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `internal_name` (`internal_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dodanie podstawowych typów jednostek
INSERT INTO `unit_types` (`internal_name`, `name_pl`, `description_pl`, `building_type`, `attack`, `defense`, `speed`, `carry_capacity`, `population`, `wood_cost`, `clay_cost`, `iron_cost`, `required_tech`, `required_tech_level`, `required_building_level`, `training_time_base`, `is_active`) VALUES
-- Koszary (barracks)
('spear', 'Pikinier', 'Podstawowa jednostka piechoty, dobra do obrony przed kawalerią.', 'barracks', 10, 15, 18, 25, 1, 50, 30, 10, NULL, 0, 1, 900, 1),
('sword', 'Miecznik', 'Silniejsza jednostka piechoty, dobra do obrony przed piechota.', 'barracks', 25, 50, 22, 15, 1, 30, 30, 70, NULL, 0, 1, 1300, 1),
('axe', 'Topornik', 'Silna jednostka piechoty do ataku.', 'barracks', 40, 10, 18, 10, 1, 60, 30, 40, NULL, 0, 2, 1000, 1),
('archer', 'Łucznik', 'Jednostka dystansowa do obrony i ataku.', 'barracks', 15, 50, 18, 10, 1, 100, 30, 60, 'improved_axe', 1, 5, 1800, 1),

-- Stajnia (stable)
('spy', 'Zwiadowca', 'Szybka jednostka kawalerii do zwiadu.', 'stable', 0, 2, 9, 0, 2, 50, 50, 20, NULL, 0, 1, 900, 1),
('light', 'Lekka kawaleria', 'Szybka jednostka kawalerii do ataku.', 'stable', 130, 30, 10, 80, 4, 125, 100, 250, NULL, 0, 3, 1800, 1),
('heavy', 'Ciężka kawaleria', 'Silna jednostka kawalerii do ataku i obrony.', 'stable', 150, 200, 11, 50, 6, 200, 150, 600, 'improved_sword', 2, 10, 3600, 1),
('marcher', 'Konny łucznik', 'Jednostka dystansowa na koniu.', 'stable', 120, 40, 10, 50, 5, 250, 100, 150, 'horseshoe', 1, 5, 2400, 1),

-- Warsztat (garage)
('ram', 'Taran', 'Oblężnicza jednostka do niszczenia murów.', 'garage', 2, 20, 30, 0, 5, 300, 200, 100, NULL, 0, 1, 4800, 1),
('catapult', 'Katapulta', 'Oblężnicza jednostka do niszczenia budynków.', 'garage', 100, 100, 30, 0, 8, 320, 400, 100, 'improved_catapult', 1, 2, 7200, 1);

-- Tworzenie tabeli jednostek w wioskach
CREATE TABLE IF NOT EXISTS `village_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `village_id` int(11) NOT NULL,
  `unit_type_id` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `village_unit_unique` (`village_id`, `unit_type_id`),
  FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Tworzenie tabeli kolejek rekrutacji jednostek
CREATE TABLE IF NOT EXISTS `unit_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `village_id` int(11) NOT NULL,
  `unit_type_id` int(11) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 1,
  `count_finished` int(11) NOT NULL DEFAULT 0,
  `started_at` int(11) NOT NULL,
  `finish_at` int(11) NOT NULL,
  `building_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT 'barracks, stable, garage',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
