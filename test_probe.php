<?php
// Direct-model smoke test — no HTTP, no JWT — tests live code paths
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
require_once __DIR__ . '/backend/models/User.php';

$db         = new Database();
$enrollment = new Enrollment($db);

echo "=== Offer 1 (lessons_count=1) ===\n";
$offer = $enrollment->getOfferById(1);
echo "offer['lessons_count'] = " . var_export($offer['lessons_count'] ?? 'MISSING', true) . "\n";
echo "offer['credits']        = " . var_export($offer['credits']        ?? 'MISSING', true) . "\n";
echo "offer keys: " . implode(', ', array_keys($offer ?? [])) . "\n\n";

echo "=== Offer columns in user_skills ===\n";
$conn = $db->connect();
$stmt = $conn->query("SHOW COLUMNS FROM user_skills");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
echo "\n=== lessons table columns ===\n";
$stmt = $conn->query("SHOW COLUMNS FROM lessons");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== Preview: create() does NOT run — checking enrollment row ===\n";
echo "enrollments table rows:\n";
$stmt = $conn->query("SELECT id, offer_id, learner_id, status, completed_lessons, remaining_lessons, created_at FROM enrollments ORDER BY id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf("  id=%d offer_id=%d learner_id=%d status=%s completed=%d remaining=%d\n",
        $r['id'],$r['offer_id'],$r['learner_id'],$r['status'],$r['completed_lessons'],$r['remaining_lessons']);
}

echo "\n=== DONE ===\n";
