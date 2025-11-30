<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "kidshuttle";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8 (for proper character encoding)
$conn->set_charset("utf8");
?>