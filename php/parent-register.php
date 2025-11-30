<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $parent_name = $_POST['parent_name'];
    $child_name = $_POST['child_name'];
    $school_input = $_POST['school'];
    $location = $_POST['location'];

    $school = trim($school_input);
    
    // Insert into users table
    $sql = "INSERT INTO users (email, passwrd, rle) VALUES ('$email', '$password', 'parent')";
    
    if ($conn->query($sql) === TRUE) {
        $user_id = $conn->insert_id;
        
        // Insert into parents table
        $sql2 = "INSERT INTO parents (user_id, parent_name, child_name, school, location) 
                 VALUES ('$user_id', '$parent_name', '$child_name', '$school', '$location')";
        
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
