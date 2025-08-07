<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get election details
$stmt = $conn->prepare("SELECT e.*, 
                       (SELECT COUNT(*) FROM candidates WHERE election_id = e.id) as candidate_count,
                       (SELECT COUNT(*) FROM votes WHERE election_id = e.id) as vote_count
                       FROM elections e
                       WHERE e.id = ?");
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

if (!$election) {
    $_SESSION['error'] = "Election not found";
    redirect('elections.php');
}

// Calculate status
$current_time = date('Y-m-d H:i:s');
if ($election['start_date'] > $current_time) {
    $election['status'] = 'upcoming';
} elseif ($election['end_date'] < $current_time) {
    $election['status'] = 'completed';
} else {
    $election['status'] = 'ongoing';
}

// Get candidates
$candidates = [];
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
}

// Handle candidate addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    $name = sanitize_input($_POST['name']);
    $bio = sanitize_input($_POST['bio']);
    
    // Handle file upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('candidate_') . '.' . $file_ext;
        $target_file = $upload_dir . $file_name;
        
        // Validate image
        $check = getimagesize($_FILES['photo']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo = $file_name;
            }
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO candidates (name, bio, photo, election_id) 
                           VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $bio, $photo, $election_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Candidate added successfully!";
        redirect("election.php?id=$election_id");
    } else {
        $error = "Failed to add candidate. Please try again.";
    }
}

// Handle candidate deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_candidate'])) {
    $candidate_id = (int)$_POST['candidate_id'];
    $conn->query("DELETE FROM candidates WHERE id = $candidate_id");
    log_activity('candidate_delete', "Deleted candidate ID $candidate_id");
    $_SESSION['success'] = "Candidate deleted successfully";
    redirect("election.php?id=$election_id");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($election['title']); ?> - Online Voting System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #43aa8b;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: var(--dark);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .election-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-light);
        }

        .election-header h2 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .election-header p {
            color: var(--gray);
            max-width: 700px;
        }

        .election-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-upcoming {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-ongoing {
            background-color: #d4edda;
            color: #155724;
        }

        .status-completed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .election-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .stat-card p {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .election-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn:hover {
            color: var(--primary);
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
            font-size: 1.3rem;
            color: var(--dark);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }

        .btn-primary:hover {
            background: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-secondary {
            background: white;
            color: var(--gray);
            border: 1px solid var(--gray-light);
        }

        .btn-secondary:hover {
            background: var(--gray-light);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: 1px solid var(--danger);
        }

        .btn-danger:hover {
            background: #d1143e;
            border-color: #d1143e;
        }

        .candidate-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .candidate-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .candidate-photo {
            height: 200px;
            background: var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .candidate-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-photo {
            color: var(--gray);
            font-size: 1rem;
        }

        .candidate-info {
            padding: 15px;
            flex-grow: 1;
        }

        .candidate-info h4 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .candidate-info p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .vote-count {
            font-weight: 600;
            color: var(--primary) !important;
        }

        .candidate-actions {
            padding: 0 15px 15px;
            margin-top: auto;
        }

        .no-candidates {
            text-align: center;
            padding: 40px;
            color: var(--gray);
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .election-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .election-meta {
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            
            .election-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .candidates-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .election-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php// include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="election-header">
            <div>
                <h2><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($election['title']); ?></h2>
                <p><?php echo htmlspecialchars($election['description']); ?></p>
            </div>
            <div class="election-meta">
                <span class="status-badge status-<?php echo $election['status']; ?>">
                    <i class="fas fa-circle"></i> <?php echo ucfirst($election['status']); ?>
                </span>
                <a href="edit_election.php?id=<?php echo $election_id; ?>" class="btn btn-sm btn-secondary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="dashboard.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
        
        <div class="election-stats">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Candidates</h3>
                <p><?php echo $election['candidate_count']; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-vote-yea"></i> Total Votes</h3>
                <p><?php echo $election['vote_count']; ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-calendar-alt"></i> Start Date</h3>
                <p><?php echo date('M j, Y H:i', strtotime($election['start_date'])); ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-calendar-times"></i> End Date</h3>
                <p><?php echo date('M j, Y H:i', strtotime($election['end_date'])); ?></p>
            </div>
        </div>
        
        <div class="election-tabs">
            <button class="tab-btn active" data-tab="candidates">
                <i class="fas fa-users"></i> Candidates
            </button>
            <button class="tab-btn" data-tab="voters">
                <i class="fas fa-user-check"></i> Voters
            </button>
            <button class="tab-btn" data-tab="results">
                <i class="fas fa-chart-bar"></i> Results
            </button>
        </div>
        
        <div class="tab-content active" id="candidates-tab">
            <div class="section-header">
                <h3><i class="fas fa-user-plus"></i> Manage Candidates</h3>
                <button id="addCandidateBtn" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Candidate
                </button>
            </div>
            
            <div id="addCandidateForm" style="display: none;">
                <form method="POST" enctype="multipart/form-data" class="candidate-form">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Candidate Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="bio"><i class="fas fa-info-circle"></i> Bio/Description</label>
                        <textarea id="bio" name="bio" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="photo"><i class="fas fa-camera"></i> Photo (Optional)</label>
                        <input type="file" id="photo" name="photo" accept="image/*">
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_candidate" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Candidate
                        </button>
                        <button type="button" id="cancelAddCandidate" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="candidates-grid">
                <?php foreach ($candidates as $candidate): ?>
                    <div class="candidate-card">
                        <div class="candidate-photo">
                            <?php if ($candidate['photo']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($candidate['photo']); ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>">
                            <?php else: ?>
                                <div class="no-photo"><i class="fas fa-user-circle fa-5x"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="candidate-info">
                            <h4><?php echo htmlspecialchars($candidate['name']); ?></h4>
                            <p><?php echo htmlspecialchars($candidate['bio']); ?></p>
                            <p class="vote-count"><i class="fas fa-vote-yea"></i> Votes: <?php echo $candidate['vote_count']; ?></p>
                        </div>
                        <form method="POST" class="candidate-actions">
                            <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                            <button type="submit" name="delete_candidate" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this candidate?')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($candidates)): ?>
                    <p class="no-candidates">
                        <i class="fas fa-users-slash fa-2x"></i><br>
                        No candidates added yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Voters Tab (Placeholder) -->
        <div class="tab-content" id="voters-tab">
            <div class="section-header">
                <h3><i class="fas fa-user-check"></i> Voter Management</h3>
                <p>Voter management features will be implemented here</p>
            </div>
        </div>
        
        <!-- Results Tab (Placeholder) -->
        <div class="tab-content" id="results-tab">
            <div class="section-header">
                <h3><i class="fas fa-chart-bar"></i> Election Results</h3>
                <p>Results visualization will be displayed here</p>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle add candidate form
        document.getElementById('addCandidateBtn').addEventListener('click', function() {
            document.getElementById('addCandidateForm').style.display = 'block';
            this.style.display = 'none';
        });
        
        document.getElementById('cancelAddCandidate').addEventListener('click', function() {
            document.getElementById('addCandidateForm').style.display = 'none';
            document.getElementById('addCandidateBtn').style.display = 'inline-block';
        });
        
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
    </script>
     
</body>
</html>