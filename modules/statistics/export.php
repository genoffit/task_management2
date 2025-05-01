<?php
require_once __DIR__ . '/../../classes/Statistics.php';
$stats = new Statistics();
$taskStats = $stats->getTaskStats();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="task_stats.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Status', 'Count']);
foreach ($taskStats as $stat) {
    fputcsv($output, [htmlspecialchars($stat['status']), htmlspecialchars($stat['count'])]);
}
fclose($output);
?>