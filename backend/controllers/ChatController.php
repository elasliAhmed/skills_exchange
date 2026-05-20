<?php
// Chat Controller
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../config/jwt.php';

class ChatController {
    private $message;

    public function __construct($database) {
        $this->message = new Message($database);
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

        $this->message->send(
            $sender_id, 
            $data['receiver_id'], 
            $data['message'], 
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