<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get all elections with summary statistics
$elections = [];
$stmt = $conn->prepare("SELECT e.*, 
                       COUNT(DISTINCT v.id) as vote_count,
                       COUNT(DISTINCT c.id) as candidate_count
                       FROM elections e
                       LEFT JOIN votes v ON e.id = v.election_id
                       LEFT JOIN candidates c ON e.id = c.election_id
                       GROUP BY e.id
                       ORDER BY e.end_date DESC");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Determine election status
    $current_time = date('Y-m-d H:i:s');
    if ($row['start_date'] > $current_time) {
        $row['status'] = 'upcoming';
    } elseif ($row['end_date'] < $current_time) {
        $row['status'] = 'completed';
    } else {
        $row['status'] = 'ongoing';
    }
    $elections[] = $row;
}

// Get system-wide statistics
$total_elections = count($elections);
$total_votes = $conn->query("SELECT COUNT(*) FROM votes")->fetch_row()[0];
$total_voters = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'voter'")->fetch_row()[0];
$active_elections = $conn->query("SELECT COUNT(*) FROM elections 
                                WHERE start_date <= NOW() AND end_date >= NOW()")->fetch_row()[0];

// Prepare data for voting trends chart (last 6 months)
$monthly_votes = [];
$month_labels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_labels[] = date('M Y', strtotime($month . '-01'));
    
    $stmt = $conn->prepare("SELECT COUNT(*) as votes 
                           FROM votes 
                           WHERE DATE_FORMAT(voted_at, '%Y-%m') = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $monthly_votes[] = $row['votes'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Dashboard - Admin Panel</title>
    
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
        a{
            text-decoration:none;
            color:black;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .back-btn {
            margin-bottom: 20px;
        }
        
        .back-btn .btn {
            padding: 10px 15px;
            font-size: 0.95rem;
        }
        
        h1, h2, h3 {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
        }
        
        h1 {
            font-size: 2.2rem;
            font-weight: 700;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        h1 i {
            color: var(--primary-color);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid var(--primary-color);
            display: flex;
            flex-direction: column;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }
        
        /* Election Filters */
        .election-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            background-color: #e9ecef;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .filter-btn:hover:not(.active) {
            background-color: #dee2e6;
        }
        
        /* Election Results Table */
        .results-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .election-results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .election-results-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        
        .election-results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .election-results-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
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
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-sm {
            font-size: 0.85rem;
            padding: 6px 10px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: 1px solid var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: 1px solid #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        /* Analytics Section */
        .analytics-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .election-filters {
                justify-content: center;
            }
            
            .action-btns {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .election-results-table {
                font-size: 0.9rem;
            }
            
            .election-results-table th, 
            .election-results-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <?php //include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="back-btn">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <h1><i class="fas fa-chart-line"></i> Election Results Dashboard</h1>
        
        <!-- Summary Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-vote-yea"></i> Total Elections</h3>
                <p><?php echo $total_elections; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-to-slot"></i> Total Votes</h3>
                <p><?php echo $total_votes; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Registered Voters</h3>
                <p><?php echo $total_voters; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-bolt"></i> Active Elections</h3>
                <p><?php echo $active_elections; ?></p>
            </div>
        </div>
        
        <!-- Election Results Table -->
        <div class="results-section">
            <h2><i class="fas fa-list-ol"></i> All Elections</h2>
            
            <div class="election-filters">
                <button class="filter-btn active" data-filter="all">
                    <i class="fas fa-layer-group"></i> All Elections
                </button>
                <button class="filter-btn" data-filter="upcoming">
                    <i class="fas fa-clock"></i> Upcoming
                </button>
                <button class="filter-btn" data-filter="ongoing">
                    <i class="fas fa-spinner"></i> Ongoing
                </button>
                <button class="filter-btn" data-filter="completed">
                    <i class="fas fa-check-circle"></i> Completed
                </button>
            </div>
            
            <table class="election-results-table">
                <thead>
                    <tr>
                        <th>Election Title</th>
                        <th>Period</th>
                        <th><i class="fas fa-users"></i> Candidates</th>
                        <th><i class="fas fa-vote-yea"></i> Votes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($elections as $election): ?>
                        <tr class="election-row" data-status="<?php echo $election['status']; ?>">
                            <td>
                                <a href="election_results.php?id=<?php echo $election['id']; ?>" class="text-primary">
                                    <i class="fas fa-poll-h"></i> <?php echo htmlspecialchars($election['title']); ?>
                                </a>
                            </td>
                            <td>
                                <i class="far fa-calendar-alt"></i> 
                                <?php echo date('M j, Y', strtotime($election['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($election['end_date'])); ?>
                            </td>
                            <td><?php echo $election['candidate_count']; ?></td>
                            <td><?php echo $election['vote_count']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $election['status']; ?>">
                                    <?php if ($election['status'] == 'upcoming'): ?>
                                        <i class="fas fa-clock"></i>
                                    <?php elseif ($election['status'] == 'ongoing'): ?>
                                        <i class="fas fa-spinner"></i>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php endif; ?>
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="election_results.php?id=<?php echo $election['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-chart-pie"></i> Results
                                    </a>
                                    <a href="export_results.php?id=<?php echo $election['id']; ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-file-export"></i> Export
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Voting Analytics Chart -->
        <div class="analytics-section">
            <h2><i class="fas fa-chart-bar"></i> Voting Analytics</h2>
            <div class="chart-container">
                <canvas id="votingTrendsChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        // Filter elections by status
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                document.querySelectorAll('.election-row').forEach(row => {
                    if (filter === 'all' || row.dataset.status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
        
        // Voting trends chart with real data
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('votingTrendsChart').getContext('2d');
            
            // Get the monthly vote data from PHP
            const monthlyData = {
                labels: <?php echo json_encode($month_labels); ?>,
                data: <?php echo json_encode($monthly_votes); ?>
            };
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthlyData.labels,
                    datasets: [{
                        label: 'Votes Cast',
                        data: monthlyData.data,
                        backgroundColor: 'rgba(67, 97, 238, 0.2)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 2,
                        tension: 0.1,
                        fill: true,
                        pointBackgroundColor: 'white',
                        pointBorderColor: 'rgba(67, 97, 238, 1)',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Votes'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Votes: ${context.raw}`;
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 12,
                            usePointStyle: true
                        },
                        title: {
                            display: true,
                            text: 'Voting Activity Over Time',
                            font: {
                                size: 16
                            },
                            padding: {
                                bottom: 20
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>