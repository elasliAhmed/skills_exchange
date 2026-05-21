<?php
// Chat Controller
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../config/jwt.php';

class ChatController {
    private $message;
    private $enrollment;

    public function __construct($database) {
        $this->message = new Message($database);
        $this->enrollment = new Enrollment($database);
    }

    private function canMessageUsers($user_id, $other_id) {
        return $this->enrollment->usersShareEnrollment($user_id, (int)$other_id);
    }

    public function sendMessage($data) {
        $sender_id = JWT::getUserId();
        if (!$sender_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $required = ['receiver_id', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(false, ["$field is required"], 400);
                return;
            }
        }

        $receiver_id = (int)$data['receiver_id'];
        if ($receiver_id === (int)$sender_id) {
            $this->sendResponse(false, ['Cannot message yourself'], 400);
            return;
        }
        if (!$this->canMessageUsers($sender_id, $receiver_id)) {
            $this->sendResponse(false, ['You can only message users you share an enrollment with'], 403);
            return;
        }

        $this->message->send(
            $sender_id,
            $receiver_id,
            trim($data['message']),
            $data['lesson_request_id'] ?? null
        );

        $this->sendResponse(true, ['message' => 'Message sent successfully']);
    }

    public function getConversation($user_id, $lesson_request_id = null) {
        $current_user_id = JWT::getUserId();
        if (!$current_user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        if (!$lesson_request_id && !$this->canMessageUsers($current_user_id, $user_id)) {
            $this->sendResponse(false, ['You can only message users you share an enrollment with'], 403);
            return;
        }

        $messages = $this->message->getConversation($current_user_id, $user_id, $lesson_request_id);
        $this->message->markAsRead($user_id, $current_user_id);

        $this->sendResponse(true, ['messages' => $messages]);
    }

    public function getConversations() {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Authentication required'], 401);
            return;
        }

        $conversations = $this->message->getUserConversations($user_id);
        $this->sendResponse(true, ['conversations' => $conversations]);
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