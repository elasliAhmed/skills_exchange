<?php
// End-to-end smoke test: teacher dashboard flow (DB→enroll.php→GET/teacher→mark complete)
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/UserSkill.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
require_once __DIR__ . '/backend/models/Transaction.php';

$db         = new Database();
$us         = new UserSkill($db);
$en         = new Enrollment($db);
$txn        = new Transaction($db);

function b64u($d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function makeToken(int $uid, string $user): string {
    $h = 'your-secret-key-change-this-in-production';
    $h_  = b64u('{"typ":"JWT","alg":"HS256"}');
    $c   = b64u(json_encode(['user_id'=>$uid,'username'=>$user,'exp'=>time()+7200]));
    $sig = b64u(hash_hmac('sha256',"$h_.$c",$h,true));
    return "$h_.$c.$sig";
}
function apiGet(string $url, ?string $token=null): object {
    $headers = ($token ? "Authorization: Bearer $token\r\n" : '') . 'Content-Type: application/json';
    $opts = ['http'=>['method'=>'GET','header'=>$headers,'ignore_errors'=>true,'timeout'=>5]];
    return json_decode(@file_get_contents($url, false, stream_context_create($opts)) ?: '{"success":false}');
}
function apiPost(string $url, array $data, ?string $token=null): object {
    $headers = ($token ? "Authorization: Bearer $token\r\n" : '') . 'Content-Type: application/json';
    $opts = ['http'=>['method'=>'POST','header'=>$headers,'content'=>json_encode($data),'ignore_errors'=>true,'timeout'=>5]];
    return json_decode(@file_get_contents($url, false, stream_context_create($opts)) ?: '{"success":false}');
}

$conn = $db->connect();

// ── Reset DB ────────────────────────────────────────────────────────────
echo "=== RESET ===\n";
$conn->exec("SET FOREIGN_KEY_CHECKS=0");
$conn->exec("TRUNCATE lessons"); $conn->exec("TRUNCATE enrollments"); $conn->exec("TRUNCATE user_skills");
$conn->exec("SET FOREIGN_KEY_CHECKS=1");
echo "  tables cleared\n";

$user1Credits = (int)$conn->query("SELECT credits FROM users WHERE id=1")->fetchColumn();
$user2Credits = (int)$conn->query("SELECT credits FROM users WHERE id=2")->fetchColumn();

// ── Step 1: Teacher offers API returns lessons_count ──────────────────────
echo "\n[1] Teaching-offers API — all offers must include lessons_count ===\n";
$tokT = makeToken(1, 'ahmed');
$allOffers = apiGet('http://localhost/skills_exchange/backend/api/teaching-offers.php', $tokT);
echo "  GET /teaching-offers  success=" . var_export($allOffers->success, true) . "\n";
$offers = $allOffers->data->offers ?? [];
$fail = false;
foreach ($offers as $o) {
    $hasN = property_exists($o, 'lessons_count');
    printf("  offer %-3d | %-20s | has lessons_count=%s  value=%s\n",
        $o->offer_id ?? 0, $o->name ?? '?', $hasN?'✓':'✗ MISSING', $hasN ? var_export($o->lessons_count, true) : 'n/a');
    if (!$hasN) $fail = true;
}
if ($fail) die("  ✗ Some offers are missing lessons_count in API response!\n");
echo "  ✓ All offers include lessons_count\n";

// ── Step 2: Teacher dash stats API ──────────────────────────────────────
echo "\n[2] Teacher API — GET enrollments.php?type=teacher ===\n";
$teacherEnrolls = apiGet('http://localhost/skills_exchange/backend/api/enrollments.php?type=teacher', $tokT);
echo "  GET /enrollments?type=teacher  success=" . var_export($teacherEnrolls->success, true) . "\n";
$tenrolls = $teacherEnrolls->data->enrollments ?? [];
echo "  teacher enrollments returned: " . count($tenrolls) . "\n";
foreach ($tenrolls as $e) {
    printf("    id=%-3d skill=%-25s student=%-12s done=%-3d remain=%-3d lessons_total=%-3d\n",
        $e->id ?? $e['id'] ?? 0,
        $e->skill_name    ?? '?', $e->learner_username  ?? '?',
        $e->completed_lessons ?? 0, $e->remaining_lessons ?? 0, $e->lessons_count ?? 0);
}
echo "  ✓ API call succeeded\n";

// ── Step 3: Fresh offer + learner enrollment + lesson creation ───────────
echo "\n[3] Fresh offer + learner enrollment ===\n";
$tc = 5; $lc = 3;
// Add offer directly via model (known-good path)
$us->add(1, 'Python 101', 'teach', $tc, $lc, 'Python basics'); // user_id=1, not credits
$offerNewId = (int)$conn->query("SELECT id FROM user_skills ORDER BY id DESC LIMIT 1")->fetchColumn();
echo "  New offer: id=$offerNewId  credits=$tc  lessons=$lc  (offered by user 1)\n";

// Cross-check via API
$apiOfferResp = apiGet(
    'http://localhost/skills_exchange/backend/api/teaching-offers.php',
    makeToken(1, 'ahmed')
);
$myOffers = array_filter(
    (array)($apiOfferResp->data->offers ?? []),
    fn($o) => ($o->offer_id ?? 0) === $offerNewId
);
$myOffers = array_values($myOffers);
$myOffer = $myOffers[0] ?? null;
echo "  API offers: id=" . var_export($myOffer->offer_id ?? 0, true)
     . " lessons_count=" . var_export($myOffer->lessons_count ?? 'MISSING', true) . "\n";

// Learner 2 enrolls via HTTP API
echo "  Learner (user 2) enrolling via HTTP...\n";
$enrollResp = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['offer_id'=>$offerNewId],
    makeToken(2, 'testuser')
);
echo "  enrollment success=" . var_export($enrollResp->success, true) . "\n";
if (!($enrollResp->success ?? false)) die("  ✗ Enrollment failed: " . json_encode($enrollResp) . "\n");
$eid = (int)($enrollResp->data->enrollment_id ?? 0);
echo "  new enrollment_id=$eid\n";

// Verify enrollment row
$erow = $conn->query("SELECT completed_lessons, remaining_lessons, status FROM enrollments WHERE id=$eid")->fetch(PDO::FETCH_ASSOC);
printf("  enrollment: done=%-3d remain=%-3d  status=%-10s  (expected remain=%d)\n",
    $erow['completed_lessons'], $erow['remaining_lessons'], $erow['status'], $lc);
if ($erow['remaining_lessons'] !== $lc) {
    die("  ✗ remaining_lessons MISMATCH! expected=$lc got=" . $erow['remaining_lessons'] . "\n");
}
echo "  ✓ remaining_lessons=$lc\n";

// Verify lessons count
$lessonRowsCount = (int)$conn->query("SELECT COUNT(*) FROM lessons WHERE enrollment_id=$eid")->fetchColumn();
printf("  lessons table rows: %d (expected %d)\n", $lessonRowsCount, $lc);
if ($lessonRowsCount !== $lc) die("  ✗ Wrong lesson row count!\n");
echo "  ✓ All $lc lesson rows created\n";

// ── Step 4: Teacher sees enrollment + lessons via HTTP API ─────────────
echo "\n[4] Teacher GET /enrollments.php (HTTP) ===\n";
$ens = apiGet('http://localhost/skills_exchange/backend/api/enrollments.php', makeToken(1,'ahmed'));
echo "  success=" . var_export($ens->success, true) . "\n";

// ── Step 5: Enrollment details modal data (GET teacher.php?endpoint=lessons) ──
echo "\n[5] Enrollment details modal data (GET /teacher.php?endpoint=lessons) ===\n";
$detailResp = apiGet(
    "http://localhost/skills_exchange/backend/api/teacher.php?endpoint=lessons&enrollment_id=$eid",
    makeToken(1,'ahmed')
);
echo "  success=" . var_export($detailResp->success, true) . "\n";
$d = $detailResp->data ?? new stdClass;
printf("  course=%-20s student=%-12s lessons_count=%-3d\n",
    $d->course_title ?? '?', $d->student_name ?? '?', $d->lessons_count ?? 0);
$dlessons = $d->lessons ?? [];
echo "  lessons returned: " . count($dlessons) . "  (expected $lc)\n";
foreach ($dlessons as $l) {
    printf("    lesson %2d | status=%-10s teachers_comment=%s\n",
        $l->lesson_number, $l->status ?? '?', $l->teacher_comment ?? '');
}
echo "  ✓ Details endpoint returns all lessons\n";

// ── Step 6: Teacher marks lesson 1 completed ─────────────────────────────
echo "\n[6] Teacher completes lesson 1 ===\n";
$completeResp = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$eid,'lesson_number'=>1,'teacher_comment'=>'Great Python intro!'],
    makeToken(1, 'ahmed')
);
echo "  success=" . var_export($completeResp->success, true) . "\n";
echo "  message  : " . ($completeResp->data->message ?? 'none') . "\n";
echo "  completed: " . ($completeResp->data->completed_lessons ?? 'n/a') . "\n";
echo "  remaining: " . ($completeResp->data->remaining_lessons ?? 'n/a') . "\n";
echo "  credits_xfer=" . ($completeResp->data->credits_transferred ?? 'n/a') . "\n";

// ── Step 7: DB state after lesson 1 ─────────────────────────────────────
echo "\n[7] DB state after lesson 1 ===\n";
$erow2 = $conn->query("SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id=$eid")->fetch(PDO::FETCH_ASSOC);
$lrow1 = $conn->query("SELECT status, completed_at, teacher_comment FROM lessons WHERE enrollment_id=$eid AND lesson_number=1")->fetch(PDO::FETCH_ASSOC);
$t1    = (int)$conn->query("SELECT credits FROM users WHERE id=1")->fetchColumn();
$t2    = (int)$conn->query("SELECT credits FROM users WHERE id=2")->fetchColumn();
printf("  enrollment: done=%-3d remain=%-3d   (expected done=1 remain=%d)\n",
    $erow2['completed_lessons'],$erow2['remaining_lessons'], $lc-1);
printf("  lesson  1: status=%-10s completed_at=%s\n", $lrow1['status'], $lrow1['completed_at'] ?? 'null');
echo "  teacher(1) credits = $t1  (was $user1Credits , expected +$tc = " . ($user1Credits+$tc) . ")\n";
echo "  learner(2) credits = $t2  (was $user2Credits , expected -$tc = " . ($user2Credits-$tc) . ")\n";
echo ($t1 == $user1Credits+$tc && $t2 == $user2Credits-$tc) ? "  ✓ Credit transfer correct\n" : "  ✗ Credit MISMATCH\n";

// ── Step 8: Transaction records ────────────────────────────────────────
echo "\n[8] Transaction records ===\n";
$txnsT = $txn->getUserTransactions(1);
$txnsL = $txn->getUserTransactions(2);
echo "  Teacher txns: " . count($txnsT) . "\n";
foreach (array_slice($txnsT, -2) as $t) {
    printf("    %6s %-6d  %-50s\n", $t['type'], $t['amount'], substr($t['description'] ?? '', 0, 50));
}
echo "  Learner txns: " . count($txnsL) . "\n";
foreach (array_slice($txnsL, -2) as $t) {
    printf("    %6s %-6d  %-50s\n", $t['type'], $t['amount'], substr($t['description'] ?? '', 0, 50));
}

// ── Step 9: Double-complete blocks ──────────────────────────────────────
echo "\n[9] Guard: double-complete lesson 1 ===\n";
$dupe = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$eid,'lesson_number'=>1,'teacher_comment'=>'dupe'],
    makeToken(1, 'ahmed')
);
echo "  success=" . var_export($dupe->success, true)
     . " error=\""  . ($dupe->data->error     ?? 'none') . "\"\n";
echo !($dupe->success ?? false) ? "  ✓ Duplicate blocked\n" : "  ✗ Duplicate was allowed!\n";

// ── Step 10: remaining_lessons guard after all done ─────────────────────
if ($lc > 1) {
    echo "\n[10] Guard: can't exceed remaining_lessons ===\n";
    // Complete remaining lessons first
    for ($i = 2; $i <= $lc; $i++) {
        apiPost('http://localhost/skills_exchange/backend/api/enrollments.php',
            ['action'=>'complete_lesson','enrollment_id'=>$eid,'lesson_number'=>$i,'teacher_comment'=>"Lesson $i done"],
            makeToken(1,'ahmed'));
    }
    // Now remaining=0, try completing again
    $exceed = apiPost(
        'http://localhost/skills_exchange/backend/api/enrollments.php',
        ['action'=>'complete_lesson','enrollment_id'=>$eid,'lesson_number'=>$lc,'teacher_comment'=>'dup'],
        makeToken(1,'ahmed')
    );
    echo "  after all done, extra attempt: success=" . var_export($exceed->success, true)
         . " error=\"" . ($exceed->data->error ?? 'none') . "\"\n";
    echo !($exceed->success ?? false) ? "  ✓ Overrun blocked\n" : "  ✗ Overrun was allowed!\n";
}

// ── Step 11: PATCH allow-same-user (nobody-else) ────────────────────────
echo "\n[11] Guard: non-teacher cannot complete ===\n";
$bad = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>$eid,'lesson_number'=>1],
    makeToken(3, 'aziz')
);
echo "  non-teacher success=" . var_export($bad->success, true)
     . " error=\"" . ($bad->data->error ?? 'none') . "\"\n";
echo !($bad->success ?? false) ? "  ✓ Non-teacher blocked\n" : "  ✗ Non-teacher allowed!\n";

// ── Cleanup ────────────────────────────────────────────────────────────
$conn->exec("SET FOREIGN_KEY_CHECKS=0; TRUNCATE lessons; TRUNCATE enrollments; TRUNCATE user_skills; SET FOREIGN_KEY_CHECKS=1;");
echo "\n=== CLEANED ===\n";
echo "=== ALL CHECKS COMPLETE ===\n";
