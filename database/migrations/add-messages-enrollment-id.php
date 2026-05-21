<?php
/**
 * Add enrollment_id to messages and backfill from enrollments.
 * Run: php database/migrations/add-messages-enrollment-id.php
 */
require_once __DIR__ . '/../../backend/config/database.php';

$db = new Database();
$conn = $db->connect();

$cols = $conn->query("SHOW COLUMNS FROM messages LIKE 'enrollment_id'")->fetch();
if (!$cols) {
    $conn->exec("ALTER TABLE messages ADD COLUMN enrollment_id INT NULL AFTER id");
    $conn->exec("ALTER TABLE messages ADD INDEX idx_messages_enrollment (enrollment_id)");
    try {
        $conn->exec("ALTER TABLE messages ADD CONSTRAINT fk_messages_enrollment
            FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE");
    } catch (PDOException $e) {
        echo "FK note: " . $e->getMessage() . "\n";
    }
    echo "Added enrollment_id column.\n";
} else {
    echo "enrollment_id already exists.\n";
}

$rows = $conn->query("SELECT id, sender_id, receiver_id FROM messages WHERE enrollment_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
$update = $conn->prepare(
    "UPDATE messages SET enrollment_id = ? WHERE id = ?"
);
$find = $conn->prepare(
    "SELECT e.id FROM enrollments e
     JOIN user_skills us ON e.offer_id = us.id
     WHERE (e.learner_id = ? AND us.user_id = ?)
        OR (e.learner_id = ? AND us.user_id = ?)
     ORDER BY e.updated_at DESC, e.id DESC
     LIMIT 1"
);

$linked = 0;
foreach ($rows as $row) {
    $s = (int)$row['sender_id'];
    $r = (int)$row['receiver_id'];
    $find->execute([$s, $r, $r, $s]);
    $eid = $find->fetchColumn();
    if ($eid) {
        $update->execute([(int)$eid, (int)$row['id']]);
        $linked++;
    }
}
echo "Backfilled {$linked} of " . count($rows) . " messages.\n";
echo "Done.\n";
