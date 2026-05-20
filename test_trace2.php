<?php
// Minimal standalone trace
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
$db = new Database();
$e  = new Enrollment($db);

$offerId = 1; // just created, lessons_count=5

$ok = $e->create($offerId, 3);
echo "create() returned: " . var_export($ok, true) . "\n";

$conn = $db->connect();
echo "\nNew enrollment row (just inserted by create()):\n";
$stmt = $conn->query("SELECT id, offer_id, learner_id, status, completed_lessons, remaining_lessons FROM enrollments ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
printf("  id=%-3d offer=%-3d learner=%-3d done=%-3d remain=%-3d\n",
    $row['id'],$row['offer_id'],$row['learner_id'],$row['completed_lessons'],$row['remaining_lessons']);
echo "  expected_remain=5   actual=" . $row['remaining_lessons'] . "  " .
     ($row['remaining_lessons']==5 ? "✓" : "✗ FAIL") . "\n";

$stmt2 = $conn->query("SELECT COUNT(*) FROM lessons WHERE enrollment_id = " . $row['id']);
echo "\nLesson rows: " . $stmt2->fetchColumn() . "  " .
     ((int)$stmt2->fetchColumn()===5 ? "✓" : "✗") . "\n";

echo "\nDONE\n";
