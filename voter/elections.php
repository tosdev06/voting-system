<?php
require_once '../config.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

// Get all elections with voting status
$elections = [];
$current_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT e.*, 
                       CASE 
                           WHEN e.start_date > ? THEN 'upcoming'
                           WHEN e.end_date < ? THEN 'completed'
                           ELSE 'ongoing'
                       END as current_status
                       FROM elections e
                       ORDER BY e.start_date DESC");
$stmt->bind_param("ss", $current_time, $current_time);
$stmt->execute();
$result = $stmt->get_result();

while ($election = $result->fetch_assoc()) {
    // Check if user has voted
    $vote_check = $conn->prepare("SELECT id FROM votes WHERE voter_id = ? AND election_id = ?");
    $vote_check->bind_param("ii", $_SESSION['user_id'], $election['id']);
    $vote_check->execute();
    $vote_check->store_result();
    $election['has_voted'] = $vote_check->num_rows > 0;
    $election['status'] = $election['current_status'];
    $elections[] = $election;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Elections - Online Voting System</title>
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

        /* Filter Buttons */
        .election-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 50px;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: #e9ecef;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        .filter-btn i {
            margin-right: 8px;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
        }

        .filter-btn:hover:not(.active) {
            background: #dee2e6;
        }

        /* Election Grid */
        .election-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
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
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
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

        .election-meta {
            margin-bottom: 1.5rem;
        }

        .election-meta p {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .election-meta i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
            color: var(--gray);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .status-upcoming {
            background: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }

        .status-ongoing {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .status-completed {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
        }

        .election-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
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

        .btn-disabled {
            background: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
        }

        .voted {
            color: var(--success);
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .voted i {
            margin-right: 8px;
        }

        .no-elections {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .no-elections i {
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

            .election-filters {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 1rem;
            }

            h1 {
                font-size: 1.3rem;
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
        .election-card:nth-child(6) { animation-delay: 0.6s; }
        .election-card:nth-child(7) { animation-delay: 0.7s; }
        .election-card:nth-child(8) { animation-delay: 0.8s; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <h1>All Elections</h1>
        </div>
        
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <nav class="nav-collapse" id="navCollapse">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="elections.php" class="active"><i class="fas fa-vote-yea"></i> All Elections</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="container">
        <div class="election-filters">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-layer-group"></i> All
            </button>
            <button class="filter-btn" data-filter="upcoming">
                <i class="fas fa-clock"></i> Upcoming
            </button>
            <button class="filter-btn" data-filter="ongoing">
                <i class="fas fa-running"></i> Ongoing
            </button>
            <button class="filter-btn" data-filter="completed">
                <i class="fas fa-flag-checkered"></i> Completed
            </button>
        </div>
        
        <div class="election-grid">
            <?php foreach ($elections as $election): ?>
                <div class="election-card" data-status="<?php echo $election['status']; ?>">
                    <h3><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($election['title']); ?></h3>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                    
                    <div class="election-meta">
                        <p><i class="far fa-calendar-alt"></i> <strong>Starts:</strong> <?php echo date('M j, Y H:i', strtotime($election['start_date'])); ?></p>
                        <p><i class="far fa-clock"></i> <strong>Ends:</strong> <?php echo date('M j, Y H:i', strtotime($election['end_date'])); ?></p>
                        <span class="status-badge status-<?php echo $election['status']; ?>">
                            <i class="<?php 
                                echo $election['status'] === 'upcoming' ? 'fas fa-clock' : 
                                      ($election['status'] === 'ongoing' ? 'fas fa-running' : 'fas fa-flag-checkered'); 
                            ?>"></i>
                            <?php echo ucfirst($election['status']); ?>
                        </span>
                    </div>
                    
                    <div class="election-actions">
                        <?php if ($election['status'] === 'upcoming'): ?>
                            <span class="btn btn-disabled">
                                <i class="fas fa-hourglass-start"></i> Not Started
                            </span>
                        <?php elseif ($election['status'] === 'ongoing' && !$election['has_voted']): ?>
                            <a href="vote.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-check"></i> Vote Now
                            </a>
                        <?php elseif ($election['status'] === 'ongoing' && $election['has_voted']): ?>
                            <span class="voted">
                                <i class="fas fa-check-circle"></i> You've Voted
                            </span>
                            <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-chart-line"></i> View Progress
                            </a>
                        <?php else: ?>
                            <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-poll"></i> View Results
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($elections)): ?>
            <div class="no-elections">
                <i class="fas fa-calendar-times"></i>
                <h3>No Elections Found</h3>
                <p>There are currently no elections available.</p>
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

        // Filter elections by status
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                document.querySelectorAll('.election-card').forEach(card => {
                    if (filter === 'all' || card.dataset.status === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Countdown timers for upcoming elections
        const upcomingCards = document.querySelectorAll('.election-card[data-status="upcoming"]');
        
        function updateUpcomingCountdowns() {
            upcomingCards.forEach(card => {
                const startDateText = card.querySelector('.election-meta p:first-child').textContent.replace('Starts: ', '').trim();
                const startDate = new Date(startDateText).getTime();
                const now = new Date().getTime();
                const distance = startDate - now;
                
                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    
                    let countdownText = 'Starts in ';
                    if (days > 0) countdownText += `${days}d `;
                    if (hours > 0 || days > 0) countdownText += `${hours}h `;
                    countdownText += `${minutes}m`;
                    
                    card.querySelector('.btn-disabled').innerHTML = `<i class="fas fa-hourglass-start"></i> ${countdownText}`;
                }
            });
        }
        
        // Update countdowns every minute
        if (upcomingCards.length > 0) {
            updateUpcomingCountdowns();
            setInterval(updateUpcomingCountdowns, 60000);
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
  
</body>
</html>