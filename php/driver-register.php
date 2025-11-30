<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $driver_name = $_POST['driver_name'];
    $license_number = $_POST['license_number'];
    $aadhar_number = $_POST['aadhar_number'];
    $schools_input = $_POST['schools_served'];
    $monthly_price = $_POST['monthly_price'];
    $yearly_price = $_POST['yearly_price'];

    $schools_array = explode(',', $schools_input);
    $schools_array = array_map('trim', $schools_array);
    $schools_served = implode(',', $schools_array);
    
    // Insert into users table
    $sql = "INSERT INTO users (email, passwrd, rle) VALUES ('$email', '$password', 'driver')";
    
    if ($conn->query($sql) === TRUE) {
        $user_id = $conn->insert_id;
        
        // Insert into drivers table
        $sql2 = "INSERT INTO drivers (user_id, driver_name, license_number, aadhar_number, schools_served, monthly_price, yearly_price, verification_status) 
                 VALUES ('$user_id', '$driver_name', '$license_number', '$aadhar_number', '$schools_served', '$monthly_price', '$yearly_price', 'verified')";
        
        if ($conn->query($sql2) === TRUE) {
            header("Location: ../login.html");
            exit();
        } else {
            echo "Error: " . $sql2 . "<br>" . $conn->error;
        }
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();

?>
