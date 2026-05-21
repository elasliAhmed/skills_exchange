<?php
// Student Dashboard API — stats and enrollments for students
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../config/jwt.php';

$database = new Database();
$enrollment = new Enrollment($database);

$user_id = JWT::getUserId();
if (!$user_id) {
    echo json_encode(['success' => false, 'data' => ['error' => 'Authentication required']]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

if ($method === 'GET') {
    switch ($endpoint) {
        // Student stats
        case 'stats':
            $conn = $database->connect();
            $stmt = $conn->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(completed_lessons) as total_completed_lessons
                 FROM enrollments WHERE learner_id = ?"
            );
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_enrollments' => (int)($stats['total'] ?? 0),
                    'active_enrollments' => (int)($stats['active'] ?? 0),
                    'completed_enrollments' => (int)($stats['completed'] ?? 0),
                    'total_completed_lessons' => (int)($stats['total_completed_lessons'] ?? 0),
                ]
            ]);
            break;

        // Student enrollments list
        case 'enrollments':
            $enrollments = $enrollment->getLearnerEnrollments($user_id);
            
            // Add computed progress info
            foreach ($enrollments as &$e) {
                $e['progress_text'] = ($e['completed_lessons'] ?? 0) . '/' . ($e['lessons_count'] ?? 0) . ' lessons';
            }
            
            echo json_encode([
                'success' => true,
                'data' => ['enrollments' => $enrollments]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'data' => ['error' => 'Unknown endpoint. Use stats or enrollments']]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'data' => ['error' => 'Method not allowed']]);