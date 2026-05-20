<?php
// Quick DB state snapshot
require_once __DIR__ . '/backend/config/database.php';
$db = new Database();
$conn = $db->connect();

echo "=== ENROLLMENTS (all columns) ===\n";
$stmt = $conn->query("SELECT * FROM enrollments");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf("  id=%-3d offer=%-3d learner=%-3d status=%-10s done=%-3d remain=%-3d LC=%s\n",
        $r['id'],$r['offer_id'],$r['learner_id'],$r['status'],
        $r['completed_lessons'],$r['remaining_lessons'],
        $r['created_at']);
}

echo "\n=== USERS ===\n";
$stmt = $conn->query("SELECT id, username, credits FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== USER_SKILLS (only lessons_count>0) ===\n";
$stmt = $conn->query("SELECT id, user_id, type, skill_name, credits, lessons_count FROM user_skills ORDER BY id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf("  id=%-3d user=%-3d type=%-5s name=%-15s credits=%-3d lessons=%-3d\n",
        $r['id'],$r['user_id'],$r['type'],$r['skill_name'],$r['credits'],$r['lessons_count']);
}

echo "\n=== LESSONS ===\n";
$stmt = $conn->query("SELECT enrollment_id, lesson_number, status, completed_at FROM lessons ORDER BY enrollment_id, lesson_number");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  total rows: " . count($rows) . "\n";
foreach ($rows as $r) {
    printf("  enroll=%-3d lesson=%-3d status=%-10s completed=%s\n",
        $r['enrollment_id'],$r['lesson_number'],$r['status'],$r['completed_at'] ?? 'null');
}

echo "\n=== tblShOW CREATE TABLE enrollments ===\n";
echo $conn->query("SHOW CREATE TABLE enrollments")->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? 'n/a';
echo "\n=== DONE ===\n";
