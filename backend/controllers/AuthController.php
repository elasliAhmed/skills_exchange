<?php
// Auth Controller
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/jwt.php';

class AuthController {
    private $user;

    public function __construct($database) {
        $this->user = new User($database);
    }

    public function register($data) {
        $errors = [];
        if (empty($data['username'])) $errors[] = 'Username is required';
        if (empty($data['email'])) $errors[] = 'Email is required';
        if (empty($data['password'])) $errors[] = 'Password is required';
        
        if (!empty($errors)) {
            $this->sendResponse(false, $errors, 400);
            return;
        }

        if ($this->user->findByUsername($data['username'])) {
            $this->sendResponse(false, ['Username already exists'], 400);
            return;
        }

        if ($this->user->findByEmail($data['email'])) {
            $this->sendResponse(false, ['Email already exists'], 400);
            return;
        }

        $id = $this->user->create(
            $data['username'], 
            $data['email'], 
            $data['password'], 
            $data['full_name'] ?? null
        );

        $this->sendResponse(true, ['message' => 'User registered successfully', 'user_id' => $id], 201);
    }

    public function login($data) {
        if (empty($data['username']) || empty($data['password'])) {
            $this->sendResponse(false, ['Username and password are required'], 400);
            return;
        }

        if (!$this->user->verifyPassword($data['username'], $data['password'])) {
            $this->sendResponse(false, ['Invalid credentials'], 401);
            return;
        }

        $user = $this->user->findByUsername($data['username']);
        $token = JWT::generate($user['id'], $user['username']);

        $this->sendResponse(true, [
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'credits' => $user['credits']
            ]
        ]);
    }

    public function verifyToken() {
        $user_id = JWT::getUserId();
        if (!$user_id) {
            $this->sendResponse(false, ['Invalid or expired token'], 401);
            return;
        }

        $user = $this->user->findById($user_id);
        if (!$user) {
            $this->sendResponse(false, ['User not found'], 404);
            return;
        }

        $this->sendResponse(true, ['user' => $user]);
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