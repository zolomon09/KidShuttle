<?php
session_start();
include 'php/config.php';

// Strong session check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'parent') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get parent's information with error handling
$sql = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    // Parent data doesn't exist - maybe registration incomplete
    session_destroy();
    echo "<script>
            alert('Parent account not found. Please register again.');
            window.location.href='login.html';
          </script>";
    exit();
}

$parent = $result->fetch_assoc();

$parent_name = isset($parent['parent_name']) ? $parent['parent_name'] : 'Parent';
$child_name = isset($parent['child_name']) ? $parent['child_name'] : 'Child';
$school = isset($parent['school']) ? trim($parent['school']) : '';
$location = isset($parent['location']) ? $parent['location'] : '';
$parent_id = $parent['id'];

// Get drivers for this school
$school_escaped = mysqli_real_escape_string($conn, $school);
$sql_drivers = "SELECT DISTINCT * FROM drivers 
                WHERE verification_status = 'verified'
                AND CONCAT(',', REPLACE(schools_served, ' ', ''), ',') 
                LIKE CONCAT('%,', REPLACE('$school_escaped', ' ', ''), ',%')";
$drivers_result = $conn->query($sql_drivers);

// Get active subscriptions
$sql_subscriptions = "SELECT s.*, d.driver_name, d.monthly_price, d.yearly_price 
                      FROM subscriptions s 
                      JOIN drivers d ON s.driver_id = d.id 
                      WHERE s.parent_id = '$parent_id' AND s.status = 'active'";
$subscriptions_result = $conn->query($sql_subscriptions);

// Check if already has subscription
$has_subscription = ($subscriptions_result && $subscriptions_result->num_rows > 0);

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_location = mysqli_real_escape_string($conn, $_POST['location']);
    
    $update_sql = "UPDATE parents SET location = '$new_location' WHERE id = '$parent_id'";
    $conn->query($update_sql);
    
    header("Location: parent_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KIDSHUTTLE • Parent Dashboard</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="icon" href="favicon.png" type="image/x-icon">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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

    .btn-secondary {
        background: #6c757d;
    }

    .btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .alert-card {
        margin-top: 25px;
        background: var(--white);
        padding: 22px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    .alert-card h3 {
        color: #e63946;
        font-size: 18px;
        margin-bottom: 10px;
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
    
    .driver-card {
        background: var(--white);
        padding: 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }
    
    .driver-card h4 {
        color: var(--primary);
        margin-bottom: 10px;
    }
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: var(--muted);
    }
    
    input[type="text"] {
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

    .alert-warning {
        background: #fff3cd;
        border: 1px solid #ffc107;
        padding: 15px;
        border-radius: var(--radius);
        margin-bottom: 20px;
        color: #856404;
    }
</style>

</head>

<body>

<div class="sidebar">
    <div class="logo">KIDSHUTTLE</div>
    <div class="nav-title">Quick Access</div>
    <div class="nav-links">
        <a onclick="showSection('dashboard')" class="active">Dashboard</a>
        <a onclick="showSection('drivers')">Available Drivers</a>
        <a onclick="showSection('subscription')">My Subscription</a>
        <a onclick="showSection('profile')">Child Profile</a>
        <a onclick="showSection('tracking')">Live Tracking</a>
        <a onclick="showSection('chat')">Chat with Driver</a>
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
                <div class="header-title">Welcome, <?php echo $parent_name; ?></div>
                <div class="header-sub">Here is your child's daily commute overview.</div>
            </div>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Child Information</h3>
                <p><strong><?php echo $child_name; ?></strong></p>
                <p>School: <?php echo $school; ?></p>
                <button class="btn" onclick="showSection('profile')">View Profile</button>
            </div>

            <div class="card">
                <h3>Find Drivers</h3>
                <p>Browse verified drivers for your school</p>
                <button class="btn" onclick="showSection('drivers')">Browse Drivers</button>
            </div>

            <div class="card">
                <h3>Subscription</h3>
                <p>Manage your active subscriptions</p>
                <button class="btn" onclick="showSection('subscription')">View Subscription</button>
            </div>

            <div class="card">
                <h3>Live Tracking</h3>
                <p>Track your child's location</p>
                <button class="btn" onclick="showSection('tracking')">Track Now</button>
            </div>
        </div>
    </div>

    <!-- DRIVERS SECTION -->
    <div id="drivers" class="section">
        <div class="header">
            <div>
                <div class="header-title">Available Drivers</div>
                <div class="header-sub">Verified drivers serving <?php echo $school; ?></div>
            </div>
        </div>

        <?php if ($has_subscription): ?>
            <div class="alert-warning">
                ⚠️ <strong>Note:</strong> You already have an active subscription. You can only subscribe to one driver at a time.
            </div>
        <?php endif; ?>

        <?php if ($drivers_result->num_rows > 0): ?>
            <?php while($driver = $drivers_result->fetch_assoc()): ?>
                <div class="driver-card">
                    <h4><?php echo $driver['driver_name']; ?></h4>
                    <p><strong>License:</strong> <?php echo $driver['license_number']; ?></p>
                    <p><strong>Schools Served:</strong> <?php echo $driver['schools_served']; ?></p>
                    <p><strong>Monthly Price:</strong> ₹<?php echo $driver['monthly_price']; ?></p>
                    <p><strong>Yearly Price:</strong> ₹<?php echo $driver['yearly_price']; ?></p>
                    
                    <?php if ($has_subscription): ?>
                        <button class="btn" disabled>Already Subscribed</button>
                    <?php else: ?>
                        <button class="btn" onclick="subscribe(<?php echo $driver['id']; ?>, 'monthly')">Subscribe Monthly</button>
                        <button class="btn btn-secondary" onclick="subscribe(<?php echo $driver['id']; ?>, 'yearly')">Subscribe Yearly</button>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-data">
                <h3>No Drivers Available</h3>
                <p>No verified drivers found serving <?php echo $school; ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- SUBSCRIPTION SECTION -->
    <div id="subscription" class="section">
        <div class="header">
            <div>
                <div class="header-title">My Subscriptions</div>
                <div class="header-sub">Manage your active subscriptions</div>
            </div>
        </div>

        <?php if ($subscriptions_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Driver Name</th>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sub = $subscriptions_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $sub['driver_name']; ?></td>
                            <td><?php echo ucfirst($sub['subscription_type']); ?></td>
                            <td><?php echo $sub['start_date']; ?></td>
                            <td><?php echo $sub['end_date']; ?></td>
                            <td><span class="badge success"><?php echo ucfirst($sub['status']); ?></span></td>
                            <td><button class="btn" onclick="showSection('chat')">Chat with Driver</button></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <h3>No Active Subscriptions</h3>
                <p>You haven't subscribed to any driver yet. <a href="#" onclick="showSection('drivers')" style="color: var(--primary);">Browse drivers</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- CHILD PROFILE SECTION -->
    <div id="profile" class="section">
        <div class="header">
            <div>
                <div class="header-title">Child Profile</div>
                <div class="header-sub">Edit your child's information</div>
            </div>
        </div>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Child Name:</label>
                    <input type="text" value="<?php echo $child_name; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>School:</label>
                    <input type="text" value="<?php echo $school; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Pickup Location:</label>
                    <input type="text" name="location" value="<?php echo $location; ?>" required>
                </div>
                
                <button type="submit" name="update_profile" class="btn">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- TRACKING SECTION -->
    <div id="tracking" class="section">
        <div class="header">
            <div>
                <div class="header-title">Live Tracking</div>
                <div class="header-sub">Track your child's current location</div>
            </div>
        </div>

        <div class="card">
            <div id="map" style="width: 100%; height: 500px; border-radius: var(--radius);"></div>
            <p id="location-info" style="margin-top: 15px; color: var(--muted);">Waiting for driver location...</p>
        </div>
    </div>

    <!-- CHAT SECTION -->
    <div id="chat" class="section">
        <div class="header">
            <div>
                <div class="header-title">Chat with Driver</div>
                <div class="header-sub">Send messages to your assigned driver</div>
            </div>
        </div>

        <div class="card" style="text-align: center; padding: 50px;">
            <h3 style="color: var(--primary); margin-bottom: 20px;">💬 Chat with Driver</h3>
            <p style="color: var(--muted); margin-bottom: 30px;">Click the button below to open the chat in a new window</p>
            <button class="btn" onclick="window.open('php/chat.php', 'Chat', 'width=1000,height=700')" 
                    style="padding: 15px 40px; font-size: 16px;">
                Open Chat Window
            </button>
        </div>
    </div>

</div>

<script>
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
    event.target.classList.add('active');
    
    if (sectionId === 'tracking') {
        startTracking();
    }
}

function subscribe(driverId, type) {
    if (confirm('Subscribe to this driver (' + type + ')?')) {
        fetch('php/subscribe.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'driver_id=' + driverId + '&type=' + type
        })
        .then(res => res.text())
        .then(data => {
            alert('Subscription successful!');
            location.reload();
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }
}

let map = null;
let marker = null;

function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
    event.target.classList.add('active');
    
    if (sectionId === 'tracking') {
        setTimeout(initMap, 100);
    }
}

function initMap() {
    if (map) {
        map.remove();
    }
    
    // Initialize map with default location
    map = L.map('map').setView([28.7041, 77.1025], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Create marker
    marker = L.marker([28.7041, 77.1025]).addTo(map);
    marker.bindPopup('Driver Location').openPopup();
    
    // Start tracking
    startTracking();
}

function startTracking() {
    loadDriverLocation();
    setInterval(loadDriverLocation, 10000);
}

function loadDriverLocation() {
    fetch('php/get_location.php')
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            document.getElementById('location-info').innerHTML = '⚠️ ' + data.error;
        } else if (data.latitude && data.longitude) {
            const lat = parseFloat(data.latitude);
            const lng = parseFloat(data.longitude);
            
            // Update marker position
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 13);
            
            document.getElementById('location-info').innerHTML = 
                '📍 Driver Location:<br>' +
                'Latitude: ' + lat + '<br>' +
                'Longitude: ' + lng + '<br>' +
                '🕒 Last updated: ' + data.updated_at;
        } else {
            document.getElementById('location-info').innerHTML = '⏳ Waiting for driver to share location...';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        document.getElementById('location-info').innerHTML = '❌ Error loading location';
    });
}
</script>

</body>
</html>