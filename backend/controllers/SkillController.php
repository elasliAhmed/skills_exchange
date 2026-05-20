<?php
// Skill Controller — skills table removed; see Skill.php stub
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../models/UserSkill.php';
require_once __DIR__ . '/../config/jwt.php';

class SkillController {
    private $userSkill;

    public function __construct($database) {
        $this->userSkill = new UserSkill($database);
    }

    public function getAllSkills() {
        $this->sendResponse(true, ['skills' => []]);
    }

    public function addSkill($data) {
        $this->sendResponse(false, ['Skill catalogue removed — enter skill name directly on forms'], 410);
    }

    public function removeSkill($data) {
        $this->sendResponse(false, ['Skill catalogue removed'], 410);
    }

    public function getUserSkills() {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $teach_skills = $this->userSkill->getUserSkills($user_id, 'teach');
        $learn_skills = $this->userSkill->getUserSkills($user_id, 'learn');

        $this->sendResponse(true, [
            'teach_skills' => $teach_skills,
            'learn_skills' => $learn_skills
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
