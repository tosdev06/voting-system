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

// Get candidates with vote counts
$results = [];
$total_votes = 0;
$stmt = $conn->prepare("SELECT c.id, c.name, c.photo, c.bio, COUNT(v.id) as votes 
                       FROM candidates c 
                       LEFT JOIN votes v ON v.candidate_id = c.id 
                       WHERE c.election_id = ? 
                       GROUP BY c.id 
                       ORDER BY votes DESC");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $results[] = $row;
    $total_votes += $row['votes'];
}

// Prepare data for chart
$chart_data = [
    'labels' => [],
    'data' => [],
    'colors' => []
];

// Generate distinct colors for each candidate
$colors = [
    '#4361ee', '#7209b7', '#f72585', '#4cc9f0', '#f8961e',
    '#3a0ca3', '#4895ef', '#3f37c9', '#560bad', '#b5179e'
];

foreach ($results as $index => $candidate) {
    $chart_data['labels'][] = $candidate['name'];
    $chart_data['data'][] = $candidate['votes'];
    $chart_data['colors'][] = $colors[$index % count($colors)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Online Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .election-info h2 i {
            margin-right: 10px;
        }

        .election-info p {
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .total-votes {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .total-votes i {
            margin-right: 10px;
        }

        .results-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .results-container h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        .results-container h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .chart-container {
            margin: 2rem 0;
            position: relative;
            height: 400px;
        }

        .result-item {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: var(--border-radius);
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .result-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
        }

        .result-candidate {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .result-candidate img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 3px solid white;
            box-shadow: var(--box-shadow);
        }

        .no-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--gray);
            font-size: 2rem;
        }

        .result-candidate h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .result-candidate p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .result-bar-container {
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            overflow: hidden;
            position: relative;
        }

        .result-bar {
            height: 100%;
            border-radius: 10px;
            background: var(--primary);
            transition: width 1s ease;
        }

        .result-percentage {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .result-votes {
            display: flex;
            align-items: center;
            color: var(--gray);
        }

        .result-votes i {
            margin-right: 5px;
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

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .chart-container {
                height: 300px;
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

            .result-candidate {
                flex-direction: column;
                text-align: center;
            }

            .result-candidate img,
            .no-photo {
                margin-right: 0;
                margin-bottom: 1rem;
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
            .results-container {
                padding: 1.5rem;
            }

            .chart-container {
                height: 250px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .election-info,
        .results-container {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .results-container {
            animation-delay: 0.1s;
        }

        .result-item {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .result-item:nth-child(1) { animation-delay: 0.2s; }
        .result-item:nth-child(2) { animation-delay: 0.3s; }
        .result-item:nth-child(3) { animation-delay: 0.4s; }
        .result-item:nth-child(4) { animation-delay: 0.5s; }
        .result-item:nth-child(5) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <h1>Results: <?php echo htmlspecialchars($election['title']); ?></h1>
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
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="election-info">
            <h2><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($election['title']); ?></h2>
            <p><?php echo htmlspecialchars($election['description']); ?></p>
            <div class="total-votes">
                <i class="fas fa-users"></i>
                <span><?php echo $total_votes; ?> total votes</span>
            </div>
        </div>
        
        <div class="results-container">
            <h3><i class="fas fa-poll"></i> Election Results</h3>
            
            <div class="chart-container">
                <canvas id="resultsChart"></canvas>
            </div>
            
            <input type="hidden" id="chartData" value="<?php echo htmlspecialchars(json_encode($chart_data)); ?>">
            
            <?php foreach ($results as $index => $candidate): ?>
                <div class="result-item">
                    <div class="result-candidate">
                        <?php if ($candidate['photo']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($candidate['photo']); ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                        <?php else: ?>
                            <div class="no-photo"><i class="fas fa-user-tie"></i></div>
                        <?php endif; ?>
                        <div>
                            <h4><?php echo htmlspecialchars($candidate['name']); ?></h4>
                            <p><?php echo htmlspecialchars($candidate['bio']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($total_votes > 0): ?>
                        <div class="result-percentage">
                            <?php echo round(($candidate['votes'] / $total_votes) * 100, 1); ?>% of votes
                        </div>
                        <div class="result-bar-container">
                            <div class="result-bar" style="width: <?php echo ($candidate['votes'] / $total_votes) * 100; ?>%; 
                                background: <?php echo $colors[$index % count($colors)]; ?>;"></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="result-votes">
                        <i class="fas fa-check-circle"></i>
                        <strong><?php echo $candidate['votes']; ?> votes</strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
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

        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            const chartData = JSON.parse(document.getElementById('chartData').value);
            const ctx = document.getElementById('resultsChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Votes',
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderColor: 'rgba(255, 255, 255, 0.8)',
                        borderWidth: 2,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' votes';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        });

        // Animate result bars when they come into view
        const animateBars = () => {
            const bars = document.querySelectorAll('.result-bar');
            
            bars.forEach(bar => {
                const barPosition = bar.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (barPosition < screenPosition) {
                    bar.style.width = bar.style.width;
                }
            });
        };

        window.addEventListener('scroll', animateBars);
        window.addEventListener('load', animateBars);
    </script>
   
</body>
</html>