<?php
// Lesson Request Controller
require_once __DIR__ . '/../models/LessonRequest.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../config/jwt.php';

class LessonRequestController {
    private $lessonRequest;
    private $user;
    private $transaction;
    private $credit_cost = 5;

    public function __construct($database) {
        $this->lessonRequest = new LessonRequest($database);
        $this->user = new User($database);
        $this->transaction = new Transaction($database);
    }

    public function createRequest($data) {
        $requester_id = JWT::getUserId();
        if (!$requester_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $required = ['teacher_id', 'skill_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(false, ["$field is required"], 400);
                return;
            }
        }

        $requester = $this->user->findById($requester_id);
        if ($requester['credits'] < $this->credit_cost) {
            $this->sendResponse(false, ['Insufficient credits. Request costs ' . $this->credit_cost . ' credits.'], 400);
            return;
        }

        $this->lessonRequest->create(
            $requester_id, 
            $data['teacher_id'], 
            $data['skill_id'], 
            $data['message'] ?? null,
            $data['scheduled_time'] ?? null
        );

        $this->user->updateCredits($requester_id, -$this->credit_cost);
        $this->transaction->create($requester_id, 'spent', $this->credit_cost, 'Lesson request', null);

        $this->sendResponse(true, ['message' => 'Lesson request sent successfully']);
    }

    public function getRequests($type = 'all') {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $requests = $this->lessonRequest->getUserRequests($user_id, $type);
        $this->sendResponse(true, ['requests' => $requests]);
    }

    public function updateRequestStatus($id, $status) {
        $teacher_id = JWT::getUserId();
        if (!$teacher_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $request = $this->lessonRequest->findById($id);
        if (!$request || $request['teacher_id'] != $teacher_id) {
            $this->sendResponse(false, ['Request not found or unauthorized'], 404);
            return;
        }

        if (!in_array($status, ['accepted', 'rejected'])) {
            $this->sendResponse(false, ['Invalid status'], 400);
            return;
        }

        $this->lessonRequest->updateStatus($id, $status);

        if ($status === 'accepted') {
            $this->user->updateCredits($teacher_id, $this->credit_cost);
            $this->transaction->create($teacher_id, 'earned', $this->credit_cost, 'Lesson accepted', $id);
        }

        $this->sendResponse(true, ['message' => "Request $status successfully"]);
    }

    public function completeRequest($id) {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $request = $this->lessonRequest->findById($id);
        if (!$request || ($request['teacher_id'] != $user_id && $request['requester_id'] != $user_id)) {
            $this->sendResponse(false, ['Request not found or unauthorized'], 404);
            return;
        }

        $this->lessonRequest->updateStatus($id, 'completed');
        $this->sendResponse(true, ['message' => 'Request marked as completed']);
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