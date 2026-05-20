<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
$userModel   = new User($database);
$transaction = new Transaction($database);

$user_id = JWT::getUserId();
if (!$user_id) {
    echo json_encode(['success' => false, 'data' => ['error' => 'Authentication required']]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $type = $input['type'] ?? $_GET['type'] ?? 'learner';
    
    if ($type === 'teacher') {
        $enrollments = $enrollment->getTeacherEnrollments($user_id);
    } else {
        $enrollments = $enrollment->getLearnerEnrollments($user_id);
    }
    
    echo json_encode(['success' => true, 'data' => ['enrollments' => $enrollments]]);
} elseif ($method === 'POST') {
    // Handle complete_lesson via POST
    if (!empty($input['action']) && $input['action'] === 'complete_lesson') {
        $enrollment_id   = (int)($input['enrollment_id'] ?? 0);
        $lesson_number   = (int)($input['lesson_number'] ?? 0);
        $lessonSummary   = $input['lesson_summary'] ?? null;
        $teacherComment  = $input['teacher_comment'] ?? null;

        if ($enrollment_id <= 0 || $lesson_number <= 0) {
            echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment ID and lesson number required']]);
            exit;
        }

        $conn = $database->connect();

        // Verify the enrolled learner and fetch offer details
        $enrollQuery = "SELECT e.*, us.credits AS credits_per_session, us.skill_name,
                           u.username AS learner_username, u.full_name AS learner_name,
                           us.user_id AS teacher_id, e.learner_id
                    FROM enrollments e
                    JOIN user_skills us ON e.offer_id = us.id
                    JOIN users u ON e.learner_id = u.id
                    WHERE e.id = ?";
        $enrollStmt = $conn->prepare($enrollQuery);
        $enrollStmt->execute([$enrollment_id]);
        $enrollmentData = $enrollStmt->fetch();
        $teacher_id = (int)($enrollmentData['teacher_id'] ?? 0);

        if (!$enrollmentData) {
            echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment not found']]);
            exit;
        }

        // Guard: only the teacher who owns the offer may complete lessons
        if ($teacher_id !== $user_id) {
            echo json_encode(['success' => false, 'data' => ['error' => 'You are not the owner of this offer']]);
            exit;
        }

        // Guard: don't allow completion when no lessons remain
        $remaining = (int)($enrollmentData['remaining_lessons'] ?? 0);
        if ($remaining <= 0) {
            echo json_encode(['success' => false, 'data' => [
                'error' => 'No remaining lessons to complete',
                'completed_lessons' => (int)($enrollmentData['completed_lessons'] ?? 0),
                'remaining_lessons' => 0,
            ]]);
            exit;
        }

        // Get the lesson row
        $lessonQuery = "SELECT * FROM lessons WHERE enrollment_id = ? AND lesson_number = ?";
        $lessonStmt  = $conn->prepare($lessonQuery);
        $lessonStmt->execute([$enrollment_id, $lesson_number]);
        $lesson = $lessonStmt->fetch();

        if (!$lesson) {
            echo json_encode(['success' => false, 'data' => ['error' => 'Lesson not found']]);
            exit;
        }

        // Guard: don't allow double-completion
        if ($lesson['status'] === 'completed') {
            echo json_encode(['success' => false, 'data' => ['error' => 'Lesson already completed']]);
            exit;
        }

        // 1. Mark lesson as completed
        $updateLessonQuery = "UPDATE lessons
                               SET status = 'completed',
                                   completed_at = NOW(),
                                   lesson_summary = ?,
                                   teacher_comment = ?
                               WHERE enrollment_id = ? AND lesson_number = ?";
        $updateLessonStmt = $conn->prepare($updateLessonQuery);
        $updateLessonStmt->execute([$lessonSummary, $teacherComment, $enrollment_id, $lesson_number]);

        // 2. Advance enrollment counters
        $updateEnrollmentQuery = "UPDATE enrollments
                                   SET completed_lessons = completed_lessons + 1,
                                       remaining_lessons = remaining_lessons - 1
                                   WHERE id = ?";
        $updateEnrollmentStmt = $conn->prepare($updateEnrollmentQuery);
        $updateEnrollmentStmt->execute([$enrollment_id]);

        // 3. Per-lesson payment: transfer credits from learner to teacher
        $creditsPerSession = (int)($enrollmentData['credits_per_session'] ?? 0);
        if ($creditsPerSession > 0) {
            $learner_id = (int)($enrollmentData['learner_id'] ?? 0);
            $userModel->updateCredits($learner_id, -$creditsPerSession);
            $userModel->updateCredits($teacher_id, $creditsPerSession);
            if ($teacher_id > 0) {
                $transaction->create($learner_id, 'spent', $creditsPerSession,
                    "Lesson {$lesson_number} completed: " . ($enrollmentData['skill_name'] ?? 'Lesson'), $enrollment_id);
                $transaction->create($teacher_id, 'earned', $creditsPerSession,
                    "Lesson {$lesson_number} completed by " . ($enrollmentData['learner_username'] ?? 'learner'), $enrollment_id);
            }
        }

        // 4. Fetch fresh stats for the response
        $finalStmt = $conn->prepare("SELECT completed_lessons, remaining_lessons FROM enrollments WHERE id = ?");
        $finalStmt->execute([$enrollment_id]);
        $finalStats = $finalStmt->fetch();

        echo json_encode(['success' => true, 'data' => [
            'message'           => 'Lesson marked as completed',
            'completed_lessons' => (int)($finalStats['completed_lessons'] ?? 0),
            'remaining_lessons' => (int)($finalStats['remaining_lessons'] ?? 0),
            'credits_transferred'=> $creditsPerSession ?? 0,
        ]]);
        exit;
    }
    
    // Original enrollment creation logic
    if (empty($input['offer_id'])) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Offer ID required']]);
        exit;
    }
    $offer = $enrollment->getOfferById($input['offer_id']);
    if (!$offer) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Teaching offer not found']]);
        exit;
    }
    $offerOwner = $offer['store_user_id'] ?? $offer['user_id'] ?? null;
    if ($offerOwner && $offerOwner == $user_id) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Cannot enroll in your own offer']]);
        exit;
    }
    $existingEnrollment = $enrollment->getEnrollmentByUserAndOffer($user_id, $input['offer_id']);
    if ($existingEnrollment) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Already enrolled in this offer']]);
        exit;
    }
    // Create enrollment — no upfront payment.
    // Credits are earned per completed lesson (see PATCH complete_lesson).
    // create() returns [success: bool, enrollment_id: int]
    [$ok, $new_id] = $enrollment->create($input['offer_id'], $user_id);
    if (!$ok || $new_id <= 0) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Failed to create enrollment']]);
        exit;
    }
    echo json_encode(['success' => true, 'data' => [
        'message'       => 'Enrolled successfully. No upfront payment — teacher is paid per completed lesson.',
        'enrollment_id' => $new_id,
    ]]);
} elseif ($method === 'PUT') {
    if (empty($input['enrollment_id']) || empty($input['status'])) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment ID and status required']]);
        exit;
    }
    
    $validStatuses = ['active', 'completed', 'cancelled'];
    if (!in_array($input['status'], $validStatuses)) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Invalid status']]);
        exit;
    }
    
    $enrollment->updateStatus($input['enrollment_id'], $input['status']);
    echo json_encode(['success' => true, 'data' => ['message' => 'Enrollment updated']]);
} elseif ($method === 'PATCH' && !empty($input['action']) && $input['action'] === 'update_lesson_report' && !empty($input['enrollment_id']) && !empty($input['lesson_number'])) {
    // Update lesson summary and/or teacher comment
    $lessonNumber = (int)$input['lesson_number'];
    $lessonSummary = $input['lesson_summary'] ?? null;
    $teacherComment = $input['teacher_comment'] ?? null;
    
    $conn = $database->connect();
    
    // Get the lesson
    $lessonQuery = "SELECT * FROM lessons WHERE enrollment_id = ? AND lesson_number = ?";
    $lessonStmt = $conn->prepare($lessonQuery);
    $lessonStmt->execute([$input['enrollment_id'], $lessonNumber]);
    $lesson = $lessonStmt->fetch();
    
    if (!$lesson) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Lesson not found']]);
        exit;
    }
    
    // Update the lesson report
    $updateLessonQuery = "UPDATE lessons SET lesson_summary = ?, teacher_comment = ? WHERE enrollment_id = ? AND lesson_number = ?";
    $updateLessonStmt = $conn->prepare($updateLessonQuery);
    $updateLessonStmt->execute([$lessonSummary, $teacherComment, $input['enrollment_id'], $lessonNumber]);
    
    echo json_encode(['success' => true, 'data' => ['message' => 'Lesson report updated']]);
}