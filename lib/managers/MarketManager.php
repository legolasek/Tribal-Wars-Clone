<?php

require_once __DIR__ . '/VillageManager.php';
require_once __DIR__ . '/BuildingManager.php';

class MarketManager {
    private $conn;
    private $villageManager;
    private $buildingManager;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->villageManager = new VillageManager($this->conn);
        $bcm = new BuildingConfigManager($this->conn);
        $this->buildingManager = new BuildingManager($this->conn, $bcm);
    }

    /**
     * Creates a new market offer.
     *
     * @param int $villageId
     * @param string $offeredResource
     * @param int $offeredAmount
     * @param string $requestedResource
     * @param int $requestedAmount
     * @return array
     */
    public function createOffer(int $villageId, string $offeredResource, int $offeredAmount, string $requestedResource, int $requestedAmount): array {
        // 1. Validate input
        if ($offeredAmount <= 0 || $requestedAmount <= 0 || !in_array($offeredResource, ['wood', 'clay', 'iron']) || !in_array($requestedResource, ['wood', 'clay', 'iron'])) {
            return ['success' => false, 'message' => 'Invalid offer data.'];
        }

        // 2. Get village resources and market level
        $this->villageManager->updateResources($villageId);
        $village = $this->villageManager->getVillageInfo($villageId);
        if (!$village) {
            return ['success' => false, 'message' => 'Village not found.'];
        }

        if ($village[strtolower($offeredResource)] < $offeredAmount) {
            return ['success' => false, 'message' => 'Not enough resources to make this offer.'];
        }

        $marketLevel = $this->buildingManager->getBuildingLevel($villageId, 'market');
        if ($marketLevel <= 0) {
            return ['success' => false, 'message' => 'You must have a market to create offers.'];
        }

        // Calculate traders required
        $distance = 1; // For now, assume a flat rate. This can be expanded later.
        $tradersRequired = ceil($offeredAmount / 1000); // 1 trader per 1000 resources

        // Check for available traders
        $totalTraders = $marketLevel * 10; // Example: 10 traders per market level
        $usedTraders = $this->getUsedTraders($villageId);

        if (($totalTraders - $usedTraders) < $tradersRequired) {
            return ['success' => false, 'message' => 'Not enough available traders.'];
        }

        // 3. Start transaction
        $this->conn->begin_transaction();

        try {
            // 4. Deduct resources
            $sql = "UPDATE villages SET $offeredResource = $offeredResource - ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $offeredAmount, $villageId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to deduct resources.");
            }
            $stmt->close();

            // 5. Insert offer
            $stmt = $this->conn->prepare("INSERT INTO market_offers (village_id, offered_resource, offered_amount, requested_resource, requested_amount, traders_required) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssii", $villageId, $offeredResource, $offeredAmount, $requestedResource, $requestedAmount, $tradersRequired);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create offer.");
            }
            $stmt->close();

            // 6. Commit transaction
            $this->conn->commit();
            return ['success' => true, 'message' => 'Offer created successfully.'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieves all open market offers.
     *
     * @return array
     */
    public function getOffers(): array {
        $sql = "
            SELECT
                mo.id,
                mo.village_id,
                v.name as village_name,
                v.x_coord,
                v.y_coord,
                mo.offered_resource,
                mo.offered_amount,
                mo.requested_resource,
                mo.requested_amount,
                mo.traders_required
            FROM market_offers mo
            JOIN villages v ON mo.village_id = v.id
            WHERE mo.status = 'open'
            ORDER BY mo.created_at DESC
        ";

        $result = $this->conn->query($sql);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        return [];
    }

    /**
     * Accepts a market offer.
     *
     * @param int $offerId
     * @param int $acceptingVillageId
     * @return array
     */
    public function acceptOffer(int $offerId, int $acceptingVillageId): array {
        $this->conn->begin_transaction();

        try {
            // 1. Lock and get the offer
            $stmt = $this->conn->prepare("SELECT * FROM market_offers WHERE id = ? AND status = 'open' FOR UPDATE");
            $stmt->bind_param("i", $offerId);
            $stmt->execute();
            $offer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$offer) {
                throw new Exception("Offer not found or already taken.");
            }

            if ($offer['village_id'] == $acceptingVillageId) {
                throw new Exception("You cannot accept your own offer.");
            }

            // 2. Check accepting village's resources and traders
            $this->villageManager->updateResources($acceptingVillageId);
            $acceptingVillage = $this->villageManager->getVillageInfo($acceptingVillageId);
            if (!$acceptingVillage) {
                throw new Exception("Accepting village not found.");
            }

            $requestedResource = strtolower($offer['requested_resource']);
            if ($acceptingVillage[$requestedResource] < $offer['requested_amount']) {
                throw new Exception("Not enough resources to accept this offer.");
            }

            $marketLevel = $this->buildingManager->getBuildingLevel($acceptingVillageId, 'market');
            $totalTraders = $marketLevel * 10;
            $usedTraders = $this->getUsedTraders($acceptingVillageId);
            $tradersForThisTrip = ceil($offer['requested_amount'] / 1000);

            if (($totalTraders - $usedTraders) < $tradersForThisTrip) {
                 throw new Exception("Not enough available traders to accept this offer.");
            }

            // 3. Perform resource exchange
            // Deduct from acceptor
            $sql = "UPDATE villages SET $requestedResource = $requestedResource - ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $offer['requested_amount'], $acceptingVillageId);
            if (!$stmt->execute()) throw new Exception("Failed to deduct resources from acceptor.");
            $stmt->close();

            // Add to acceptor
            $offeredResource = strtolower($offer['offered_resource']);
            $sql = "UPDATE villages SET $offeredResource = $offeredResource + ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $offer['offered_amount'], $acceptingVillageId);
            if (!$stmt->execute()) throw new Exception("Failed to add resources to acceptor.");
            $stmt->close();

            // Add to original offerer
            $sql = "UPDATE villages SET $requestedResource = $requestedResource + ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $offer['requested_amount'], $offer['village_id']);
            if (!$stmt->execute()) throw new Exception("Failed to add resources to offerer.");
            $stmt->close();

            // 4. Update offer status
            $stmt = $this->conn->prepare("UPDATE market_offers SET status = 'closed' WHERE id = ?");
            $stmt->bind_param("i", $offerId);
            if (!$stmt->execute()) throw new Exception("Failed to close the offer.");
            $stmt->close();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Offer accepted successfully.'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Cancels a market offer.
     *
     * @param int $offerId
     * @param int $villageId The ID of the village attempting to cancel the offer.
     * @return array
     */
    public function cancelOffer(int $offerId, int $villageId): array {
        $this->conn->begin_transaction();

        try {
            // 1. Lock and get the offer, ensuring it belongs to the user
            $stmt = $this->conn->prepare("SELECT * FROM market_offers WHERE id = ? AND village_id = ? AND status = 'open' FOR UPDATE");
            $stmt->bind_param("ii", $offerId, $villageId);
            $stmt->execute();
            $offer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$offer) {
                throw new Exception("Offer not found or you don't have permission to cancel it.");
            }

            // 2. Refund the resources
            $offeredResource = strtolower($offer['offered_resource']);
            $sql = "UPDATE villages SET $offeredResource = $offeredResource + ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $offer['offered_amount'], $villageId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to refund resources.");
            }
            $stmt->close();

            // 3. Update the offer status to 'cancelled'
            $stmt = $this->conn->prepare("UPDATE market_offers SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $offerId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update offer status.");
            }
            $stmt->close();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Offer cancelled successfully.'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    private function getUsedTraders(int $villageId): int {
        $stmt = $this->conn->prepare("SELECT SUM(traders_required) as used FROM market_offers WHERE village_id = ? AND status = 'open'");
        $stmt->bind_param("i", $villageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['used'] ?? 0;
    }
}