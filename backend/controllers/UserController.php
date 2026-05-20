<?php
// User Controller
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserSkill.php';
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../config/jwt.php';

class UserController {
    private $user;
    private $userSkill;
    private $review;

    public function __construct($database) {
        $this->user = new User($database);
        $this->userSkill = new UserSkill($database);
        $this->review = new Review($database);
    }

    public function getProfile($id = null) {
        $user_id = $id ?? JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $profile = $this->user->findById($user_id);
        if (!$profile) {
            $this->sendResponse(false, ['User not found'], 404);
            return;
        }

        $teach_skills = $this->userSkill->getUserSkills($user_id, 'teach');
        $learn_skills = $this->userSkill->getUserSkills($user_id, 'learn');
        $ratings = $this->review->getAverageRating($user_id);

        $this->sendResponse(true, [
            'profile' => $profile,
            'teach_skills' => $teach_skills,
            'learn_skills' => $learn_skills,
            'average_rating' => $ratings['average'],
            'total_reviews' => $ratings['count']
        ]);
    }

    public function updateProfile($data) {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $allowed_fields = ['full_name', 'bio', 'profile_picture'];
        $update_data = array_intersect_key($data, array_flip($allowed_fields));

        if ($this->user->updateProfile($user_id, $update_data)) {
            $this->sendResponse(true, ['message' => 'Profile updated successfully']);
        } else {
            $this->sendResponse(false, ['Failed to update profile'], 500);
        }
    }

    public function searchBySkill($skill_name) {
        $results = $this->userSkill->searchBySkill($skill_name);
        $this->sendResponse(true, ['results' => $results]);
    }

    public function getPublicProfile($id) {
        $profile = $this->user->findById($id);
        if (!$profile) {
            $this->sendResponse(false, ['User not found'], 404);
            return;
        }

        $teach_skills = $this->userSkill->getUserSkills($id, 'teach');
        $ratings = $this->review->getAverageRating($id);

        $this->sendResponse(true, [
            'profile' => $profile,
            'teach_skills' => $teach_skills,
            'average_rating' => $ratings['average'],
            'total_reviews' => $ratings['count']
        ]);
    }

    private function sendResponse($success, $data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            'data' => $data
        ]);
    }
}