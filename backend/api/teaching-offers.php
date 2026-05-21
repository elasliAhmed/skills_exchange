<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserSkill.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../config/jwt.php';

$database = new Database();
$userSkill = new UserSkill($database);
$userModel = new User($database);
$enrollment = new Enrollment($database);
$transaction = new Transaction($database);

$user_id = JWT::getUserId();
if (!$user_id) {
    echo json_encode(['success' => false, 'data' => ['error' => 'Authentication required']]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    if (isset($_GET['my']) && $_GET['my'] === 'true') {
        $offers = $userSkill->getUserSkills($user_id, 'teach');
    } elseif (isset($_GET['learners'])) {
        $offer_id = (int)$_GET['learners'];
        $offer = $userSkill->getOfferById($offer_id);
        if (!$offer || $offer['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'data' => ['error' => 'Offer not found or unauthorized']]);
            exit;
        }
        $learners = $enrollment->getLearnersByOfferId($offer_id);
        echo json_encode(['success' => true, 'data' => ['learners' => $learners]]);
        exit;
    } else {
        $offers = $userSkill->getTeachingOffers();
    }
    
    foreach ($offers as &$offer) {
        $offer['average_rating'] = 0;
        $offer['total_reviews'] = 0;
    }
    
    echo json_encode(['success' => true, 'data' => ['offers' => $offers]]);
} elseif ($method === 'POST') {
    if (empty($input['skill_name']) || empty($input['credits'])) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Skill name and credits required']]);
        exit;
    }

    $existing = $userSkill->getOfferByUserAndSkillName($user_id, $input['skill_name']);
    if ($existing) {
        $userSkill->updateDetails(
            $user_id,
            $input['skill_name'],
            'teach',
            (int)$input['credits'],
            (int)($input['lessons_count'] ?? 1),
            $input['skill_description'] ?? null,
            $input['skill_level'] ?? null,
            $input['lesson_format'] ?? null,
            $input['learner_gains'] ?? null
        );
    } else {
        $userSkill->add(
            $user_id,
            $input['skill_name'],
            'teach',
            (int)$input['credits'],
            (int)($input['lessons_count'] ?? 1),
            $input['skill_description'] ?? null,
            $input['skill_level'] ?? null,
            $input['lesson_format'] ?? null,
            $input['learner_gains'] ?? null
        );
    }

    echo json_encode(['success' => true, 'data' => ['message' => 'Teaching offer added']]);
} elseif ($method === 'PUT') {
    if (empty($input['offer_id'])) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Offer ID required']]);
        exit;
    }
    $offer = $userSkill->getOfferById($input['offer_id']);
    if (!$offer || $offer['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Not found or unauthorized']]);
        exit;
    }
    $input['skill_name']        = $input['skill_name']        ?? $offer['skill_name'];
    $input['credits']           = (int)($input['credits']          ?? $offer['credits']);
    $input['lessons_count']     = (int)($input['lessons_count']    ?? $offer['lessons_count']     ?? 1);
    $input['skill_description'] = $input['skill_description'] ?? $offer['skill_description'] ?? null;
    $input['skill_level']       = $input['skill_level']       ?? $offer['skill_level']        ?? null;
    $input['lesson_format']     = $input['lesson_format']     ?? $offer['lesson_format']      ?? null;
    $input['learner_gains']     = $input['learner_gains']     ?? $offer['learner_gains']      ?? null;
    $userSkill->updateDetails(
        $user_id,
        $input['skill_name'], 'teach',
        (int)$input['credits'],
        (int)$input['lessons_count'],
        $input['skill_description'],
        $input['skill_level'],
        $input['lesson_format'],
        $input['learner_gains']
    );
    echo json_encode(['success' => true, 'data' => ['message' => 'Teaching offer updated']]);
} elseif ($method === 'DELETE') {
    if (empty($input['offer_id'])) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Offer ID required']]);
        exit;
    }

    $userSkillModel = new UserSkill($database);
    $offer = $userSkillModel->getOfferById($input['offer_id']);
    if (!$offer || $offer['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'data' => ['error' => 'Offer not found or unauthorized']]);
        exit;
    }

    $userSkillModel->removeByOfferId($input['offer_id']);
    echo json_encode(['success' => true, 'data' => ['message' => 'Offer removed']]);
}
