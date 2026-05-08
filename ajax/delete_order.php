<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!hasRole('admin')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['id']) ? (int)$data['id'] : 0;

if (!$order_id) {
    $order_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
}

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID', 'received' => $data]);
    exit;
}

$check_sql = "SELECT id, order_code FROM orders WHERE id = ?";
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

$delete_items_sql = "DELETE FROM order_items WHERE order_id = ?";
$delete_items_stmt = $conn->prepare($delete_items_sql);
$delete_items_stmt->bind_param("i", $order_id);
$delete_items_stmt->execute();
$delete_items_stmt->close();

$delete_logs_sql = "DELETE FROM order_status_logs WHERE order_id = ?";
$delete_logs_stmt = $conn->prepare($delete_logs_sql);
$delete_logs_stmt->bind_param("i", $order_id);
$delete_logs_stmt->execute();
$delete_logs_stmt->close();

$delete_order_sql = "DELETE FROM orders WHERE id = ?";
$delete_order_stmt = $conn->prepare($delete_order_sql);
$delete_order_stmt->bind_param("i", $order_id);

if ($delete_order_stmt->execute()) {
    $delete_order_stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order deleted successfully',
        'order_id' => $order_id,
        'order_code' => $order['order_code']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to delete order']);
}
