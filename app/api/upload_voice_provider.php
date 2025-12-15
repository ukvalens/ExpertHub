<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$order_id = $_POST['order_id'] ?? null;
if (!$order_id || !isset($_FILES['voice_note'])) {
    http_response_code(400);
    exit();
}

// Get customer ID from order
$stmt = $conn->prepare("SELECT customer_id FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_info = $stmt->get_result()->fetch_assoc();
$receiver_id = $order_info['customer_id'];

// Create uploads directory if it doesn't exist
$upload_dir = '../../../uploads/voice_notes/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_extension = 'wav';
$filename = 'voice_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
$file_path = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($_FILES['voice_note']['tmp_name'], $file_path)) {
    // Save to database
    $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_content, message_type, file_path, created_at) VALUES (?, ?, ?, '[Voice Note]', 'voice', ?, NOW())");
    $stmt->bind_param("iiis", $order_id, $_SESSION['user_id'], $receiver_id, $filename);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Voice note sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
}
?>