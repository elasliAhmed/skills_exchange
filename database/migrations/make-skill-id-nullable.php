<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();

// Find the real FK name
$fks = $c->query("SELECT CONSTRAINT_NAME, COLUMN_NAME
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'user_skills'
                    AND REFERENCED_TABLE_NAME IS NOT NULL")
     ->fetchAll(PDO::FETCH_ASSOC);

foreach ($fks as $fk) {
    echo "Dropping FK: {$fk['CONSTRAINT_NAME']} on {$fk['COLUMN_NAME']}\n";
    $c->exec("ALTER TABLE user_skills DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
}

// Drop the index that the FK created (MySQL keeps the index)
foreach ($fks as $fk) {
    $c->exec("ALTER TABLE user_skills DROP INDEX `{$fk['CONSTRAINT_NAME']}`");
    echo "Dropped index: {$fk['CONSTRAINT_NAME']}\n";
}

// Now make skill_id nullable
$c->exec("ALTER TABLE user_skills MODIFY COLUMN skill_id INT DEFAULT NULL");
$col = $c->query("SHOW COLUMNS FROM user_skills LIKE 'skill_id'")->fetch();
echo "skill_id | Null: {$col['Null']} | Default: " . ($col['Default'] ?? 'NULL') . "\n";

echo "\nDone. Current columns:\n";
foreach ($c->query("SHOW COLUMNS FROM user_skills")->fetchAll() as $r) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Default:" . ($r['Default'] ?? 'NULL') . "\n";
}
