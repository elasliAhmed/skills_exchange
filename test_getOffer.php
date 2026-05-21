<?php
require_once __DIR__ . '/backend/config/database.php';
// Test all 5 offers through getOfferById()
$db = new Database();
$conn = $db->connect();

$offers_raw = [
    ['id'=>1,'label'=>'test (1 lesson)'],
    ['id'=>2,'label'=>'aziz (1 lesson)'],
    ['id'=>4,'label'=>'aziz1 (0 lessons)'],
    ['id'=>5,'label'=>'test22 (0 lessons)'],
    ['id'=>6,'label'=>'HTML5 (0 lessons)'],
];

echo "=== getOfferById() shape check ===\n";
foreach ($offers_raw as $o) {
    $stmt = $conn->prepare("SELECT us.*, u.username as teacher_username FROM user_skills us JOIN users u ON us.user_id = u.id WHERE us.id = ? AND us.type = 'teach'");
    $stmt->execute([$o['id']]);
    $raw = $stmt->fetch(PDO::FETCH_ASSOC);
    $lc  = $raw['lessons_count'] ?? 'MISSING';
    $lc_i = is_null($raw['lessons_count']) ? 'NULL' : (int)$raw['lessons_count'];
    $lc_type = gettype($raw['lessons_count']);
    $lc_hash = '0x' . substr(md5(var_export($raw, true)), 0, 8);
    echo sprintf("  offer_id=%-3d | %-25s | lessons_count=%-5s (type=%-6s, int=%s)\n",
        $o['id'], $o['label'], var_export($lc, true), $lc_type, $lc_i);
}

echo "\n=== Enrollment::create() test against offer_id=1 (should NOT run, just read) ===\n";
require_once __DIR__ . '/backend/models/Enrollment.php';
$enrollment = new Enrollment($db);

$offer = $enrollment->getOfferById(1);
$lc  = $offer['lessons_count'] ?? null;
$lc2 = is_null($lc) ? 1 : (int)$lc;
echo "  getOfferById returns lessons_count=" . var_export($lc, true)
     . " → enrollment create would set remaining_lessons = $lc2\n";

echo "\n=== DONE ===\n";
