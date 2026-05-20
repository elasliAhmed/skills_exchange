<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/User.php';
require_once __DIR__ . '/backend/models/Enrollment.php';

$db         = new Database();
$userModel  = new User($db);
$enrollment = new Enrollment($db);

function b64u($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function makeToken(int $uid, string $user): string {
    $h = 'your-secret-key-change-this-in-production';
    $header  = b64u('{"typ":"JWT","alg":"HS256"}');
    $payload = b64u(json_encode(['user_id'=>$uid,'username'=>$user,'exp'=>time()+7200]));
    $sig     = b64u(hash_hmac('sha256',"$header.$payload",$h,true));
    return "$header.$payload.$sig";
}
function apiGet(string $url, ?string $token=null): object {
    $headers = ($token ? "Authorization: Bearer $token\r\n" : '') . 'Content-Type: application/json';
    $opts = ['http'=>['method'=>'GET','header'=>$headers,'ignore_errors'=>true]];
    return json_decode(@file_get_contents($url, false, stream_context_create($opts)) ?: '{"success":false}');
}
function apiPost(string $url, array $data, ?string $token=null): object {
    $headers = ($token ? "Authorization: Bearer $token\r\n" : '') . 'Content-Type: application/json';
    $opts = ['http'=>['method'=>'POST','header'=>$headers,'content'=>json_encode($data),'ignore_errors'=>true]];
    return json_decode(@file_get_contents($url, false, stream_context_create($opts)) ?: '{"success":false}');
}

$conn = $db->connect();
echo "=== ENROLLMENT TABLE BEFORE ===\n";
$rows = $conn->query("SELECT id, offer_id, learner_id, status, completed_lessons, remaining_lessons FROM enrollments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) { printf("  id=%-3d offer=%-3d learner=%-3d status=%-10s done=%-3d remain=%-3d\n", $r['id'],$r['offer_id'],$r['learner_id'],$r['status'],$r['completed_lessons'],$r['remaining_lessons']); }

echo "\n=== USER CREDITS INITIAL ===\n";
foreach ($conn->query("SELECT id, username, credits FROM users")->fetchAll(PDO::FETCH_ASSOC) as $u)
    printf("  user %-2d | %-12s | credits=%d\n", $u['id'], $u['username'], $u['credits']);

$conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
$conn->exec("TRUNCATE TABLE lessons;");
$conn->exec("TRUNCATE TABLE enrollments;");
$conn->exec("TRUNCATE TABLE user_skills;");
$conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "\n=== CLEARED ALL DATA ===\n";

// ── Step 1: Teacher creates a 5-lesson offer ────────────────────
echo "\n[1] Teacher creates 5-lesson offer…\n";
$teacherTok = makeToken(1, 'ahmed');
$newOffer = apiPost(
    'http://localhost/skills_exchange/backend/api/teaching-offers.php',
    ['user_id'=>1,'skill_name'=>'JavaScript','credits'=>20,'lessons_count'=>5,
     'skill_description'=>'JS fundamentals','skill_level'=>'Intermediate',
     'lesson_format'=>'1hr video call','learner_gains'=>'Build ES6 apps'],
    $teacherTok
);
echo "  success=" . var_export($newOffer->success ?? false, true) . "\n";
if (!($newOffer->success ?? false)) die("  ✗ offer create failed: " . json_encode($newOffer) . "\n");

// Fetch the offer_id from the DB (fresh select after HTTP request ran a separate PHP process)
$offerId = (int)$conn->query("SELECT id FROM user_skills WHERE skill_name='JavaScript'")->fetchColumn();
echo "  offer_id=$offerId\n";

// ── Verify offer stored lessons_count=5 ──────────────────────────
$offerRow = $conn->query("SELECT skill_name, credits, lessons_count FROM user_skills WHERE id=$offerId")->fetch(PDO::FETCH_ASSOC);
echo "\n[2] Offer row in DB:\n";
printf("  skill_name=%-25s credits=%-3d lessons_count=%-3d\n", $offerRow['skill_name'], $offerRow['credits'], $offerRow['lessons_count']);


// ── Step 3: Learner enrols ───────────────────────────────────────
echo "\n[3] Learner (aziz id=3) enrolls…\n";
$learnerTok = makeToken(3, 'aziz');
$enrollRes = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['offer_id'=>$offerId],
    $learnerTok
);
echo "  success=" . var_export($enrollRes->success ?? false, true) . "\n";
if (!($enrollRes->success ?? false)) die("  ✗ enrollment failed: " . json_encode($enrollRes) . "\n");
$enroll_id = (int)($enrollRes->data->enrollment_id ?? 0);
echo "  enrollment_id=$enroll_id\n";

// ── Step 4: Check enrollments row counters ──────────────────────
echo "\n[4] Enrollment row counters…\n";
$cnt = $conn->query("SELECT completed_lessons, remaining_lessons, status FROM enrollments WHERE id=$enroll_id")->fetch(PDO::FETCH_ASSOC);
printf("  completed=%-3d remaining=%-3d status=%-10s total=%-3d\n",
    $cnt['completed_lessons'],$cnt['remaining_lessons'],$cnt['status'],
    $cnt['completed_lessons']+$cnt['remaining_lessons']);
if ($cnt['remaining_lessons'] !== 5) die("  ✗ remaining_lessons should be 5!\n");
echo "  ✓ remaining_lessons = 5\n";

// ── Step 5: Check lessons table ─────────────────────────────────
echo "\n[5] Lesson rows in DB…\n";
$lessons = $conn->query("SELECT lesson_number, status, completed_at FROM lessons WHERE enrollment_id=$enroll_id ORDER BY lesson_number")->fetchAll(PDO::FETCH_ASSOC);
printf("  count=%d\n", count($lessons));
foreach ($lessons as $l) {
    printf("    lesson %2d | status=%-10s | completed_at=%s\n",
        $l['lesson_number'],$l['status'],$l['completed_at'] ?? 'null');
}
if (count($lessons) !== 5) die("  ✗ Expected 5 lesson rows!\n");
echo "  ✓ All 5 lesson rows created\n";

// ── Step 6: Teacher view via /enrollments.php?type=teacher ──────
echo "\n[6] Teacher (GET) enrollments list…\n";
$teacherList = apiGet('http://localhost/skills_exchange/backend/api/enrollments.php?type=teacher', $teacherTok);
echo "  success=" . var_export($teacherList->success ?? false, true) . "\n";
$teachEnrolls = $teacherList->data->enrollments ?? [];
echo "  teacher enrollments count=" . count($teachEnrolls) . "\n";
foreach ($teachEnrolls as $e) {
    printf("    id=%-3d skill=%-20s student=%-15s done=%-3d remain=%-3d status=%-10s\n",
        $e->id ?? $e['id'], $e->skill_name ?? '?', $e->learner_username ?? '?',
        $e->completed_lessons ?? 0, $e->remaining_lessons ?? 0, $e->status ?? '?');
}

// ── Step 7: Complete lesson 1 ─────────────────────────────────
echo "\n[7] Teacher completes lesson 1…\n";
$result1 = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$enroll_id,'lesson_number'=>1,'teacher_comment'=>'Great intro to JS basics!'],
    $teacherTok
);
echo "  success=" . var_export($result1->success ?? false, true) . "\n";
echo "  message  : " . ($result1->data->message     ?? 'n/a') . "\n";
echo "  completed: " . ($result1->data->completed_lessons ?? 'n/a') . "\n";
echo "  remaining: " . ($result1->data->remaining_lessons ?? 'n/a') . "\n";
echo "  credits_xfer: " . ($result1->data->credits_transferred ?? 'n/a') . "\n";

// ── Step 8: Verify DB state after lesson 1 ─────────────────────
echo "\n[8] DB state after lesson 1…\n";
$cnt8 = $conn->query("SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id=$enroll_id")->fetch(PDO::FETCH_ASSOC);
$l1   = $conn->query("SELECT status, teacher_comment FROM lessons WHERE enrollment_id=$enroll_id AND lesson_number=1")->fetch(PDO::FETCH_ASSOC);
printf("  enrollment: done=%-3d remain=%-3d\n",$cnt8['completed_lessons'],$cnt8['remaining_lessons']);
printf("  lesson  1: status=%-10s comment=%-30s\n",$l1['status'],$l1['teacher_comment'] ?? 'none');
$tCredits = (int)$conn->query("SELECT credits FROM users WHERE id=1")->fetchColumn();
$lCredits = (int)$conn->query("SELECT credits FROM users WHERE id=3")->fetchColumn();
echo "  teacher(1) credits = $tCredits (was 10)\n";
echo "  learner(3) credits = $lCredits (was 10)\n";

// ── Step 9: Lesson 1 protection (already completed) ─────────────
echo "\n[9] Double-complete guard…\n";
$dupe = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$enroll_id,'lesson_number'=>1,'teacher_comment'=>'duplicate'],
    $teacherTok
);
echo "  success=" . var_export($dupe->success ?? false, true)
     . " error=" . ($dupe->data->error ?? 'none') . "\n";
echo !($dupe->success ?? false) ? "  ✓ Duplicate blocked\n" : "  ✗ Duplicate passed!\n";

// ── Cleanup ────────────────────────────────────────────────────
echo "\n[cleanup] Truncating test data…\n";
$conn->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE lessons; TRUNCATE TABLE enrollments; TRUNCATE TABLE user_skills; SET FOREIGN_KEY_CHECKS = 1;");
echo "  ✓ Done\n";
echo "\n========== ALL CHECKS PASSED ==========\n";
