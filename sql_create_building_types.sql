-- Tworzenie tabeli typów budynków
CREATE TABLE IF NOT EXISTS `building_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `base_wood_cost` INT(11) DEFAULT 0,
  `base_clay_cost` INT(11) DEFAULT 0,
  `base_iron_cost` INT(11) DEFAULT 0,
  `base_time_cost` INT(11) DEFAULT 0 COMMENT 'Czas budowy w sekundach na poziomie 1',
  `population_cost` INT(11) DEFAULT 0 COMMENT 'Zużycie populacji na poziom',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 