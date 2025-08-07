<?php
require_once '../config.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get election details
$stmt = $conn->prepare("SELECT * FROM elections WHERE id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    $_SESSION['error'] = "Election not found";
    redirect('dashboard.php');
}

// Check if election is active
$current_time = date('Y-m-d H:i:s');
if ($election['start_date'] > $current_time || $election['end_date'] < $current_time) {
    $_SESSION['error'] = "This election is not currently active";
    redirect('dashboard.php');
}

// Check if user has already voted
$stmt = $conn->prepare("SELECT id FROM votes WHERE voter_id = ? AND election_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $election_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error'] = "You have already voted in this election";
    redirect('dashboard.php');
}

// Get candidates
$candidates = [];
$stmt = $conn->prepare("SELECT * FROM candidates WHERE election_id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

// Process vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    $candidate_id = (int)$_POST['candidate_id'];
    
    // Verify candidate belongs to this election
    $valid_candidate = false;
    foreach ($candidates as $c) {
        if ($c['id'] === $candidate_id) {
            $valid_candidate = true;
            break;
        }
    }
    
    if ($valid_candidate) {
        $stmt = $conn->prepare("INSERT INTO votes (voter_id, election_id, candidate_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $_SESSION['user_id'], $election_id, $candidate_id);
        
        if ($stmt->execute()) {
            // Update election status if needed
            $conn->query("UPDATE elections SET status = 'ongoing' WHERE id = $election_id AND status = 'upcoming'");
            
            log_activity('vote', "Voted in election: {$election['title']}");
            
            $_SESSION['success'] = "Your vote has been recorded successfully!";
            redirect('results.php?id=' . $election_id);
        } else {
            $error = "Failed to record your vote. Please try again.";
        }
    } else {
        $error = "Invalid candidate selected";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - Online Voting System</title>
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
        }

        nav ul li a:hover {
            color: var(--primary);
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

        .election-info {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border-top: 4px solid var(--primary);
        }

        .election-info h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .election-info p {
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .countdown {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .countdown i {
            margin-right: 10px;
        }

        .voting-form {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
        }

        .voting-form h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .voting-form h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .candidate-card {
            position: relative;
        }

        .candidate-card label {
            display: block;
            height: 100%;
            cursor: pointer;
        }

        .candidate-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .candidate-card input[type="radio"]:checked + .candidate-content {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .candidate-card input[type="radio"]:checked + .candidate-content::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .candidate-content {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            height: 100%;
            transition: var(--transition);
            position: relative;
        }

        .candidate-content:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
        }

        .candidate-content img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .no-photo {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--gray);
        }

        .candidate-content h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .candidate-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
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

        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
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

        /* Responsive Styles */
        @media (max-width: 992px) {
            .candidates-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 1rem;
            }

            h1 {
                font-size: 1.3rem;
            }

            .election-info,
            .voting-form {
                padding: 1.5rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .election-info,
        .voting-form {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .voting-form {
            animation-delay: 0.1s;
        }

        .candidate-card {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .candidate-card:nth-child(1) { animation-delay: 0.2s; }
        .candidate-card:nth-child(2) { animation-delay: 0.3s; }
        .candidate-card:nth-child(3) { animation-delay: 0.4s; }
        .candidate-card:nth-child(4) { animation-delay: 0.5s; }
        .candidate-card:nth-child(5) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <h1>Vote: <?php echo htmlspecialchars($election['title']); ?></h1>
        </div>
        
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <nav class="nav-collapse" id="navCollapse">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="elections.php"><i class="fas fa-vote-yea"></i> Elections</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="election-info">
            <h2><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($election['title']); ?></h2>
            <p><?php echo htmlspecialchars($election['description']); ?></p>
            <div class="countdown">
                <i class="fas fa-clock"></i>
                <span id="countdown"></span>
            </div>
        </div>
        
        <form method="POST" class="voting-form">
            <h3><i class="fas fa-user-check"></i> Select Your Candidate</h3>
            
            <div class="candidates-grid">
                <?php foreach ($candidates as $candidate): ?>
                    <div class="candidate-card">
                        <label>
                            <input type="radio" name="candidate_id" value="<?php echo $candidate['id']; ?>" required>
                            <div class="candidate-content">
                                <?php if ($candidate['photo']): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($candidate['photo']); ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                                <?php else: ?>
                                    <div class="no-photo"><i class="fas fa-user-tie"></i> No Photo Available</div>
                                <?php endif; ?>
                                <h4><?php echo htmlspecialchars($candidate['name']); ?></h4>
                                <p><?php echo htmlspecialchars($candidate['bio']); ?></p>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Submit Vote
                </button>
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
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

        // Countdown timer
        const endDate = new Date("<?php echo $election['end_date']; ?>").getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = endDate - now;
            
            if (distance < 0) {
                document.getElementById("countdown").innerHTML = "Election has ended";
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById("countdown").innerHTML = 
                `${days}d ${hours}h ${minutes}m ${seconds}s`;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);

        // Form submission animation
        const form = document.querySelector('.voting-form');
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
        });

        // Animate elements when they come into view
        const animateOnScroll = () => {
            const elements = document.querySelectorAll('.candidate-card');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (elementPosition < screenPosition) {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }
            });
        };

        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);
    </script>
   
</body>
</html>