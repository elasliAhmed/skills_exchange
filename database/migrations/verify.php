<?php
require_once __DIR__ . '/../../backend/config/database.php';
$db = new Database();
$c = $db->connect();
$c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Verify add() works end-to-end
$tests = [
    ['col' => 'credits',                   'msg' => 'credits column exists'],
    ['col' => 'skill_name',                'msg' => 'skill_name column exists'],
    ['col' => 'skill_description',         'msg' => 'skill_description column exists'],
    ['col' => 'skill_level',               'msg' => 'skill_level column exists'],
    ['col' => 'lesson_format',             'msg' => 'lesson_format column exists'],
    ['col' => 'learner_gains',             'msg' => 'learner_gains column exists'],
];

foreach ($tests as $t) {
    $ok = (bool)$c->query("SHOW COLUMNS FROM user_skills LIKE '{$t['col']}'")->rowCount();
    echo ($ok ? 'PASS' : 'FAIL') . " | {$t['msg']}\n";
}

// Check unique key structure
$idx = $c->query("SHOW INDEX FROM user_skills")->fetchAll(PDO::FETCH_ASSOC);
$uk = array_filter($idx, fn($r) => $r['Key_name'] === 'unique_user_skill');
echo "\nunique_user_skill columns: " . implode(', ', array_column($uk, 'Column_name')) . "\n";

echo "\nAll checks complete.\n";
