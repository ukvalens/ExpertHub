<?php
require_once 'config/database.php';

$sql = "CREATE TABLE IF NOT EXISTS additional_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    provider_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (provider_id) REFERENCES service_providers(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Additional earnings table created successfully";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>