<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();
foreach ($c->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    echo $t . "\n";
}
