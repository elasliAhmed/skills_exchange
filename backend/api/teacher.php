<?php
// Teacher Dashboard API — stats, teacher enrollments, enrollment lessons, mark lesson complete
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../config/jwt.php';

$database = new Database();
$enrollment = new Enrollment($database);
$userModel = new User($database);
$transaction = new Transaction($database);

$user_id = JWT::getUserId();
if (!$user_id) {
    echo json_encode(['success' => false, 'data' => ['error' => 'Authentication required']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$method = $_SERVER['REQUEST_METHOD'];
$action = $input['action'] ?? $_GET['action'] ?? '';

// ── GET endpoints ──────────────────────────────────────────────
if ($method === 'GET') {
    $endpoint = $action ?: $_GET['endpoint'] ?? '';
    $conn = $database->connect();

    switch ($endpoint) {
        // Stats dashboard cards
        case 'stats':
            $all   = $enrollment->getTeacherEnrollments($user_id);
            $total = count($all);
            $active = count(array_filter($all, fn($e) => ($e['status'] ?? '') === 'active'));
            $totalCompleted = 0;
            foreach ($all as $e) {
                $totalCompleted += (int)($e['completed_lessons'] ?? 0);
            }
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_students'       => $total,
                    'active_enrollments'   => $active,
                    'completed_lessons'    => $totalCompleted,
                ]
            ]);
            break;

        // Full enrollment list for the teacher
        case 'enrollments':
            $rows = $enrollment->getTeacherEnrollments($user_id);
            echo json_encode(['success' => true, 'data' => ['enrollments' => $rows]]);
            break;

        // Fetch final teacher comment for a completed enrollment
        case 'final_comment':
            $enrollment_id = $_GET['enrollment_id'] ?? null;
            if (!$enrollment_id) {
                echo json_encode(['success' => false, 'data' => ['error' => 'enrollment_id required']]);
                exit;
            }
            $eRow = $conn->prepare(
                "SELECT e.id, e.status, e.remaining_lessons, us.user_id, e.final_teacher_comment, e.final_comment_created_at
                   FROM enrollments e
                   JOIN user_skills us ON e.offer_id = us.id
                  WHERE e.id = ? AND us.user_id = ?"
            );
            $eRow->execute([$enrollment_id, $user_id]);
            $eData = $eRow->fetch(PDO::FETCH_ASSOC);
            if (!$eData) {
                echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment not found or access denied']]);
                exit;
            }
            echo json_encode([
                'success' => true,
                'data'    => [
                    'enrollment_id'             => (int)$enrollment_id,
                    'status'                    => $eData['status'],
                    'final_teacher_comment'     => $eData['final_teacher_comment'],
                    'final_comment_created_at'  => $eData['final_comment_created_at'],
                ]
            ]);
            break;

        // Individual enrollment lessons — accessible by teacher or enrolled student
        case 'lessons':
            $enrollment_id = $_GET['enrollment_id'] ?? null;
            $student_name  = $_GET['student_name'] ?? 'Unknown';

            if (!$enrollment_id) {
                echo json_encode(['success' => false, 'data' => ['error' => 'enrollment_id required']]);
                exit;
            }

            $conn = $database->connect();

            // Resolve ownership: is the caller the teacher?
            $offerQuery = "SELECT us.*
                             FROM user_skills us
                             JOIN enrollments e ON e.offer_id = us.id
                            WHERE e.id = ? AND us.user_id = ?";
            $offerStmt = $conn->prepare($offerQuery);
            $offerStmt->execute([$enrollment_id, $user_id]);
            $offer = $offerStmt->fetch();

            if (!$offer) {
                // Maybe caller is the enrolled student
                $lrnStmt = $conn->prepare(
                    "SELECT e.id FROM enrollments e
                      WHERE e.id = ? AND e.learner_id = ?");
                $lrnStmt->execute([$enrollment_id, $user_id]);
                if (!$lrnStmt->fetch()) {
                    echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment not found or access denied']]);
                    exit;
                }
                // Student is allowed — proceed without offer data
            }

            $stmt = $conn->prepare(
                "SELECT * FROM lessons WHERE enrollment_id = ? ORDER BY lesson_number ASC"
            );
            $stmt->execute([$enrollment_id]);
            $lessons = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => [
                    'enrollment_id' => $enrollment_id,
                    'student_name'  => $student_name,
                    'course_title'  => $offer['skill_name'] ?? 'Unknown',
                    'lessons_count' => $offer['lessons_count'] ?? count($lessons),
                    'lessons'       => $lessons,
                    'final_teacher_comment'        => $offer['final_teacher_comment']        ?? null,
                    'final_comment_created_at'     => $offer['final_comment_created_at']     ?? null,
                    'enrollment_status'            => $offer['enrollment_status']            ?? null,
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'data' => ['error' => 'Unknown endpoint. Use stats, enrollments, final_comment, or lessons']]);
    }
    exit;
}

// ── POST endpoints ─────────────────────────────────────────────
if ($method === 'POST') {
    switch ($action) {
        // Mark a lesson as completed
        case 'complete_lesson':
            $enrollment_id   = (int)($input['enrollment_id'] ?? 0);
            $lesson_number   = (int)($input['lesson_number'] ?? 0);
            $lesson_summary  = $input['lesson_summary']   ?? null;
            $teacher_comment = $input['teacher_comment']  ?? null;

            if ($enrollment_id <= 0 || $lesson_number <= 0) {
                echo json_encode(['success' => false, 'data' => ['error' => 'enrollment_id and lesson_number required']]);
                exit;
            }

            $conn = $database->connect();

            // Verify lesson belongs to this teacher's enrollment
            $checkStmt = $conn->prepare(
                "SELECT l.*, us.user_id, us.credits, us.skill_name, e.learner_id
                 FROM lessons l
                 JOIN enrollments e ON l.enrollment_id = e.id
                 JOIN user_skills us ON e.offer_id = us.id
                 WHERE l.enrollment_id = ? AND l.lesson_number = ? AND us.user_id = ?"
            );
            $checkStmt->execute([$enrollment_id, $lesson_number, $user_id]);
            $lesson = $checkStmt->fetch();

            if (!$lesson) {
                echo json_encode(['success' => false, 'data' => ['error' => 'Lesson not found or access denied']]);
                exit;
            }

            if ($lesson['status'] === 'completed') {
                echo json_encode(['success' => false, 'data' => ['error' => 'Lesson already completed']]);
                exit;
            }

            // Check remaining lessons
            $eCheck = $conn->prepare("SELECT remaining_lessons, completed_lessons FROM enrollments WHERE id = ?");
            $eCheck->execute([$enrollment_id]);
            $eData = $eCheck->fetch();
            if ((int)($eData['remaining_lessons'] ?? 0) <= 0) {
                echo json_encode(['success' => false, 'data' => [
                    'error' => 'No remaining lessons to complete',
                    'completed_lessons' => (int)($eData['completed_lessons'] ?? 0),
                    'remaining_lessons' => 0,
                ]]);
                exit;
            }

            // Update lesson
            $updateLesson = $conn->prepare(
                "UPDATE lessons
                 SET status = 'completed',
                     completed_at = NOW(),
                     lesson_summary = ?,
                     teacher_comment = ?
                 WHERE enrollment_id = ? AND lesson_number = ?"
            );
            $updateLesson->execute([$lesson_summary, $teacher_comment, $enrollment_id, $lesson_number]);

            // Update enrollment counters
            $updateEnrollment = $conn->prepare(
                "UPDATE enrollments
                  SET completed_lessons = completed_lessons + 1,
                      remaining_lessons = remaining_lessons - 1
                  WHERE id = ?"
            );
            $updateEnrollment->execute([$enrollment_id]);

            // Auto-complete enrollment when all lessons are done
            $postStmt = $conn->prepare(
                "SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id = ?"
            );
            $postStmt->execute([$enrollment_id]);
            $postData = $postStmt->fetch(PDO::FETCH_ASSOC);
            $enrollmentCompleted = (int)($postData['remaining_lessons'] ?? 1) === 0;

            if ($enrollmentCompleted) {
                $conn->prepare(
                    "UPDATE enrollments SET status = 'completed', updated_at = NOW() WHERE id = ?"
                )->execute([$enrollment_id]);
            }

            // Per-lesson payment: transfer credits from learner to teacher
            $creditsPerSession = (int)($lesson['credits'] ?? 0);
            $learner_id = (int)($lesson['learner_id'] ?? 0);
            if ($creditsPerSession > 0 && $learner_id > 0) {
                $userModel->updateCredits($learner_id, -$creditsPerSession);
                $userModel->updateCredits($user_id, $creditsPerSession);
                $transaction->create($learner_id, 'spent', $creditsPerSession,
                    "Lesson {$lesson_number} completed: " . ($lesson['skill_name'] ?? 'Lesson'), $enrollment_id);
                $transaction->create($user_id, 'earned', $creditsPerSession,
                    "Lesson {$lesson_number} completed by learner", $enrollment_id);
            }

            // Fetch fresh stats
            $finalStmt = $conn->prepare("SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id = ?");
            $finalStmt->execute([$enrollment_id]);
            $finalStats = $finalStmt->fetch();

            echo json_encode(['success' => true, 'data' => [
                'message'              => 'Lesson marked as completed',
                'completed_lessons'    => (int)($finalStats['completed_lessons'] ?? 0),
                'remaining_lessons'    => (int)($finalStats['remaining_lessons'] ?? 0),
                'credits_transferred'  => $creditsPerSession,
                'enrollment_completed' => $enrollmentCompleted,
            ]]);
            break;

        // Update only the teacher_comment on a lesson (no credits, no counter change)
        case 'save_comment':
            $enrollment_id   = (int)($input['enrollment_id'] ?? 0);
            $lesson_number   = (int)($input['lesson_number'] ?? 0);
            $teacher_comment = trim($input['teacher_comment'] ?? '');

            if ($enrollment_id <= 0 || $lesson_number <= 0) {
                echo json_encode(['success' => false, 'data' => ['error' => 'enrollment_id and lesson_number required']]);
                exit;
            }
            if ($teacher_comment === '') {
                echo json_encode(['success' => false, 'data' => ['error' => 'Comment cannot be empty']]);
                exit;
            }

            $conn = $database->connect();

            // Verify lesson belongs to this teacher
            $chk = $conn->prepare(
                "SELECT l.id FROM lessons l
                 JOIN enrollments e ON l.enrollment_id = e.id
                 JOIN user_skills us ON e.offer_id = us.id
                 WHERE l.enrollment_id = ? AND l.lesson_number = ? AND us.user_id = ?"
            );
            $chk->execute([$enrollment_id, $lesson_number, $user_id]);
            if (!$chk->fetch()) {
                echo json_encode(['success' => false, 'data' => ['error' => 'Lesson not found or access denied']]);
                exit;
            }

            $upd = $conn->prepare(
                "UPDATE lessons
                 SET teacher_comment = ?, comment_created_at = NOW()
                 WHERE enrollment_id = ? AND lesson_number = ?"
            );
            $upd->execute([$teacher_comment, $enrollment_id, $lesson_number]);

            $fresh = $conn->prepare(
                "SELECT * FROM lessons WHERE enrollment_id = ? AND lesson_number = ?"
            );
            $fresh->execute([$enrollment_id, $lesson_number]);
            echo json_encode(['success' => true, 'data' => [
                'message' => 'Comment saved',
                'lesson'  => $fresh->fetch(),
            ]]);
            break;

        // Save / update the final teacher comment for a completed enrollment
        case 'save_final_comment':
            $enrollment_id       = (int)($input['enrollment_id'] ?? 0);
            $final_teacher_comment = trim($input['final_teacher_comment'] ?? '');

            if ($enrollment_id <= 0) {
                echo json_encode(['success' => false, 'data' => ['error' => 'enrollment_id required']]);
                exit;
            }
            if ($final_teacher_comment === '') {
                echo json_encode(['success' => false, 'data' => ['error' => 'Final comment cannot be empty']]);
                exit;
            }

            $conn2 = $database->connect();

            // Verify this teacher owns the enrollment
            $ownStmt = $conn2->prepare(
                "SELECT e.id, e.status, e.remaining_lessons
                   FROM enrollments e
                   JOIN user_skills us ON e.offer_id = us.id
                  WHERE e.id = ? AND us.user_id = ?"
            );
            $ownStmt->execute([$enrollment_id, $user_id]);
            $ownData = $ownStmt->fetch(PDO::FETCH_ASSOC);

            if (!$ownData) {
                echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment not found or access denied']]);
                exit;
            }
            if ((int)($ownData['remaining_lessons'] ?? 1) > 0) {
                echo json_encode(['success' => false, 'data' => ['error' => 'Cannot leave final comment until all lessons are completed']]);
                exit;
            }

            // Update enrollment status to completed if not already
            if (($ownData['status'] ?? '') !== 'completed') {
                $conn2->prepare("UPDATE enrollments SET status = 'completed', updated_at = NOW() WHERE id = ?")
                    ->execute([$enrollment_id]);
            }

            $updFinal = $conn2->prepare(
                "UPDATE enrollments
                    SET final_teacher_comment = ?, final_comment_created_at = NOW(), updated_at = NOW()
                  WHERE id = ?"
            );
            $updFinal->execute([$final_teacher_comment, $enrollment_id]);

            echo json_encode(['success' => true, 'data' => [
                'message'               => 'Final course comment saved',
                'final_teacher_comment' => $final_teacher_comment,
            ]]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'data' => ['error' => 'Unknown action. Use complete_lesson, save_comment, or save_final_comment']]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'data' => ['error' => 'Method not allowed']]);
