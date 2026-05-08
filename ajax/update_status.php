<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!hasRole(['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$new_status = isset($data['new_status']) ? trim($data['new_status']) : '';

if (!$order_id) {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
}
if (!$new_status) {
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
}

$valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];

if (!$order_id || !in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$check_sql = "SELECT id, status, order_code FROM orders WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $order_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$order = $check_result->fetch_assoc();
$check_stmt->close();

$valid_transitions = [
    'pending' => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => []
];

if (!isset($valid_transitions[$order['status']]) || !in_array($new_status, $valid_transitions[$order['status']])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status transition']);
    exit;
}

$update_sql = "UPDATE orders SET status = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $new_status, $order_id);

if ($update_stmt->execute()) {
    $update_stmt->close();
    
    $log_sql = "INSERT INTO order_status_logs (order_id, status, message) VALUES (?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $message = "Status changed from {$order['status']} to $new_status";
    $log_stmt->bind_param("iss", $order_id, $new_status, $message);
    $log_stmt->execute();
    $log_stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'order_id' => $order_id,
        'new_status' => $new_status,
        'order_code' => $order['order_code']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
}
