<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get all elections with status
$elections = [];
$stmt = $conn->prepare("SELECT *, 
    CASE 
        WHEN start_date > NOW() THEN 'upcoming'
        WHEN end_date < NOW() THEN 'completed'
        ELSE 'ongoing'
    END as status
    FROM elections ORDER BY start_date DESC");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $elections[] = $row;
}

// Get system statistics
$total_elections = count($elections);
$active_elections = count(array_filter($elections, function($e) { return $e['status'] === 'ongoing'; }));
$upcoming_elections = count(array_filter($elections, function($e) { return $e['status'] === 'upcoming'; }));
$completed_elections = count(array_filter($elections, function($e) { return $e['status'] === 'completed'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Voting System</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-color);
            color: white;
            position: fixed;
            height: 100vh;
            z-index: 100;
            transition: all 0.3s ease-in-out;
            transform: translateX(-100%);
            overflow-y: auto;
        }
        
        .sidebar.active {
            transform: translateX(0);
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .sidebar-header h2 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-nav ul {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .sidebar-nav li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-nav li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .sidebar-nav li a span {
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .sidebar-nav li.active a {
            background: var(--primary-color);
            color: white;
        }
        
        /* Mobile Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            justify-content: center;
            align-items: center;
            transition: all 0.3s;
        }
        
        .sidebar-toggle:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
        }
        
        .sidebar-toggle.active {
            left: calc(var(--sidebar-width) + 10px);
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Collapse Button */
        .sidebar-collapse-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            position: absolute;
            right: 10px;
            top: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: none;
        }
        
        .sidebar-collapse-btn:hover {
            color: white;
            transform: scale(1.1);
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 0;
            transition: all 0.3s;
            position: relative;
            z-index: 1;
        }
        
        @media (min-width: 993px) {
            .sidebar {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: var(--sidebar-width);
            }
            
            .sidebar-toggle {
                display: none !important;
            }
            
            .sidebar-collapse-btn {
                display: block;
            }
        }
        
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 992px) {
            .main-header {
                padding-top: 70px;
            }
        }
        
        .main-header h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .avatar {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .content-wrapper {
            padding: 20px;
        }
        
        /* Stats Section */
        .stats-section {
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
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }
        
        .bg-primary { background: var(--primary-color); }
        .bg-success { background: var(--success-color); }
        .bg-warning { background: var(--warning-color); }
        .bg-danger { background: var(--danger-color); }
        
        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .stat-info p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Elections Section */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .election-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .election-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .election-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .election-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .election-header h3 {
            font-size: 1.2rem;
            color: var(--dark-color);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-upcoming {
            background: rgba(248, 150, 30, 0.1);
            color: var(--warning-color);
        }
        
        .status-ongoing {
            background: rgba(76, 201, 240, 0.1);
            color: var(--success-color);
        }
        
        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .election-description {
            color: #6c757d;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .election-dates {
            margin-bottom: 15px;
        }
        
        .date-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .date-item i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .date-item span {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .date-item strong {
            display: block;
            font-size: 0.95rem;
        }
        
        .election-actions {
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .stats-section {
                grid-template-columns: 1fr 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .sidebar-toggle {
                display: flex;
            }
        }
        
        @media (max-width: 576px) {
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .election-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Collapsed Sidebar Styles */
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .sidebar-header h2 span,
        .sidebar.collapsed .sidebar-nav li a span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-nav li a {
            justify-content: center;
            padding: 15px 0;
        }
        
        .sidebar.collapsed .sidebar-collapse-btn i {
            transform: rotate(180deg);
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }
        
        /* Prevent body scrolling when sidebar is open */
        body.sidebar-open {
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="false" aria-controls="sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay (will be added by JavaScript) -->

    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar" aria-hidden="true">
            <div class="sidebar-header">
                <h2><i class="fas fa-user-shield"></i> <span>Admin Panel</span></h2>
                <button id="toggleSidebarText" class="sidebar-collapse-btn" aria-label="Toggle sidebar text">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="elections.php">
                            <i class="fas fa-vote-yea"></i>
                            <span>Elections</span>
                        </a>
                    </li>
                    <li>
                        <a href="results_dashboard.php">
                            <i class="fas fa-chart-pie"></i>
                            <span>Results</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <header class="main-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
                <div class="user-profile">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <div class="avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </header>
            
            <div class="content-wrapper">
                <section class="stats-section">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_elections; ?></h3>
                            <p>Total Elections</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $active_elections; ?></h3>
                            <p>Active Elections</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $upcoming_elections; ?></h3>
                            <p>Upcoming Elections</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-danger">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $completed_elections; ?></h3>
                            <p>Completed Elections</p>
                        </div>
                    </div>
                </section>
                
                <section class="elections-section">
                    <div class="section-header">
                        <h2><i class="fas fa-calendar-alt"></i> Recent Elections</h2>
                        <a href="create_election.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create New Election
                        </a>
                    </div>
                    
                    <div class="election-grid">
                        <?php if (empty($elections)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No elections found. Create your first election!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($elections, 0, 6) as $election): ?>
                                <div class="election-card card-<?php echo $election['status']; ?>">
                                    <div class="election-header">
                                        <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                                        <span class="status-badge status-<?php echo $election['status']; ?>">
                                            <?php echo ucfirst($election['status']); ?>
                                        </span>
                                    </div>
                                    <p class="election-description"><?php echo htmlspecialchars($election['description']); ?></p>
                                    
                                    <div class="election-dates">
                                        <div class="date-item">
                                            <i class="fas fa-play-circle"></i>
                                            <div>
                                                <span>Start Date</span>
                                                <strong><?php echo date('M j, Y H:i', strtotime($election['start_date'])); ?></strong>
                                            </div>
                                        </div>
                                        <div class="date-item">
                                            <i class="fas fa-stop-circle"></i>
                                            <div>
                                                <span>End Date</span>
                                                <strong><?php echo date('M j, Y H:i', strtotime($election['end_date'])); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="election-actions">
                                        <a href="election.php?id=<?php echo $election['id']; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                        <a href="election_results.php?id=<?php echo $election['id']; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-chart-bar"></i> Results
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const toggleSidebarText = document.getElementById('toggleSidebarText');
            const body = document.body;
            
            // Create overlay element
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            
            // Toggle sidebar
            function toggleSidebar() {
                const isOpen = sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                body.classList.toggle('sidebar-open');
                sidebarToggle.classList.toggle('active');
                sidebar.setAttribute('aria-hidden', !isOpen);
                sidebarToggle.setAttribute('aria-expanded', isOpen);
                
                // Store state in localStorage
                localStorage.setItem('sidebarOpen', isOpen);
            }
            
            // Toggle sidebar text (desktop only)
            function toggleCollapseSidebar() {
                if (window.innerWidth > 992) {
                    sidebar.classList.toggle('collapsed');
                    // Store preference in localStorage
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                }
            }
            
            // Initialize sidebar states
            if (localStorage.getItem('sidebarOpen') === 'true') {
                toggleSidebar();
            }
            
            if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 992) {
                sidebar.classList.add('collapsed');
            }
            
            // Event listeners
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
            
            if (toggleSidebarText) {
                toggleSidebarText.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleCollapseSidebar();
                });
            }
            
            // Close sidebar when clicking overlay
            overlay.addEventListener('click', toggleSidebar);
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 992 && 
                    sidebar.classList.contains('active') && 
                    !sidebar.contains(e.target) && 
                    e.target !== sidebarToggle) {
                    toggleSidebar();
                }
            });
            
            // Add active class to current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.sidebar-nav li a');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.parentElement.classList.add('active');
                }
                
                // Close sidebar when a nav link is clicked (for mobile)
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        toggleSidebar();
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    // Ensure sidebar is visible on desktop
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    body.classList.remove('sidebar-open');
                    sidebarToggle.classList.remove('active');
                    sidebar.setAttribute('aria-hidden', 'false');
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                } else {
                    // Ensure sidebar is hidden on mobile if collapsed
                    if (sidebar.classList.contains('collapsed')) {
                        sidebar.classList.remove('collapsed');
                    }
                }
            });
        });
    </script>
</body>
</html>