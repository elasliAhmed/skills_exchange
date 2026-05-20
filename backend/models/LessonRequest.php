<?php
// Lesson Request model
class LessonRequest {
    private $db;
    private $table = 'lesson_requests';

    public function __construct($database) {
        $this->db = $database;
    }

    public function create($requester_id, $teacher_id, $skill_id, $message = null, $scheduled_time = null) {
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (requester_id, teacher_id, skill_id, message, scheduled_time) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$requester_id, $teacher_id, $skill_id, $message, $scheduled_time]);
    }

    public function findById($id) {
        $conn = $this->db->connect();
        $query = "SELECT lr.*, u1.username as requester_username, u2.username as teacher_username, 
                  s.name as skill_name 
                  FROM {$this->table} lr 
                  JOIN users u1 ON lr.requester_id = u1.id 
                  JOIN users u2 ON lr.teacher_id = u2.id 
                  JOIN skills s ON lr.skill_id = s.id 
                  WHERE lr.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateStatus($id, $status) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$status, $id]);
    }

    public function getUserRequests($user_id, $type = 'all') {
        $conn = $this->db->connect();
        if ($type === 'sent') {
            $query = "SELECT lr.*, u.username as teacher_username, s.name as skill_name 
                      FROM {$this->table} lr 
                      JOIN users u ON lr.teacher_id = u.id 
                      JOIN skills s ON lr.skill_id = s.id 
                      WHERE lr.requester_id = ? 
                      ORDER BY lr.created_at DESC";
        } elseif ($type === 'received') {
            $query = "SELECT lr.*, u.username as requester_username, s.name as skill_name 
                      FROM {$this->table} lr 
                      JOIN users u ON lr.requester_id = u.id 
                      JOIN skills s ON lr.skill_id = s.id 
                      WHERE lr.teacher_id = ? 
                      ORDER BY lr.created_at DESC";
        } else {
            $query = "SELECT lr.*, 
                      u1.username as requester_username, 
                      u2.username as teacher_username,
                      s.name as skill_name 
                      FROM {$this->table} lr 
                      JOIN users u1 ON lr.requester_id = u1.id 
                      JOIN users u2 ON lr.teacher_id = u2.id 
                      JOIN skills s ON lr.skill_id = s.id 
                      WHERE lr.requester_id = ? OR lr.teacher_id = ? 
                      ORDER BY lr.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id, $user_id]);
            return $stmt->fetchAll();
        }
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getPendingRequests($teacher_id) {
        $conn = $this->db->connect();
        $query = "SELECT lr.*, u.username as requester_username, s.name as skill_name 
                  FROM {$this->table} lr 
                  JOIN users u ON lr.requester_id = u.id 
                  JOIN skills s ON lr.skill_id = s.id 
                  WHERE lr.teacher_id = ? AND lr.status = 'pending' 
                  ORDER BY lr.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll();
    }
}