<?php
// Review model
class Review {
    private $db;
    private $table = 'reviews';

    public function __construct($database) {
        $this->db = $database;
    }

    public function create($reviewer_id, $reviewed_id, $lesson_request_id, $rating, $comment = null) {
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (reviewer_id, reviewed_id, lesson_request_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$reviewer_id, $reviewed_id, $lesson_request_id, $rating, $comment]);
    }

    public function getUserReviews($user_id) {
        $conn = $this->db->connect();
        $query = "SELECT r.*, u.username as reviewer_username, lr.status as lesson_status 
                  FROM {$this->table} r 
                  JOIN users u ON r.reviewer_id = u.id 
                  JOIN lesson_requests lr ON r.lesson_request_id = lr.id 
                  WHERE r.reviewed_id = ? 
                  ORDER BY r.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getAverageRating($user_id) {
        $conn = $this->db->connect();
        $query = "SELECT AVG(rating) as average, COUNT(*) as count FROM {$this->table} WHERE reviewed_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

    public function hasReviewed($lesson_request_id, $reviewer_id) {
        $conn = $this->db->connect();
        $query = "SELECT id FROM {$this->table} WHERE lesson_request_id = ? AND reviewer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$lesson_request_id, $reviewer_id]);
        return $stmt->fetch() ? true : false;
    }
}