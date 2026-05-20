<?php
// JWT Authentication helper
class JWT {
    private static $secret_key = 'your-secret-key-change-this-in-production';
    private static $token_validity = 86400; // 24 hours

    public static function generate($user_id, $username) {
        $header = base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64UrlEncode(json_encode([
            'user_id' => $user_id,
            'username' => $username,
            'exp' => time() + self::$token_validity
        ]));
        $signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", self::$secret_key, true));
        return "$header.$payload.$signature";
    }

    public static function decode($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $expected_signature = base64UrlEncode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], self::$secret_key, true));
        if ($parts[2] !== $expected_signature) {
            return null;
        }
        
        $payload = base64UrlDecode($parts[1]);
        $payload_data = json_decode($payload, true);
        if (!$payload_data || ($payload_data['exp'] ?? 0) < time()) {
            return null;
        }
        
        return $payload_data;
    }

    public static function getBearerToken() {
        // Get authorization header from various sources
        $authHeader = null;
        
        // Try getallheaders() first (more reliable in newer PHP)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }
        
        // Try apache_request_headers
        if (!$authHeader && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }
        
        // Try $_SERVER
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        // Try REDIRECT_HTTP_AUTHORIZATION (for CGI)
        if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        
        // Write to temp log for debugging
        file_put_contents(sys_get_temp_dir() . '/jwt_debug.log',
            date('Y-m-d H:i:s') . " authHeader=" . var_export($authHeader, true) .
            " SERVER=" . var_export($_SERVER['HTTP_AUTHORIZATION'] ?? 'N/A', true) .
            " REDIRECT=" . var_export($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'N/A', true) . "\n",
            FILE_APPEND
        );
        
        if ($authHeader) {
            $matches = [];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    public static function getUserId() {
        $token = self::getBearerToken();
        if (!$token) return null;
        $decoded = self::decode($token);
        return $decoded ? $decoded['user_id'] : null;
    }
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}