<?php
// Fix user_skills table — add missing credits column and patch the unique key
require_once __DIR__ . '/../../backend/config/database.php';

$db = new Database();
$conn = $db->connect();

echo "Connected. Applying fixes...\n\n";

// 1. Check if credits column exists
$stmt = $conn->query("SHOW COLUMNS FROM user_skills LIKE 'credits'");
if ($stmt->rowCount() === 0) {
    $conn->exec("ALTER TABLE user_skills ADD COLUMN credits INT DEFAULT 5");
    echo "Added credits column\n";
} else {
    echo "credits column already exists\n";
}

// 2. Check type column exists
$stmt = $conn->query("SHOW COLUMNS FROM user_skills LIKE 'type'");
if ($stmt->rowCount() === 0) {
    // Migration added skill_name before type so type was never appended
    $conn->exec("ALTER TABLE user_skills ADD COLUMN type ENUM('teach','learn') NOT NULL DEFAULT 'teach'");
    echo "Added type column\n";
} else {
    echo "type column already exists\n";
}

// 3. Fix UNIQUE KEY from (user_id,skill_id,type) to (user_id,skill_name,type)
// Drop the old key and recreate it
$conn->exec("ALTER TABLE user_skills DROP INDEX unique_user_skill");
$conn->exec("ALTER TABLE user_skills ADD UNIQUE KEY unique_user_skill (user_id, skill_name, type)");
echo "Rebuilt unique constraint on (user_id, skill_name, type)\n";

echo "\nDone. Current columns:\n";
foreach ($conn->query("SHOW COLUMNS FROM user_skills")->fetchAll() as $r) {
    echo "  " . $r['Field'] . " | " . $r['Type'] . " | " . ($r['Null'] ?? 'Y') . " | " . ($r['Default'] ?? 'NULL') . "\n";
}
