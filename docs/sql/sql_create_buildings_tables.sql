DROP TABLE IF EXISTS `building_requirements`;
DROP TABLE IF EXISTS `village_buildings`;
DROP TABLE IF EXISTS `building_types`;

CREATE TABLE IF NOT EXISTS building_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    internal_name VARCHAR(50) NOT NULL UNIQUE, -- np. 'main_building', 'barracks', 'warehouse', 'sawmill', 'clay_pit', 'iron_mine'
    name_pl VARCHAR(100) NOT NULL, -- Polska nazwa budynku
    description_pl TEXT,
    max_level INT DEFAULT 20,
    base_build_time_initial INT DEFAULT 900, -- Czas budowy pierwszego poziomu w sekundach
    build_time_factor FLOAT DEFAULT 1.2, -- Mnożnik czasu budowy dla kolejnych poziomów
    cost_wood_initial INT DEFAULT 100,
    cost_clay_initial INT DEFAULT 100,
    cost_iron_initial INT DEFAULT 100,
    cost_factor FLOAT DEFAULT 1.25, -- Mnożnik kosztu dla kolejnych poziomów
    production_type VARCHAR(50) NULL, -- 'wood', 'clay', 'iron' lub NULL jeśli nie produkuje
    production_initial INT NULL, -- Produkcja na poziomie 1
    production_factor FLOAT NULL, -- Mnożnik produkcji dla kolejnych poziomów
    -- Dodana kolumna dla bonusu redukcji czasu budowy (dla Ratusza)
    bonus_time_reduction_factor FLOAT DEFAULT 1.0 COMMENT 'Współczynnik redukcji czasu budowy (dla Ratusza)'
);

-- Tabela przechowująca zależności między budynkami (wymagania)
CREATE TABLE IF NOT EXISTS building_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_type_id INT NOT NULL,
    required_building VARCHAR(50) NOT NULL, -- internal_name wymaganego budynku
    required_level INT NOT NULL DEFAULT 1,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS village_buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    village_id INT NOT NULL,
    building_type_id INT NOT NULL,
    level INT DEFAULT 0, -- Aktualny, ukończony poziom
    upgrade_level_to INT DEFAULT NULL, -- Poziom do którego trwa rozbudowa (NULL jeśli nie trwa)
    upgrade_ends_at DATETIME DEFAULT NULL, -- Czas zakończenia rozbudowy (NULL jeśli nie trwa)
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE,
    UNIQUE (village_id, building_type_id) -- Każdy typ budynku może być tylko raz w wiosce
);

-- Dodanie kilku podstawowych typów budynków
INSERT INTO `building_types` (`internal_name`, `name_pl`, `description_pl`, `max_level`, `base_build_time_initial`, `build_time_factor`, `cost_wood_initial`, `cost_clay_initial`, `cost_iron_initial`, `cost_factor`, `production_type`, `production_initial`, `production_factor`, `bonus_time_reduction_factor`) VALUES
('main_building', 'Ratusz', 'Ratusz jest centralnym punktem Twojej wioski. Im wyższy poziom ratusza, tym szybciej możesz budować inne budynki.', 20, 900, 1.2, 90, 80, 70, 1.25, NULL, NULL, NULL, 0.95), -- Dodano 0.95 dla Ratusza
('sawmill', 'Tartak', 'Tartak produkuje drewno. Im wyższy poziom, tym większa produkcja drewna.', 30, 600, 1.18, 50, 60, 40, 1.26, 'wood', 30, 1.16, 1.0),
('clay_pit', 'Cegielnia', 'Cegielnia produkuje glinę. Im wyższy poziom, tym większa produkcja gliny.', 30, 600, 1.18, 65, 50, 40, 1.26, 'clay', 30, 1.16, 1.0),
('iron_mine', 'Huta Żelaza', 'Huta żelaza produkuje żelazo. Im wyższy poziom, tym większa produkcja żelaza.', 30, 720, 1.18, 75, 65, 60, 1.26, 'iron', 30, 1.16, 1.0),
('warehouse', 'Magazyn', 'Magazyn przechowuje Twoje surowce. Im wyższy poziom, tym większa pojemność magazynu.', 30, 800, 1.15, 60, 50, 40, 1.22, NULL, 1000, 1.227, 1.0),
('farm', 'Farma', 'Farma zwiększa populację wioski. Im wyższy poziom, tym więcej mieszkańców może żyć w wiosce.', 30, 1000, 1.2, 80, 100, 70, 1.28, NULL, 240, 1.17, 1.0),
('barracks', 'Koszary', 'W koszarach możesz szkolić jednostki piechoty.', 25, 1200, 1.22, 200, 170, 90, 1.26, NULL, NULL, NULL, 1.0),
('stable', 'Stajnia', 'W stajni możesz szkolić jednostki kawalerii.', 20, 1500, 1.25, 270, 240, 260, 1.28, NULL, NULL, NULL, 1.0),
('workshop', 'Warsztat', 'W warsztacie możesz budować machiny oblężnicze.', 15, 2000, 1.3, 300, 320, 290, 1.3, NULL, NULL, NULL, 1.0),
('smithy', 'Kuźnia', 'W kuźni możesz ulepszać swoją broń i zbroje.', 20, 1100, 1.24, 180, 250, 220, 1.24, NULL, NULL, NULL, 1.0),
('market', 'Targ', 'Na targu możesz handlować surowcami z innymi graczami.', 25, 1300, 1.22, 150, 200, 130, 1.23, NULL, NULL, NULL, 1.0),
('wall', 'Mur', 'Mur zapewnia ochronę przed atakami wroga.', 20, 1400, 1.26, 100, 300, 200, 1.25, NULL, NULL, NULL, 1.0),
('academy', 'Akademia', 'W akademii możesz prowadzić badania nowych technologii.', 20, 1600, 1.28, 260, 300, 220, 1.27, NULL, NULL, NULL, 1.0);

-- Dodanie wymagań do poszczególnych budynków
INSERT INTO `building_requirements` (`building_type_id`, `required_building`, `required_level`) VALUES
((SELECT id FROM building_types WHERE internal_name = 'barracks'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'stable'), 'barracks', 3),
((SELECT id FROM building_types WHERE internal_name = 'stable'), 'smithy', 2),
((SELECT id FROM building_types WHERE internal_name = 'workshop'), 'stable', 3),
((SELECT id FROM building_types WHERE internal_name = 'workshop'), 'smithy', 3),
((SELECT id FROM building_types WHERE internal_name = 'smithy'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'market'), 'main_building', 3),
((SELECT id FROM building_types WHERE internal_name = 'market'), 'warehouse', 2),
((SELECT id FROM building_types WHERE internal_name = 'wall'), 'barracks', 1),
((SELECT id FROM building_types WHERE internal_name = 'academy'), 'main_building', 5),
((SELECT id FROM building_types WHERE internal_name = 'academy'), 'smithy', 1); 