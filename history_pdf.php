<?php
require 'vendor/autoload.php'; // Make sure Dompdf is installed via Composer

use Dompdf\Dompdf;

session_start();
$username = $_SESSION['username'] ?? 'Anonymous';

// Load complaint data from JSON file
$reportsFile = 'user_reports.json';
if (!file_exists($reportsFile)) {
    die("Complaint data file not found.");
}

$reports = json_decode(file_get_contents($reportsFile), true);
if (!$reports || !is_array($reports)) {
    die("Complaint data is missing or invalid.");
}

// Build HTML content
$html = '<h1 style="text-align:center; font-family:Arial;">Complaint History for ' . htmlspecialchars($username) . '</h1>';
$html .= '<table style="width:100%; border-collapse:collapse; font-family:Arial;" border="1">';
$html .= '<thead>
<tr>
  <th>#</th>
  <th>Subject</th>
  <th>Description</th>
  <th>Category</th>
  <th>Status</th>
  <th>Date</th>
  <th>Solution</th>
</tr>
</thead><tbody>';

foreach ($reports as $index => $report) {
    $html .= '<tr>';
    $html .= '<td>' . ($index + 1) . '</td>';
    $html .= '<td>' . htmlspecialchars($report['subject']) . '</td>';
    $html .= '<td>' . htmlspecialchars($report['description']) . '</td>';
    $html .= '<td>' . htmlspecialchars($report['category']) . '</td>';
    $html .= '<td>' . htmlspecialchars($report['status']) . '</td>';
    $html .= '<td>' . htmlspecialchars($report['dateFormatted']) . '</td>';
    $html .= '<td>' . htmlspecialchars($report['solution']) . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Complaint_History.pdf", ["Attachment" => true]); // Forces download
?>