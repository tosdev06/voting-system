<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get all elections
$elections = [];
$stmt = $conn->prepare("SELECT e.*, COUNT(c.id) as candidate_count, 
                       (SELECT COUNT(*) FROM votes WHERE election_id = e.id) as vote_count
                       FROM elections e
                       LEFT JOIN candidates c ON c.election_id = e.id
                       GROUP BY e.id
                       ORDER BY e.start_date DESC");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Calculate status based on current time
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

// Handle election deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_election'])) {
    $election_id = (int)$_POST['election_id'];
    $conn->query("DELETE FROM elections WHERE id = $election_id");
    log_activity('election_delete', "Deleted election ID $election_id");
    $_SESSION['success'] = "Election deleted successfully";
    redirect('elections.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections - Online Voting System</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        a{
            text-decoration:none;
            color:black;
        }
        h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        h2 i {
            color: var(--primary-color);
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }
        
        /* Election Table */
        .election-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 15px;
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
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .btn-sm {
            padding: 6px 10px;
            font-size: 0.85rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #e11d74;
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

        .btn-disabled {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            border: 1px solid #dee2e6;
        }
        
        /* Action Buttons */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .actions form {
            margin: 0;
        }
        
        /* No Elections Message */
        .no-elections {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .no-elections a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }
        
        .no-elections a:hover {
            text-decoration: underline;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 576px) {
            h2 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 15px;
            }
            
            .election-table {
                padding: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .status-badge {
                padding: 4px 8px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php //include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-vote-yea"></i> Manage Elections</h2>
            <a href="create_election.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Election
            </a>
            <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="election-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th><i class="far fa-calendar-alt"></i> Start Date</th>
                        <th><i class="far fa-calendar-alt"></i> End Date</th>
                        <th><i class="fas fa-users"></i> Candidates</th>
                        <th><i class="fas fa-vote-yea"></i> Votes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($elections as $election): ?>
                        <tr>
                            <td><?php echo $election['id']; ?></td>
                            <td>
                                <a href="election.php?id=<?php echo $election['id']; ?>" class="text-primary">
                                    <i class="fas fa-poll-h"></i> <?php echo htmlspecialchars($election['title']); ?>
                                </a>
                            </td>
                            <td><?php echo date('M j, Y H:i', strtotime($election['start_date'])); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($election['end_date'])); ?></td>
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
                            <td class="actions">
                                <?php if ($election['status'] == 'upcoming'): ?>
                                    <a href="edit_election.php?id=<?php echo $election['id']; ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-sm btn-disabled" title="Editing only allowed for upcoming elections">
                                        <i class="fas fa-edit"></i> Edit
                                    </span>
                                <?php endif; ?>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this election? All associated data will be permanently removed.');">
                                    <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                                    <button type="submit" name="delete_election" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($elections)): ?>
                <p class="no-elections">
                    <i class="fas fa-box-open"></i> No elections found. <a href="create_election.php">Create your first election</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../js/admin.js"></script>
    <script>
        // Enhance delete confirmation
        document.querySelectorAll('button[name="delete_election"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('WARNING: This will permanently delete the election and all associated data (candidates, votes, etc.). Are you sure?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>