<?php
// Message model — one conversation per user pair (messages still tied to enrollments)
class Message {
    private $db;
    private $table = 'messages';

    public function __construct($database) {
        $this->db = $database;
    }

    public function send($sender_id, $receiver_id, $enrollment_id, $message) {
        $text = trim($message);
        if ($text === '') {
            return false;
        }
        $conn = $this->db->connect();
        $query = "INSERT INTO {$this->table} (enrollment_id, sender_id, receiver_id, message)
                  VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        return $stmt->execute([(int)$enrollment_id, (int)$sender_id, (int)$receiver_id, $text]);
    }

    /** All messages between two users who share at least one enrollment. */
    public function getBetweenUsers($user_id, $other_user_id) {
        $conn = $this->db->connect();
        $uid = (int)$user_id;
        $oid = (int)$other_user_id;
        $query = "SELECT m.*, u.username AS sender_username, u.full_name AS sender_full_name,
                         us.skill_name AS course_title
                  FROM {$this->table} m
                  JOIN users u ON m.sender_id = u.id
                  LEFT JOIN enrollments e ON m.enrollment_id = e.id
                  LEFT JOIN user_skills us ON e.offer_id = us.id
                  WHERE (
                      (m.sender_id = ? AND m.receiver_id = ?)
                      OR (m.sender_id = ? AND m.receiver_id = ?)
                  )
                  AND (
                      m.enrollment_id IS NULL
                      OR EXISTS (
                          SELECT 1 FROM enrollments e2
                          JOIN user_skills us2 ON e2.offer_id = us2.id
                          WHERE e2.id = m.enrollment_id
                            AND (
                                (e2.learner_id = ? AND us2.user_id = ?)
                                OR (e2.learner_id = ? AND us2.user_id = ?)
                            )
                      )
                  )
                  ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$uid, $oid, $oid, $uid, $uid, $oid, $oid, $uid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsReadBetweenUsers($receiver_id, $sender_id) {
        $conn = $this->db->connect();
        $rid = (int)$receiver_id;
        $sid = (int)$sender_id;
        $query = "UPDATE {$this->table} m
                  SET read_status = 1
                  WHERE m.receiver_id = ? AND m.sender_id = ? AND m.read_status = 0
                  AND (
                      m.enrollment_id IS NULL
                      OR EXISTS (
                          SELECT 1 FROM enrollments e
                          JOIN user_skills us ON e.offer_id = us.id
                          WHERE e.id = m.enrollment_id
                            AND (
                                (e.learner_id = ? AND us.user_id = ?)
                                OR (e.learner_id = ? AND us.user_id = ?)
                            )
                      )
                  )";
        $stmt = $conn->prepare($query);
        return $stmt->execute([$rid, $sid, $rid, $sid, $sid, $rid]);
    }

    public function getUnreadTotal($user_id) {
        $conn = $this->db->connect();
        $uid = (int)$user_id;
        $query = "SELECT COUNT(*) FROM {$this->table} m
                  WHERE m.receiver_id = ? AND m.read_status = 0
                  AND (
                      m.enrollment_id IS NULL
                      OR EXISTS (
                          SELECT 1 FROM enrollments e
                          JOIN user_skills us ON e.offer_id = us.id
                          WHERE e.id = m.enrollment_id
                            AND (e.learner_id = ? OR us.user_id = ?)
                      )
                  )";
        $stmt = $conn->prepare($query);
        $stmt->execute([$uid, $uid, $uid]);
        return (int)$stmt->fetchColumn();
    }

    /** One row per other user (merged across all shared enrollments). */
    public function getUserConversations($user_id) {
        $conn = $this->db->connect();
        $uid = (int)$user_id;
        $query = "SELECT
                    p.other_user_id,
                    u.username AS other_username,
                    u.full_name AS other_full_name,
                    p.course_titles,
                    lm.message AS last_message,
                    lm.created_at AS message_time,
                    (SELECT COUNT(*) FROM {$this->table} um
                     WHERE um.receiver_id = :uid1 AND um.sender_id = p.other_user_id
                       AND um.read_status = 0
                       AND (
                           um.enrollment_id IS NULL
                           OR EXISTS (
                               SELECT 1 FROM enrollments e3
                               JOIN user_skills us3 ON e3.offer_id = us3.id
                               WHERE e3.id = um.enrollment_id
                                 AND (e3.learner_id = :uid2 OR us3.user_id = :uid3)
                           )
                       )
                    ) AS unread_count
                  FROM (
                      SELECT
                          CASE WHEN e.learner_id = :uid4 THEN us.user_id ELSE e.learner_id END AS other_user_id,
                          GROUP_CONCAT(DISTINCT us.skill_name ORDER BY us.skill_name SEPARATOR ', ') AS course_titles
                      FROM enrollments e
                      JOIN user_skills us ON e.offer_id = us.id
                      WHERE e.learner_id = :uid5 OR us.user_id = :uid6
                      GROUP BY other_user_id
                  ) p
                  JOIN users u ON u.id = p.other_user_id
                  LEFT JOIN {$this->table} lm ON lm.id = (
                      SELECT MAX(m2.id) FROM {$this->table} m2
                      WHERE (m2.sender_id = :uid7 AND m2.receiver_id = p.other_user_id)
                         OR (m2.sender_id = p.other_user_id AND m2.receiver_id = :uid8)
                  )
                  ORDER BY COALESCE(lm.created_at, '1970-01-01') DESC, u.full_name ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':uid1' => $uid, ':uid2' => $uid, ':uid3' => $uid,
            ':uid4' => $uid, ':uid5' => $uid, ':uid6' => $uid,
            ':uid7' => $uid, ':uid8' => $uid,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
