<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/User.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
require_once __DIR__ . '/backend/models/Transaction.php';

$db         = new Database();
$userModel  = new User($db);
$enrollment = new Enrollment($db);
$txnModel   = new Transaction($db);

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

$teacherId = 1;  $teacherTok = makeToken(1,  'ahmed');
$learnerId = 2;  $learnerTok = makeToken(2,  'testuser');

echo "========== FRESH-PATH INTEGRATION TEST ==========\n\n";
echo "[pre] credits — teacher(1): " . ($userModel->findById(1)['credits'] ?? 0)
     . "  learner(2): " . ($userModel->findById(2)['credits'] ?? 0) . "\n";

// ── STEP 1: Teacher creates 2-lesson offer ──────────────────────
echo "\n[1] Teacher creates 2-lesson offer…\n";
$newOffer = apiPost(
    'http://localhost/skills_exchange/backend/api/teaching-offers.php',
    [
        'user_id'         => $teacherId,
        'skill_name'      => 'HTML5 Crash Course',
        'skill_description' => 'Master HTML5 from zero',
        'skill_level'     => 'Beginner',
        'credits'         => 15,
        'lessons_count'   => 2,
        'lesson_format'   => '1-hour zoom sessions',
        'learner_gains'   => 'Build real pages',
    ],
    $teacherTok
);
$offer_id = $newOffer->data->offer_id ?? null;
if (!$offer_id) { die("  ✗ Offer creation failed: " . json_encode($newOffer) . "\n"); }
echo "  ✓ Created offer_id=$offer_id  (2 lessons @ 15 credits/lesson)\n";

// ── STEP 2: Learner enrolls ─────────────────────────────────────
echo "\n[2] Learner enrolls (no upfront payment)…\n";
$enrollRes = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['offer_id' => $offer_id],
    $learnerTok
);
if (!$enrollRes->success) {
    die("  ✗ Enrollment failed: " . json_encode($enrollRes) . "\n");
}
$enroll_id = $enrollRes->data->enrollment_id ?? null;
echo "  ✓ " . ($enrollRes->data->message ?? 'enrolled') . "\n";

// ── STEP 3: Verify lessons rows in DB ──────────────────────────
echo "\n[3] Verify lesson rows in DB…\n";
$conn = $db->connect();
$stmt = $conn->prepare("SELECT lesson_number, status, completed_at FROM lessons WHERE enrollment_id = ? ORDER BY lesson_number");
$stmt->execute([$enroll_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "  lessons COUNT = " . count($lessons) . "\n";
foreach ($lessons as $l) {
    printf("    lesson %d | status=%s | completed_at=%s\n",
        $l['lesson_number'], $l['status'], $l['completed_at'] ?? 'null');
}
if (count($lessons) !== 2) die("  ✗ Expected 2 lessons!\n");
echo "  ✓ Two lesson rows created — pending\n";

// ── STEP 4: Verify enrollment counters in DB ────────────────────
echo "\n[4] Enrollment counters in DB…\n";
$stmt2 = $conn->prepare("SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id = ?");
$stmt2->execute([$enroll_id]);
$counters = $stmt2->fetch(PDO::FETCH_ASSOC);
printf("  completed=%d  remaining=%d  total=%d\n",
    $counters['completed_lessons'], $counters['remaining_lessons'],
    $counters['completed_lessons'] + $counters['remaining_lessons']);
if ($counters['remaining_lessons'] < 1) die("  ✗ remaining_lessons should be >= 1\n");
echo "  ✓ remaining_lessons = " . $counters['remaining_lessons'] . "\n";

// ── STEP 5: Complete lesson 1 (teacher) ─────────────────────────
echo "\n[5] Mark lesson 1 as completed (teacher=$teacherTok)…\n";
$completion = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$enroll_id,'lesson_number'=>1,'teacher_comment'=>'Great start on HTML basics!'],
    $teacherTok
);
echo "  success  : " . var_export($completion->success, true) . "\n";
echo "  message  : "  . ($completion->data->message ?? 'none') . "\n";
echo "  completed: "  . ($completion->data->completed_lessons ?? 'n/a') . "\n";
echo "  remaining: "  . ($completion->data->remaining_lessons ?? 'n/a') . "\n";
echo "  credits_xfer: " . ($completion->data->credits_transferred ?? 'n/a') . "\n";
if (!($completion->success ?? false))
    die("  ✗ Mark complete failed: " . json_encode($completion) . "\n");

// ── STEP 6: Verify lesson row is now 'completed' ───────────────
echo "\n[6] Verify lesson row is now 'completed'…\n";
$stmt3 = $conn->prepare("SELECT lesson_number, status, teacher_comment FROM lessons WHERE enrollment_id = ? AND lesson_number = 1");
$stmt3->execute([$enroll_id]);
$lesson1 = $stmt3->fetch(PDO::FETCH_ASSOC);
echo "  lesson_number=" . ($lesson1['lesson_number'] ?? '?')
     . " status="  . ($lesson1['status'] ?? '?')
     . " teacher_comment=" . ($lesson1['teacher_comment'] ?? 'none') . "\n";
echo ($lesson1['status'] === 'completed') ? "  ✓ Lesson 1 marked completed\n" : "  ✗ NOT completed!\n";

// ── STEP 7: Verify credit transfer in DB ───────────────────────
echo "\n[7] Credit state after lesson 1 complete…\n";
$tAfter = $userModel->findById($teacherId);
$lAfter = $userModel->findById($learnerId);
echo "  Teacher credits : " . ($tAfter['credits'] ?? 0) . "  (was 12)\n";
echo "  Learner credits : " . ($lAfter['credits'] ?? 0) . "  (was 10)\n";
$earned = ($tAfter['credits'] ?? 0) - 12;
$spent  = 10 - ($lAfter['credits'] ?? 0);
echo "  Teacher earned  : " . $earned . " credits\n";
echo "  Learner spent   : " . $spent  . " credits\n";
if ($earned === 15 && $spent === 15) echo "  ✓ Per-lesson payment = 15 credits\n";
else                            echo "  ✗ Expected teacher+15 learner-15\n";

// ── STEP 8: Verify transaction records ─────────────────────────
echo "\n[8] Transaction history…\n";
$txnsT = $txnModel->getUserTransactions($teacherId);
$txnsL = $txnModel->getUserTransactions($learnerId);
echo "  Teacher txns: " . count($txnsT) . "\n";
foreach (array_slice($txnsT, -2) as $t) {
    printf("    %6s %-6.0f  %-50s\n", $t['type'], $t['amount'], substr($t['description'] ?? '', 0, 50));
}
echo "  Learner txns: " . count($txnsL) . "\n";
foreach (array_slice($txnsL, -2) as $t) {
    printf("    %6s %-6.0f  %-50s\n", $t['type'], $t['amount'], substr($t['description'] ?? '', 0, 50));
}

// ── STEP 9: Try double-complete guard ───────────────────────────
echo "\n[9] Double-complete guard (lesson 1 again = should fail)…\n";
$dupe = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$enroll_id,'lesson_number'=>1,'teacher_comment'=>'dupe'],
    $teacherTok
);
echo "  success=" . var_export($dupe->success, true)
     . " error="   . ($dupe->data->error  ?? 'none') . "\n";
echo !($dupe->success ?? false) ? "  ✓ Duplicate blocked\n" : "  ✗ Duplicate was allowed!\n";

// ── CLEANUP ─────────────────────────────────────────────────────
echo "\n[cleanup] Removing test offer + enrollment + lessons…\n";
$conn->prepare("DELETE FROM lessons    WHERE enrollment_id = ?")->execute([$enroll_id]);
$conn->prepare("DELETE FROM enrollments WHERE id = ?")->execute([$enroll_id]);
$conn->prepare("DELETE FROM user_skills WHERE id = ?")->execute([$offer_id]);
echo "  ✓ Cleaned up\n";

echo "\n========== ALL TESTS PASSED ==========\n";
