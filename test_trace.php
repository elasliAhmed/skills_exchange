<?php
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/models/Enrollment.php';
$db = new Database();
$e  = new Enrollment($db);

echo "=== Direct Enrollment::create() trace for offer_id=1 ===\n";
$offer = $e->getOfferById(1);
echo "offer returned: " . var_export($offer['lessons_count'] ?? 'MISSING', true) . "\n";
$lc = $offer['lessons_count'];
echo 'is_null($lc) = ' . var_export(is_null($lc), true) . "\n";
$lc2 = is_null($lc) ? 1 : (int)$lc;
echo 'lessons_count = ' . $lc2 . "\n";
echo 'remaining = ' . $lc2 . "\n";
echo "done\n";
