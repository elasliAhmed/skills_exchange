<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();

$stmt = $c->query("SHOW COLUMNS FROM lessons LIKE 'comment_created_at'");
if ($stmt->rowCount() === 0) {
    $c->exec("ALTER TABLE lessons ADD COLUMN comment_created_at TIMESTAMP NULL DEFAULT NULL AFTER teacher_comment");
    echo "comment_created_at added\n";
} else {
    echo "comment_created_at already exists\n";
}

echo "\nCurrent columns:\n";
foreach ($c->query("SHOW COLUMNS FROM lessons")->fetchAll() as $r) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Default:" . ($r['Default'] ?? 'NULL') . "\n";
}
