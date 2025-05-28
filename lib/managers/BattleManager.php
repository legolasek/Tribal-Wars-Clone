<?php
/**
 * Klasa BattleManager - zarządzanie walkami pomiędzy wioskami
 */
class BattleManager
{
    private $conn;
    private $villageManager; // Dodajemy właściwość dla VillageManager
    
    /**
     * Konstruktor
     * 
     * @param mysqli $conn Połączenie z bazą danych
     * @param VillageManager $villageManager Instancja VillageManager
     */
    public function __construct($conn, VillageManager $villageManager)
    {
        $this->conn = $conn;
        $this->villageManager = $villageManager; // Przypisujemy instancję
    }
    
    /**
     * Wysyła atak z jednej wioski na drugą
     * 
     * @param int $source_village_id ID wioski źródłowej (atakującej)
     * @param int $target_village_id ID wioski docelowej (atakowanej)
     * @param array $units_sent Tablica z ID typów jednostek i ich liczbą
     * @param string $attack_type Typ ataku ('attack', 'raid', 'support')
     * @return array Status operacji
     */
    public function sendAttack($source_village_id, $target_village_id, $units_sent, $attack_type = 'attack')
    {
        // Sprawdź, czy wioski istnieją
        $stmt_check_villages = $this->conn->prepare("
            SELECT 
                v1.id as source_id, v1.name as source_name, v1.x_coord as source_x, v1.y_coord as source_y, v1.user_id as source_user_id,
                v2.id as target_id, v2.name as target_name, v2.x_coord as target_x, v2.y_coord as target_y, v2.user_id as target_user_id
            FROM villages v1, villages v2
            WHERE v1.id = ? AND v2.id = ?
        ");
        $stmt_check_villages->bind_param("ii", $source_village_id, $target_village_id);
        $stmt_check_villages->execute();
        $result = $stmt_check_villages->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Jedna lub obie wioski nie istnieją.'
            ];
        }
        
        $villages = $result->fetch_assoc();
        $stmt_check_villages->close();
        
        // Sprawdź, czy wioski nie należą do tego samego gracza (nie można atakować własnych wiosek)
        if ($villages['source_user_id'] === $villages['target_user_id'] && $attack_type !== 'support') {
            return [
                'success' => false,
                'error' => 'Nie możesz atakować własnych wiosek.'
            ];
        }
        
        // Sprawdź, czy gracz ma wystarczającą liczbę jednostek
        $stmt_check_units = $this->conn->prepare("
            SELECT unit_type_id, count 
            FROM village_units 
            WHERE village_id = ?
        ");
        $stmt_check_units->bind_param("i", $source_village_id);
        $stmt_check_units->execute();
        $units_result = $stmt_check_units->get_result();
        
        $available_units = [];
        while ($unit = $units_result->fetch_assoc()) {
            $available_units[$unit['unit_type_id']] = $unit['count'];
        }
        $stmt_check_units->close();
        
        // Sprawdź, czy gracz próbuje wysłać więcej jednostek niż posiada
        foreach ($units_sent as $unit_type_id => $count) {
            if (!isset($available_units[$unit_type_id]) || $available_units[$unit_type_id] < $count) {
                return [
                    'success' => false,
                    'error' => 'Nie masz wystarczającej liczby jednostek do przeprowadzenia tego ataku.'
                ];
            }
        }
        
        // Sprawdź, czy gracz wysyła jakiekolwiek jednostki
        $total_units = 0;
        foreach ($units_sent as $count) {
            $total_units += $count;
        }
        
        if ($total_units === 0) {
            return [
                'success' => false,
                'error' => 'Musisz wysłać co najmniej jedną jednostkę.'
            ];
        }
        
        // Oblicz odległość między wioskami i czas podróży
        $distance = $this->calculateDistance(
            $villages['source_x'], $villages['source_y'],
            $villages['target_x'], $villages['target_y']
        );
        
        // Znajdź najwolniejszą jednostkę
        $stmt_get_speed = $this->conn->prepare("
            SELECT unit_type_id, speed 
            FROM unit_types
            WHERE id IN (" . implode(',', array_keys($units_sent)) . ")
            ORDER BY speed ASC
            LIMIT 1
        ");
        $stmt_get_speed->execute();
        $speed_result = $stmt_get_speed->get_result();
        $slowest_unit = $speed_result->fetch_assoc();
        $stmt_get_speed->close();
        
        if (!$slowest_unit) {
            return [
                'success' => false,
                'error' => 'Nie można znaleźć informacji o jednostkach.'
            ];
        }
        
        // Oblicz czas podróży w sekundach (im wyższa wartość speed, tym wolniejsza jednostka)
        $travel_time = ceil($distance * $slowest_unit['speed'] * 60); // w sekundach
        $start_time = time();
        $arrival_time = $start_time + $travel_time;
        
        // Rozpocznij transakcję
        $this->conn->begin_transaction();
        
        try {
            // Odejmij jednostki z wioski źródłowej
            foreach ($units_sent as $unit_type_id => $count) {
                $stmt_update_units = $this->conn->prepare("
                    UPDATE village_units 
                    SET count = count - ? 
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_update_units->bind_param("iii", $count, $source_village_id, $unit_type_id);
                $stmt_update_units->execute();
                $stmt_update_units->close();
            }
            
            // Dodaj atak do tabeli attacks
            $stmt_add_attack = $this->conn->prepare("
                INSERT INTO attacks (
                    source_village_id, target_village_id, 
                    attack_type, start_time, arrival_time, 
                    is_completed, is_canceled
                ) VALUES (?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), 0, 0)
            ");
            $stmt_add_attack->bind_param(
                "iisis",
                $source_village_id, $target_village_id,
                $attack_type, $start_time, $arrival_time
            );
            $stmt_add_attack->execute();
            $attack_id = $stmt_add_attack->insert_id;
            $stmt_add_attack->close();
            
            // Dodaj jednostki do tabeli attack_units
            foreach ($units_sent as $unit_type_id => $count) {
                $stmt_add_units = $this->conn->prepare("
                    INSERT INTO attack_units (
                        attack_id, unit_type_id, count
                    ) VALUES (?, ?, ?)
                ");
                $stmt_add_units->bind_param("iii", $attack_id, $unit_type_id, $count);
                $stmt_add_units->execute();
                $stmt_add_units->close();
            }
            
            // Zatwierdź transakcję
            $this->conn->commit();
            
            // Przygotuj odpowiedź
            $arrival_date = date('Y-m-d H:i:s', $arrival_time);
            
            return [
                'success' => true,
                'message' => "Pomyślnie wysłano atak. Dotarcie do celu: $arrival_date",
                'attack_id' => $attack_id,
                'source_village_id' => $source_village_id,
                'target_village_id' => $target_village_id,
                'attack_type' => $attack_type,
                'units_sent' => $units_sent,
                'distance' => $distance,
                'travel_time' => $travel_time,
                'arrival_time' => $arrival_time,
                'arrival_date' => $arrival_date
            ];
        } catch (Exception $e) {
            // Wycofaj transakcję w przypadku błędu
            $this->conn->rollback();
            
            return [
                'success' => false,
                'error' => 'Wystąpił błąd podczas wysyłania ataku: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Anuluj atak, jeśli jeszcze nie dotarł do celu
     * 
     * @param int $attack_id ID ataku
     * @param int $user_id ID użytkownika (dla sprawdzenia uprawnień)
     * @return array Status operacji
     */
    public function cancelAttack($attack_id, $user_id)
    {
        // Sprawdź, czy atak istnieje i należy do użytkownika
        $stmt_check_attack = $this->conn->prepare("
            SELECT a.id, a.source_village_id, a.target_village_id, a.attack_type, 
                   a.start_time, a.arrival_time, a.is_completed, a.is_canceled
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            WHERE a.id = ? AND v.user_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
        ");
        $stmt_check_attack->bind_param("ii", $attack_id, $user_id);
        $stmt_check_attack->execute();
        $result = $stmt_check_attack->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Atak nie istnieje, został już zakończony, anulowany lub nie masz do niego dostępu.'
            ];
        }
        
        $attack = $result->fetch_assoc();
        $stmt_check_attack->close();
        
        // Sprawdź, czy atak już dotarł do celu
        $current_time = time();
        $arrival_time = strtotime($attack['arrival_time']);
        
        if ($current_time >= $arrival_time) {
            return [
                'success' => false,
                'error' => 'Nie można anulować ataku, który już dotarł do celu.'
            ];
        }
        
        // Rozpocznij transakcję
        $this->conn->begin_transaction();
        
        try {
            // Oznacz atak jako anulowany
            $stmt_cancel_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_canceled = 1 
                WHERE id = ?
            ");
            $stmt_cancel_attack->bind_param("i", $attack_id);
            $stmt_cancel_attack->execute();
            $stmt_cancel_attack->close();
            
            // Pobierz jednostki z ataku
            $stmt_get_units = $this->conn->prepare("
                SELECT unit_type_id, count 
                FROM attack_units 
                WHERE attack_id = ?
            ");
            $stmt_get_units->bind_param("i", $attack_id);
            $stmt_get_units->execute();
            $units_result = $stmt_get_units->get_result();
            
            $units_to_return = [];
            while ($unit = $units_result->fetch_assoc()) {
                $units_to_return[$unit['unit_type_id']] = $unit['count'];
            }
            $stmt_get_units->close();
            
            // Zwróć jednostki do wioski źródłowej
            foreach ($units_to_return as $unit_type_id => $count) {
                $stmt_check_existing = $this->conn->prepare("
                    SELECT id, count 
                    FROM village_units 
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_check_existing->bind_param("ii", $attack['source_village_id'], $unit_type_id);
                $stmt_check_existing->execute();
                $existing_result = $stmt_check_existing->get_result();
                
                if ($existing_result->num_rows > 0) {
                    // Aktualizuj istniejące jednostki
                    $existing = $existing_result->fetch_assoc();
                    $new_count = $existing['count'] + $count;
                    
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units 
                        SET count = ? 
                        WHERE id = ?
                    ");
                    $stmt_update->bind_param("ii", $new_count, $existing['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    // Dodaj nowe jednostki
                    $stmt_insert = $this->conn->prepare("
                        INSERT INTO village_units (
                            village_id, unit_type_id, count
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt_insert->bind_param("iii", $attack['source_village_id'], $unit_type_id, $count);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                
                $stmt_check_existing->close();
            }
            
            // Zatwierdź transakcję
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Atak został anulowany, a jednostki powróciły do wioski.',
                'attack_id' => $attack_id,
                'returned_units' => $units_to_return
            ];
        } catch (Exception $e) {
            // Wycofaj transakcję w przypadku błędu
            $this->conn->rollback();
            
            return [
                'success' => false,
                'error' => 'Wystąpił błąd podczas anulowania ataku: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Przetwarza zakończone ataki, generując raporty i komunikaty dla użytkownika.
     * @param int $user_id ID użytkownika, dla którego przetwarzamy ataki.
     * @return array Tablica komunikatów do wyświetlenia dla użytkownika.
     */
    public function processCompletedAttacks(int $user_id): array
    {
        $messages = [];
        $current_time = date('Y-m-d H:i:s');
        
        // Pobierz ID wiosek należących do użytkownika
        $user_village_ids = $this->villageManager->getUserVillageIds($user_id);
        
        if (empty($user_village_ids)) {
             return []; // Użytkownik nie ma wiosek, brak ataków do przetworzenia
        }

        // Pobierz wszystkie niezakończone i nieanulowane ataki, które powinny już dotrzeć do celu,
        // a dotyczą wiosek użytkownika (jako atakujący lub obrońca)
        // Używamy FIND_IN_SET, ponieważ nie możemy bezpośrednio bindować tablicy do IN z prepare()
        $village_ids_string = implode(',', $user_village_ids);

        $stmt_get_attacks = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type
            FROM attacks
            WHERE is_completed = 0 AND is_canceled = 0 AND arrival_time <= ?
              AND (FIND_IN_SET(source_village_id, ?) OR FIND_IN_SET(target_village_id, ?))
            ORDER BY arrival_time ASC
        ");
        
         if ($stmt_get_attacks === false) {
             error_log("Prepare failed for getCompletedAttacks (BattleManager): " . $this->conn->error);
             return ['<p class="error-message">Wystąpił błąd podczas pobierania zakończonych ataków.</p>'];
         }

        $stmt_get_attacks->bind_param("sss", $current_time, $village_ids_string, $village_ids_string);
        $stmt_get_attacks->execute();
        $attacks_result = $stmt_get_attacks->get_result();
        
        while ($attack = $attacks_result->fetch_assoc()) {
            // Przetwórz pojedynczą bitwę - ta metoda generuje raport i aktualizuje DB
            $battle_result = $this->processBattle($attack['id']);

            if ($battle_result && $battle_result['success']) {
                // Pobierz szczegóły ataku i wiosek dla komunikatu
                 $stmt_details = $this->conn->prepare("
                    SELECT
                        a.id, a.source_village_id, a.target_village_id, a.attack_type,
                        sv.name as source_name, tv.name as target_name
                    FROM attacks a
                    JOIN villages sv ON a.source_village_id = sv.id
                    JOIN villages tv ON a.target_village_id = tv.id
                    WHERE a.id = ? LIMIT 1
                 ");
                 $stmt_details->bind_param("i", $attack['id']);
                 $stmt_details->execute();
                 $attack_details = $stmt_details->get_result()->fetch_assoc();
                 $stmt_details->close();

                 if ($attack_details) {
                     // Pobierz raport bitwy, aby uzyskać zwycięzcę i łupy (jeśli były)
                     // processBattle tworzy raport, więc pobierzemy go zaraz po.
                     $report = $this->getBattleReportForAttack($attack['id']); // Potrzebna metoda

                     if ($report) {
                         $source_name = htmlspecialchars($attack_details['source_name']);
                         $target_name = htmlspecialchars($attack_details['target_name']);
                         $winner = $report['winner']; // 'attacker' lub 'defender'
                         $loot = json_decode($report['details_json'], true)['loot'] ?? ['wood' => 0, 'clay' => 0, 'iron' => 0];

                         // Komunikat dla atakującego (jeśli wioska źródłowa należy do użytkownika)
                         if (in_array($attack['source_village_id'], $user_village_ids)) {
                             if ($winner === 'attacker') {
                                 $messages[] = "<p class='success-message'>Twój atak z wioski <b>{$source_name}</b> na <b>{$target_name}</b> zakończył się zwycięstwem! Złupiono: Drewno: {$loot['wood']}, Glina: {$loot['clay']}, Żelazo: {$loot['iron']}.</p>";
                             } else {
                                 $messages[] = "<p class='error-message'>Twój atak z wioski <b>{$source_name}</b> na <b>{$target_name}</b> zakończył się porażką.</p>";
                             }
                         }

                         // Komunikat dla obrońcy (jeśli wioska docelowa należy do użytkownika)
                         if (in_array($attack['target_village_id'], $user_village_ids)) {
                             if ($winner === 'defender') {
                                 $messages[] = "<p class='success-message'>Twoja wioska <b>{$target_name}</b> obroniła się przed atakiem z wioski <b>{$source_name}</b>.</p>";
                             } else {
                                 $messages[] = "<p class='error-message'>Twoja wioska <b>{$target_name}</b> została pokonana w ataku z wioski <b>{$source_name}</b>. Stracono surowce.</p>";
                             }
                         }

                         // Można dodać link do pełnego raportu bitwy tutaj, jeśli taki system istnieje

                     } else {
                          error_log("Błąd: Nie znaleziono raportu bitwy dla zakończonego ataku ID: " . $attack['id']);
                          // Można dodać ogólny komunikat błędu dla użytkownika
                          $messages[] = "<p class='error-message'>Wystąpił błąd podczas generowania raportu bitwy dla ataku ID: " . $attack['id'] . ".</p>";
                     }
                 } else {
                     error_log("Błąd: Nie znaleziono szczegółów ataku ID: " . $attack['id'] . " podczas generowania komunikatów.");
                     $messages[] = "<p class='error-message'>Wystąpił błąd podczas pobierania szczegółów ataku ID: " . $attack['id'] . ".</p>";
                 }

            } else {
                 error_log("Błąd przetwarzania bitwy dla ataku ID: " . $attack['id'] . ". Wynik: " . json_encode($battle_result));
                 $messages[] = "<p class='error-message'>Wystąpił błąd podczas przetwarzania bitwy dla ataku ID: " . $attack['id'] . ".</p>";
            }
        }

        $attacks_result->free(); // Zwolnij pamięć
        $stmt_get_attacks->close();

        return $messages; // Zwróć zebrane komunikaty
    }
    
    /**
     * Pobiera raport bitwy na podstawie ID ataku.
     * Potrzebne do generowania komunikatów po bitwie.
     * @param int $attack_id ID ataku
     * @return array|null Dane raportu bitwy lub null jeśli brak.
     */
    public function getBattleReportForAttack(int $attack_id): ?array
    {
         $stmt = $this->conn->prepare("
            SELECT id, winner, details_json
            FROM battle_reports
            WHERE attack_id = ?
            LIMIT 1
         ");
         if ($stmt === false) {
              error_log("Prepare failed for getBattleReportForAttack: " . $this->conn->error);
              return null;
         }
         $stmt->bind_param("i", $attack_id);
         $stmt->execute();
         $result = $stmt->get_result();
         $report = $result->fetch_assoc();
         $stmt->close();
         return $report;
    }

    /**
     * Przetwarza pojedynczą bitwę - oblicza straty, łupy, aktualizuje DB, tworzy raport.
     * @param int $attack_id ID ataku do przetworzenia.
     * @return array Wynik przetwarzania bitwy (success/error).
     */
    private function processBattle(int $attack_id): array
    {
        // Pobierz szczegóły ataku
        $stmt_get_attack = $this->conn->prepare("
            SELECT id, source_village_id, target_village_id, attack_type
            FROM attacks
            WHERE id = ?
        ");
        $stmt_get_attack->bind_param("i", $attack_id);
        $stmt_get_attack->execute();
        $attack = $stmt_get_attack->get_result()->fetch_assoc();
        $stmt_get_attack->close();
        if (!$attack) {
            return [ 'success' => false, 'error' => 'Atak nie istnieje.' ];
        }
        // Pobierz jednostki atakujące
        $stmt_get_attack_units = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.attack, ut.defense, ut.name_pl, ut.capacity
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt_get_attack_units->bind_param("i", $attack_id);
        $stmt_get_attack_units->execute();
        $attack_units_result = $stmt_get_attack_units->get_result();
        $attacking_units = [];
        $attack_capacity = 0;
        while ($unit = $attack_units_result->fetch_assoc()) {
            $attacking_units[$unit['unit_type_id']] = $unit;
            $attack_capacity += $unit['capacity'] * $unit['count'];
        }
        $stmt_get_attack_units->close();
        // Pobierz jednostki obronne
        $stmt_get_defense_units = $this->conn->prepare("
            SELECT vu.unit_type_id, vu.count, ut.attack, ut.defense, ut.name_pl
            FROM village_units vu
            JOIN unit_types ut ON vu.unit_type_id = ut.id
            WHERE vu.village_id = ?
        ");
        $stmt_get_defense_units->bind_param("i", $attack['target_village_id']);
        $stmt_get_defense_units->execute();
        $defense_units_result = $stmt_get_defense_units->get_result();
        $defending_units = [];
        while ($unit = $defense_units_result->fetch_assoc()) {
            $defending_units[$unit['unit_type_id']] = $unit;
        }
        $stmt_get_defense_units->close();
        // --- LOSOWOŚĆ: ±10% ---
        $attack_random = mt_rand(90, 110) / 100;
        $defense_random = mt_rand(90, 110) / 100;
        // --- MORALE: prosty przelicznik (np. atakujący z mniejszą liczbą punktów ma bonus) ---
        $morale = 1.0;
        // Można pobrać punkty graczy i wyliczyć morale, na razie uproszczone:
        // $morale = min(1.5, max(0.5, $attacker_points / max($defender_points,1)));
        // --- SUMA SIŁ ---
        $total_attack_strength = 0;
        foreach ($attacking_units as $unit) {
            $total_attack_strength += $unit['attack'] * $unit['count'];
        }
        $total_defense_strength = 0;
        foreach ($defending_units as $unit) {
            $total_defense_strength += $unit['defense'] * $unit['count'];
        }
        $total_attack_strength = round($total_attack_strength * $attack_random * $morale);
        $total_defense_strength = round($total_defense_strength * $defense_random);
        // --- STRATY ---
        $attacker_win = $total_attack_strength > $total_defense_strength;
        $attacker_loss_factor = $attacker_win ? 0.3 : 0.7;
        $defender_loss_factor = $attacker_win ? 0.7 : 0.3;
        $attacker_losses = [];
        $remaining_attacking_units = [];
        foreach ($attacking_units as $unit_type_id => $unit) {
            $loss_count = round($unit['count'] * $attacker_loss_factor);
            $remaining_count = $unit['count'] - $loss_count;
            $attacker_losses[$unit_type_id] = [
                'unit_name' => $unit['name_pl'],
                'initial_count' => $unit['count'],
                'lost_count' => $loss_count,
                'remaining_count' => $remaining_count
            ];
            if ($remaining_count > 0) {
                $remaining_attacking_units[$unit_type_id] = $remaining_count;
            }
        }
        $defender_losses = [];
        $remaining_defending_units = [];
        foreach ($defending_units as $unit_type_id => $unit) {
            $loss_count = round($unit['count'] * $defender_loss_factor);
            $remaining_count = $unit['count'] - $loss_count;
            $defender_losses[$unit_type_id] = [
                'unit_name' => $unit['name_pl'],
                'initial_count' => $unit['count'],
                'lost_count' => $loss_count,
                'remaining_count' => $remaining_count
            ];
            if ($remaining_count > 0) {
                $remaining_defending_units[$unit_type_id] = $remaining_count;
            }
        }
        // --- ŁUPY ---
        $loot = [ 'wood' => 0, 'clay' => 0, 'iron' => 0 ];
        if ($attacker_win && $attack_capacity > 0) {
            // Pobierz surowce z wioski
            $stmt_res = $this->conn->prepare("SELECT wood, clay, iron FROM villages WHERE id = ?");
            $stmt_res->bind_param("i", $attack['target_village_id']);
            $stmt_res->execute();
            $res = $stmt_res->get_result()->fetch_assoc();
            $stmt_res->close();
            $total_loot = min($attack_capacity, $res['wood'] + $res['clay'] + $res['iron']);
            // Proporcjonalnie rozdziel łup
            $sum = $res['wood'] + $res['clay'] + $res['iron'];
            if ($sum > 0) {
                $loot['wood'] = floor($total_loot * ($res['wood'] / $sum));
                $loot['clay'] = floor($total_loot * ($res['clay'] / $sum));
                $loot['iron'] = $total_loot - $loot['wood'] - $loot['clay'];
            }
            // Odejmij surowce z wioski
            $stmt_update = $this->conn->prepare("UPDATE villages SET wood = wood - ?, clay = clay - ?, iron = iron - ? WHERE id = ?");
            $stmt_update->bind_param("iiii", $loot['wood'], $loot['clay'], $loot['iron'], $attack['target_village_id']);
            $stmt_update->execute();
            $stmt_update->close();
        }
        // --- TRANSAKCJA ---
        $this->conn->begin_transaction();
        try {
            // Oznacz atak jako zakończony
            $stmt_complete_attack = $this->conn->prepare("
                UPDATE attacks 
                SET is_completed = 1 
                WHERE id = ?
            ");
            $stmt_complete_attack->bind_param("i", $attack_id);
            $stmt_complete_attack->execute();
            $stmt_complete_attack->close();
            // Zaktualizuj jednostki obronne w wiosce
            foreach ($defending_units as $unit_type_id => $unit) {
                $new_count = isset($remaining_defending_units[$unit_type_id]) ? $remaining_defending_units[$unit_type_id] : 0;
                if ($new_count > 0) {
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units 
                        SET count = ? 
                        WHERE village_id = ? AND unit_type_id = ?
                    ");
                    $stmt_update->bind_param("iii", $new_count, $attack['target_village_id'], $unit_type_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    $stmt_delete = $this->conn->prepare("
                        DELETE FROM village_units 
                        WHERE village_id = ? AND unit_type_id = ?
                    ");
                    $stmt_delete->bind_param("ii", $attack['target_village_id'], $unit_type_id);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                }
            }
            // Zwróć pozostałe jednostki atakujące do wioski źródłowej
            foreach ($remaining_attacking_units as $unit_type_id => $count) {
                $stmt_check_existing = $this->conn->prepare("
                    SELECT id, count 
                    FROM village_units 
                    WHERE village_id = ? AND unit_type_id = ?
                ");
                $stmt_check_existing->bind_param("ii", $attack['source_village_id'], $unit_type_id);
                $stmt_check_existing->execute();
                $existing_result = $stmt_check_existing->get_result();
                if ($existing_result->num_rows > 0) {
                    // Aktualizuj istniejące jednostki
                    $existing = $existing_result->fetch_assoc();
                    $new_count = $existing['count'] + $count;
                    $stmt_update = $this->conn->prepare("
                        UPDATE village_units 
                        SET count = ? 
                        WHERE id = ?
                    ");
                    $stmt_update->bind_param("ii", $new_count, $existing['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    // Dodaj nowe jednostki
                    $stmt_insert = $this->conn->prepare("
                        INSERT INTO village_units (
                            village_id, unit_type_id, count
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt_insert->bind_param("iii", $attack['source_village_id'], $unit_type_id, $count);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_check_existing->close();
            }
            // Dodaj raport z bitwy (z detalami JSON)
            $winner = $attacker_win ? 'attacker' : 'defender';
            $details = [
                'attacker_losses' => $attacker_losses,
                'defender_losses' => $defender_losses,
                'loot' => $loot,
                'attack_random' => $attack_random,
                'defense_random' => $defense_random,
                'morale' => $morale
            ];
            $stmt_add_report = $this->conn->prepare("
                INSERT INTO battle_reports (
                    attack_id, source_village_id, target_village_id, 
                    attack_type, winner, total_attack_strength, 
                    total_defense_strength, details_json, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $details_json = json_encode($details);
            $stmt_add_report->bind_param(
                "iiisssis",
                $attack_id, $attack['source_village_id'], $attack['target_village_id'],
                $attack['attack_type'], $winner, $total_attack_strength,
                $total_defense_strength, $details_json
            );
            $stmt_add_report->execute();
            $stmt_add_report->close();
            $this->conn->commit();
            return [ 'success' => true ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }
    
    /**
     * Oblicza odległość między dwoma punktami na mapie
     * 
     * @param int $x1 Współrzędna X punktu 1
     * @param int $y1 Współrzędna Y punktu 1
     * @param int $x2 Współrzędna X punktu 2
     * @param int $y2 Współrzędna Y punktu 2
     * @return float Odległość między punktami
     */
    private function calculateDistance($x1, $y1, $x2, $y2)
    {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }
    
    /**
     * Pobiera listę przychodzących ataków na wioskę
     * 
     * @param int $village_id ID wioski
     * @return array Lista przychodzących ataków
     */
    public function getIncomingAttacks($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.source_village_id, a.attack_type, a.start_time, a.arrival_time,
                   v.name as source_village_name, v.x_coord as source_x, v.y_coord as source_y,
                   u.username as attacker_name
            FROM attacks a
            JOIN villages v ON a.source_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE a.target_village_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
            ORDER BY a.arrival_time ASC
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $incoming_attacks = [];
        while ($attack = $result->fetch_assoc()) {
            // Oblicz pozostały czas
            $arrival_time = strtotime($attack['arrival_time']);
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            
            $incoming_attacks[] = $attack;
        }
        $stmt->close();
        
        return $incoming_attacks;
    }
    
    /**
     * Pobiera listę wychodzących ataków z wioski
     * 
     * @param int $village_id ID wioski
     * @return array Lista wychodzących ataków
     */
    public function getOutgoingAttacks($village_id)
    {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.target_village_id, a.attack_type, a.start_time, a.arrival_time,
                   v.name as target_village_name, v.x_coord as target_x, v.y_coord as target_y,
                   u.username as defender_name
            FROM attacks a
            JOIN villages v ON a.target_village_id = v.id
            JOIN users u ON v.user_id = u.id
            WHERE a.source_village_id = ? AND a.is_completed = 0 AND a.is_canceled = 0
            ORDER BY a.arrival_time ASC
        ");
        $stmt->bind_param("i", $village_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $outgoing_attacks = [];
        while ($attack = $result->fetch_assoc()) {
            // Oblicz pozostały czas
            $arrival_time = strtotime($attack['arrival_time']);
            $current_time = time();
            $remaining_time = max(0, $arrival_time - $current_time);
            
            $attack['remaining_time'] = $remaining_time;
            $attack['formatted_remaining_time'] = $this->formatTime($remaining_time);
            
            // Dodaj informacje o wysłanych jednostkach
            $attack['units'] = $this->getAttackUnits($attack['id']);
            
            $outgoing_attacks[] = $attack;
        }
        $stmt->close();
        
        return $outgoing_attacks;
    }
    
    /**
     * Pobiera jednostki biorące udział w ataku
     * 
     * @param int $attack_id ID ataku
     * @return array Lista jednostek
     */
    public function getAttackUnits($attack_id)
    {
        $stmt = $this->conn->prepare("
            SELECT au.unit_type_id, au.count, ut.name_pl, ut.internal_name
            FROM attack_units au
            JOIN unit_types ut ON au.unit_type_id = ut.id
            WHERE au.attack_id = ?
        ");
        $stmt->bind_param("i", $attack_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($unit = $result->fetch_assoc()) {
            $units[] = $unit;
        }
        $stmt->close();
        
        return $units;
    }
    
    /**
     * Pobiera raport z bitwy
     * 
     * @param int $report_id ID raportu
     * @param int $user_id ID użytkownika (dla sprawdzenia uprawnień)
     * @return array Raport z bitwy
     */
    public function getBattleReport($report_id, $user_id)
    {
        // Sprawdź, czy raport istnieje i czy użytkownik ma do niego dostęp
        $stmt = $this->conn->prepare("
            SELECT br.id, br.attack_id, br.source_village_id, br.target_village_id, 
                   br.attack_type, br.winner, br.total_attack_strength, 
                   br.total_defense_strength, br.created_at,
                   sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y,
                   tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y,
                   attacker.username as attacker_name, defender.username as defender_name
            FROM battle_reports br
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users attacker ON sv.user_id = attacker.id
            JOIN users defender ON tv.user_id = defender.id
            WHERE br.id = ? AND (sv.user_id = ? OR tv.user_id = ?)
        ");
        $stmt->bind_param("iii", $report_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'error' => 'Raport nie istnieje lub nie masz do niego dostępu.'
            ];
        }
        
        $report = $result->fetch_assoc();
        $stmt->close();
        
        // Pobierz szczegóły jednostek
        $stmt_units = $this->conn->prepare("
            SELECT bru.unit_type_id, bru.side, bru.initial_count, 
                   bru.lost_count, bru.remaining_count,
                   ut.name_pl, ut.internal_name, ut.attack, ut.defense
            FROM battle_report_units bru
            JOIN unit_types ut ON bru.unit_type_id = ut.id
            WHERE bru.battle_report_id = ?
        ");
        $stmt_units->bind_param("i", $report_id);
        $stmt_units->execute();
        $units_result = $stmt_units->get_result();
        
        $attacker_units = [];
        $defender_units = [];
        
        while ($unit = $units_result->fetch_assoc()) {
            if ($unit['side'] === 'attacker') {
                $attacker_units[] = $unit;
            } else {
                $defender_units[] = $unit;
            }
        }
        $stmt_units->close();
        
        $report['attacker_units'] = $attacker_units;
        $report['defender_units'] = $defender_units;
        
        return [
            'success' => true,
            'report' => $report
        ];
    }
    
    /**
     * Formatuje czas w sekundach na czytelny format
     * 
     * @param int $seconds Czas w sekundach
     * @return string Sformatowany czas
     */
    private function formatTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * Pobiera listę raportów bitewnych dla użytkownika z paginacją.
     *
     * @param int $userId ID użytkownika.
     * @param int $limit Liczba raportów na stronę.
     * @param int $offset Offset dla paginacji.
     * @return array Lista raportów bitewnych.
     */
    public function getBattleReportsForUser(int $userId, int $limit, int $offset): array
    {
        $reports = [];
        $stmt = $this->conn->prepare("
            SELECT
                br.report_id, br.attacker_won, br.battle_time as created_at,
                sv.name as source_village_name, sv.x_coord as source_x, sv.y_coord as source_y, sv.user_id as source_user_id,
                tv.name as target_village_name, tv.x_coord as target_x, tv.y_coord as target_y, tv.user_id as target_user_id,
                u_attacker.username as attacker_name, u_defender.username as defender_name,
                r.is_read -- Pobieramy status odczytania z tabeli reports
            FROM battle_reports br
            JOIN villages sv ON br.source_village_id = sv.id
            JOIN villages tv ON br.target_village_id = tv.id
            JOIN users u_attacker ON sv.user_id = u_attacker.id
            JOIN users u_defender ON tv.user_id = u_defender.id
            JOIN reports r ON br.report_id = r.id AND r.user_id = ? -- Łączymy z tabelą reports
            WHERE sv.user_id = ? OR tv.user_id = ?
            ORDER BY br.battle_time DESC
            LIMIT ?, ?
        ");
        // Bind parametry w kolejności: r.user_id, sv.user_id, tv.user_id, limit, offset
        $stmt->bind_param("iiiii", $userId, $userId, $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Określ, czy użytkownik był atakującym czy broniącym
            $row['is_attacker'] = ($row['source_user_id'] == $userId);
            // Formatuj datę (można też zrobić w frontendzie)
            $row['formatted_date'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
            $reports[] = $row;
        }
        $stmt->close();

        return $reports;
    }

    /**
     * Pobiera całkowitą liczbę raportów bitewnych dla użytkownika.
     *
     * @param int $userId ID użytkownika.
     * @return int Całkowita liczba raportów.
     */
    public function getTotalBattleReportsForUser(int $userId): int
    {
        $countQuery = "SELECT COUNT(*) as total
                     FROM battle_reports br
                     JOIN villages sv ON br.source_village_id = sv.id
                     JOIN villages tv ON br.target_village_id = tv.id
                     WHERE sv.user_id = ? OR tv.user_id = ?";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->bind_param("ii", $userId, $userId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();

        return $countResult['total'] ?? 0;
    }
} 