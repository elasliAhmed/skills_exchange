<?php
// Direct unit test: Lesson model without HTTP
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/models/Lesson.php';
require_once __DIR__ . '/../../backend/models/Enrollment.php';

$db   = new Database();
$conn = $db->connect();

$lessonModel = new Lesson($db);
$enrollModel = new Enrollment($db);

// Check lesson 1 exists
$lesson = $lessonModel->getById(1);
echo "Lesson 1: " . ($lesson ? 'found (enrollment_id=' . $lesson['enrollment_id'] . ')' : 'NOT FOUND') . "\n";

// Check enrollment for this lesson
$enrollments = $enrollModel->getOfferById($lesson['enrollment_id']);
echo "Enrollment lookup by ID: " . ($enrollments ? 'found' : 'NOT FOUND') . "\n";

// Check getByEnrollmentId
$lessons = $lessonModel->getByEnrollmentId(1);
echo "Lessons for enrollment 1: " . count($lessons) . "\n";
foreach ($lessons as $l) {
    echo "  #{$l['id']} lesson_number={$l['lesson_number']} status={$l['status']} comment=" . ($l['teacher_comment'] ?? 'null') . "\n";
}

// Test saveComment
$ok = $lessonModel->saveComment(1, 'Completed HTML forms and CSS basics');
echo "saveComment: " . ($ok ? 'OK' : 'FAIL') . "\n";

$after = $lessonModel->getById(1);
echo "After save comment_created_at: " . ($after['comment_created_at'] ?? 'NULL') . "\n";
echo "After teacher_comment: " . substr($after['teacher_comment'] ?? 'null', 0, 60) . "\n";
