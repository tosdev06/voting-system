<?php
require_once '../config.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get voting history
$votes = [];
$stmt = $conn->prepare("SELECT e.title as election_title, c.name as candidate_name, v.voted_at 
                       FROM votes v
                       JOIN elections e ON v.election_id = e.id
                       JOIN candidates c ON v.candidate_id = c.id
                       WHERE v.voter_id = ?
                       ORDER BY v.voted_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $votes[] = $row;
}

// Handle profile updates
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = sanitize_input($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        // Verify current password if changing password
        if (!empty($new_password)) {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $db_password = $stmt->get_result()->fetch_assoc()['password'];
            
            if (!password_verify($current_password, $db_password)) {
                $error = "Current password is incorrect";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssi", $email, $hashed_password, $user_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $email, $user_id);
        }
        
        if (empty($error) && $stmt->execute()) {
            $_SESSION['email'] = $email;
            $success = "Profile updated successfully!";
            log_activity('profile_update', 'Updated profile information');
        } elseif (empty($error)) {
            $error = "Failed to update profile. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Online Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #e6e9ff;
            --secondary: #7209b7;
            --danger: #f72585;
            --danger-light: #fde8ef;
            --success: #4cc9f0;
            --success-light: #e6f7fd;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border-radius: 10px;
            --box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            line-height: 1.6;
            background-color: #f8fafc;
        }

        /* Header Styles */
        header {
            background: white;
            box-shadow: var(--box-shadow);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .header-content {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: 600;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 1.5rem;
        }

        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            position: relative;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        nav ul li a.active {
            color: var(--primary);
        }

        nav ul li a.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
        }

        nav ul li a i {
            margin-right: 8px;
            font-size: 1rem;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
        }

        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        .profile-container {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }

        .profile-sidebar {
            flex: 0 0 300px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            align-self: flex-start;
        }

        .profile-picture {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-picture img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--primary-light);
            margin-bottom: 1rem;
        }

        .profile-info {
            text-align: center;
        }

        .profile-info h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .profile-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .profile-content {
            flex: 1;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
        }

        .profile-section {
            margin-bottom: 2.5rem;
        }

        .profile-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .profile-section h2 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-group input[disabled] {
            background: #f8f9fa;
            color: var(--gray);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: #e9ecef;
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #dee2e6;
        }

        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .alert-danger {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(247, 37, 133, 0.3);
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }

        .voting-history {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .vote-record {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .vote-record:hover {
            transform: translateX(5px);
        }

        .vote-info h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .vote-info p {
            color: var(--gray);
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }

        .vote-date {
            color: var(--gray);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
        }

        .vote-date i {
            margin-right: 5px;
        }

        .no-votes {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .no-votes i {
            font-size: 2.5rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .profile-container {
                flex-direction: column;
            }

            .profile-sidebar {
                flex: 1;
                display: flex;
                align-items: center;
                gap: 2rem;
            }

            .profile-picture {
                margin-bottom: 0;
            }

            .profile-picture img {
                width: 100px;
                height: 100px;
            }

            .profile-info {
                text-align: left;
            }
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                align-items: flex-start;
            }

            nav {
                width: 100%;
                margin-top: 1rem;
            }

            nav ul {
                flex-direction: column;
                width: 100%;
            }

            nav ul li {
                margin: 0.5rem 0;
            }

            .mobile-menu-btn {
                display: block;
                position: absolute;
                top: 1rem;
                right: 5%;
            }

            .nav-collapse {
                display: none;
            }

            .nav-collapse.show {
                display: block;
            }

            .profile-sidebar {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .profile-info {
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 1rem;
            }

            h1 {
                font-size: 1.3rem;
            }

            .profile-sidebar,
            .profile-content {
                padding: 1.5rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-sidebar,
        .profile-content {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .profile-content {
            animation-delay: 0.1s;
        }

        .vote-record {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .vote-record:nth-child(1) { animation-delay: 0.2s; }
        .vote-record:nth-child(2) { animation-delay: 0.3s; }
        .vote-record:nth-child(3) { animation-delay: 0.4s; }
        .vote-record:nth-child(4) { animation-delay: 0.5s; }
        .vote-record:nth-child(5) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <h1>My Profile</h1>
        </div>
        
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <nav class="nav-collapse" id="navCollapse">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="elections.php"><i class="fas fa-vote-yea"></i> Elections</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="container">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-picture">
                    <img src="../images/default-profile.png" alt="Profile Picture">
                    <button class="btn btn-secondary"><i class="fas fa-camera"></i> Change Photo</button>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                    <p><i class="far fa-calendar"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    <p><i class="fas fa-vote-yea"></i> <?php echo count($votes); ?> votes cast</p>
                </div>
            </div>
            
            <div class="profile-content">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-section">
                    <h2><i class="fas fa-user-cog"></i> Account Settings</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="current_password">Current Password (leave blank to keep unchanged)</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter current password">
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
                
                <div class="profile-section">
                    <h2><i class="fas fa-history"></i> Voting History</h2>
                    <?php if (empty($votes)): ?>
                        <div class="no-votes">
                            <i class="far fa-calendar-times"></i>
                            <h3>No Voting History</h3>
                            <p>You haven't voted in any elections yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="voting-history">
                            <?php foreach ($votes as $vote): ?>
                                <div class="vote-record">
                                    <div class="vote-info">
                                        <h4><?php echo htmlspecialchars($vote['election_title']); ?></h4>
                                        <p>Voted for: <?php echo htmlspecialchars($vote['candidate_name']); ?></p>
                                        <p class="vote-date">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M j, Y H:i', strtotime($vote['voted_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navCollapse = document.getElementById('navCollapse');
        
        mobileMenuBtn.addEventListener('click', () => {
            navCollapse.classList.toggle('show');
            mobileMenuBtn.innerHTML = navCollapse.classList.contains('show') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });

        // Password strength indicator
        const newPassword = document.getElementById('new_password');
        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const strengthMeter = document.createElement('div');
                strengthMeter.className = 'password-strength';
                
                const strength = calculatePasswordStrength(this.value);
                let strengthText = '';
                let strengthColor = '';
                
                if (strength < 3) {
                    strengthText = 'Weak';
                    strengthColor = 'var(--danger)';
                } else if (strength < 6) {
                    strengthText = 'Medium';
                    strengthColor = 'var(--warning)';
                } else {
                    strengthText = 'Strong';
                    strengthColor = 'var(--success)';
                }
                
                strengthMeter.innerHTML = `
                    <div style="width: ${strength * 10}%; height: 4px; background: ${strengthColor}; border-radius: 2px; margin-top: 5px;"></div>
                    <small style="color: ${strengthColor}; font-weight: 500;">${strengthText}</small>
                `;
                
                if (!this.nextElementSibling.classList.contains('password-strength')) {
                    this.parentNode.appendChild(strengthMeter);
                } else {
                    this.nextElementSibling.innerHTML = strengthMeter.innerHTML;
                }
            });
        }
        
        function calculatePasswordStrength(password) {
            let strength = 0;
            
            // Length contributes up to 40%
            if (password.length > 0) strength += Math.min(4, password.length / 2);
            
            // Mixed case contributes up to 20%
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 2;
            
            // Numbers contribute up to 20%
            if (password.match(/\d+/)) strength += 2;
            
            // Special chars contribute up to 20%
            if (password.match(/[^a-zA-Z0-9]/)) strength += 2;
            
            return Math.min(10, strength);
        }

        // Form submission animation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    btn.disabled = true;
                }
            });
        }
    </script>

</body>
</html>