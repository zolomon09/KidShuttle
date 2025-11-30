<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $driver_id = $_POST['driver_id'];
    $subscription_type = $_POST['type'];
    
    // Get parent_id
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT id FROM parents WHERE user_id = '$user_id'";
    $result = $conn->query($sql);
    $parent = $result->fetch_assoc();
    $parent_id = $parent['id'];
    
    // Check if already has subscription
    $check = "SELECT * FROM subscriptions WHERE parent_id = '$parent_id' AND status = 'active'";
    $check_result = $conn->query($check);
    
    if ($check_result->num_rows > 0) {
        echo "You already have an active subscription!";
        exit();
    }
    
    // Calculate dates
    $start_date = date('Y-m-d');
    if ($subscription_type == 'monthly') {
        $end_date = date('Y-m-d', strtotime('+1 month'));
    } else {
        $end_date = date('Y-m-d', strtotime('+1 year'));
    }
    
    // Insert subscription
    $insert = "INSERT INTO subscriptions (parent_id, driver_id, subscription_type, start_date, end_date, status) 
               VALUES ('$parent_id', '$driver_id', '$subscription_type', '$start_date', '$end_date', 'active')";
    
    if ($conn->query($insert) === TRUE) {
        echo "Subscription successful!";
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>