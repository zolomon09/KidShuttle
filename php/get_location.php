<?php
session_start();
include 'config.php';

$user_id = $_SESSION['user_id'];

// Get parent's subscribed driver
$sql = "SELECT d.id FROM drivers d 
        JOIN subscriptions s ON d.id = s.driver_id 
        JOIN parents p ON s.parent_id = p.id 
        WHERE p.user_id = '$user_id' AND s.status = 'active' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $driver_id = $row['id'];
    
    // Get driver's latest location
    $loc_sql = "SELECT latitude, longitude, updated_at FROM driver_locations WHERE driver_id = '$driver_id' ORDER BY updated_at DESC LIMIT 1";
    $loc_result = $conn->query($loc_sql);
    
    if ($loc_result->num_rows > 0) {
        $location = $loc_result->fetch_assoc();
        echo json_encode($location);
    } else {
        echo json_encode(['error' => 'No location data']);
    }
} else {
    echo json_encode(['error' => 'No active subscription']);
}

$conn->close();
?>