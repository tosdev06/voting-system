<?php
require_once '../config.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'pdf';

// Validate format
$allowed_formats = ['pdf', 'csv', 'excel', 'json'];
if (!in_array($format, $allowed_formats)) {
    $_SESSION['error'] = "Invalid export format specified";
    redirect('results_dashboard.php');
}

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

// Determine election status
$current_time = date('Y-m-d H:i:s');
if ($election['start_date'] > $current_time) {
    $election['status'] = 'Upcoming';
} elseif ($election['end_date'] < $current_time) {
    $election['status'] = 'Completed';
} else {
    $election['status'] = 'Ongoing';
}

// Export based on format
switch ($format) {
    case 'csv':
        exportCSV($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate);
        break;
    case 'excel':
        exportExcel($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate);
        break;
    case 'json':
        exportJSON($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate);
        break;
    case 'pdf':
    default:
        exportPDF($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate);
}

function exportCSV($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="election_results_' . $election['id'] . '_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Election header
    fputcsv($output, ['Election Results Report']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Election Details']);
    fputcsv($output, ['Title', $election['title']]);
    fputcsv($output, ['Description', $election['description']]);
    fputcsv($output, ['Start Date', $election['start_date']]);
    fputcsv($output, ['End Date', $election['end_date']]);
    fputcsv($output, ['Status', $election['status']]);
    fputcsv($output, []);
    
    // Statistics
    fputcsv($output, ['Voting Statistics']);
    fputcsv($output, ['Total Votes', $total_votes]);
    fputcsv($output, ['Registered Voters', $total_registered_voters]);
    fputcsv($output, ['Voters Participated', $total_voters_participated]);
    fputcsv($output, ['Participation Rate', $participation_rate . '%']);
    fputcsv($output, []);
    
    // Candidates header
    fputcsv($output, ['Candidate Results']);
    fputcsv($output, ['Rank', 'Candidate Name', 'Votes', 'Percentage', 'Percentage of Total Voters']);
    
    // Candidates data
    $rank = 1;
    foreach ($candidates as $candidate) {
        $percentage = $total_votes > 0 ? round(($candidate['vote_count'] / $total_votes * 100), 2) : 0;
        $percentage_of_voters = $total_registered_voters > 0 ? round(($candidate['vote_count'] / $total_registered_voters * 100), 2) : 0;
        fputcsv($output, [
            $rank++,
            $candidate['name'],
            $candidate['vote_count'],
            $percentage . '%',
            $percentage_of_voters . '%'
        ]);
    }
    
    fclose($output);
    exit;
}

function exportExcel($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate) {
    // Create temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'excel');
    
    // Create new PHPExcel object
    require_once '../vendor/phpoffice/phpexcel/Classes/PHPExcel.php';
    $objPHPExcel = new PHPExcel();
    
    // Set document properties
    $objPHPExcel->getProperties()->setCreator("Online Voting System")
                                 ->setLastModifiedBy("Admin")
                                 ->setTitle("Election Results - " . $election['title'])
                                 ->setSubject("Election Results")
                                 ->setDescription("Election results document");
    
    // Add data
    $objPHPExcel->setActiveSheetIndex(0);
    $sheet = $objPHPExcel->getActiveSheet();
    $sheet->setTitle('Election Results');
    
    // Header
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', 'Election Results Report');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->setCellValue('A2', 'Generated on');
    $sheet->setCellValue('B2', date('Y-m-d H:i:s'));
    
    // Election details
    $sheet->setCellValue('A4', 'Election Details');
    $sheet->getStyle('A4')->getFont()->setBold(true);
    $sheet->setCellValue('A5', 'Title');
    $sheet->setCellValue('B5', $election['title']);
    $sheet->setCellValue('A6', 'Description');
    $sheet->setCellValue('B6', $election['description']);
    $sheet->setCellValue('A7', 'Start Date');
    $sheet->setCellValue('B7', $election['start_date']);
    $sheet->setCellValue('A8', 'End Date');
    $sheet->setCellValue('B8', $election['end_date']);
    $sheet->setCellValue('A9', 'Status');
    $sheet->setCellValue('B9', $election['status']);
    
    // Statistics
    $sheet->setCellValue('A11', 'Voting Statistics');
    $sheet->getStyle('A11')->getFont()->setBold(true);
    $sheet->setCellValue('A12', 'Total Votes');
    $sheet->setCellValue('B12', $total_votes);
    $sheet->setCellValue('A13', 'Registered Voters');
    $sheet->setCellValue('B13', $total_registered_voters);
    $sheet->setCellValue('A14', 'Voters Participated');
    $sheet->setCellValue('B14', $total_voters_participated);
    $sheet->setCellValue('A15', 'Participation Rate');
    $sheet->setCellValue('B15', $participation_rate . '%');
    
    // Candidate results
    $sheet->setCellValue('A17', 'Candidate Results');
    $sheet->getStyle('A17')->getFont()->setBold(true);
    
    // Table header
    $sheet->setCellValue('A18', 'Rank');
    $sheet->setCellValue('B18', 'Candidate Name');
    $sheet->setCellValue('C18', 'Votes');
    $sheet->setCellValue('D18', 'Percentage');
    $sheet->setCellValue('E18', 'Percentage of Total Voters');
    $sheet->getStyle('A18:E18')->getFont()->setBold(true);
    
    // Table data
    $row = 19;
    $rank = 1;
    foreach ($candidates as $candidate) {
        $percentage = $total_votes > 0 ? round(($candidate['vote_count'] / $total_votes * 100), 2) : 0;
        $percentage_of_voters = $total_registered_voters > 0 ? round(($candidate['vote_count'] / $total_registered_voters * 100), 2) : 0;
        
        $sheet->setCellValue('A' . $row, $rank++);
        $sheet->setCellValue('B' . $row, $candidate['name']);
        $sheet->setCellValue('C' . $row, $candidate['vote_count']);
        $sheet->setCellValue('D' . $row, $percentage . '%');
        $sheet->setCellValue('E' . $row, $percentage_of_voters . '%');
        $row++;
    }
    
    // Auto size columns
    foreach (range('A', 'E') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Save Excel file
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save($temp_file);
    
    // Output to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="election_results_' . $election['id'] . '_' . date('Ymd') . '.xlsx"');
    header('Content-Length: ' . filesize($temp_file));
    readfile($temp_file);
    
    // Clean up
    unlink($temp_file);
    exit;
}

function exportJSON($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="election_results_' . $election['id'] . '_' . date('Ymd') . '.json"');
    
    $result = [
        'metadata' => [
            'generated_at' => date('c'),
            'format_version' => '1.0'
        ],
        'election' => [
            'id' => $election['id'],
            'title' => $election['title'],
            'description' => $election['description'],
            'start_date' => $election['start_date'],
            'end_date' => $election['end_date'],
            'status' => $election['status']
        ],
        'statistics' => [
            'total_votes' => $total_votes,
            'registered_voters' => $total_registered_voters,
            'voters_participated' => $total_voters_participated,
            'participation_rate' => $participation_rate
        ],
        'candidates' => []
    ];
    
    $rank = 1;
    foreach ($candidates as $candidate) {
        $percentage = $total_votes > 0 ? round(($candidate['vote_count'] / $total_votes * 100), 2) : 0;
        $percentage_of_voters = $total_registered_voters > 0 ? round(($candidate['vote_count'] / $total_registered_voters * 100), 2) : 0;
        
        $result['candidates'][] = [
            'rank' => $rank++,
            'id' => $candidate['id'],
            'name' => $candidate['name'],
            'votes' => $candidate['vote_count'],
            'percentage_of_votes' => $percentage,
            'percentage_of_voters' => $percentage_of_voters
        ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportPDF($election, $candidates, $total_votes, $total_registered_voters, $total_voters_participated, $participation_rate) {
    require_once '../vendor/tcpdf.php';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Online Voting System');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Election Results - ' . $election['title']);
    $pdf->SetSubject('Election Results Report');
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'Election Results Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Election details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Election Details', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    
    $pdf->Cell(40, 7, 'Title:', 0, 0);
    $pdf->Cell(0, 7, $election['title'], 0, 1);
    
    $pdf->Cell(40, 7, 'Description:', 0, 0);
    $pdf->MultiCell(0, 7, $election['description'], 0, 1);
    
    $pdf->Cell(40, 7, 'Start Date:', 0, 0);
    $pdf->Cell(0, 7, $election['start_date'], 0, 1);
    
    $pdf->Cell(40, 7, 'End Date:', 0, 0);
    $pdf->Cell(0, 7, $election['end_date'], 0, 1);
    
    $pdf->Cell(40, 7, 'Status:', 0, 0);
    $pdf->Cell(0, 7, $election['status'], 0, 1);
    $pdf->Ln(10);
    
    // Statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Voting Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    
    $pdf->Cell(60, 7, 'Total Votes:', 0, 0);
    $pdf->Cell(0, 7, $total_votes, 0, 1);
    
    $pdf->Cell(60, 7, 'Registered Voters:', 0, 0);
    $pdf->Cell(0, 7, $total_registered_voters, 0, 1);
    
    $pdf->Cell(60, 7, 'Voters Participated:', 0, 0);
    $pdf->Cell(0, 7, $total_voters_participated, 0, 1);
    
    $pdf->Cell(60, 7, 'Participation Rate:', 0, 0);
    $pdf->Cell(0, 7, $participation_rate . '%', 0, 1);
    $pdf->Ln(15);
    
    // Candidate results
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Candidate Results', 0, 1);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(15, 10, 'Rank', 1, 0, 'C');
    $pdf->Cell(80, 10, 'Candidate Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Votes', 1, 0, 'C');
    $pdf->Cell(30, 10, '% of Votes', 1, 0, 'C');
    $pdf->Cell(30, 10, '% of Voters', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('helvetica', '', 10);
    $rank = 1;
    foreach ($candidates as $candidate) {
        $percentage = $total_votes > 0 ? round(($candidate['vote_count'] / $total_votes * 100), 2) : 0;
        $percentage_of_voters = $total_registered_voters > 0 ? round(($candidate['vote_count'] / $total_registered_voters * 100), 2) : 0;
        
        $pdf->Cell(15, 10, $rank++, 1, 0, 'C');
        $pdf->Cell(80, 10, $candidate['name'], 1, 0);
        $pdf->Cell(30, 10, $candidate['vote_count'], 1, 0, 'C');
        $pdf->Cell(30, 10, $percentage . '%', 1, 0, 'C');
        $pdf->Cell(30, 10, $percentage_of_voters . '%', 1, 1, 'C');
    }
    
    // Output PDF
    $pdf->Output('election_results_' . $election['id'] . '_' . date('Ymd') . '.pdf', 'D');
    exit;
}