DROP TABLE IF EXISTS `unit_types`;

-- Tworzenie tabeli typów jednostek
CREATE TABLE IF NOT EXISTS `unit_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `internal_name` VARCHAR(50) NOT NULL UNIQUE, -- np. 'spear', 'sword', 'axe', 'archer'
  `name_pl` VARCHAR(100) NOT NULL, -- Polska nazwa jednostki
  `description_pl` TEXT,
  `building_type` VARCHAR(50) NULL, -- internal_name budynku wymagany do rekrutacji (np. 'barracks', 'stable')
  `attack` INT(11) DEFAULT 0,
  `defense` INT(11) DEFAULT 0,
  `speed` INT(11) DEFAULT 0 COMMENT 'Prędkość w polach na godzinę',
  `carry_capacity` INT(11) DEFAULT 0 COMMENT 'Udźwig surowców',
  `population` INT(11) DEFAULT 1 COMMENT 'Zużycie populacji na jednostkę',
  `wood_cost` INT(11) DEFAULT 0,
  `clay_cost` INT(11) DEFAULT 0,
  `iron_cost` INT(11) DEFAULT 0,
  `required_tech` VARCHAR(50) NULL, -- nazwa wewnętrzna wymaganej technologii
  `required_tech_level` INT(11) DEFAULT 0, -- wymagany poziom technologii
  `required_building_level` INT(11) DEFAULT 0, -- wymagany poziom budynku
  `training_time_base` INT(11) DEFAULT 0 COMMENT 'Czas rekrutacji w sekundach',
  `is_active` TINYINT(1) DEFAULT 1, -- Czy jednostka jest aktywna
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 