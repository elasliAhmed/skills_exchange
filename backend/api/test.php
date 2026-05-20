<?php
// Test endpoint - direct access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode(['success' => true, 'data' => ['message' => 'API is working']]);