<?php
// Messaging API — one conversation per user pair, secured via shared enrollments
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../config/jwt.php';

$database = new Database();
$messageModel = new Message($database);
$enrollmentModel = new Enrollment($database);

$user_id = JWT::getUserId();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'data' => ['error' => 'Authentication required']]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$endpoint = $_GET['endpoint'] ?? '';

function respond($success, $data, $status = 200) {
    http_response_code($status);
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

if ($method === 'GET') {
    if ($endpoint === 'unread') {
        respond(true, ['unread_count' => $messageModel->getUnreadTotal($user_id)]);
    }

    if ($endpoint === 'conversations') {
        respond(true, ['conversations' => $messageModel->getUserConversations($user_id)]);
    }

    $other_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if (!$other_id) {
        respond(false, ['error' => 'user_id is required'], 400);
    }
    if ($other_id === (int)$user_id) {
        respond(false, ['error' => 'Invalid user'], 400);
    }
    if (!$enrollmentModel->usersShareEnrollment($user_id, $other_id)) {
        respond(false, ['error' => 'You can only message users you share an enrollment with'], 403);
    }

    $messages = $messageModel->getBetweenUsers($user_id, $other_id);
    $messageModel->markAsReadBetweenUsers($user_id, $other_id);

    $conv = array_filter(
        $messageModel->getUserConversations($user_id),
        fn($c) => (int)$c['other_user_id'] === $other_id
    );
    $meta = $conv ? reset($conv) : null;

    respond(true, [
        'messages' => $messages,
        'partner' => [
            'user_id' => $other_id,
            'username' => $meta['other_username'] ?? null,
            'full_name' => $meta['other_full_name'] ?? null,
            'course_titles' => $meta['course_titles'] ?? null,
        ],
    ]);
}

if ($method === 'POST') {
    $receiver_id = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
    $text = trim($input['message'] ?? '');
    $enrollment_id = isset($input['enrollment_id']) ? (int)$input['enrollment_id'] : 0;

    if (!$receiver_id) {
        respond(false, ['error' => 'receiver_id is required'], 400);
    }
    if ($text === '') {
        respond(false, ['error' => 'message cannot be empty'], 400);
    }
    if (strlen($text) > 2000) {
        respond(false, ['error' => 'message is too long (max 2000 characters)'], 400);
    }
    if ($receiver_id === (int)$user_id) {
        respond(false, ['error' => 'Cannot message yourself'], 400);
    }
    if (!$enrollmentModel->usersShareEnrollment($user_id, $receiver_id)) {
        respond(false, ['error' => 'You can only message users you share an enrollment with'], 403);
    }

    if ($enrollment_id) {
        if (!$enrollmentModel->userCanAccessEnrollment($user_id, $enrollment_id)) {
            respond(false, ['error' => 'You do not have access to this enrollment'], 403);
        }
        if (!$enrollmentModel->receiverBelongsToEnrollment($enrollment_id, $receiver_id)) {
            respond(false, ['error' => 'Receiver is not part of this enrollment'], 403);
        }
    } else {
        $enrollment_id = $enrollmentModel->getSharedEnrollmentId($user_id, $receiver_id);
        if (!$enrollment_id) {
            respond(false, ['error' => 'No shared enrollment found'], 403);
        }
    }

    if (!$messageModel->send($user_id, $receiver_id, $enrollment_id, $text)) {
        respond(false, ['error' => 'Failed to send message'], 500);
    }
    respond(true, ['message' => 'Message sent successfully']);
}

respond(false, ['error' => 'Method not allowed'], 405);
