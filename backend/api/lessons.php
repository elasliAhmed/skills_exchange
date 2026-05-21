<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/LessonController.php';

$database = new Database();
$controller = new LessonController($database);

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// Parse path: /lessons.php/[segment]/[id]
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// The URI will be like /skills_exchange/backend/api/lessons.php/comment
$segments  = explode('/', trim($uri, '/'));
$lessonIdx = array_search('lessons.php', $segments);
$segment   = $segments[$lessonIdx + 1] ?? null;
$id        = $segments[$lessonIdx + 2] ?? null;
$sub       = $segments[$lessonIdx + 3] ?? null;

if ($method === 'POST' && $segment === 'comment') {
    $controller->saveComment($input);
} elseif ($method === 'GET' && $segment === 'enrollment' && $id) {
    $controller->getByEnrollment((int)$id);
} elseif ($method === 'GET' && $segment === 'enrollment' && $id && $sub === 'final-comment') {
    // Return only the final teacher comment for a student who owns the enrollment
    $conn = $database->connect();
    $enr_id = (int)$id;
    $row = $conn->prepare(
        "SELECT e.id, e.status, e.final_teacher_comment, e.final_comment_created_at
           FROM enrollments e
          WHERE e.id = ?"
    );
    $row->execute([$enr_id]);
    $enr = $row->fetch(PDO::FETCH_ASSOC);
    if (!$enr) {
        http_response_code(404);
        echo json_encode(['success' => false, 'data' => ['error' => 'Enrollment not found']]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'data'    => [
            'enrollment_id'            => $enr_id,
            'status'                   => $enr['status'],
            'final_teacher_comment'    => $enr['final_teacher_comment'],
            'final_comment_created_at' => $enr['final_comment_created_at'],
        ]
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'data' => ['error' => 'Endpoint not found']]);
}
