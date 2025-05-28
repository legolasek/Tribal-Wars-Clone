<?php

class RankingManager
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Pobiera ranking graczy z paginacją.
     *
     * @param int $limit Liczba graczy na stronę.
     * @param int $offset Offset dla paginacji.
     * @return array Lista graczy z danymi rankingowymi.
     */
    public function getPlayersRanking(int $limit, int $offset): array
    {
        $query = "
            SELECT 
                u.id, 
                u.username, 
                COUNT(v.id) as village_count, 
                SUM(v.population) as total_population,
                SUM(
                    (SELECT COUNT(*) FROM village_units vu WHERE vu.village_id = v.id)
                ) as total_units -- Ta suma jest niepoprawna, powinna być sumą populacji jednostek
            FROM 
                users u
            LEFT JOIN 
                villages v ON u.id = v.user_id
            GROUP BY 
                u.id
            ORDER BY 
                total_population DESC, village_count DESC
            LIMIT ?, ?
        ";

        $stmt = $this->conn->prepare($query);
        // Check for prepare errors
        if ($stmt === false) {
            error_log("RankingManager::getPlayersRanking prepare failed: " . $this->conn->error);
            return []; // Return empty array on error
        }

        $stmt->bind_param("ii", $offset, $limit); // Note: limit is second parameter in LIMIT clause
        $stmt->execute();
        $result = $stmt->get_result();

        $players = [];
        // Rank will be calculated in the calling code based on offset
        while ($row = $result->fetch_assoc()) {
            // Calculate points based on total population (example logic)
            $row['points'] = $row['total_population'] ? $row['total_population'] * 10 : 0;
            $players[] = $row;
        }

        $stmt->close();

        return $players;
    }

    /**
     * Pobiera całkowitą liczbę graczy dla paginacji rankingu.
     *
     * @return int Całkowita liczba graczy.
     */
    public function getTotalPlayersCount(): int
    {
        $count_query = "SELECT COUNT(*) as total FROM users";
        $stmt_count = $this->conn->prepare($count_query);
         // Check for prepare errors
        if ($stmt_count === false) {
            error_log("RankingManager::getTotalPlayersCount prepare failed: " . $this->conn->error);
            return 0; // Return 0 on error
        }
        $stmt_count->execute();
        $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        return $total_records ?? 0;
    }

    /**
     * Pobiera ranking plemion z paginacją.
     *
     * @param int $limit Liczba plemion na stronę.
     * @param int $offset Offset dla paginacji.
     * @return array Lista plemion z danymi rankingowymi.
     */
    public function getTribesRanking(int $limit, int $offset): array
    {
        // TODO: Implement logic to fetch tribes ranking from the database
        // For now, returning an empty array as the system is not fully implemented.
        
        // Example query structure if a 'tribes' table existed:
        /*
        $query = "
            SELECT 
                t.id,
                t.name,
                COUNT(v.id) as village_count,
                COUNT(u.id) as member_count,
                SUM(v.population) as total_population
            FROM
                tribes t
            LEFT JOIN
                users u ON u.tribe_id = t.id
            LEFT JOIN
                villages v ON v.user_id = u.id
            GROUP BY
                t.id
            ORDER BY
                total_population DESC, member_count DESC
            LIMIT ?, ?
        ";

        $stmt = $this->conn->prepare($query);
        if ($stmt === false) {
            error_log("RankingManager::getTribesRanking prepare failed: " . $this->conn->error);
            return [];
        }
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $tribes = [];
        while ($row = $result->fetch_assoc()) {
             // Calculate points for tribes (example logic)
             $row['points'] = $row['total_population'] ? $row['total_population'] * 10 : 0;
             $tribes[] = $row;
        }
        $stmt->close();
        return $tribes;
        */

        return []; // Return empty array for now
    }

     /**
     * Pobiera całkowitą liczbę plemion dla paginacji rankingu.
     *
     * @return int Całkowita liczba plemion.
     */
    public function getTotalTribesCount(): int
    {
        // TODO: Implement logic to get total tribes count
        // For now, returning 0.

        /*
        $count_query = "SELECT COUNT(*) as total FROM tribes";
        $stmt_count = $this->conn->prepare($count_query);
        if ($stmt_count === false) {
            error_log("RankingManager::getTotalTribesCount prepare failed: " . $this->conn->error);
            return 0;
        }
        $stmt_count->execute();
        $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();
        return $total_records ?? 0;
        */

        return 0; // Return 0 for now
    }

    // Metody do pobierania rankingu plemion zostaną dodane później

}

?> 