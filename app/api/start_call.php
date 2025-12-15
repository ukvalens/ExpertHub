<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;

if (!$order_id || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    exit();
}

// Insert call record
$stmt = $conn->prepare("INSERT INTO video_calls (order_id, caller_id, status, created_at) VALUES (?, ?, 'calling', NOW())");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();

echo json_encode(['success' => true]);
?>