<?php
/**
 * Inventory Manager Class
 * Handles stock additions, deductions, and logs stock movements.
 * Synchronizes both master products table and branch-specific product_stocks table.
 */

class InventoryManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Add stock to a product (master + branch stock)
     *
     * @param int $productId
     * @param float $quantity
     * @param string $description
     * @param int|null $userId
     * @param string $type
     * @param string|null $refType
     * @param int|null $refId
     * @param int|null $branchId
     * @return bool
     */
    public function addStock($productId, $quantity, $description = '', $userId = null, $type = 'return', $refType = null, $refId = null, $branchId = null) {
        if (!$userId && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        if (!$branchId) {
            $branchId = $_SESSION['branch_id'] ?? 1;
        }

        // 1. Update master products table
        $stmt = $this->conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmt->bind_param("di", $quantity, $productId);
        $result = $stmt->execute();
        $stmt->close();

        // 2. Synchronize branch-specific product_stocks table
        $qtyInt = (int)$quantity;
        $bStmt = $this->conn->prepare("
            INSERT INTO product_stocks (product_id, branch_id, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + ?
        ");
        if ($bStmt) {
            $bStmt->bind_param("iiii", $productId, $branchId, $qtyInt, $qtyInt);
            $bStmt->execute();
            $bStmt->close();
        }

        if ($result) {
            $this->logMovement($productId, $type, $quantity, $description, $refType, $refId);
        }

        return $result;
    }

    /**
     * Remove stock from a product (master + branch stock)
     */
    public function removeStock($productId, $quantity, $description = '', $userId = null, $type = 'adjustment', $refType = null, $refId = null, $branchId = null) {
        if (!$userId && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        if (!$branchId) {
            $branchId = $_SESSION['branch_id'] ?? 1;
        }

        // 1. Update master products table
        $stmt = $this->conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmt->bind_param("di", $quantity, $productId);
        $result = $stmt->execute();
        $stmt->close();

        // 2. Synchronize branch-specific product_stocks table
        $qtyInt = (int)$quantity;
        $negQty = (int)-$quantity;
        $bStmt = $this->conn->prepare("
            INSERT INTO product_stocks (product_id, branch_id, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity - ?
        ");
        if ($bStmt) {
            $bStmt->bind_param("iiii", $productId, $branchId, $negQty, $qtyInt);
            $bStmt->execute();
            $bStmt->close();
        }

        if ($result) {
            $this->logMovement($productId, $type, -$quantity, $description, $refType, $refId);
        }

        return $result;
    }

    /**
     * Log the stock movement
     */
    private function logMovement($productId, $type, $quantity, $description, $refType, $refId) {
        $stmt = $this->conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, description, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isdssi", $productId, $type, $quantity, $description, $refType, $refId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
