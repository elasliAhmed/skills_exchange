<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();

// 1. Create enrollments table (no FK — user_skills is MyISAM which doesn't support FKs)
$c->exec("CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    learner_id INT NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (offer_id, learner_id),
    KEY learner_id (learner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "enrollments table created or verified\n";

// 2. Convert user_skills to InnoDB so FKs work if needed later
$c->exec("ALTER TABLE user_skills ENGINE=InnoDB");
echo "user_skills engine converted to InnoDB\n";

// 3. Add store_user_id (the shop owner who publishes on behalf of the student)
$stmt = $c->query("SHOW COLUMNS FROM user_skills LIKE 'store_user_id'");
if ($stmt->rowCount() === 0) {
    $c->exec("ALTER TABLE user_skills ADD COLUMN store_user_id INT DEFAULT NULL");
    echo "store_user_id column added\n";
    $c->exec("UPDATE user_skills SET store_user_id = user_id");
    echo "store_user_id backfilled\n";
} else {
    $c->exec("UPDATE user_skills SET store_user_id = user_id WHERE store_user_id IS NULL");
    echo "store_user_id already exists, nulls backfilled\n";
}

// 4. Add proper FK to user_skills (now that it's InnoDB)
try {
    $c->exec("ALTER TABLE enrollments
        ADD CONSTRAINT fk_enrollments_offer
        FOREIGN KEY (offer_id) REFERENCES user_skills(id) ON DELETE CASCADE");
    echo "FK enrollments.offer_id -> user_skills.id created\n";
} catch (PDOException $e) {
    echo "FK already exists or error: " . $e->getMessage() . "\n";
}
try {
    $c->exec("ALTER TABLE enrollments
        ADD CONSTRAINT fk_enrollments_learner
        FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "FK enrollments.learner_id -> users.id created\n";
} catch (PDOException $e) {
    echo "FK already exists or error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
