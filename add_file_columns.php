<?php
require_once 'config/database.php';

$sql = "ALTER TABLE messages 
        ADD COLUMN file_path VARCHAR(500) NULL AFTER message_content,
        ADD COLUMN file_name VARCHAR(255) NULL AFTER file_path,
        ADD COLUMN file_size INT NULL AFTER file_name";

if ($conn->query($sql) === TRUE) {
    echo "File sharing columns added successfully to messages table";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>