<?php
// Message model
class Message {
    private $db;
    private $table = 'messages';

    public function __construct($database) {
        $this->db = $database;
    }

    public function send($sender_id, $receiver_id, $message, $lesson_request_id = null) {
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (sender_id, receiver_id, message, lesson_request_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$sender_id, $receiver_id, $message, $lesson_request_id]);
    }

    public function getConversation($user1_id, $user2_id, $lesson_request_id = null) {
        $conn = $this->db->connect();
        if ($lesson_request_id) {
            $query = "SELECT m.*, u.username as sender_username 
                      FROM {$this->table} m 
                      JOIN users u ON m.sender_id = u.id 
                      WHERE m.lesson_request_id = ? 
                      ORDER BY m.created_at ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$lesson_request_id]);
        } else {
            $query = "SELECT m.*, u.username as sender_username 
                      FROM {$this->table} m 
                      JOIN users u ON m.sender_id = u.id 
                      WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                      OR (m.sender_id = ? AND m.receiver_id = ?) 
                      ORDER BY m.created_at ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
        }
        return $stmt->fetchAll();
    }

    public function markAsRead($sender_id, $receiver_id) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table} SET read_status = TRUE WHERE sender_id = ? AND receiver_id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$sender_id, $receiver_id]);
    }

    public function getUserConversations($user_id) {
        $conn = $this->db->connect();
        $query = "SELECT DISTINCT u.id as user_id, u.username, u.full_name, 
                  m.message as last_message, m.created_at as message_time
                  FROM messages m 
                  JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
                  WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
                  GROUP BY u.id 
                  ORDER BY m.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $user_id, $user_id]);
        return $stmt->fetchAll();
    }
}