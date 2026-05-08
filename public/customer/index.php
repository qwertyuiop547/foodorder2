<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/helpers.php';

requiredRole('customer', '../login.php');

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['name'] ?? 'Customer';

$sql = "SELECT 
            orders.*,
            GROUP_CONCAT(CONCAT(order_items.item_name, ' (x', order_items.quantity, ')') SEPARATOR '||') AS items
        FROM orders
        LEFT JOIN order_items ON orders.id = order_items.order_id
        WHERE orders.user_id = ?
        GROUP BY orders.id
        ORDER BY orders.created_at DESC";

global $conn;

if (!$conn) {
    die('Connection Failed: ' . mysqli_connect_error());
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pending_count = 0;
$preparing_count = 0;
$ready_count = 0;
$completed_count = 0;

foreach ($orders as $o) {
    if ($o['status'] === 'pending') $pending_count++;
    elseif ($o['status'] === 'preparing') $preparing_count++;
    elseif ($o['status'] === 'ready') $ready_count++;
    elseif ($o['status'] === 'completed') $completed_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - FoodPulse</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/toast.css">
    <link rel="stylesheet" href="../../assets/css/components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="kitchen-body">
    <div class="kitchen-container">
        <div class="kitchen-header">
            <h1>My Orders</h1>
            <div>
                <span class="welcome-text">Hi, <?= htmlspecialchars($user_name); ?></span>
                <a href="actions/logout.php" class="logout-link">Logout</a>
            </div>
        </div>

        <?php include '../../template/alerts.php'; ?>

        <div class="kitchen-stats" id="statsContainer">
            <div class="stat-box">Pending: <strong id="stat-pending"><?= $pending_count; ?></strong></div>
            <div class="stat-box">Preparing: <strong id="stat-preparing"><?= $preparing_count; ?></strong></div>
            <div class="stat-box">Ready: <strong id="stat-ready"><?= $ready_count; ?></strong></div>
            <div class="stat-box">Completed: <strong id="stat-completed"><?= $completed_count; ?></strong></div>
        </div>

        <div class="menu-button">
            <a href="../menu.php">Add Menu</a>
        </div>

        <div class="orders-list" id="ordersList">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <?php 
                    $items = $order['items'] ? explode('||', $order['items']) : [];
                    ?>
                    <div class="order-item" data-order-id="<?= $order['id'] ?>" data-status="<?= $order['status'] ?>">
                        <div class="order-top">
                            <span class="order-code"><?= htmlspecialchars($order['order_code']); ?></span>
                            <span class="order-status <?= $order['status']; ?>"><?= $order['status']; ?></span>
                        </div>
                        <div class="order-details">
                            <strong>Items:</strong>
                            <ul>
                                <?php foreach ($items as $item): ?>
                                    <?php if (!empty($item)): ?>
                                    <li><?= htmlspecialchars($item); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="order-meta">
                            <span>Total: ₱<?= number_format($order['total_amount'], 2); ?></span>
                            <span><?= date('M d, h:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                 <p class="no-orders" id="noOrders">No orders yet. <a href="../menu.php">Order now!</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/js/ajax.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ordersList = document.getElementById('ordersList');
            const noOrdersEl = document.getElementById('noOrders');
            
             function renderOrders(orders, counts) {
                 if (!orders || orders.length === 0) {
                     ordersList.innerHTML = '<p class="no-orders" id="noOrders">No orders yet. <a href="../menu.php">Order now!</a></p>';
                     updateStats(counts);
                     return;
                 }

                ordersList.innerHTML = orders.map(order => {
                    const items = order.items ? order.items.split('||') : [];
                    
                    return `
                        <div class="order-item" data-order-id="${order.id}" data-status="${order.status}">
                            <div class="order-top">
                                <span class="order-code">${AJAX.formatText(order.order_code)}</span>
                                <span class="order-status ${order.status}">${order.status}</span>
                            </div>
                            <div class="order-details">
                                <strong>Items:</strong>
                                <ul>
                                    ${items.map(item => item ? `<li>${AJAX.formatText(item)}</li>` : '').join('')}
                                </ul>
                            </div>
                            <div class="order-meta">
                                <span>Total: ₱${parseFloat(order.total_amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}</span>
                                <span>${AJAX.formatDateTime(order.created_at)}</span>
                            </div>
                        </div>
                    `;
                }).join('');

                updateStats(counts);
            }

            function updateStats(counts) {
                if (counts) {
                    document.getElementById('stat-pending').textContent = counts.pending || 0;
                    document.getElementById('stat-preparing').textContent = counts.preparing || 0;
                    document.getElementById('stat-ready').textContent = counts.ready || 0;
                    document.getElementById('stat-completed').textContent = counts.completed || 0;
                }
            }

            

            AJAX.startAutoRefresh(async function(orders) {
                if (orders.success) {
                    renderOrders(orders.orders, orders.counts);
                }
            }, 3000);

            renderOrders(<?= json_encode($orders) ?>, {
                pending: <?= $pending_count ?>,
                preparing: <?= $preparing_count ?>,
                ready: <?= $ready_count ?>,
                completed: <?= $completed_count ?>
            });
        });
    </script>
</body>
</html>
