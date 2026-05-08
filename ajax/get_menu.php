<?php
session_start();

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit;
}

$sql = "SELECT id, item_name, category, price, image_url, is_available, updated_at
        FROM food_items
        WHERE is_available = 1
        ORDER BY category, item_name";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => mysqli_error($conn)
    ]);
    exit;
}

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

echo json_encode([
    'success' => true,
    'items' => $items
]);

