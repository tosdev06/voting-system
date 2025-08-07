<?php
require_once 'config.php';

// Log activity
if (isset($_SESSION['user_id'])) {
    log_activity('logout', 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>