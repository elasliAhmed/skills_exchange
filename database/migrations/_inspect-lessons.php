<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();

echo "=== lessons columns ===\n";
foreach ($c->query("SHOW COLUMNS FROM lessons")->fetchAll() as $r) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Default:" . ($r['Default'] ?? 'NULL') . "\n";
}

echo "\n=== lessons CREATE TABLE ===\n";
echo $c->query("SHOW CREATE TABLE lessons")->fetchColumn(1) . "\n";

echo "\n=== Sample rows ===\n";
$rows = $c->query("SELECT * FROM lessons LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    print_r($r);
}
