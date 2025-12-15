<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'provider') {
    http_response_code(403);
    exit();
}

// Get provider ID
$stmt = $conn->prepare("SELECT id FROM service_providers WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();

if (!$provider) {
    http_response_code(404);
    exit();
}

// Check for incoming calls
$stmt = $conn->prepare("SELECT vc.*, o.order_number, o.service_title, u.first_name, u.last_name 
                       FROM video_calls vc 
                       JOIN orders o ON vc.order_id = o.id 
                       JOIN users u ON vc.caller_id = u.id 
                       WHERE o.provider_id = ? AND vc.status = 'calling' 
                       ORDER BY vc.created_at DESC LIMIT 1");
$stmt->bind_param("i", $provider['id']);
$stmt->execute();
$call = $stmt->get_result()->fetch_assoc();

if ($call) {
    echo json_encode([
        'has_call' => true,
        'call_id' => $call['id'],
        'order_id' => $call['order_id'],
        'order_number' => $call['order_number'],
        'service_title' => $call['service_title'],
        'caller_name' => $call['first_name'] . ' ' . $call['last_name']
    ]);
} else {
    echo json_encode(['has_call' => false]);
}
?>