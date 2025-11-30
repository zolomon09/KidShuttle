<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['passwrd'])) {
            // CREATE SESSION
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $row['rle'];
            $_SESSION['logged_in'] = true;
            
            // Redirect based on role
            if ($row['rle'] == 'parent') {
                header("Location: ../parent_dashboard.php");
                exit();
            } else if ($row['rle'] == 'driver') {
                header("Location: ../driver_dashboard.php");
                exit();
            }
        } else {
            echo "<script>
                    alert('Incorrect password!');
                    window.location.href='../login.html';
                  </script>";
        }
    } else {
        echo "<script>
                alert('No account found with this email!');
                window.location.href='../login.html';
              </script>";
    }
}

$conn->close();
?>