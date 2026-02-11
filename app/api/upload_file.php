<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $order_id = $_POST['order_id'];
    $receiver_id = $_POST['receiver_id'];
    $file = $_FILES['file'];
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit();
    }
    
    if ($file['size'] > 10485760) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        exit();
    }
    
    $upload_dir = '../../uploads/messages/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $stmt = $conn->prepare("INSERT INTO messages (order_id, sender_id, receiver_id, message_content, message_type, file_path, created_at) VALUES (?, ?, ?, ?, 'file', ?, NOW())");
        $message = "Sent a file: " . $file['name'];
        $stmt->bind_param("iiiss", $order_id, $_SESSION['user_id'], $receiver_id, $message, $filename);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
}
