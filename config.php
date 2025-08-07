<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ovoting');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Session configuration
session_start();

// Helper functions
function sanitize_input($data) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($data))));
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function log_activity($action, $details = '') {
    global $conn;
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
}
function updateElectionStatus($election_id) {
    global $conn;
    
    $current_time = date('Y-m-d H:i:s');
    $election = $conn->query("SELECT * FROM elections WHERE id = $election_id")->fetch_assoc();
    
    if (!$election) return false;
    
    $new_status = '';
    if ($election['start_date'] > $current_time) {
        $new_status = 'upcoming';
    } elseif ($election['end_date'] < $current_time) {
        $new_status = 'completed';
    } else {
        $new_status = 'ongoing';
    }
    
    // Only update if status has changed
    if ($election['status'] != $new_status) {
        $conn->query("UPDATE elections SET status = '$new_status' WHERE id = $election_id");
        log_activity('election_status', "Status changed to $new_status for election ID $election_id");
        return true;
    }
    
    return false;
}
?>