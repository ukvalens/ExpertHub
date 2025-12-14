<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'experthub';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
else {
    //echo "Connected successfully to the database.";
}

// Set charset
$conn->set_charset("utf8");
?>