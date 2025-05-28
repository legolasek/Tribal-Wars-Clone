<?php
class ResourceManager {
    private $conn;
    private $buildingManager;

    public function __construct($conn, $buildingManager) {
        $this->conn = $conn;
        $this->buildingManager = $buildingManager;
    }

    /**
     * Pobiera produkcję surowców na godzinę dla danej wioski (wszystkie typy)
     */
    public function getProductionRates(int $village_id): array {
        $stmt = $this->conn->prepare(
            "SELECT bt.internal_name, vb.level
             FROM village_buildings vb
             JOIN building_types bt ON vb.building_type_id = bt.id
             WHERE vb.village_id = ? AND bt.production_type IS NOT NULL"
        );
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $levels = [];
        while ($row = $result->fetch_assoc()) {
            $levels[$row['internal_name']] = (int)$row['level'];
        }
        $stmt->close();

        return [
            'wood' => $this->buildingManager->getHourlyProduction('sawmill', $levels['sawmill'] ?? 0),
            'clay' => $this->buildingManager->getHourlyProduction('clay_pit', $levels['clay_pit'] ?? 0),
            'iron' => $this->buildingManager->getHourlyProduction('iron_mine', $levels['iron_mine'] ?? 0),
        ];
    }

    /**
     * Pobiera produkcję pojedynczego surowca na godzinę dla danej wioski.
     */
    public function getHourlyProductionRate(int $village_id, string $resource_type): float {
        // Sprawdź, czy typ surowca jest prawidłowy i ma odpowiadający budynek
        $building_map = [
            'wood' => 'sawmill',
            'clay' => 'clay_pit',
            'iron' => 'iron_mine',
        ];

        if (!isset($building_map[$resource_type])) {
            // Nieprawidłowy typ surowca, zwróć 0 lub zgłoś błąd
            return 0.0;
        }

        $building_internal_name = $building_map[$resource_type];

        // Pobierz poziom odpowiedniego budynku produkcyjnego
        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE vb.village_id = ? AND bt.internal_name = ?
        ");
        $stmt->bind_param("is", $village_id, $building_internal_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $level = 0;
        if ($row = $result->fetch_assoc()) {
            $level = (int)$row['level'];
        }
        $stmt->close();

        // Użyj BuildingManager do obliczenia produkcji na godzinę
        return $this->buildingManager->getHourlyProduction($building_internal_name, $level);
    }

    /**
     * Aktualizuje surowce w bazie i zwraca zaktualizowane dane wioski
     */
    public function updateVillageResources(array $village): array {
        $village_id = (int)$village['id'];
        $rates = $this->getProductionRates($village_id);
        $now = time();
        $last_update = strtotime($village['last_resource_update']);
        $elapsed = max(0, $now - $last_update);

        $produced_wood = ($rates['wood'] / 3600) * $elapsed;
        $produced_clay = ($rates['clay'] / 3600) * $elapsed;
        $produced_iron = ($rates['iron'] / 3600) * $elapsed;

        // Pobierz pojemność magazynu - najlepiej z aktualnych danych wioski
        $warehouse_capacity = $village['warehouse_capacity']; // Używamy pojemności z przekazanej tablicy wioski

        $village['wood'] = min($village['wood'] + $produced_wood, $warehouse_capacity);
        $village['clay'] = min($village['clay'] + $produced_clay, $warehouse_capacity);
        $village['iron'] = min($village['iron'] + $produced_iron, $warehouse_capacity);

        $stmt = $this->conn->prepare(
            "UPDATE villages
             SET wood = ?, clay = ?, iron = ?, last_resource_update = NOW()
             WHERE id = ?"
        );
        // Upewnij się, że przekazujesz numeryczne wartości (double) dla surowców
        $stmt->bind_param("dddi", $village['wood'], $village['clay'], $village['iron'], $village_id);
        $stmt->execute();
        $stmt->close();

        $village['last_resource_update'] = date('Y-m-d H:i:s', $now);
        
        // Zwróć całą zaktualizowaną tablicę wioski
        return $village;
    }
} 