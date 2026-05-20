<?php
// Migrate: create enrollments table + fix foreign keys + add store_user_id
require_once __DIR__ . '/../../backend/config/database.php';

$db = new Database();
$c = $db->connect();

// 1. Create enrollments table (defined in schema.sql but missing)
$c->exec("CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    learner_id INT NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offer_id) REFERENCES user_skills(id) ON DELETE CASCADE,
    FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (offer_id, learner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "enrollments table created or verified\n";

// 2. Add store_user_id to user_skills if missing
$stmt = $c->query("SHOW COLUMNS FROM user_skills LIKE 'store_user_id'");
if ($stmt->rowCount() === 0) {
    $c->exec("ALTER TABLE user_skills ADD COLUMN store_user_id INT DEFAULT NULL");
    echo "store_user_id column added\n";

    // Populate: every existing offer gets the creating user as store_user_id = user_id
    $c->exec("UPDATE user_skills SET store_user_id = user_id");
    echo "store_user_id populated from user_id\n";
} else {
    echo "store_user_id column already exists\n";

    // Backfill nulls
    $c->exec("UPDATE user_skills SET store_user_id = user_id WHERE store_user_id IS NULL");
    echo "store_user_id backfilled for blank rows\n";
}

// 3. Verify
echo "\n=== enrollments columns ===\n";
foreach ($c->query("SHOW COLUMNS FROM enrollments")->fetchAll() as $r) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Default:" . ($r['Default'] ?? 'NULL') . "\n";
}

echo "\n=== user_skills columns ===\n";
foreach ($c->query("SHOW COLUMNS FROM user_skills")->fetchAll() as $r) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Default:" . ($r['Default'] ?? 'NULL') . "\n";
}

echo "\n=== store_user_id sample ===\n";
$rows = $c->query("SELECT id, user_id, store_user_id, name FROM user_skills LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  id={$r['id']} user_id={$r['user_id']} store_user_id=" . ($r['store_user_id'] ?? 'NULL') . " name={$r['name']}\n";
}
