CREATE TABLE IF NOT EXISTS `building_queue` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    village_id INT NOT NULL,
    village_building_id INT NOT NULL, -- ID wpisu w village_buildings
    building_type_id INT NOT NULL, -- ID typu budynku z building_types
    level INT NOT NULL, -- Poziom po zakończeniu budowy
    starts_at DATETIME NOT NULL, -- Czas rozpoczęcia budowy
    finish_time DATETIME NOT NULL, -- Czas zakończenia budowy
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (village_building_id) REFERENCES village_buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE
); 