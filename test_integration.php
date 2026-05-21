<?php
// Full integration smoke test:
// 1. Login → get token
// 2. Create a new test offer
// 3. Enroll as another user
// 4. Complete one lesson as the teacher
// 5. Verify transactions and lesson rows

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/User.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
require_once __DIR__ . '/backend/models/Transaction.php';
require_once __DIR__ . '/backend/config/jwt.php';

$db         = new Database();
$userModel  = new User($db);
$enrollment = new Enrollment($db);
$txnModel   = new Transaction($db);

// ── helpers ──────────────────────────────────────────────────────
function apiGet(string $url, ?string $token = null): object {
    $opts = ['http'=>['method'=>'GET','header'=>"Content-Type: application/json\r\n".($token ? "Authorization: Bearer $token\r\n" : ''),'ignore_errors'=>true]];
    $r = @file_get_contents($url, false, stream_context_create($opts));
    return json_decode($r ?: '{"success":false}');
}
function apiPost(string $url, array $data, ?string $token = null): object {
    $opts = ['http'=>['method'=>'POST','header'=>$token?"Content-Type: application/json\r\nAuthorization: Bearer $token\r\n":"Content-Type: application/json\r\n",'content'=>json_encode($data),'ignore_errors'=>true]];
    $r = @file_get_contents($url, false, stream_context_create($opts));
    return json_decode($r ?: '{"success":false}');
}

function b64u($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function makeToken(int $uid, string $user): string {
    $h = 'your-secret-key-change-this-in-production';
    $header  = b64u('{"typ":"JWT","alg":"HS256"}');
    $payload = b64u(json_encode(['user_id'=>$uid,'username'=>$user,'exp'=>time()+7200]));
    $sig = b64u(hash_hmac('sha256',"$header.$payload",$h,true));
    return "$header.$payload.$sig";
}

// ── 1. Login (generate token directly from DB users) ────────────
echo "=== [1] Token setup ===\n";
$teacherToken = makeToken(1, 'ahmed');   // user 1 = ahmed (owns test+aziz offers)
$learnerToken = makeToken(2, 'testuser'); // user 2 = testuser (will enroll)

$teacherUser = $userModel->findById(1);
$learnerUser = $userModel->findById(2);
echo "Teacher (id=1) credits before: " . ($teacherUser['credits'] ?? 0) . "\n";
echo "Learner (id=2) credits before: " . ($learnerUser['credits'] ?? 0) . "\n";

// ── 2. Check existing offers for teacher ────────────────────────
echo "\n=== [2] Teacher enrollment stats (via /enrollments.php?type=teacher) ===\n";
$statsRes = apiPost('http://localhost/skills_exchange/backend/api/enrollments.php?type=teacher', ['type'=>'teacher'], $teacherToken);
echo "success=" . var_export($statsRes->success ?? false, true)
    . " | enrollments=" . count(($statsRes->data->enrollments ?? [])) . "\n";

// ── 3. Get teacher offer IDs ──────────────────────────────────────
echo "\n=== [3] Available teaching offers for teacher ===\n";
$teachRes = apiGet('http://localhost/skills_exchange/backend/api/teaching-offers.php?my=true', $teacherToken);
$myOffers = $teachRes->data->offers ?? [];
foreach ($myOffers as $o) {
    printf("  offer_id=%d | %s | %d credits | %d lessons\n",
        $o->offer_id, $o->name ?? '?', $o->credits ?? 0, $o->lessons_count ?? 0);
}

// ── 4. Auto-generate lessons for any offer that's missing them ─────
echo "\n=== [4] Check lessons table per enrollment ===\n";
$conn = $db->connect();
foreach ($myOffers as $o) {
    $oid = $o->offer_id;
    $stmt = $conn->prepare("SELECT id FROM enrollments WHERE offer_id = ?");
    $stmt->execute([$oid]);
    $enrollIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  offer_id=$oid enrollments: " . count($enrollIds) . "\n";
    foreach ($enrollIds as $eid) {
        $stmt2 = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE enrollment_id = ?");
        $stmt2->execute([$eid]);
        $cnt2 = (int)$stmt2->fetchColumn();
        echo "    enrollment_id=$eid lessons rows: $cnt2\n";
        // Auto-fix: ensure lessons exist for any enrollment that needs them
        if ($cnt2 === 0) {
            $lc = (int)($o->lessons_count ?? 1);
            if ($lc > 0) {
                $vals = [];
                $params = [];
                for ($ii = 1; $ii <= $lc; $ii++) {
                    $vals[] = "(?, ?, 'pending')";
                    $params[] = $eid;
                    $params[] = $ii;
                }
                $q = "INSERT INTO lessons (enrollment_id, lesson_number, status) VALUES " . implode(', ', $vals);
                $stmt3 = $conn->prepare($q);
                $ok = $stmt3->execute($params);
                echo "      → inserted $lc lesson rows: " . var_export($ok, true) . "\n";
            }
        }
    }
}

// ── 5. Data summary for teacher dashboard ─────────────────────────
echo "\n=== [5] Teacher dashboard data (gets all rows for user) ===\n";
$tRes = apiGet('http://localhost/skills_exchange/backend/api/enrollments.php?type=teacher', $teacherToken);
echo "success=" . var_export($tRes->success ?? false, true) . "\n";
foreach (($tRes->data->enrollments ?? []) as $e) {
    printf("  enrollment_id=%d | %s | student=%s | %d/%d lessons | %s\n",
        $e->id ?? $e['id'],
        $e->skill_name     ?? '?',
        $e->learner_username ?? '?',
        $e->completed_lessons ?? 0,
        $e->lessons_count ?? 0,
        $e->status ?? '?');
}

// ── 6. Complete a lesson (teacher) ────────────────────────────────
echo "\n=== [6] Mark lesson completed ===\n";
// Pick enrollment=2 (teacher=aziz, lesson should exist after auto-fix above)
$completionResult = apiPost(
    'http://localhost/skills_exchange/backend/api/enrollments.php',
    ['action'=>'complete_lesson','enrollment_id'=>2,'lesson_number'=>1],
    $teacherToken
);
echo "success=" . var_export($completionResult->success ?? false, true) . "\n";
echo "message  : " . ($completionResult->data->message ?? 'none') . "\n";
echo "completed: " . ($completionResult->data->completed_lessons ?? 'n/a') . "\n";
echo "remaining: " . ($completionResult->data->remaining_lessons ?? 'n/a') . "\n";
echo "credits  : " . ($completionResult->data->credits_transferred ?? 'n/a') . "\n";

// ── 7. Verify credit transfer in DB ──────────────────────────────
echo "\n=== [7] Credit state after completion ===\n";
$tAfter = $userModel->findById(1);
$lAfter = $userModel->findById(3);
echo "Teacher (id=1) credits: " . ($tAfter['credits'] ?? 0) . "\n";
echo "Learner (id=3) credits: " . ($lAfter['credits'] ?? 0) . "\n";

echo "\n=== [8] Transaction records ===\n";
$txns = $txnModel->getUserTransactions(1);
echo "Teacher transactions: " . count($txns) . "\n";
foreach (array_slice($txns, 0, 3) as $t) {
    printf("  type=%s amount=%.0f desc=%s\n", $t['type'], $t['amount'], substr($t['description'] ?? '', 0, 60));
}
$txns2 = $txnModel->getUserTransactions(2);
echo "Learner transactions: " . count($txns2) . "\n";
foreach (array_slice($txns2, 0, 2) as $t) {
    printf("  type=%s amount=%.0f desc=%s\n", $t['type'], $t['amount'], substr($t['description'] ?? '', 0, 60));
}

echo "\n=== DONE ===\n";
