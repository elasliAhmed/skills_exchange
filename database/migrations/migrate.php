<?php
// Apply schema changes to existing database
require_once __DIR__ . '/../../backend/config/database.php';

$db = new Database();
$conn = $db->connect();

// Disable emulate prepares so SHOW COLUMNS LIKE works
$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$newColumns = [
    'skill_name'        => "ADD COLUMN skill_name VARCHAR(100) NOT NULL DEFAULT 'Custom Skill'",
    'skill_description' => 'ADD COLUMN skill_description TEXT',
    'skill_level'       => 'ADD COLUMN skill_level VARCHAR(50)',
    'lesson_format'     => 'ADD COLUMN lesson_format TEXT',
    'learner_gains'     => 'ADD COLUMN learner_gains TEXT',
];

foreach ($newColumns as $col => $ddl) {
    $stmt = $conn->prepare("SHOW COLUMNS FROM user_skills LIKE '$col'");
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        $conn->exec("ALTER TABLE user_skills $ddl");
        echo "Added: $col\n";
    } else {
        echo "Skipped (exists): $col\n";
    }
}

echo "Migration complete.\n";
