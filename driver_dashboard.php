<?php
session_start();
include 'php/config.php';

// Strong session check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'driver') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get driver's information with error handling
$sql = "SELECT * FROM drivers WHERE user_id = '$user_id'";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    // Driver data doesn't exist - maybe registration incomplete
    session_destroy();
    echo "<script>
            alert('Driver account not found. Please register again.');
            window.location.href='login.html';
          </script>";
    exit();
}

$driver = $result->fetch_assoc();

$driver_name = isset($driver['driver_name']) ? $driver['driver_name'] : 'Driver';
$license_number = isset($driver['license_number']) ? $driver['license_number'] : 'N/A';
$aadhar_number = isset($driver['aadhar_number']) ? $driver['aadhar_number'] : 'N/A';
$schools_served = isset($driver['schools_served']) ? $driver['schools_served'] : '';
$monthly_price = isset($driver['monthly_price']) ? $driver['monthly_price'] : 0;
$yearly_price = isset($driver['yearly_price']) ? $driver['yearly_price'] : 0;
$driver_id = $driver['id'];

// Get subscribed children
$sql_children = "SELECT s.*, p.child_name, p.parent_name, p.school, p.location, u.email 
                 FROM subscriptions s 
                 JOIN parents p ON s.parent_id = p.id 
                 JOIN users u ON p.user_id = u.id
                 WHERE s.driver_id = '$driver_id' AND s.status = 'active'";
$children_result = $conn->query($sql_children);

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_schools = mysqli_real_escape_string($conn, $_POST['schools_served']);
    $new_monthly = mysqli_real_escape_string($conn, $_POST['monthly_price']);
    $new_yearly = mysqli_real_escape_string($conn, $_POST['yearly_price']);
    
    $update_sql = "UPDATE drivers SET schools_served = '$new_schools', monthly_price = '$new_monthly', yearly_price = '$new_yearly' WHERE id = '$driver_id'";
    $conn->query($update_sql);
    
    header("Location: driver_dashboard.php");
    exit();
}

// Handle location update (AJAX call)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_location'])) {
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);
    
    // Check if location exists
    $check = "SELECT * FROM driver_locations WHERE driver_id = '$driver_id'";
    $check_result = $conn->query($check);
    
    if ($check_result->num_rows > 0) {
        $update_loc = "UPDATE driver_locations SET latitude = '$latitude', longitude = '$longitude' WHERE driver_id = '$driver_id'";
    } else {
        $update_loc = "INSERT INTO driver_locations (driver_id, latitude, longitude) VALUES ('$driver_id', '$latitude', '$longitude')";
    }
    $conn->query($update_loc);
    
    echo json_encode(['status' => 'success']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KIDSHUTTLE • Driver Dashboard</title>
<link rel="icon" href="favicon.png" type="image/x-icon">

<style>
    :root {
        --primary: #3b5bfd;
        --primary-soft: #e8edff;
        --primary-dark: #2c47d6;
        --bg: #f5f7ff;
        --white: #ffffff;
        --text: #333;
        --muted: #777;
        --radius: 12px;
        --shadow: 0 10px 30px rgba(0,0,0,0.08);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
    }

    body {
        background: var(--bg);
        display: flex;
        min-height: 100vh;
    }

    .sidebar {
        width: 250px;
        background: var(--white);
        min-height: 100vh;
        padding: 25px;
        box-shadow: 4px 0 18px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
    }

    .logo {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 25px;
    }

    .nav-title {
        font-size: 11px;
        color: var(--muted);
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.15em;
    }

    .nav-links a {
        display: block;
        padding: 10px 12px;
        font-size: 14px;
        color: var(--text);
        text-decoration: none;
        border-radius: var(--radius);
        margin-bottom: 8px;
        transition: 0.2s;
        cursor: pointer;
    }

    .nav-links a:hover,
    .nav-links a.active {
        background: var(--primary-soft);
        color: var(--primary);
        font-weight: 600;
    }

    .sidebar-footer {
        margin-top: auto;
        font-size: 13px;
    }

    .sidebar-footer a {
        color: #e63946;
        text-decoration: none;
        font-weight: 500;
    }

    .main {
        flex: 1;
        padding: 32px 40px;
        overflow-y: auto;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .header-title {
        font-size: 26px;
        font-weight: 600;
    }

    .header-sub {
        font-size: 13px;
        color: var(--muted);
    }

    .section {
        display: none;
    }

    .section.active {
        display: block;
    }

    .cards {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .card {
        background: var(--white);
        padding: 22px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        flex: 1;
        min-width: 260px;
    }

    .card h3 {
        color: var(--primary);
        font-size: 17px;
        margin-bottom: 6px;
    }

    .card p {
        font-size: 14px;
        margin-bottom: 8px;
    }

    .btn {
        padding: 10px 16px;
        background: var(--primary);
        color: var(--white);
        border: none;
        border-radius: var(--radius);
        cursor: pointer;
        font-size: 14px;
        margin-top: 8px;
        transition: 0.2s;
    }

    .btn:hover {
        background: var(--primary-dark);
    }

    .btn-success {
        background: #28a745;
    }

    .btn-danger {
        background: #dc3545;
    }

    .table-card {
        margin-top: 30px;
        background: var(--white);
        padding: 22px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        margin-top: 10px;
    }

    table th {
        background: var(--primary-soft);
        padding: 12px;
        text-align: left;
    }

    table td {
        padding: 12px;
    }

    table tr:nth-child(even) {
        background: #f3f5ff;
    }

    .badge {
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .success { background: #d4f8e8; color: #1b6c46; }
    .pending { background: #ffe8b2; color: #8a6d1c; }
    
    .child-card {
        background: var(--white);
        padding: 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }
    
    .child-card h4 {
        color: var(--primary);
        margin-bottom: 10px;
    }
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: var(--muted);
    }
    
    input[type="text"], input[type="number"] {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border: 1px solid #ddd;
        border-radius: var(--radius);
        font-size: 14px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        font-size: 14px;
    }

    .location-status {
        padding: 10px;
        background: var(--primary-soft);
        border-radius: var(--radius);
        margin-top: 15px;
        font-size: 14px;
    }

    .location-status.active {
        background: #d4f8e8;
        color: #1b6c46;
    }
</style>

</head>

<body>

<div class="sidebar">
    <div class="logo">KIDSHUTTLE</div>
    <div class="nav-title">Quick Access</div>
    <div class="nav-links">
        <a onclick="showSection('dashboard')" class="active">Dashboard</a>
        <a onclick="showSection('profile')">My Profile</a>
        <a onclick="showSection('children')">Subscribed Children</a>
        <a onclick="showSection('location')">Share Location</a>
        <a onclick="showSection('chat')">Chat with Parents</a>
    </div>
    <div class="sidebar-footer">
        <a href="php/logout.php">Logout</a>
    </div>
</div>

<div class="main">

    <!-- DASHBOARD SECTION -->
    <div id="dashboard" class="section active">
        <div class="header">
            <div>
                <div class="header-title">Welcome, <?php echo $driver_name; ?></div>
                <div class="header-sub">Manage your carpool services</div>
            </div>
        </div>

        <div class="cards">
            <div class="card">
                <h3>My Profile</h3>
                <p><strong><?php echo $driver_name; ?></strong></p>
                <p>License: <?php echo $license_number; ?></p>
                <button class="btn" onclick="showSection('profile')">View Profile</button>
            </div>

            <div class="card">
                <h3>Subscribed Children</h3>
                <p>Total: <?php echo $children_result->num_rows; ?> children</p>
                <button class="btn" onclick="showSection('children')">View All</button>
            </div>

            <div class="card">
                <h3>Location Sharing</h3>
                <p>Share your live location with parents</p>
                <button class="btn" onclick="showSection('location')">Manage Location</button>
            </div>

            <div class="card">
                <h3>Messages</h3>
                <p>Chat with parents</p>
                <button class="btn" onclick="showSection('chat')">Open Chat</button>
            </div>
        </div>
    </div>

    <!-- PROFILE SECTION -->
    <div id="profile" class="section">
        <div class="header">
            <div>
                <div class="header-title">My Profile</div>
                <div class="header-sub">Edit your driver information</div>
            </div>
        </div>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Driver Name:</label>
                    <input type="text" value="<?php echo $driver_name; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>License Number:</label>
                    <input type="text" value="<?php echo $license_number; ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Aadhar Number:</label>
                    <input type="text" value="<?php echo $aadhar_number; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Schools Served (comma separated):</label>
                    <input type="text" name="schools_served" value="<?php echo $schools_served; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Monthly Price (₹):</label>
                    <input type="number" name="monthly_price" value="<?php echo $monthly_price; ?>" step="0.01" required>
                </div>

                <div class="form-group">
                    <label>Yearly Price (₹):</label>
                    <input type="number" name="yearly_price" value="<?php echo $yearly_price; ?>" step="0.01" required>
                </div>
                
                <button type="submit" name="update_profile" class="btn">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- CHILDREN SECTION -->
    <div id="children" class="section">
        <div class="header">
            <div>
                <div class="header-title">Subscribed Children</div>
                <div class="header-sub">All children using your carpool service</div>
            </div>
        </div>

        <?php if ($children_result->num_rows > 0): ?>
            <?php 
            mysqli_data_seek($children_result, 0);
            while($child = $children_result->fetch_assoc()): 
            ?>
                <div class="child-card">
                    <h4><?php echo $child['child_name']; ?></h4>
                    <p><strong>Parent:</strong> <?php echo $child['parent_name']; ?></p>
                    <p><strong>School:</strong> <?php echo $child['school']; ?></p>
                    <p><strong>Pickup Location:</strong> <?php echo $child['location']; ?></p>
                    <p><strong>Parent Email:</strong> <?php echo $child['email']; ?></p>
                    <p><strong>Subscription Type:</strong> <?php echo ucfirst($child['subscription_type']); ?></p>
                    <p><strong>Valid Till:</strong> <?php echo $child['end_date']; ?></p>
                    <button class="btn" onclick="showSection('chat')">Chat with Parent</button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-data">
                <h3>No Subscribed Children</h3>
                <p>No parents have subscribed to your service yet</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- LOCATION SECTION -->
    <div id="location" class="section">
        <div class="header">
            <div>
                <div class="header-title">Share Location</div>
                <div class="header-sub">Enable location sharing for live tracking</div>
            </div>
        </div>

        <div class="card">
            <h3>Location Sharing Status</h3>
            <div id="location-status" class="location-status">
                ⏸ Location sharing is OFF
            </div>
            
            <button id="start-location" class="btn btn-success" onclick="startLocationSharing()">Start Sharing Location</button>
            <button id="stop-location" class="btn btn-danger" onclick="stopLocationSharing()" style="display:none;">Stop Sharing Location</button>
            
            <div style="margin-top: 20px;">
                <p><strong>Current Location:</strong></p>
                <p id="current-coords">Not available</p>
            </div>
        </div>
    </div>

    <!-- CHAT SECTION -->
    <div id="chat" class="section">
        <div class="header">
            <div>
                <div class="header-title">Chat with Parents</div>
                <div class="header-sub">Send messages to parents</div>
            </div>
        </div>

        <div class="card" style="text-align: center; padding: 50px;">
            <h3 style="color: var(--primary); margin-bottom: 20px;">💬 Chat with Parents</h3>
            <p style="color: var(--muted); margin-bottom: 30px;">Click the button below to open the chat in a new window</p>
            <button class="btn" onclick="window.open('php/chat.php', 'Chat', 'width=1000,height=700')" 
                    style="padding: 15px 40px; font-size: 16px;">
                Open Chat Window
            </button>
        </div>
    </div>

</div>

<script>
let locationInterval = null;
let isSharing = false;

function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
    event.target.classList.add('active');
}

function startLocationSharing() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    isSharing = true;
    document.getElementById('location-status').innerHTML = '✓ Location sharing is ACTIVE';
    document.getElementById('location-status').classList.add('active');
    document.getElementById('start-location').style.display = 'none';
    document.getElementById('stop-location').style.display = 'inline-block';

    // Get location immediately
    updateLocation();

    // Then update every 30 seconds
    locationInterval = setInterval(updateLocation, 30000);
}

function stopLocationSharing() {
    isSharing = false;
    clearInterval(locationInterval);
    
    document.getElementById('location-status').innerHTML = '⏸ Location sharing is OFF';
    document.getElementById('location-status').classList.remove('active');
    document.getElementById('start-location').style.display = 'inline-block';
    document.getElementById('stop-location').style.display = 'none';
    document.getElementById('current-coords').innerHTML = 'Not available';
}

function updateLocation() {
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;
            
            document.getElementById('current-coords').innerHTML = 
                'Latitude: ' + latitude + '<br>Longitude: ' + longitude;
            
            // Send to server
            fetch('driver_dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'update_location=1&latitude=' + latitude + '&longitude=' + longitude
            })
            .then(res => res.json())
            .then(data => {
                console.log('Location updated successfully');
            })
            .catch(err => console.error('Error updating location:', err));
        },
        function(error) {
            alert('Error getting location: ' + error.message);
            stopLocationSharing();
        }
    );
}
</script>

</body>
</html>