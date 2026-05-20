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
    if (empty($input['offer_id'])) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Offer ID required']]);
        exit;
    }
    $offer = $enrollment->getOfferById($input['offer_id']);
    if (!$offer) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Teaching offer not found']]);
        exit;
    }
    $offerOwner = $offer['store_user_id'] ?? $offer['user_id'];
    if ($offerOwner == $user_id) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Cannot enroll in your own offer']]);
        exit;
    }
    $existingEnrollment = $enrollment->getEnrollmentByUserAndOffer($user_id, $input['offer_id']);
    if ($existingEnrollment) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Already enrolled in this offer']]);
        exit;
    }
    $learner = $userModel->findById($user_id);
    $offerCost = $offer['credits'];
    if ($learner['credits'] < $offerCost) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Insufficient credits. Need ' . $offerCost . ' credits.']]);
        exit;
    }
    $userModel->updateCredits($user_id, -$offerCost);
    $userModel->updateCredits($offer['user_id'], $offerCost);
    $transaction->create($user_id, 'spent', $offerCost, 'Enrolled in teaching offer', $input['offer_id']);
    $transaction->create($offer['user_id'], 'earned', $offerCost, 'Enrollment in teaching offer', $input['offer_id']);
    $enrollment->create($input['offer_id'], $user_id);
    echo json_encode(['success' => true, 'data' => ['message' => 'Enrolled successfully']]);
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
} elseif ($method === 'PATCH' && !empty($input['action']) && $input['action'] === 'complete_lesson' && !empty($input['enrollment_id']) && !empty($input['lesson_number'])) {
    // Mark a specific lesson as completed
    $lessonId = $input['lesson_id'] ?? null;
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
    
    if ($lesson['status'] === 'completed') {
        echo json_encode(['success' => false, 'data' => ['error' => 'Lesson already completed']]);
        exit;
    }
    
    // Update the lesson
    $updateLessonQuery = "UPDATE lessons SET status = 'completed', completed_at = NOW(), lesson_summary = ?, teacher_comment = ? WHERE enrollment_id = ? AND lesson_number = ?";
    $updateLessonStmt = $conn->prepare($updateLessonQuery);
    $updateLessonStmt->execute([$lessonSummary, $teacherComment, $input['enrollment_id'], $lessonNumber]);
    
    // Update enrollment counters
    $updateEnrollmentQuery = "UPDATE enrollments SET completed_lessons = completed_lessons + 1, remaining_lessons = remaining_lessons - 1 WHERE id = ?";
    $updateEnrollmentStmt = $conn->prepare($updateEnrollmentQuery);
    $updateEnrollmentStmt->execute([$input['enrollment_id']]);
    
    echo json_encode(['success' => true, 'data' => ['message' => 'Lesson marked as completed']]);
} elseif ($method === 'PATCH' && !empty($input['action']) && $input['action'] === 'update_lesson_report' && !empty($input['enrollment_id']) && !empty($input['lesson_number'])) {
    // Update lesson summary and/or teacher comment
    $lessonId = $input['lesson_id'] ?? null;
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