<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();

// 1. Ensure users table is InnoDB
$c->exec("ALTER TABLE users ENGINE=InnoDB");
echo "users engine = InnoDB\n";

// 2. Retry the missing FK
try {
    $c->exec("ALTER TABLE enrollments
        ADD CONSTRAINT fk_enrollments_learner
        FOREIGN KEY (learner_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "FK enrollments.learner_id -> users.id created\n";
} catch (PDOException $e) {
    echo "FK error (already exists?): " . $e->getMessage() . "\n";
}

// 3. Strip cascade off offer_id FK (safe to re-create)
try {
    $c->exec("ALTER TABLE enrollments DROP FOREIGN KEY fk_enrollments_offer");
    $c->exec("ALTER TABLE enrollments ADD CONSTRAINT fk_enrollments_offer FOREIGN KEY (offer_id) REFERENCES user_skills(id) ON DELETE CASCADE");
    echo "offer_id FK rebuilt\n";
} catch (PDOException $e) {
    echo "offer_id FK error: " . $e->getMessage() . "\n";
}

// Final check
foreach (['users','user_skills','enrollments','skills','transactions'] as $t) {
    $r = $c->query("SHOW TABLE STATUS WHERE Name='$t'")->fetch();
    echo "$t => engine={$r['Engine']}\n";
}
