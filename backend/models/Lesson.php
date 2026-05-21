<?php
// Lesson model
require_once __DIR__ . '/../config/database.php';

class Lesson {
    private $db;
    private $table = 'lessons';

    public function __construct($database) {
        $this->db = $database;
    }

    public function getById($id) {
        $conn = $this->db->connect();
        $query = "SELECT l.*,
                         e.learner_id,
                         us.user_id as teacher_id,
                         u.username as teacher_username,
                         u.full_name as teacher_full_name
                  FROM {$this->table} l
                  JOIN enrollments e ON l.enrollment_id = e.id
                  JOIN user_skills us ON e.offer_id = us.id
                  JOIN users u ON us.user_id = u.id
                  WHERE l.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByEnrollmentId($enrollment_id) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table}
                  WHERE enrollment_id = ?
                  ORDER BY lesson_number ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$enrollment_id]);
        return $stmt->fetchAll();
    }

    public function saveComment($lesson_id, $comment) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table}
                  SET teacher_comment = ?, comment_created_at = NOW()
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$comment, $lesson_id]);
    }

    public function getByIdForTeacher($lesson_id, $teacher_id) {
        $conn = $this->db->connect();
        $query = "SELECT l.*,
                         e.learner_id,
                         u.username as learner_username,
                         u.full_name as learner_full_name,
                         COALESCE(us.skill_name, s.name) AS skill_name
                  FROM {$this->table} l
                  JOIN enrollments e ON l.enrollment_id = e.id
                  JOIN user_skills us ON e.offer_id = us.id
                  JOIN users u ON e.learner_id = u.id
                  LEFT JOIN skills s ON us.skill_id = s.id
                  WHERE l.id = ? AND us.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$lesson_id, $teacher_id]);
        return $stmt->fetch();
    }
}
