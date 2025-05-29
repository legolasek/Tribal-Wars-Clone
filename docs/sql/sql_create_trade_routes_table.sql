CREATE TABLE IF NOT EXISTS trade_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_village_id INT NOT NULL,
    target_village_id INT NULL,
    target_x INT NOT NULL,
    target_y INT NOT NULL,
    wood INT NOT NULL,
    clay INT NOT NULL,
    iron INT NOT NULL,
    traders_count INT NOT NULL DEFAULT 1,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    FOREIGN KEY (source_village_id) REFERENCES villages(id) ON DELETE CASCADE,
    FOREIGN KEY (target_village_id) REFERENCES villages(id) ON DELETE CASCADE
); 