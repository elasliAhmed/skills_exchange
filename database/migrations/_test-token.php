<?php
// Minimal unit test: saveComment via direct JWT decode
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/config/jwt.php';

// We know user_id=1 from the live DB; build a fake-but-valid token
$secret = 'your-secret-key-change-in-production';

// Encode header
$header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
// Encode payload (expires far in future)
$payload = base64UrlEncode(json_encode([
    'user_id'  => 1,
    'username' => 'ahmed',
    'exp'      => time() + 86400,
]));
// Signature
$sig = base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
$token = "$header.$payload.$sig";

echo "Token built\n";
$decoded = JWT::decode($token);
echo "Decoded user_id: " . ($decoded['user_id'] ?? 'N/A') . "\n";
echo "getUserId: " . (JWT::getUserId() ?? 'N/A') . "\n";
echo "getBearerToken: " . (JWT::getBearerToken() ?? 'N/A') . "\n";
