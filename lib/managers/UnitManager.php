<?php
/**
 * Klasa UnitManager - zarządzanie jednostkami wojskowymi
 */
class UnitManager
{
    private $conn;
    private $unit_types_cache = [];

    /**
     * Konstruktor
     *
     * @param mysqli $conn Połączenie z bazą danych
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadUnitTypes();
    }

    /**
     * Załaduj wszystkie typy jednostek do pamięci podręcznej
     */
    private function loadUnitTypes()
    {
        $result = $this->conn->query("SELECT * FROM unit_types WHERE is_active = 1");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->unit_types_cache[$row['id']] = $row;
            }
        }
    }

    /**
     * Pobierz wszystkie typy jednostek
     *
     * @return array Tablica typów jednostek
     */
    public function getAllUnitTypes()
    {
        return $this->unit_types_cache;
    }

    /**
     * Pobierz jednostki, które można rekrutować w danym budynku
     *
     * @param string $building_type Typ budynku (barracks, stable, garage)
     * @param int $building_level Poziom budynku
     * @return array Tablica jednostek dostępnych do rekrutacji
     */
    public function getAvailableUnitsByBuilding($building_type, $building_level)
    {
        $available_units = [];

        foreach ($this->unit_types_cache as $unit) {
            if ($unit['building_type'] === $building_type && $unit['required_building_level'] <= $building_level) {
                $available_units[] = $unit;
            }
        }

        return $available_units;
    }

    /**
     * Oblicz czas rekrutacji jednostki
     *
     * @param int $unit_type_id ID typu jednostki
     * @param int $building_level Poziom budynku
     * @return int Czas rekrutacji w sekundach
     */
    public function calculateRecruitmentTime($unit_type_id, $building_level)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return 0;
        }

        $unit = $this->unit_types_cache[$unit_type_id];
        $base_time = $unit['training_time_base'];

        // Im wyższy poziom budynku, tym szybsza rekrutacja (5% szybciej na poziom)
        return floor($base_time * pow(0.95, $building_level - 1));
    }

    /**
     * Sprawdź wymagania dla rekrutacji jednostki
     *
     * @param int $unit_type_id ID typu jednostki
     * @param int $village_id ID wioski
     * @return array Status i powód w przypadku niepowodzenia
     */
    public function checkRecruitRequirements($unit_type_id, $village_id)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return ['can_recruit' => false, 'reason' => 'unit_not_found'];
        }

        $unit = $this->unit_types_cache[$unit_type_id];

        // Sprawdź, czy istnieje budynek odpowiedniego typu
        $stmt = $this->conn->prepare("
            SELECT vb.level
            FROM village_buildings vb
            JOIN building_types bt ON vb.building_type_id = bt.id
            WHERE bt.internal_name = ? AND vb.village_id = ?
        ");

        $building_type = $unit['building_type'];
        $stmt->bind_param("si", $building_type, $village_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return ['can_recruit' => false, 'reason' => 'building_not_found'];
        }

        $building = $result->fetch_assoc();
        $stmt->close();

        // Sprawdź poziom budynku
        if ($building['level'] < $unit['required_building_level']) {
            return [
                'can_recruit' => false,
                'reason' => 'building_level_too_low',
                'required_building_level' => $unit['required_building_level'],
                'current_building_level' => $building['level']
            ];
        }

        // Sprawdź, czy wymagane badania są na odpowiednim poziomie
        if (!empty($unit['required_tech']) && $unit['required_tech_level'] > 0) {
            $stmt = $this->conn->prepare("
                SELECT level
                FROM village_researches
                WHERE village_id = ? AND research_type_id = (
                    SELECT id FROM research_types WHERE internal_name = ?
                )
            ");

            $stmt->bind_param("is", $village_id, $unit['required_tech']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'can_recruit' => false,
                    'reason' => 'tech_level_too_low',
                    'required_tech' => $unit['required_tech'],
                    'required_tech_level' => $unit['required_tech_level'],
                    'current_tech_level' => 0
                ];
            }

            $research = $result->fetch_assoc();
            $stmt->close();

            if ($research['level'] < $unit['required_tech_level']) {
                return [
                    'can_recruit' => false,
                    'reason' => 'tech_level_too_low',
                    'required_tech' => $unit['required_tech'],
                    'required_tech_level' => $unit['required_tech_level'],
                    'current_tech_level' => $research['level']
                ];
            }
        }

        return ['can_recruit' => true];
    }

    /**
     * Sprawdź, czy gracz ma wystarczające zasoby na rekrutację jednostek
     *
     * @param int $unit_type_id ID typu jednostki
     * @param int $count Ilość jednostek
     * @param array $resources Zasoby gracza (wood, clay, iron)
     * @return array Status i koszty
     */
    public function checkResourcesForRecruitment($unit_type_id, $count, $resources)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return [
            'can_afford' => false,
                'reason' => 'unit_not_found'
            ];
        }

        $unit = $this->unit_types_cache[$unit_type_id];

        $wood_cost = $unit['cost_wood'] * $count;
        $clay_cost = $unit['cost_clay'] * $count;
        $iron_cost = $unit['cost_iron'] * $count;

        $can_afford = (
            $resources['wood'] >= $wood_cost &&
            $resources['clay'] >= $clay_cost &&
            $resources['iron'] >= $iron_cost
        );

        return [
            'can_afford' => $can_afford,
            'total_costs' => [
                'wood' => $wood_cost,
                'clay' => $clay_cost,
                'iron' => $iron_cost
            ],
            'missing' => [
                'wood' => max(0, $wood_cost - $resources['wood']),
                'clay' => max(0, $clay_cost - $resources['clay']),
                'iron' => max(0, $iron_cost - $resources['iron'])
            ]
        ];
    }

    /**
     * Rekrutuj jednostki - dodaj do kolejki rekrutacji
     *
     * @param int $village_id ID wioski
     * @param int $unit_type_id ID typu jednostki
     * @param int $count Ilość jednostek do rekrutacji
     * @param int $building_level Poziom budynku
     * @return array Status operacji
     */
    public function recruitUnits($village_id, $unit_type_id, $count, $building_level)
    {
        if (!isset($this->unit_types_cache[$unit_type_id])) {
            return [
            'success' => false,
                'error' => 'Jednostka nie istnieje.'
            ];
        }

        $unit = $this->unit_types_cache[$unit_type_id];
        $building_type = $unit['building_type'];

        // Oblicz czas treningu
        $time_per_unit = $this->calculateRecruitmentTime($unit_type_id, $building_level);
        $total_time = $time_per_unit * $count;

        // Pobierz aktualny czas
        $current_time = time();
        $finish_time = $current_time + $total_time;

        // Dodaj do kolejki rekrutacji
        $stmt = $this->conn->prepare("
            INSERT INTO unit_queue
            (village_id, unit_type_id, count, count_finished, started_at, finish_at, building_type)
            VALUES (?, ?, ?, 0, ?, ?, ?)
        ");

        $stmt->bind_param("iiiiss", $village_id, $unit_type_id, $count, $current_time, $finish_time, $building_type);

        if (!$stmt->execute()) {
            return [
                'success' => false,
                'error' => 'Błąd bazy danych podczas dodawania do kolejki rekrutacji.'
            ];
        }

        $queue_id = $stmt->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'message' => "Rozpoczęto rekrutację $count jednostek. Zakończenie o " . date('H:i:s d.m.Y', $finish_time),
            'queue_id' => $queue_id,
            'finish_time' => $finish_time
        ];
    }

    /**
     * Aktualizuj kolejki rekrutacji - sprawdź zakończone jednostki
     */
    public function updateRecruitmentQueues()
    {
        $current_time = time();

        // Pobierz wszystkie aktywne kolejki rekrutacji
        $stmt = $this->conn->prepare("
            SELECT id, village_id, unit_type_id, count, count_finished, finish_at
            FROM unit_queue
            WHERE count_finished < count
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        while ($queue = $result->fetch_assoc()) {
            $queue_id = $queue['id'];
            $village_id = $queue['village_id'];
            $unit_type_id = $queue['unit_type_id'];
            $total_units = $queue['count'];
            $finished_units = $queue['count_finished'];
            $finish_time = $queue['finish_at'];

            // Sprawdź, czy kolejka jest zakończona
            if ($current_time >= $finish_time) {
                // Zaktualizuj ilość ukończonych jednostek
                $remaining_units = $total_units - $finished_units;
                $new_finished = $total_units;

                // Zaktualizuj kolejkę
                $update_stmt = $this->conn->prepare("
                    UPDATE unit_queue
                    SET count_finished = ?
                    WHERE id = ?
                ");

                $update_stmt->bind_param("ii", $new_finished, $queue_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Dodaj ukończone jednostki do wioski
                $this->addUnitsToVillage($village_id, $unit_type_id, $remaining_units);
            }
        }

        $stmt->close();

        // Usuń zakończone kolejki
        $this->cleanupFinishedQueues();
    }

    /**
     * Dodaj jednostki do wioski po zakończeniu rekrutacji
     *
     * @param int $village_id ID wioski
     * @param int $unit_type_id ID typu jednostki
     * @param int $count Ilość jednostek do dodania
     */
    private function addUnitsToVillage($village_id, $unit_type_id, $count)
    {
        // Sprawdź, czy jednostki danego typu już istnieją w wiosce
        $stmt = $this->conn->prepare("
            SELECT id, count
            FROM village_units
            WHERE village_id = ? AND unit_type_id = ?
        ");

        $stmt->bind_param("ii", $village_id, $unit_type_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Zaktualizuj istniejące jednostki
            $unit = $result->fetch_assoc();
            $new_count = $unit['count'] + $count;

            $update_stmt = $this->conn->prepare("
                UPDATE village_units
                SET count = ?
                WHERE id = ?
            ");

            $update_stmt->bind_param("ii", $new_count, $unit['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Dodaj nowe jednostki
            $insert_stmt = $this->conn->prepare("
                INSERT INTO village_units
                (village_id, unit_type_id, count)
                VALUES (?, ?, ?)
            ");

            $insert_stmt->bind_param("iii", $village_id, $unit_type_id, $count);
            $insert_stmt->execute();
            $insert_stmt->close();
        }

        $stmt->close();
    }

    /**
     * Usuń zakończone kolejki rekrutacji
     */
    private function cleanupFinishedQueues()
    {
        $this->conn->query("
            DELETE FROM unit_queue
            WHERE count_finished >= count
        ");
    }

    /**
     * Pobierz aktualne jednostki w wiosce
     *
     * @param int $village_id ID wioski
     * @return array Tablica jednostek
     */
    public function getVillageUnits($village_id)
    {
        $units = [];

        $stmt = $this->conn->prepare("
            SELECT vu.unit_type_id, vu.count, ut.internal_name, ut.name_pl,
                   ut.attack, ut.defense, ut.speed, ut.population
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ?
        ");

        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($unit = $result->fetch_assoc()) {
            $units[$unit['unit_type_id']] = $unit;
        }

        $stmt->close();
        return $units;
    }

    /**
     * Pobierz aktualne kolejki rekrutacji dla wioski
     *
     * @param int $village_id ID wioski
     * @param string $building_type Opcjonalnie typ budynku
     * @return array Tablica kolejek rekrutacji
     */
    public function getRecruitmentQueues($village_id, $building_type = null)
    {
        $queues = [];

        $sql = "
            SELECT uq.id, uq.unit_type_id, uq.count, uq.count_finished,
                   uq.started_at, uq.finish_at, uq.building_type,
                   ut.name_pl, ut.internal_name
            FROM unit_queue uq
            JOIN unit_types ut ON uq.unit_type_id = ut.id
            WHERE uq.village_id = ?
        ";

        if ($building_type) {
            $sql .= " AND uq.building_type = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $village_id, $building_type);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $village_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($queue = $result->fetch_assoc()) {
            $queues[] = $queue;
        }

        $stmt->close();
        return $queues;
    }

    /**
     * Przetwórz kolejkę rekrutacji dla konkretnej wioski
     *
     * @param int $village_id ID wioski
     * @return array Informacje o zakończonych i zaktualizowanych kolejkach
     */
    public function processRecruitmentQueue($village_id)
    {
        $current_time = time();
        $result = [
            'completed_queues' => [],
            'updated_queues' => []
        ];

        // Pobierz wszystkie aktywne kolejki rekrutacji dla wioski
        $stmt = $this->conn->prepare("
            SELECT id, unit_type_id, count, count_finished, finish_at, building_type
            FROM unit_queue
            WHERE village_id = ? AND count_finished < count
        ");

        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $queues = $stmt->get_result();

        while ($queue = $queues->fetch_assoc()) {
            $queue_id = $queue['id'];
            $unit_type_id = $queue['unit_type_id'];
            $total_units = $queue['count'];
            $finished_units = $queue['count_finished'];
            $finish_time = $queue['finish_at'];

            // Pobierz nazwę jednostki
            $unit_name = "";
            if (isset($this->unit_types_cache[$unit_type_id])) {
                $unit_name = $this->unit_types_cache[$unit_type_id]['name_pl'];
            }

            // Oblicz liczbę jednostek, które powinny być ukończone teraz
            $time_per_unit = ($finish_time - $queue['count_finished']) / ($total_units - $finished_units);
            $elapsed_time = $current_time - $finish_time + ($total_units - $finished_units) * $time_per_unit;
            $units_should_be_finished = min($total_units, $finished_units + floor($elapsed_time / $time_per_unit));

            if ($units_should_be_finished > $finished_units) {
                // Nowe jednostki do dodania
                $new_units = $units_should_be_finished - $finished_units;

                // Dodaj jednostki do wioski
                $this->addUnitsToVillage($village_id, $unit_type_id, $new_units);

                // Zaktualizuj stan kolejki
                $update_stmt = $this->conn->prepare("
                    UPDATE unit_queue
                    SET count_finished = ?
                    WHERE id = ?
                ");

                $update_stmt->bind_param("ii", $units_should_be_finished, $queue_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Jeśli wszystkie jednostki są ukończone
                if ($units_should_be_finished >= $total_units) {
                    $result['completed_queues'][] = [
                        'queue_id' => $queue_id,
                        'unit_type_id' => $unit_type_id,
                        'unit_name' => $unit_name,
                        'count' => $total_units
                    ];
                } else {
                    $result['updated_queues'][] = [
                        'queue_id' => $queue_id,
                        'unit_type_id' => $unit_type_id,
                        'unit_name' => $unit_name,
                        'units_finished' => $new_units,
                        'total_units' => $total_units,
                        'remaining_units' => $total_units - $units_should_be_finished
                    ];
                }
            }
        }

        $stmt->close();

        // Usuń zakończone kolejki
        $this->cleanupFinishedQueues();

        return $result;
    }

    /**
     * Anuluj rekrutację jednostek z kolejki
     *
     * @param int $queue_id ID kolejki rekrutacji do anulowania
     * @param int $user_id ID użytkownika
     * @return array Status operacji
     */
    public function cancelRecruitment($queue_id, $user_id)
    {
        // Pobierz informacje o kolejce i sprawdź, czy należy do użytkownika
        $stmt = $this->conn->prepare("
            SELECT uq.id, uq.village_id, uq.unit_type_id, uq.count, uq.count_finished, ut.name_pl
            FROM unit_queue uq
            JOIN unit_types ut ON uq.unit_type_id = ut.id
            JOIN villages v ON uq.village_id = v.id
            WHERE uq.id = ? AND v.user_id = ?
        ");

        $stmt->bind_param("ii", $queue_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Kolejka rekrutacji nie istnieje lub nie masz do niej dostępu.'
            ];
        }

        $queue = $result->fetch_assoc();
        $stmt->close();

        // Dodaj już wytrenowane jednostki do wioski
        if ($queue['count_finished'] > 0) {
            $this->addUnitsToVillage($queue['village_id'], $queue['unit_type_id'], $queue['count_finished']);
        }

        // Usuń kolejkę rekrutacji
        $stmt_delete = $this->conn->prepare("DELETE FROM unit_queue WHERE id = ?");
        $stmt_delete->bind_param("i", $queue_id);
        $success = $stmt_delete->execute();
        $stmt_delete->close();

        if (!$success) {
            return [
                'success' => false,
                'error' => 'Wystąpił błąd podczas anulowania rekrutacji.'
            ];
        }

        $message = '';
        if ($queue['count_finished'] > 0) {
            $message = "Anulowano rekrutację jednostek {$queue['name_pl']}. Dodano {$queue['count_finished']} ukończonych jednostek do wioski.";
        } else {
            $message = "Anulowano rekrutację jednostek {$queue['name_pl']}.";
        }

        return [
            'success' => true,
            'message' => $message,
            'village_id' => $queue['village_id'],
            'unit_type_id' => $queue['unit_type_id'],
            'count_finished' => $queue['count_finished']
        ];
    }
}

?> 