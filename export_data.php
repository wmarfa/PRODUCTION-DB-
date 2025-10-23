<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Get all data
$query = "SELECT * FROM daily_performance ORDER BY date DESC";
$data = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=performance_data.csv');

$output = fopen('php://output', 'w');

// Add headers
fputcsv($output, array_keys($data[0]));

// Add data
foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;