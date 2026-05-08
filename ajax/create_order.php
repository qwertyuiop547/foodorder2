<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if(!$conn){
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit; 
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid Request'
    ]);
    exit;
}

// Check if user is logged in as customer
if (!hasRole('customer')) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to place an order'
    ]);
    exit;
}

$user_id = authUserId('customer');

// Process form data
$items = [];
$total_amount = 0;

// Get all available items to validate quantities
$foodItems = getAll($conn, "SELECT * FROM food_items WHERE is_available = 1");

foreach ($foodItems as $item) {
    $qty_key = 'qty_' . $item['id'];
    if (isset($_POST[$qty_key]) && is_numeric($_POST[$qty_key]) && (int)$_POST[$qty_key] > 0) {
        $quantity = (int)$_POST[$qty_key];
        $items[] = [
            'name' => $item['item_name'],
            'quantity' => $quantity,
            'price' => (float)$item['price']
        ];
        $total_amount += $quantity * (float)$item['price'];
    }
}

// Check if any items were selected
if (empty($items)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select at least one item'
    ]);
    exit;
}

// Generate order code
$order_code = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));

// Start transaction
$conn->autocommit(FALSE);

try {
    // Insert order
    $insert_order_sql = "INSERT INTO orders (user_id, order_code, total_amount, status) VALUES (?, ?, ?, 'pending')";
    $insert_order_stmt = $conn->prepare($insert_order_sql);
    if (!$insert_order_stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $insert_order_stmt->bind_param('isd', $user_id, $order_code, $total_amount);
    
    if(!$insert_order_stmt->execute()){
        throw new Exception('Failed to insert order: ' . $insert_order_stmt->error);
    }
    
    $order_id = $conn->insert_id;
    $insert_order_stmt->close();
    
    // Insert order items
    $insert_item_sql = "INSERT INTO order_items (order_id, item_name, quantity, price) VALUES (?, ?, ?, ?)";
    $insert_item_stmt = $conn->prepare($insert_item_sql);
    if (!$insert_item_stmt) {
        throw new Exception('Failed to prepare items statement: ' . $conn->error);
    }
    
    foreach ($items as $item) {
        $insert_item_stmt->bind_param('isid', $order_id, $item['name'], $item['quantity'], $item['price']);
        
        if(!$insert_item_stmt->execute()){
            throw new Exception('Failed to insert order item: ' . $insert_item_stmt->error);
        }
    }
    
    $insert_item_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error placing order: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>
