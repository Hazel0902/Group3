<?php

require_once __DIR__ . '/db_connect.php';

class SCMIntegration {
    private $pdo;

    public $inventoryApiBase = 'http://localhost/Module1/api';      // example, optional
    public $procurementApiBase = 'http://localhost/Module3/api';    // example, optional

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

   
    public function listSuppliers() {
        $stmt = $this->pdo->query("SELECT supplier_id, supplier_name, contact_info, rating FROM suppliers ORDER BY supplier_name");
        return $stmt->fetchAll();
    }

    public function listShipments($limit = 200) {
        $stmt = $this->pdo->prepare("SELECT * FROM shipments ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $ships = $stmt->fetchAll();

      
        foreach ($ships as &$s) {
            $stmt2 = $this->pdo->prepare("SELECT product_id, product_name, quantity FROM shipment_items WHERE shipment_id = :sid");
            $stmt2->execute([':sid' => $s['shipment_id']]);
            $s['items'] = $stmt2->fetchAll();
        }
        return $ships;
    }

    
    private function httpGetJson($url, $params = []) {
        $chUrl = $url;
        if (!empty($params)) $chUrl .= '?' . http_build_query($params);
        $ch = curl_init($chUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code < 200 || $code >= 300) {
            return null;
        }
        $decoded = json_decode($resp, true);
        return $decoded;
    }

 
    public function fetchInventoryFromModule1() {
        $url = rtrim($this->inventoryApiBase, '/') . '/get_stock.php';
        return $this->httpGetJson($url) ?? [];
    }

    
    public function fetchSupplierDeliveriesFromProcurement() {
        $url = rtrim($this->procurementApiBase, '/') . '/get_supplier_deliveries.php';
        return $this->httpGetJson($url) ?? [];
    }

    
    public function createShipment(array $payload) {
        $sql = "INSERT INTO shipments (external_ref, supplier_id, type, status, origin, destination, departure_at, expected_arrival_at)
                VALUES (:external_ref, :supplier_id, :type, :status, :origin, :destination, :departure_at, :expected_arrival_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':external_ref' => $payload['external_ref'] ?? null,
            ':supplier_id' => $payload['supplier_id'] ?? null,
            ':type' => $payload['type'] ?? 'inbound',
            ':status' => $payload['status'] ?? 'scheduled',
            ':origin' => $payload['origin'] ?? null,
            ':destination' => $payload['destination'] ?? null,
            ':departure_at' => $payload['departure_at'] ?? null,
            ':expected_arrival_at' => $payload['expected_arrival_at'] ?? null
        ]);
        $shipmentId = $this->pdo->lastInsertId();

        if (!empty($payload['items']) && is_array($payload['items'])) {
            $stmtItem = $this->pdo->prepare("INSERT INTO shipment_items (shipment_id, product_id, product_name, quantity) VALUES (:shipment_id, :product_id, :product_name, :quantity)");
            foreach ($payload['items'] as $it) {
                $stmtItem->execute([
                    ':shipment_id' => $shipmentId,
                    ':product_id' => $it['product_id'] ?? 0,
                    ':product_name' => $it['product_name'] ?? null,
                    ':quantity' => $it['quantity'] ?? 0
                ]);
            }
        }

        return $shipmentId;
    }

   
    public function updateShipmentStatus(int $shipmentId, string $newStatus) {
        $allowed = ['scheduled','pending','in_transit','delayed','delivered','cancelled'];
        if (!in_array($newStatus, $allowed)) {
            throw new InvalidArgumentException("Invalid status");
        }
        $stmt = $this->pdo->prepare("UPDATE shipments SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE shipment_id = :sid");
        $stmt->execute([':status' => $newStatus, ':sid' => $shipmentId]);
        return $stmt->rowCount();
    }
}
