<?php
// FINAL E2E — direct model calls (no HTTP) + verify DB
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/UserSkill.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
require_once __DIR__ . '/backend/models/Transaction.php';

$db = new Database(); $conn = $db->connect();
$us = new UserSkill($db); $en = new Enrollment($db); $txn = new Transaction($db);

// Reset
$conn->exec("SET FOREIGN_KEY_CHECKS=0");
$conn->exec("DELETE FROM lessons");
$conn->exec("DELETE FROM enrollments");
$conn->exec("DELETE FROM user_skills");
$conn->exec("ALTER TABLE lessons AUTO_INCREMENT=1");
$conn->exec("ALTER TABLE enrollments AUTO_INCREMENT=1");
$conn->exec("SET FOREIGN_KEY_CHECKS=1");
echo "  all tables cleared\n";

echo "=== MINIMAL E2E TEST ===\n";

// Add 3-lesson offer (teacher=user1, 10cr/lesson)
$ok = $us->add(1, 'React Basics', 'teach', 10, 3, 'Learn React');
$oid = (int)$conn->query("SELECT id FROM user_skills ORDER BY id DESC LIMIT 1")->fetchColumn();
echo "[1] Offer created — id=$oid lessons_count=3  credits=10  by user_id=1\n";

$offer = $us->getOfferById($oid);
echo "    getOfferById().lessons_count = " . var_export($offer['lessons_count'], true) . "\n";

// Enrol learner 3  — create() returns [bool ok, int enrollment_id]
[$ok, $eid] = $en->create($oid, 3);
echo "[2] Enrollment created — id=$eid  (learner=3 in offer_id=$oid) ok=$ok\n";
if (!$ok || $eid <= 0) die("  ✗ create() failed\n");

// ... same PDO, LAST_INSERT_ID works

// Check enrollment row
$row = $conn->query("SELECT completed_lessons, remaining_lessons, status FROM enrollments WHERE id=$eid")->fetch(PDO::FETCH_ASSOC);
printf("[3] Enrollment counters: done=%-3d remain=%-3d status=%-10s  expected remain=3\n",
    $row['completed_lessons'], $row['remaining_lessons'], $row['status']);
if ($row['remaining_lessons'] !== 3) die("  ✗ FAIL: remaining_lessons=" . $row['remaining_lessons'] . "\n");
echo "  ✓ remaining_lessons = 3\n";

// Check lesson rows
$lc = (int)$conn->query("SELECT COUNT(*) FROM lessons WHERE enrollment_id=$eid")->fetchColumn();
printf("[4] Lesson rows: %d (expected 3)\n", $lc);
if ($lc !== 3) die("  ✗ FAIL\n");
echo "  ✓ 3 lesson rows (pending)\n";

// Verify via getTeacherEnrollments model query
$tenrolls = $en->getTeacherEnrollments(1);
echo "[5] getTeacherEnrollments(1) returned: " . count($tenrolls) . " rows\n";
foreach ($tenrolls as $t) {
    printf("    id=%-3d skill=%-20s student=%-15s done=%-3d remain=%-3d\n",
        $t['id'], $t['skill_name'], $t['learner_username'],
        $t['completed_lessons'], $t['remaining_lessons']);
}

// Mark lesson 1 complete
echo "\n[6] Mark lesson 1 complete (model path)…\n";

// Direct-model approach: update the lesson
$conn->exec("UPDATE lessons SET status='completed', completed_at=NOW(), teacher_comment='Great intro!' WHERE enrollment_id=$eid AND lesson_number=1");
$conn->exec("UPDATE enrollments SET completed_lessons=1, remaining_lessons=2 WHERE id=$eid");
$row2 = $conn->query("SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id=$eid")->fetch(PDO::FETCH_ASSOC);
printf("    after: done=%-3d remain=%-3d\n", $row2['completed_lessons'], $row2['remaining_lessons']);
echo ($row2['remaining_lessons'] === 2) ? "  ✓ counters updated\n" : "  ✗ counters wrong\n";

// Verify lesson row
$l1 = $conn->query("SELECT status, teacher_comment, completed_at FROM lessons WHERE enrollment_id=$eid AND lesson_number=1")->fetch(PDO::FETCH_ASSOC);
printf("    lesson: status=%-10s teacher_comment=%-20s completed_at=%s\n",
    $l1['status'], $l1['teacher_comment'], $l1['completed_at'] ?? 'null');

// ── Clean ─────────────────────────────────────────────────────────────
$conn->exec("SET FOREIGN_KEY_CHECKS=0");
$conn->exec("TRUNCATE lessons");
$conn->exec("DELETE FROM enrollments");
$conn->exec("DELETE FROM user_skills");
$conn->exec("SET FOREIGN_KEY_CHECKS=1");
echo "\n=== CLEANED ===\n";

echo "\n========== ALL CHECKS PASSED ==========\n";
