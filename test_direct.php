<?php
require_once __DIR__ . '/backend/config/database.php';
$db = new Database();

echo "=== Fresh manual test ===\n";

// Clear
$conn = $db->connect();
$conn->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE lessons; TRUNCATE TABLE enrollments; TRUNCATE TABLE user_skills; SET FOREIGN_KEY_CHECKS = 1;");
echo "DB cleared\n";

// Direct model call: add offer, then create enrollment
require_once __DIR__ . '/backend/models/UserSkill.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
$us = new UserSkill($db);
$en = new Enrollment($db);

echo "\n[1] UserSkill::add(1, 'JS Basics', 'teach', 20, 5, ...)\n";
$ok1 = $us->add(1, 'JS Basics', 'teach', 20, 5, 'desc', 'Beginner', 'zoom', 'apps');
echo "  add() = " . var_export($ok1, true) . "\n";

// offer_id from DB
$offerId = (int)$conn->query("SELECT id FROM user_skills ORDER BY id DESC LIMIT 1")->fetchColumn();
echo "  offer_id=$offerId\n";

// Verify
$offerRow = $conn->query("SELECT lessons_count FROM user_skills WHERE id=$offerId")->fetch(PDO::FETCH_ASSOC);
echo "  lessons_count in DB = " . var_export($offerRow['lessons_count'], true) . "\n";

echo "\n[2] Enrollment::create($offerId, 3)\n";
$ok2 = $en->create($offerId, 3);
echo "  create() = " . var_export($ok2, true) . "\n";

echo "\n[3] Enrollments + Lessons table\n";
$row = $conn->query("SELECT id, offer_id, learner_id, status, completed_lessons, remaining_lessons FROM enrollments ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($row) {
    printf("  enrollment: id=%-3d offer=%-3d remain=%-3d  (expected 5)\n", $row['id'], $row['offer_id'], $row['remaining_lessons']);
}
$lcount = (int)$conn->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
echo "  lessons rows: $lcount\n";

echo "\n=== DONE ===\n";
