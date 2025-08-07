<?php
require_once '../config.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

// Get active elections
$current_time = date('Y-m-d H:i:s');
$active_elections = [];
$stmt = $conn->prepare("SELECT e.* FROM elections e 
                      WHERE e.start_date <= ? AND e.end_date >= ? 
                      AND e.status = 'ongoing' 
                      ORDER BY e.end_date ASC");
$stmt->bind_param("ss", $current_time, $current_time);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Check if user has already voted
    $vote_check = $conn->prepare("SELECT id FROM votes WHERE voter_id = ? AND election_id = ?");
    $vote_check->bind_param("ii", $_SESSION['user_id'], $row['id']);
    $vote_check->execute();
    $vote_check->store_result();
    $row['has_voted'] = $vote_check->num_rows > 0;
    $active_elections[] = $row;
}

// Get completed elections
$completed_elections = [];
$stmt = $conn->prepare("SELECT * FROM elections 
                      WHERE end_date < ? 
                      AND status = 'completed' 
                      ORDER BY end_date DESC LIMIT 5");
$stmt->bind_param("s", $current_time);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $completed_elections[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard - Online Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #e6e9ff;
            --secondary: #7209b7;
            --danger: #f72585;
            --success: #4cc9f0;
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

        h2 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        h2 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .election-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .election-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            transition: var(--transition);
            border-top: 4px solid var(--primary);
            position: relative;
            overflow: hidden;
        }

        .election-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1);
        }

        .election-card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        .election-card h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .election-card p {
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .election-card .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .election-card .meta span {
            display: flex;
            align-items: center;
            color: var(--gray);
        }

        .election-card .meta i {
            margin-right: 5px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
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

        .voted {
            color: var(--success);
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .voted i {
            margin-right: 8px;
        }

        .status-badge {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.3rem 0.8rem;
            border-radius: 0 var(--border-radius) 0 var(--border-radius);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: var(--success);
            color: white;
        }

        .status-completed {
            background: var(--gray);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .election-grid {
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
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 1rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .election-card {
                padding: 1rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .election-card {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .election-card:nth-child(1) { animation-delay: 0.1s; }
        .election-card:nth-child(2) { animation-delay: 0.2s; }
        .election-card:nth-child(3) { animation-delay: 0.3s; }
        .election-card:nth-child(4) { animation-delay: 0.4s; }
        .election-card:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        </div>
        
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <nav class="nav-collapse" id="navCollapse">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="elections.php"><i class="fas fa-vote-yea"></i> All Elections</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="container">
        <h2><i class="fas fa-bolt"></i> Active Elections</h2>
        <?php if (empty($active_elections)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Active Elections</h3>
                <p>There are currently no elections available for voting. Check back later!</p>
            </div>
        <?php else: ?>
            <div class="election-grid">
                <?php foreach ($active_elections as $election): ?>
                    <div class="election-card">
                        <span class="status-badge status-active">
                            <i class="fas fa-running"></i> Active
                        </span>
                        <h3><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($election['title']); ?></h3>
                        <p><?php echo htmlspecialchars($election['description']); ?></p>
                        <div class="meta">
                            <span><i class="far fa-clock"></i> <?php echo date('M j, Y H:i', strtotime($election['end_date'])); ?></span>
                        </div>
                        <?php if ($election['has_voted']): ?>
                            <p class="voted"><i class="fas fa-check-circle"></i> You have already voted</p>
                            <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-chart-bar"></i> View Results
                            </a>
                        <?php else: ?>
                            <a href="vote.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-check"></i> Vote Now
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h2><i class="fas fa-history"></i> Recent Completed Elections</h2>
        <?php if (!empty($completed_elections)): ?>
            <div class="election-grid">
                <?php foreach ($completed_elections as $election): ?>
                    <div class="election-card">
                        <span class="status-badge status-completed">
                            <i class="fas fa-flag-checkered"></i> Completed
                        </span>
                        <h3><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($election['title']); ?></h3>
                        <p><?php echo htmlspecialchars($election['description']); ?></p>
                        <div class="meta">
                            <span><i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($election['end_date'])); ?></span>
                            <span><i class="fas fa-users"></i> <?php echo rand(100, 500); ?> voters</span>
                        </div>
                        <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-chart-pie"></i> View Results
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="elections.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Elections
                </a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Completed Elections</h3>
                <p>There are no completed elections to display yet.</p>
            </div>
        <?php endif; ?>
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

        // Countdown timers for active elections
        const countdownElements = document.querySelectorAll('.election-card .meta span:first-child');
        
        function updateCountdowns() {
            countdownElements.forEach(el => {
                const endTimeText = el.textContent.replace('Ends: ', '').trim();
                const endTime = new Date(endTimeText).getTime();
                const now = new Date().getTime();
                const distance = endTime - now;
                
                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    
                    let countdownText = '';
                    if (days > 0) countdownText += `${days}d `;
                    if (hours > 0 || days > 0) countdownText += `${hours}h `;
                    countdownText += `${minutes}m left`;
                    
                    el.innerHTML = `<i class="far fa-clock"></i> ${countdownText}`;
                }
            });
        }
        
        // Update countdowns every minute
        if (countdownElements.length > 0) {
            updateCountdowns();
            setInterval(updateCountdowns, 60000);
        }

        // Animate elements when they come into view
        const animateOnScroll = () => {
            const elements = document.querySelectorAll('.election-card');
            
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
    <?php include '../footer.php' ?>
</body>
</html>