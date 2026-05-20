<?php
// User model
class User {
    private $db;
    private $table = 'users';

    public function __construct($database) {
        $this->db = $database;
    }

    public function create($username, $email, $password, $full_name = null) {
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (username, email, password, full_name) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->execute([$username, $email, $hashed_password, $full_name]);
        return $conn->lastInsertId();
    }

    public function findByUsername($username) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $conn = $this->db->connect();
        $query = "SELECT id, username, email, full_name, bio, profile_picture, credits, created_at FROM {$this->table} WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByEmail($email) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function updateProfile($id, $data) {
        $conn = $this->db->connect();
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        $values[] = $id;
        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute($values);
    }

    public function verifyPassword($username, $password) {
        $user = $this->findByUsername($username);
        if (!$user) return false;
        return password_verify($password, $user['password']);
    }

    public function updateCredits($id, $amount) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table} SET credits = credits + ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$amount, $id]);
    }
}