<?php
// Enrollment model
class Enrollment {
    private $db;
    private $table = 'enrollments';

    public function __construct($database) {
        $this->db = $database;
    }

    public function create($offer_id, $learner_id) {
        $conn = $this->db->connect();
        
        // Get the offer details including lessons_count
        $offer = $this->getOfferById($offer_id);
        if (!$offer) {
            return false;
        }
        
        $lessons_count = $offer['lessons_count'] ?? 1;
        
        // Insert enrollment with lesson counts
        $query = "INSERT INTO {$this->table} (offer_id, learner_id, status, completed_lessons, remaining_lessons) 
                  VALUES (?, ?, 'active', 0, ?)";
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([$offer_id, $learner_id, $lessons_count]);
        
        if ($result) {
            $enrollment_id = $conn->lastInsertId();
            
            // Create lesson records
            $lessonQuery = "INSERT INTO lessons (enrollment_id, lesson_number, status) VALUES ";
            $lessonValues = [];
            $lessonParams = [];
            
            for ($i = 1; $i <= $lessons_count; $i++) {
                $lessonValues[] = "(?, ?, 'pending')";
                $lessonParams[] = $enrollment_id;
                $lessonParams[] = $i;
            }
            
            if (!empty($lessonValues)) {
                $lessonQuery .= implode(", ", $lessonValues);
                $lessonStmt = $conn->prepare($lessonQuery);
                $lessonStmt->execute($lessonParams);
            }
            
            return true;
        }
        
        return false;
    }

    public function getEnrollmentByUserAndOffer($learner_id, $offer_id) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE learner_id = ? AND offer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$learner_id, $offer_id]);
        return $stmt->fetch();
    }

    public function getOfferById($offer_id) {
        $conn = $this->db->connect();
        $query = "SELECT us.*, u.username as teacher_username, u.full_name as teacher_full_name,
                          us.skill_name as skill_name,
                          us.skill_description as skill_description,
                          us.skill_level as skill_level,
                          us.lesson_format as lesson_format,
                          us.learner_gains as learner_gains,
                          us.credits as credits,
                          us.lessons_count as lessons_count
                   FROM user_skills us
                   JOIN users u ON us.user_id = u.id
                   WHERE us.id = ? AND us.type = 'teach'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$offer_id]);
        return $stmt->fetch();
    }

    public function getLearnerEnrollments($learner_id) {
        $conn = $this->db->connect();
        $query = "SELECT e.*,
                          us.skill_name as skill_name,
                          us.skill_description as skill_description,
                          us.skill_level as skill_level,
                          us.lesson_format as lesson_format,
                          us.learner_gains as learner_gains,
                          us.credits as credits,
                          us.lessons_count as lessons_count,
                          e.completed_lessons,
                          e.remaining_lessons,
                          u.username as teacher_username, 
                          u.id as teacher_id,
                          u.full_name as teacher_full_name
                   FROM {$this->table} e
                   JOIN user_skills us ON e.offer_id = us.id
                   JOIN users u ON us.user_id = u.id
                   WHERE e.learner_id = ?
                   ORDER BY e.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$learner_id]);
        return $stmt->fetchAll();
    }

    public function getTeacherEnrollments($teacher_id) {
        $conn = $this->db->connect();
        $query = "SELECT e.*,
                          us.skill_name as skill_name,
                          us.skill_description as skill_description,
                          us.skill_level as skill_level,
                          us.lesson_format as lesson_format,
                          us.learner_gains as learner_gains,
                          us.credits as credits,
                          us.lessons_count as lessons_count,
                          e.completed_lessons,
                          e.remaining_lessons,
                          u.username as learner_username
                   FROM {$this->table} e
                   JOIN user_skills us ON e.offer_id = us.id
                   JOIN users u ON e.learner_id = u.id
                   WHERE us.user_id = ?
                   ORDER BY e.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll();
    }

    public function getLearnersByOfferId($offer_id) {
        $conn = $this->db->connect();
        $query = "SELECT e.*, u.username, u.email, u.full_name
                  FROM {$this->table} e
                  JOIN users u ON e.learner_id = u.id
                  WHERE e.offer_id = ?
                  ORDER BY e.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$offer_id]);
        return $stmt->fetchAll();
    }

    public function updateStatus($id, $status) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$status, $id]);
    }
}