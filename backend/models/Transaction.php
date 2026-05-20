<?php
// Transaction model
class Transaction {
    private $db;
    private $table = 'transactions';

    public function __construct($database) {
        $this->db = $database;
    }

    public function create($user_id, $type, $amount, $description = null, $reference_id = null) {
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (user_id, type, amount, description, reference_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$user_id, $type, $amount, $description, $reference_id]);
    }

    public function getUserTransactions($user_id) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
}