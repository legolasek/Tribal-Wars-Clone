<?php

class ResearchManager {
    private $conn;
    private $research_types_cache = [];

    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->loadResearchTypes();
    }

    /**
     * Ładuje wszystkie typy badań do pamięci podręcznej dla szybszego dostępu
     */
    private function loadResearchTypes() {
        $stmt = $this->conn->prepare("SELECT * FROM research_types");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $this->research_types_cache[$row['internal_name']] = $row;
        }
        $stmt->close();
    }

    /**
     * Pobiera informacje o typie badania
     * 
     * @param string $internal_name Nazwa wewnętrzna badania
     * @return array|null Informacje o badaniu lub null, jeśli nie znaleziono
     */
    public function getResearchType($internal_name) {
        return $this->research_types_cache[$internal_name] ?? null;
    }

    /**
     * Pobiera wszystkie typy badań dla danego typu budynku
     * 
     * @param string $buildingType Typ budynku (np. 'smithy', 'academy')
     * @return array Tablica typów badań
     */
    public function getResearchTypesForBuilding($buildingType) {
        $result = [];
        foreach ($this->research_types_cache as $research) {
            if ($research['building_type'] === $buildingType && $research['is_active'] == 1) {
                $result[$research['internal_name']] = $research;
            }
        }
        return $result;
    }

    /**
     * Pobiera poziomy wszystkich badań dla danej wioski
     * 
     * @param int $villageId ID wioski
     * @return array Tablica z poziomami badań [internal_name => level]
     */
    public function getVillageResearchLevels($villageId) {
        $result = [];
        
        // Najpierw ustaw wszystkie badania na poziom 0 (domyślny)
        foreach ($this->research_types_cache as $internal_name => $research) {
            $result[$internal_name] = 0;
        }
        
        // Pobierz faktyczne poziomy z bazy danych
        $stmt = $this->conn->prepare("
            SELECT rt.internal_name, vr.level
            FROM village_research vr
            JOIN research_types rt ON vr.research_type_id = rt.id
            WHERE vr.village_id = ?
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $db_result = $stmt->get_result();
        
        while ($row = $db_result->fetch_assoc()) {
            $result[$row['internal_name']] = (int)$row['level'];
        }
        $stmt->close();
        
        return $result;
    }

    /**
     * Sprawdza, czy spełnione są wymagania do przeprowadzenia badania
     * 
     * @param int $researchTypeId ID typu badania
     * @param int $villageId ID wioski
     * @param int $targetLevel Docelowy poziom badania
     * @return array Status i informacja o wymaganiach
     */
    public function checkResearchRequirements($researchTypeId, $villageId, $targetLevel = null) {
        $response = [
            'can_research' => false,
            'reason' => 'unknown',
            'required_building_level' => 0,
            'current_building_level' => 0,
            'prerequisite_name' => null,
            'prerequisite_required_level' => 0,
            'prerequisite_current_level' => 0
        ];

        // Pobierz informacje o typie badania
        $stmt = $this->conn->prepare("SELECT * FROM research_types WHERE id = ?");
        $stmt->bind_param("i", $researchTypeId);
        $stmt->execute();
        $research = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$research) {
            $response['reason'] = 'research_not_found';
            return $response;
        }

        // Jeśli nie podano docelowego poziomu, zakładamy następny poziom
        if ($targetLevel === null) {
            // Pobierz aktualny poziom badania
            $stmt = $this->conn->prepare("
                SELECT level FROM village_research 
                WHERE village_id = ? AND research_type_id = ?
            ");
            $stmt->bind_param("ii", $villageId, $researchTypeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_level = 0;
            
            if ($row = $result->fetch_assoc()) {
                $current_level = (int)$row['level'];
            }
            $stmt->close();
            
            $targetLevel = $current_level + 1;
        }

        // Sprawdź, czy nie przekroczono maksymalnego poziomu
        if ($targetLevel > $research['max_level']) {
            $response['reason'] = 'max_level_reached';
            return $response;
        }

        // Sprawdź poziom budynku
        $buildingType = $research['building_type'];
        $requiredLevel = $research['required_building_level'];
        $response['required_building_level'] = $requiredLevel;

        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb 
            JOIN building_types bt ON vb.building_type_id = bt.id 
            WHERE bt.internal_name = ? AND vb.village_id = ?
        ");
        $stmt->bind_param("si", $buildingType, $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $building = $result->fetch_assoc();
        $stmt->close();

        if (!$building) {
            $response['reason'] = 'building_not_found';
            return $response;
        }

        $currentLevel = $building['level'];
        $response['current_building_level'] = $currentLevel;

        if ($currentLevel < $requiredLevel) {
            $response['reason'] = 'building_level_too_low';
            return $response;
        }

        // Sprawdź warunek poprzedniego badania, jeśli istnieje
        if ($research['prerequisite_research_id']) {
            $prereq_id = $research['prerequisite_research_id'];
            $prereq_level = $research['prerequisite_research_level'];
            
            // Pobierz informacje o wymaganym badaniu
            $stmt = $this->conn->prepare("SELECT name_pl, internal_name FROM research_types WHERE id = ?");
            $stmt->bind_param("i", $prereq_id);
            $stmt->execute();
            $prereq_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($prereq_info) {
                $response['prerequisite_name'] = $prereq_info['name_pl'];
                $response['prerequisite_required_level'] = $prereq_level;
                
                // Sprawdź aktualny poziom wymaganego badania
                $stmt = $this->conn->prepare("
                    SELECT level FROM village_research 
                    WHERE village_id = ? AND research_type_id = ?
                ");
                $stmt->bind_param("ii", $villageId, $prereq_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $prereq_current_level = 0;
                
                if ($row = $result->fetch_assoc()) {
                    $prereq_current_level = (int)$row['level'];
                }
                $stmt->close();
                
                $response['prerequisite_current_level'] = $prereq_current_level;
                
                if ($prereq_current_level < $prereq_level) {
                    $response['reason'] = 'prerequisite_not_met';
                    return $response;
                }
            }
        }

        $response['can_research'] = true;
        $response['reason'] = 'ok';
        return $response;
    }

    /**
     * Oblicza koszt badania dla danego typu i poziomu
     * 
     * @param int $researchTypeId ID typu badania
     * @param int $targetLevel Docelowy poziom badania
     * @return array|null Koszt badania [wood, clay, iron] lub null w przypadku błędu
     */
    public function getResearchCost($researchTypeId, $targetLevel) {
        if ($targetLevel <= 0) return null;
        
        $stmt = $this->conn->prepare("SELECT * FROM research_types WHERE id = ?");
        $stmt->bind_param("i", $researchTypeId);
        $stmt->execute();
        $research = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$research || $targetLevel > $research['max_level']) {
            return null;
        }

        // Formuła kosztów: koszt_bazowy * (współczynnik_kosztu ^ (poziom - 1))
        // Zamiast używać współczynnika budowy, używamy dedykowanego współczynnika badania
        $cost_factor = 1.2; // Domyślny mnożnik, można umieścić w tabeli jeśli każde badanie ma inny mnożnik
        
        $cost_wood = floor($research['cost_wood'] * pow($cost_factor, $targetLevel - 1));
        $cost_clay = floor($research['cost_clay'] * pow($cost_factor, $targetLevel - 1));
        $cost_iron = floor($research['cost_iron'] * pow($cost_factor, $targetLevel - 1));
        
        return [
            'wood' => $cost_wood,
            'clay' => $cost_clay,
            'iron' => $cost_iron
        ];
    }

    /**
     * Oblicza czas potrzebny na przeprowadzenie badania
     * 
     * @param int $researchTypeId ID typu badania
     * @param int $targetLevel Docelowy poziom badania
     * @param int $buildingLevel Poziom budynku badawczego
     * @return int|null Czas badania w sekundach lub null w przypadku błędu
     */
    public function calculateResearchTime($researchTypeId, $targetLevel, $buildingLevel) {
        if ($targetLevel <= 0) return null;
        
        $stmt = $this->conn->prepare("SELECT * FROM research_types WHERE id = ?");
        $stmt->bind_param("i", $researchTypeId);
        $stmt->execute();
        $research = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$research || $targetLevel > $research['max_level']) {
            return null;
        }

        // Bazowy czas badania
        $base_time = $research['research_time_base'];
        
        // Oblicz czas badania dla danego poziomu
        $time_for_level = floor($base_time * pow($research['research_time_factor'], $targetLevel - 1));
        
        // Redukcja czasu w zależności od poziomu budynku badawczego
        // Przykładowa formuła: czas / (1 + (poziom_budynku * 0.05))
        // Ten współczynnik może być różny dla różnych budynków badawczych
        $time_reduction_factor = 0.05;
        $time_with_building = floor($time_for_level / (1 + ($buildingLevel * $time_reduction_factor)));
        
        return max(10, $time_with_building); // Minimalny czas badania to 10 sekund
    }

    /**
     * Rozpoczyna badanie w danej wiosce
     * 
     * @param int $villageId ID wioski
     * @param int $researchTypeId ID typu badania
     * @param int $targetLevel Docelowy poziom badania
     * @param array $resources Dostępne zasoby wioski [wood, clay, iron]
     * @return array Status operacji i ewentualne informacje o błędach
     */
    public function startResearch($villageId, $researchTypeId, $targetLevel, $resources) {
        $response = [
            'success' => false,
            'message' => '',
            'error' => '',
            'research_id' => 0,
            'ends_at' => null
        ];
        
        // Sprawdź, czy badanie jest już w kolejce
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM research_queue 
            WHERE village_id = ? AND research_type_id = ?
        ");
        $stmt->bind_param("ii", $villageId, $researchTypeId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] > 0) {
            $response['error'] = 'Badanie tego typu jest już w trakcie realizacji.';
            return $response;
        }
        
        // Sprawdź wymagania
        $req_check = $this->checkResearchRequirements($researchTypeId, $villageId, $targetLevel);
        if (!$req_check['can_research']) {
            $response['error'] = 'Nie spełnione wymagania do badania.';
            $response['reason'] = $req_check['reason'];
            return $response;
        }
        
        // Pobierz koszt badania
        $cost = $this->getResearchCost($researchTypeId, $targetLevel);
        if (!$cost) {
            $response['error'] = 'Nie można obliczyć kosztu badania.';
            return $response;
        }
        
        // Sprawdź, czy gracz ma wystarczające zasoby
        if ($resources['wood'] < $cost['wood'] || 
            $resources['clay'] < $cost['clay'] || 
            $resources['iron'] < $cost['iron']) {
            $response['error'] = 'Niewystarczające zasoby do przeprowadzenia badania.';
            return $response;
        }
        
        // Pobierz poziom budynku badawczego
        $research_type = $this->getResearchTypeById($researchTypeId);
        if (!$research_type) {
            $response['error'] = 'Nieprawidłowy typ badania.';
            return $response;
        }
        
        $building_type = $research_type['building_type'];
        $stmt = $this->conn->prepare("
            SELECT vb.level 
            FROM village_buildings vb 
            JOIN building_types bt ON vb.building_type_id = bt.id 
            WHERE bt.internal_name = ? AND vb.village_id = ?
        ");
        $stmt->bind_param("si", $building_type, $villageId);
        $stmt->execute();
        $building = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$building) {
            $response['error'] = 'Brak wymaganego budynku.';
            return $response;
        }
        
        $building_level = (int)$building['level'];
        
        // Oblicz czas badania
        $research_time = $this->calculateResearchTime($researchTypeId, $targetLevel, $building_level);
        if (!$research_time) {
            $response['error'] = 'Nie można obliczyć czasu badania.';
            return $response;
        }
        
        // Rozpocznij transakcję
        $this->conn->begin_transaction();
        
        try {
            // Pobierz zasoby
            $stmt = $this->conn->prepare("
                UPDATE villages 
                SET wood = wood - ?, clay = clay - ?, iron = iron - ? 
                WHERE id = ?
            ");
            $stmt->bind_param("dddi", $cost['wood'], $cost['clay'], $cost['iron'], $villageId);
            if (!$stmt->execute()) {
                throw new Exception("Błąd aktualizacji zasobów.");
            }
            $stmt->close();
            
            // Oblicz czas zakończenia
            $end_time = time() + $research_time;
            $end_time_sql = date('Y-m-d H:i:s', $end_time);
            
            // Dodaj badanie do kolejki
            $stmt = $this->conn->prepare("
                INSERT INTO research_queue 
                (village_id, research_type_id, level_after, ends_at) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiis", $villageId, $researchTypeId, $targetLevel, $end_time_sql);
            if (!$stmt->execute()) {
                throw new Exception("Błąd dodawania badania do kolejki.");
            }
            $queue_id = $stmt->insert_id;
            $stmt->close();
            
            $this->conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Badanie rozpoczęte pomyślnie.';
            $response['research_id'] = $queue_id;
            $response['ends_at'] = $end_time_sql;
            
            return $response;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            $response['error'] = 'Wystąpił błąd podczas rozpoczynania badania: ' . $e->getMessage();
            return $response;
        }
    }

    /**
     * Anuluje trwające badanie
     * 
     * @param int $queueId ID badania w kolejce
     * @param int $userId ID użytkownika (dla weryfikacji)
     * @return array Status operacji
     */
    public function cancelResearch($queueId, $userId) {
        $response = [
            'success' => false,
            'message' => '',
            'error' => '',
            'refunded' => [
                'wood' => 0,
                'clay' => 0,
                'iron' => 0
            ]
        ];
        
        // Pobierz informacje o badaniu z kolejki
        $stmt = $this->conn->prepare("
            SELECT rq.*, v.user_id, rt.cost_wood, rt.cost_clay, rt.cost_iron, rt.research_time_factor
            FROM research_queue rq
            JOIN villages v ON rq.village_id = v.id
            JOIN research_types rt ON rq.research_type_id = rt.id
            WHERE rq.id = ?
        ");
        $stmt->bind_param("i", $queueId);
        $stmt->execute();
        $queue_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$queue_item) {
            $response['error'] = 'Nie znaleziono badania do anulowania.';
            return $response;
        }
        
        // Sprawdź czy badanie należy do użytkownika
        if ($queue_item['user_id'] != $userId) {
            $response['error'] = 'Nie masz uprawnień do anulowania tego badania.';
            return $response;
        }
        
        // Oblicz ile zasobów zwrócić
        $current_time = time();
        $start_time = strtotime($queue_item['started_at']);
        $end_time = strtotime($queue_item['ends_at']);
        $total_time = $end_time - $start_time;
        $elapsed_time = $current_time - $start_time;
        
        // Jeśli badanie jeszcze się nie zaczęło lub trwa bardzo krótko, zwróć 100%
        if ($elapsed_time <= 10) {
            $refund_percentage = 1.0;
        } else {
            // Zwrot proporcjonalny do pozostałego czasu (minimum 50%)
            $remaining_percentage = max(0, 1 - ($elapsed_time / $total_time));
            $refund_percentage = 0.5 + ($remaining_percentage * 0.5);
        }
        
        // Oblicz zwrot zasobów
        $refund_wood = floor($queue_item['cost_wood'] * $refund_percentage);
        $refund_clay = floor($queue_item['cost_clay'] * $refund_percentage);
        $refund_iron = floor($queue_item['cost_iron'] * $refund_percentage);
        
        $response['refunded'] = [
            'wood' => $refund_wood,
            'clay' => $refund_clay,
            'iron' => $refund_iron
        ];
        
        $village_id = $queue_item['village_id'];
        
        // Rozpocznij transakcję
        $this->conn->begin_transaction();
        
        try {
            // Zwróć zasoby
            $stmt = $this->conn->prepare("
                UPDATE villages 
                SET wood = wood + ?, clay = clay + ?, iron = iron + ? 
                WHERE id = ?
            ");
            $stmt->bind_param("dddi", $refund_wood, $refund_clay, $refund_iron, $village_id);
            if (!$stmt->execute()) {
                throw new Exception("Błąd aktualizacji zasobów.");
            }
            $stmt->close();
            
            // Usuń zadanie z kolejki
            $stmt = $this->conn->prepare("DELETE FROM research_queue WHERE id = ?");
            $stmt->bind_param("i", $queueId);
            if (!$stmt->execute()) {
                throw new Exception("Błąd usuwania badania z kolejki.");
            }
            $stmt->close();
            
            $this->conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Badanie anulowane pomyślnie. Zwrócono część zasobów.';
            
            return $response;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            $response['error'] = 'Wystąpił błąd podczas anulowania badania: ' . $e->getMessage();
            return $response;
        }
    }

    /**
     * Przetwarza kolejkę badań dla danej wioski
     * 
     * @param int $villageId ID wioski
     * @return array Status i informacje o zakończonych badaniach
     */
    public function processResearchQueue($villageId) {
        $response = [
            'completed_research' => [],
            'updated_queue' => []
        ];
        
        // Pobierz wszystkie trwające badania dla wioski
        $stmt = $this->conn->prepare("
            SELECT rq.*, rt.name_pl, rt.internal_name
            FROM research_queue rq
            JOIN research_types rt ON rq.research_type_id = rt.id
            WHERE rq.village_id = ? AND rq.ends_at <= NOW()
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $completed = [];
        
        while ($queue_item = $result->fetch_assoc()) {
            $completed[] = $queue_item;
        }
        $stmt->close();
        
        if (empty($completed)) {
            return $response;
        }
        
        // Rozpocznij transakcję
        $this->conn->begin_transaction();
        
        try {
            foreach ($completed as $item) {
                $research_type_id = $item['research_type_id'];
                $level_after = $item['level_after'];
                
                // Sprawdź, czy istnieje już wpis w village_research
                $stmt = $this->conn->prepare("
                    SELECT id, level FROM village_research 
                    WHERE village_id = ? AND research_type_id = ?
                ");
                $stmt->bind_param("ii", $villageId, $research_type_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();
                
                if ($existing) {
                    // Aktualizuj istniejący wpis
                    $stmt = $this->conn->prepare("
                        UPDATE village_research 
                        SET level = ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ii", $level_after, $existing['id']);
                    if (!$stmt->execute()) {
                        throw new Exception("Błąd aktualizacji poziomu badania.");
                    }
                    $stmt->close();
                } else {
                    // Utwórz nowy wpis
                    $stmt = $this->conn->prepare("
                        INSERT INTO village_research 
                        (village_id, research_type_id, level) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("iii", $villageId, $research_type_id, $level_after);
                    if (!$stmt->execute()) {
                        throw new Exception("Błąd dodawania nowego badania.");
                    }
                    $stmt->close();
                }
                
                // Usuń badanie z kolejki
                $stmt = $this->conn->prepare("DELETE FROM research_queue WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                if (!$stmt->execute()) {
                    throw new Exception("Błąd usuwania badania z kolejki.");
                }
                $stmt->close();
                
                // Dodaj informację o zakończonym badaniu do odpowiedzi
                $response['completed_research'][] = [
                    'research_name' => $item['name_pl'],
                    'research_internal_name' => $item['internal_name'],
                    'level' => $level_after
                ];
            }
            
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Błąd przetwarzania kolejki badań: " . $e->getMessage());
        }
        
        return $response;
    }

    /**
     * Pobiera aktualnie trwające badania dla wioski
     * 
     * @param int $villageId ID wioski
     * @return array Lista badań w kolejce
     */
    public function getResearchQueue($villageId) {
        $queue = [];
        
        $stmt = $this->conn->prepare("
            SELECT rq.*, rt.name_pl, rt.internal_name, rt.building_type
            FROM research_queue rq
            JOIN research_types rt ON rq.research_type_id = rt.id
            WHERE rq.village_id = ?
            ORDER BY rq.ends_at ASC
        ");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Oblicz pozostały czas
            $end_time = strtotime($row['ends_at']);
            $current_time = time();
            $remaining_time = max(0, $end_time - $current_time);
            
            $queue[] = [
                'id' => $row['id'],
                'research_type_id' => $row['research_type_id'],
                'research_name' => $row['name_pl'],
                'research_internal_name' => $row['internal_name'],
                'building_type' => $row['building_type'],
                'level_after' => $row['level_after'],
                'ends_at' => $row['ends_at'],
                'remaining_time' => $remaining_time
            ];
        }
        $stmt->close();
        
        return $queue;
    }

    /**
     * Pobiera szczegóły badania na podstawie jego ID
     * 
     * @param int $researchTypeId ID typu badania
     * @return array|null Szczegóły badania lub null jeśli nie znaleziono
     */
    public function getResearchTypeById($researchTypeId) {
        foreach ($this->research_types_cache as $research) {
            if ($research['id'] == $researchTypeId) {
                return $research;
            }
        }
        return null;
    }

    /**
     * Pobiera informację, czy badanie jest dostępne dla danego poziomu budynku
     * 
     * @param string $internalName Nazwa wewnętrzna badania
     * @param int $buildingLevel Poziom budynku
     * @return bool True jeśli badanie jest dostępne, false w przeciwnym wypadku
     */
    public function isResearchAvailable($internalName, $buildingLevel) {
        $research = $this->getResearchType($internalName);
        if (!$research) {
            return false;
        }

        return $buildingLevel >= $research['required_building_level'];
    }
}
?> 