<?php
header('Content-Type: text/plain');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

echo "=== TEMP DIR ===\n";
echo sys_get_temp_dir() . "\n";

echo "\n=== $_SERVER keys with AUTH/AUTHZ ===\n";
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'auth') !== false || stripos($k, 'AUTH') !== false) {
        echo "$k => $v\n";
    }
}

echo "\n=== getallheaders() (getallheaders=" . (function_exists('getallheaders') ? 'YES' : 'NO') . ") ===\n";
if (function_exists('getallheaders')) {
    $h = getallheaders();
    foreach ($h as $k => $v) {
        if (stripos($k, 'authorization') !== false) {
            echo "  $k => $v\n";
        } else {
            echo "  $k => (hidden)\n";
        }
    }
}

echo "\n=== apache_request_headers() (exists=" . (function_exists('apache_request_headers') ? 'YES' : 'NO') . ") ===\n";
if (function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    foreach ($h as $k => $v) {
        if (stripos($k, 'authorization') !== false) {
            echo "  $k => $v\n";
        } else {
            echo "  $k => (hidden)\n";
        }
    }
}

echo "\n=== $_SERVER['REMOTE_USER'] ===\n";
echo ($_SERVER['REMOTE_USER'] ?? 'NOT SET') . "\n";

echo "\n=== PHP_SELF ===\n";
echo $_SERVER['PHP_SELF'] . "\n";
