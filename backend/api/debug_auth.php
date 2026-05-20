<?php
// Dump JWT headers received by PHP
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../config/jwt.php';

echo "getallheaders() works: " . (function_exists('getallheaders') ? 'YES' : 'NO') . "\n\n";

if (function_exists('getallheaders')) {
    echo "--- getallheaders() ---\n";
    print_r(getallheaders());
}

echo "\n--- \$_SERVER relevant keys ---\n";
foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization', 'CONTENT_TYPE'] as $k) {
    echo "$k => " . ($_SERVER[$k] ?? 'NOT SET') . "\n";
}

echo "\n--- JWT::getBearerToken() ---\n";
var_dump(JWT::getBearerToken());

echo "\n--- JWT::getUserId() ---\n";
var_dump(JWT::getUserId());
