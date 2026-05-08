<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!hasRole('admin')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$users = countTable($conn, 'users');
$totalOrders = countTable($conn, 'orders');

$pendingResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$pendingRow = mysqli_fetch_assoc($pendingResult);
$pendingOrders = $pendingRow['count'];

$completedResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'completed'");
$completedRow = mysqli_fetch_assoc($completedResult);
$completedOrders = $completedRow['count'];

$totalRevenue = totalRevenue($conn, 'orders');

echo json_encode([
    'success' => true,
    'stats' => [
        'users' => $users,
        'totalOrders' => $totalOrders,
        'pendingOrders' => $pendingOrders,
        'completedOrders' => $completedOrders,
        'totalRevenue' => $totalRevenue
    ],
    'timestamp' => time()
]);
