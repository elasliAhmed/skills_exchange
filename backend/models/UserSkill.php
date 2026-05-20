<?php
// User Skills model
class UserSkill {
    private $db;
    private $table = 'user_skills';

    public function __construct($database) {
        $this->db = $database;
    }

    public function add($user_id, $skill_name, $type, $credits = 5, $lessons_count = 1, $skill_description = null, $skill_level = null, $lesson_format = null, $learner_gains = null) {
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (user_id, skill_name, type, credits, lessons_count, skill_description, skill_level, lesson_format, learner_gains)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$user_id, $skill_name, $type, $credits, $lessons_count, $skill_description, $skill_level, $lesson_format, $learner_gains]);
    }

    public function updateCredits($user_id, $skill_id, $type, $credits) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table} SET credits = ? WHERE user_id = ? AND skill_id = ? AND type = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$credits, $user_id, $skill_id, $type]);
    }

    public function updateDetails($user_id, $skill_name, $type, $credits, $lessons_count, $skill_description = null, $skill_level = null, $lesson_format = null, $learner_gains = null) {
        $conn = $this->db->connect();
        $query = "UPDATE {$this->table}
                   SET credits = ?, lessons_count = ?, skill_name = ?, skill_description = ?, skill_level = ?, lesson_format = ?, learner_gains = ?
                   WHERE user_id = ? AND skill_name = ? AND type = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$credits, $lessons_count, $skill_name, $skill_description, $skill_level, $lesson_format, $learner_gains, $user_id, $skill_name, $type]);
    }

    public function remove($user_id, $skill_id, $type) {
        $conn = $this->db->connect();
        $query = "DELETE FROM {$this->table} WHERE user_id = ? AND skill_id = ? AND type = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$user_id, $skill_id, $type]);
    }

    public function getUserSkills($user_id, $type = null) {
        $conn = $this->db->connect();
        if ($type) {
            $query = "SELECT us.id as offer_id, us.skill_name as name, us.skill_description as description,
                              us.credits, us.lessons_count,
                              us.skill_description, us.skill_level, us.lesson_format, us.learner_gains
                       FROM {$this->table} us
                       WHERE us.user_id = ? AND us.type = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id, $type]);
        } else {
            $query = "SELECT us.id as offer_id, us.skill_name as name, us.skill_description as description,
                              us.type, us.credits, us.lessons_count,
                              us.skill_description, us.skill_level, us.lesson_format, us.learner_gains
                       FROM {$this->table} us
                       WHERE us.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id]);
        }
        return $stmt->fetchAll();
    }

    public function getTeachingOffers() {
        $conn = $this->db->connect();
        $query = "SELECT us.id as offer_id, us.user_id, u.username, u.full_name,
                          us.skill_name as name,
                          us.skill_description as description,
                          us.skill_level, us.lesson_format, us.learner_gains,
                          us.credits, us.lessons_count
                   FROM {$this->table} us
                   JOIN users u ON us.user_id = u.id
                   WHERE us.type = 'teach'
                   ORDER BY us.credits ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getOfferByUserAndSkill($user_id, $skill_id) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE user_id = ? AND skill_id = ? AND type = 'teach'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $skill_id]);
        return $stmt->fetch();
    }

    public function getOfferByUserAndSkillName($user_id, $skill_name) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE user_id = ? AND skill_name = ? AND type = 'teach'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $skill_name]);
        return $stmt->fetch();
    }

    public function getOfferById($id) {
        $conn = $this->db->connect();
        $query = "SELECT * FROM {$this->table} WHERE id = ? AND type = 'teach'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function removeByOfferId($id) {
        $conn = $this->db->connect();
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$id]);
    }
}