<?php
// API Router
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/LessonController.php';
require_once __DIR__ . '/../controllers/ChatController.php';
require_once __DIR__ . '/../controllers/ReviewController.php';

$database = new Database();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Find 'api' in path (handles subfolder installations)
$api_index = array_search('api', $path_parts);
if ($api_index === false) {
    http_response_code(404);
    echo json_encode(['success' => false, 'data' => ['error' => 'Invalid API path']]);
    exit;
}

// Reindex path parts to start from 'api'
$path_parts = array_slice($path_parts, $api_index);

$userController = new UserController($database);
$authController = new AuthController($database);
$chatController = new ChatController($database);
$reviewController = new ReviewController($database);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// API Routes
$resource = $path_parts[1] ?? '';
$id = $path_parts[2] ?? null;

switch ($resource) {
    // Auth routes
    case 'register':
        if ($method === 'POST') $authController->register($input);
        break;

    case 'login':
        if ($method === 'POST') $authController->login($input);
        break;

    case 'verify':
        if ($method === 'GET') $authController->verifyToken();
        break;

    // User routes
    case 'profile':
        if ($method === 'GET') $userController->getProfile($id);
        if ($method === 'PUT') $userController->updateProfile($input);
        break;

    case 'users':
        if ($id && $method === 'GET') $userController->getPublicProfile($id);
        break;

    case 'search':
        if ($method === 'GET' && isset($_GET['skill'])) $userController->searchBySkill($_GET['skill']);
        break;

    // Teaching offers
    case 'teaching-offers':
        require __DIR__ . '/../api/teaching-offers.php';
        break;

    // Enrollments
    case 'enrollments':
        require __DIR__ . '/../api/enrollments.php';
        break;

    // Lessons
    case 'lessons':
        require __DIR__ . '/../api/lessons.php';
        break;

    // Chat
    case 'messages':
        if ($method === 'GET' && isset($_GET['user_id'])) $chatController->getConversation($_GET['user_id'], $_GET['lesson_request_id'] ?? null);
        if ($method === 'POST') $chatController->sendMessage($input);
        break;

    case 'conversations':
        if ($method === 'GET') $chatController->getConversations();
        break;

    // Reviews
    case 'reviews':
        if ($method === 'POST') $reviewController->createReview($input);
        if ($method === 'GET' && $id) $reviewController->getUserReviews($id);
        break;

    case 'rating':
        if ($method === 'GET' && $id) $reviewController->getAverageRating($id);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'data' => ['error' => 'Endpoint not found']]);
        exit(0);
}
}