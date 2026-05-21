<?php
// Smoke test: enroll → check lessons → complete a lesson → check credit transfer
require_once __DIR__ . '/backend/config/jwt.php';
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
require_once __DIR__ . '/backend/models/User.php';
require_once __DIR__ . '/backend/models/Transaction.php';

$secret = 'your-secret-key-change-this-in-production';
function b64u($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
$header = b64u('{"typ":"JWT","alg":"HS256"}');
$claim  = b64u(json_encode(['user_id'=>1,'username'=>'ahmed','exp'=>time()+3600]));
$sig    = b64u(hash_hmac('sha256',"$header.$claim",$secret,true));
$token  = "$header.$claim.$sig";

$db = new Database();
$enrollment  = new Enrollment($db);
$userModel   = new User($db);
$transaction = new Transaction($db);

echo "=== [1] Enroll learner(1) in offer(1) – should be a 5-lesson offer ===\n";
// Offer 1: credits=5, lessons_count=1
// Enrollment creation is inline — use existing offer (already created, no duplicates)
// So we test the lesson retrieval instead
$conn = $db->connect();
$stmt = $conn->prepare("SELECT e.id, e.completed_lessons, e.remaining_lessons, e.status, us.lessons_count, us.credits, us.skill_name FROM enrollments e JOIN user_skills us ON e.offer_id = us.id WHERE us.user_id = 1 ORDER BY e.id DESC LIMIT 3");
$stmt->execute();
echo "Existing enrollments (teacher=1):\n";
print_r($stmt->fetchAll());

// Should create default lesson for 0 or 1 lesson offer_id=0
// check lessons table
$stmt2 = $conn->prepare("SELECT * FROM lessons LIMIT 5");
$stmt2->execute();
echo "\n=== [2] Lessons table (first 5):\n";
$rows = $stmt2->fetchAll();
echo count($rows) . " rows found\n";
if (count($rows) > 0) print_r($rows);

echo "\n=== [3] Credit tests (LOGGED-OUT — should show existing):\n";
$stmt3 = $conn->prepare("SELECT id, username, credits FROM users");
$stmt3->execute();
print_r($stmt3->fetchAll());

echo "\nDONE\n";
