-- Tabela do przechowywania ofert na targu
CREATE TABLE IF NOT EXISTS market_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    village_id INT NOT NULL,
    offered_resource VARCHAR(10) NOT NULL,
    offered_amount INT NOT NULL,
    requested_resource VARCHAR(10) NOT NULL,
    requested_amount INT NOT NULL,
    traders_required INT NOT NULL,
    status ENUM('open', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE
);