<?php
// Helper: get the token for user 'ahmed' and test the comment endpoint
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/config/jwt.php';

// Hit login to get a fresh token
$ch = curl_init('http://localhost/skills_exchange/backend/api/login.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['username' => 'ahmed', 'password' => 'test']),
    CURLOPT_RETURNTRANSFER => true,
]);
$resp = curl_exec($ch);
curl_close($ch);
$login = json_decode($resp, true);
$token = $login['data']['token'] ?? null;
echo "Login: " . ($login['success'] ? 'OK (uid=' . $login['data']['user']['id'] . ')' : 'FAIL') . "\n";

if (!$token) { exit; }

// POST comment
$ch2 = curl_init('http://localhost/skills_exchange/backend/api/lessons.php/comment');
curl_setopt_array($ch2, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode(['lesson_id' => 1, 'teacher_comment' => 'Great HTML practice session']),
    CURLOPT_RETURNTRANSFER => true,
]);
$resp2 = curl_exec($ch2);
curl_close($ch2);
echo "Save comment: " . $resp2 . "\n";
