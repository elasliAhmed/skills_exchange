<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->connect();

$cols = $conn->query("SHOW COLUMNS FROM user_skills")->fetchAll(PDO::FETCH_ASSOC);
$indexes = $conn->query("SHOW INDEX FROM user_skills")->fetchAll(PDO::FETCH_ASSOC);
$count = $conn->query("SELECT COUNT(*) AS cnt FROM user_skills")->fetch();

echo json_encode([
    'columns'      => $cols,
    'indexes'      => $indexes,
    'row_count'    => $count,
    'create_table' => $conn->query("SHOW CREATE TABLE user_skills")->fetchColumn(1),
], JSON_PRETTY_PRINT);
