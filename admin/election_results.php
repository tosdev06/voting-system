<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
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
    redirect('results_dashboard.php');
}

// Get candidates with vote counts
$candidates = [];
$total_votes = 0;
$stmt = $conn->prepare("SELECT c.*, COUNT(v.id) as vote_count
                       FROM candidates c
                       LEFT JOIN votes v ON v.candidate_id = c.id
                       WHERE c.election_id = ?
                       GROUP BY c.id
                       ORDER BY vote_count DESC");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
    $total_votes += $row['vote_count'];
}

// Get voter participation data
$total_registered_voters = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetch_row()[0];
$total_voters_participated = $conn->query("SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = $election_id")->fetch_row()[0];
$participation_rate = $total_registered_voters > 0 ? round(($total_voters_participated / $total_registered_voters) * 100, 1) : 0;

// Prepare data for charts
$chart_labels = [];
$chart_data = [];
$chart_colors = ['#4361ee', '#3f37c9', '#4cc9f0', '#4895ef', '#560bad', '#b5179e'];

foreach ($candidates as $index => $candidate) {
    $chart_labels[] = $candidate['name'];
    $chart_data[] = $candidate['vote_count'];
}

// Get voting timeline data
$timeline_labels = [];
$timeline_data = [];
$stmt = $conn->prepare("SELECT DATE(voted_at) as day, COUNT(*) as votes
                       FROM votes
                       WHERE election_id = ?
                       GROUP BY DATE(voted_at)
                       ORDER BY day");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$timeline_result = $stmt->get_result();

while ($row = $timeline_result->fetch_assoc()) {
    $timeline_labels[] = date('M j', strtotime($row['day']));
    $timeline_data[] = $row['votes'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results: <?php echo htmlspecialchars($election['title']); ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        h1 i {
            color: var(--primary-color);
        }
        
        h2, h3, h4 {
            color: var(--dark-color);
            margin-top: 0;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Stats Grid */
        .election-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card h3 {
            font-size: 1rem;
            color: #6c757d;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-card p {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }
        
        /* Tabs */
        .results-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: #6c757d;
            position: relative;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn.active {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .tab-btn:hover:not(.active) {
            color: var(--dark-color);
            background-color: #f8f9fa;
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Charts */
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Candidate Info */
        .candidate-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .candidate-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .no-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        /* Percentage Bar */
        .percentage-bar-container {
            width: 100%;
            background-color: #e9ecef;
            border-radius: 20px;
            height: 25px;
            position: relative;
        }
        
        .percentage-bar {
            height: 100%;
            border-radius: 20px;
            background-color: var(--primary-color);
            transition: width 0.5s ease;
        }
        
        .percentage-text {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-upcoming {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning-color);
        }
        
        .status-ongoing {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success-color);
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        /* Export Options */
        .export-options {
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .export-options h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .election-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .chart-container {
                height: 300px;
            }
            
            th, td {
                padding: 10px 12px;
            }
        }
        
        @media (max-width: 576px) {
            .election-stats {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 1.6rem;
            }
            
            .tab-btn {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .candidate-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <?php //include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-poll-h"></i> <?php echo htmlspecialchars($election['title']); ?></h1>
            <div>
                <a href="results_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="export_results.php?id=<?php echo $election_id; ?>" class="btn btn-primary">
                    <i class="fas fa-file-export"></i> Export Results
                </a>
            </div>
        </div>
        
        <!-- Election Summary Stats -->
        <div class="election-stats">
            <div class="stat-card">
                <h3><i class="fas fa-vote-yea"></i> Total Votes</h3>
                <p><?php echo $total_votes; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-check"></i> Voter Participation</h3>
                <p><?php echo $total_voters_participated; ?>/<?php echo $total_registered_voters; ?> (<?php echo $participation_rate; ?>%)</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Candidates</h3>
                <p><?php echo count($candidates); ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-info-circle"></i> Status</h3>
                <p>
                    <?php
                    $current_time = date('Y-m-d H:i:s');
                    if ($election['start_date'] > $current_time) {
                        echo '<span class="status-badge status-upcoming"><i class="fas fa-clock"></i> Upcoming</span>';
                    } elseif ($election['end_date'] < $current_time) {
                        echo '<span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Completed</span>';
                    } else {
                        echo '<span class="status-badge status-ongoing"><i class="fas fa-spinner"></i> Ongoing</span>';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <!-- Results Tabs -->
        <div class="results-tabs">
            <button class="tab-btn active" data-tab="results">
                <i class="fas fa-chart-pie"></i> Results Overview
            </button>
            <button class="tab-btn" data-tab="candidates">
                <i class="fas fa-users"></i> Candidate Details
            </button>
            <button class="tab-btn" data-tab="voters">
                <i class="fas fa-user-friends"></i> Voter Participation
            </button>
            <button class="tab-btn" data-tab="timeline">
                <i class="fas fa-chart-line"></i> Voting Timeline
            </button>
        </div>
        
        <!-- Results Overview Tab -->
        <div class="tab-content active" id="results-tab">
            <div class="chart-container">
                <canvas id="resultsChart"></canvas>
                <input type="hidden" id="chartData" value="<?php echo htmlspecialchars(json_encode([
                    'labels' => $chart_labels,
                    'data' => $chart_data,
                    'colors' => array_slice($chart_colors, 0, count($candidates))
                ])); ?>">
            </div>
        </div>
        
        <!-- Candidate Details Tab -->
        <div class="tab-content" id="candidates-tab">
            <table class="candidate-results">
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Votes</th>
                        <th>Percentage</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td>
                                <div class="candidate-info">
                                    <?php if ($candidate['photo']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($candidate['photo']); ?>" 
                                             alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-photo"><?php echo strtoupper(substr($candidate['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <h4><?php echo htmlspecialchars($candidate['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($candidate['bio']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $candidate['vote_count']; ?></td>
                            <td>
                                <div class="percentage-bar-container">
                                    <div class="percentage-bar" 
                                         style="width: <?php echo $total_votes > 0 ? ($candidate['vote_count'] / $total_votes * 100) : 0; ?>%">
                                    </div>
                                    <span class="percentage-text">
                                        <?php echo $total_votes > 0 ? round(($candidate['vote_count'] / $total_votes * 100), 1) : 0; ?>%
                                    </span>
                                </div>
                            </td>
                            <td>
                                <a href="candidate_details.php?id=<?php echo $candidate['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Voter Participation Tab -->
        <div class="tab-content" id="voters-tab">
            <div class="election-stats">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Total Registered Voters</h3>
                    <p><?php echo $total_registered_voters; ?></p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-check"></i> Voters Participated</h3>
                    <p><?php echo $total_voters_participated; ?></p>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-percentage"></i> Participation Rate</h3>
                    <p><?php echo $participation_rate; ?>%</p>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="participationChart"></canvas>
            </div>
            
            <h3>Recent Votes</h3>
            <table class="voter-list">
                <thead>
                    <tr>
                        <th>Voter ID</th>
                        <th>Username</th>
                        <th>Voted At</th>
                        <th>Candidate Chosen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT v.id, u.username, v.voted_at, c.name as candidate_name
                                          FROM votes v
                                          JOIN users u ON v.voter_id = u.id
                                          JOIN candidates c ON v.candidate_id = c.id
                                          WHERE v.election_id = ?
                                          ORDER BY v.voted_at DESC
                                          LIMIT 100");
                    $stmt->bind_param("i", $election_id);
                    $stmt->execute();
                    $voters = $stmt->get_result();
                    
                    while ($voter = $voters->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $voter['id']; ?></td>
                            <td><?php echo htmlspecialchars($voter['username']); ?></td>
                            <td><i class="far fa-clock"></i> <?php echo date('M j, Y H:i', strtotime($voter['voted_at'])); ?></td>
                            <td><?php echo htmlspecialchars($voter['candidate_name']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Voting Timeline Tab -->
        <div class="tab-content" id="timeline-tab">
            <div class="chart-container">
                <canvas id="timelineChart"></canvas>
                <input type="hidden" id="timelineData" value="<?php echo htmlspecialchars(json_encode([
                    'labels' => $timeline_labels,
                    'data' => $timeline_data
                ])); ?>">
            </div>
        </div>
        
        <!-- Export Options -->
        <div class="export-options">
            <h3><i class="fas fa-file-export"></i> Export Results:</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="export_results.php?id=<?php echo $election_id; ?>&format=pdf" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="export_results.php?id=<?php echo $election_id; ?>&format=excel" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export_results.php?id=<?php echo $election_id; ?>&format=csv" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
                <a href="export_results.php?id=<?php echo $election_id; ?>&format=json" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-code"></i> JSON
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update active tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Results chart (pie/doughnut)
            const chartData = JSON.parse(document.getElementById('chartData').value);
            const resultsCtx = document.getElementById('resultsChart').getContext('2d');
            new Chart(resultsCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Timeline chart (bar)
            const timelineData = JSON.parse(document.getElementById('timelineData').value);
            const timelineCtx = document.getElementById('timelineChart').getContext('2d');
            new Chart(timelineCtx, {
                type: 'bar',
                data: {
                    labels: timelineData.labels,
                    datasets: [{
                        label: 'Votes per Day',
                        data: timelineData.data,
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Participation chart (pie)
            const participationCtx = document.getElementById('participationChart').getContext('2d');
            new Chart(participationCtx, {
                type: 'pie',
                data: {
                    labels: ['Participated', 'Did Not Participate'],
                    datasets: [{
                        data: [<?php echo $total_voters_participated; ?>, <?php echo $total_registered_voters - $total_voters_participated; ?>],
                        backgroundColor: ['#4cc9f0', '#e9ecef'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>