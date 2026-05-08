<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requiredRole('customer', 'login.php');

$user_id = (int) $_SESSION['user_id'];

$foodItems = [];
$foodItemsResult = mysqli_query($conn, "SELECT * FROM food_items WHERE is_available = 1 ORDER BY category, item_name");

if ($foodItemsResult) {
    while ($row = mysqli_fetch_assoc($foodItemsResult)) {
        $foodItems[] = $row;
    }
} else {
    setFlash('Failed to load menu items: ' . mysqli_error($conn), 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_data = [];
    $total_amount = 0.0;

    foreach ($foodItems as $foodItem) {
        $qty_key = 'qty_' . $foodItem['id'];
        $quantity = isset($_POST[$qty_key]) ? (int) $_POST[$qty_key] : 0;

        if ($quantity > 0) {
            $items_data[] = [
                'name' => $foodItem['item_name'],
                'quantity' => $quantity,
                'price' => (float) $foodItem['price'],
            ];
            $total_amount += $quantity * (float) $foodItem['price'];
        }
    }

    if (empty($items_data)) {
        setFlash('Please select at least one item', 'error');
        header('Location: menu.php');
        exit;
    }

    $order_code = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));

    $insert_order_sql = "INSERT INTO orders (user_id, order_code, total_amount, status) VALUES (?, ?, ?, 'pending')";
    $insert_order_stmt = $conn->prepare($insert_order_sql);

    if (!$insert_order_stmt) {
        setFlash('Failed to prepare order insert: ' . $conn->error, 'error');
        header('Location: menu.php');
        exit;
    }

    $insert_order_stmt->bind_param('isd', $user_id, $order_code, $total_amount);

    if ($insert_order_stmt->execute()) {
        $order_id = $conn->insert_id;
        $insert_order_stmt->close();

        $insert_item_sql = "INSERT INTO order_items (order_id, item_name, quantity, price) VALUES (?, ?, ?, ?)";
        $insert_item_stmt = $conn->prepare($insert_item_sql);

        if (!$insert_item_stmt) {
            setFlash('Failed to prepare order items insert: ' . $conn->error, 'error');
            header('Location: menu.php');
            exit;
        }

        foreach ($items_data as $cartItem) {
            $insert_item_stmt->bind_param(
                'isid',
                $order_id,
                $cartItem['name'],
                $cartItem['quantity'],
                $cartItem['price']
            );
            $insert_item_stmt->execute();
        }

        $insert_item_stmt->close();

        setFlash('Order placed successfully!', 'success');
        header('Location: customer/index.php');
        exit;
    }


    setFlash('Failed to place order: ' . $insert_order_stmt->error, 'error');
    $insert_order_stmt->close();
    header('Location: menu.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - FoodPulse</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/components.css">
</head>
<body>
    <div style="padding: 30px;">
        <div class="menu-header">
        <h2>Menu</h2>
        <a href="customer/index.php" class="back-btn">Back</a>
        </div>
        
        <?php if (!empty($foodItems)): ?>
            <form method="POST" action="menu.php">
                <div class="menu-cards-container">
                    <?php foreach ($foodItems as $item): ?>
                        <?php $imgSrc = !empty($item['image_url']) ? $item['image_url'] : '../assets/images/food.jpg'; ?>
                        <div class="menu-cards">
                            <div class="menu-img">
                                <img src="<?= $imgSrc ?>" alt="" class="image-url">
                            </div>

                            <div class="menu-card-body">
                                <strong class="menu-title"><?= htmlspecialchars($item['item_name']) ?></strong><br>
                                <small><?= htmlspecialchars($item['category']) ?></small><br>
                                <strong>&#8369;<?= number_format($item['price'], 2) ?></strong><br>
                                Quantity:
                                <input type="number" name="qty_<?= $item['id'] ?>" value="0" min="0" style="width: 50px;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-menu">
                    Place Order
                </button>
                <?php include '../template/alerts.php'; ?>
            </form>
        <?php else: ?>
            <p>No menu items available at the moment.</p>
        <?php endif; ?>
    </div>
</body>
</html>
