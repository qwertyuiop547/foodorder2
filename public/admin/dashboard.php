<?php
session_start();

require '../../includes/helpers.php';
require '../../config/app.php';

requireAdmin();

global $conn;

$users = countTable($conn, 'users');
$totalOrders = countTable($conn, 'orders');

$pendingResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
$pendingRow = mysqli_fetch_assoc($pendingResult);
$pendingOrders = $pendingRow['count'];

$completedResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'completed'");
$completedRow = mysqli_fetch_assoc($completedResult);
$completedOrders = $completedRow['count'];

$totalRevenue = totalRevenue($conn, 'orders');

$cards = [
    ["title" => "Total Users", "value" => $users, "icon" => "users", "color" => "#3B82F6"],
    ["title" => "Total Orders", "value" => $totalOrders, "icon" => "orders", "color" => "#8B5CF6"],
    ["title" => "Pending Orders", "value" => $pendingOrders, "icon" => "pending", "color" => "#F59E0B"],
    ["title" => "Completed Orders", "value" => $completedOrders, "icon" => "completed", "color" => "#10B981"],
    ["title" => "Total Revenue", "value" => "₱" . number_format($totalRevenue, 2), "icon" => "revenue", "color" => "#EF4444"]
];

$orderUser = getAll($conn, "
SELECT 
    orders.*,
    users.name,
    GROUP_CONCAT(order_items.item_name) AS items
FROM orders
JOIN users ON orders.user_id = users.id
LEFT JOIN order_items ON orders.id = order_items.order_id
GROUP BY orders.id
ORDER BY orders.id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Food Pulse</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/components.css">
    <link rel="stylesheet" href="../../assets/css/responsive.css">
    <link rel="stylesheet" href="../../assets/css/toast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../template/navbarAdmin.php'; ?>

    <main class="dashboard">
        <div class="header-dashboard">
            <h1>Dashboard</h1>
        </div>

        <?php include '../../template/alerts.php'; ?>

        <div class="dashboard-stats" id="statsContainer">
            <?php 
            $colorMap = ['users' => 'bg-blue', 'orders' => 'bg-purple', 'pending' => 'bg-amber', 'completed' => 'bg-green', 'revenue' => 'bg-red'];
            $iconMap = ['users' => 'users', 'orders' => 'shopping-bag', 'pending' => 'clock', 'completed' => 'check-circle', 'revenue' => 'money-bill-wave'];
            $statKeys = ['users', 'totalOrders', 'pendingOrders', 'completedOrders', 'totalRevenue'];
            ?>
            <?php foreach ($cards as $index => $card): ?>
            <div class="stat-card" data-stat="<?= $statKeys[$index] ?>">
                <div class="stat-header">
                    <span class="stat-label"><?= htmlspecialchars($card['title']); ?></span>
                    <!-- <div class="stat-icon <?= $colorMap[$card['icon']]; ?>">
                        <i class="fas fa-<?= $iconMap[$card['icon']]; ?>"></i>
                    </div> -->
                </div>
                <div class="stat-value" id="stat-<?= $statKeys[$index] ?>">
                    <?= $card['icon'] === 'revenue' ? $card['value'] : htmlspecialchars($card['value']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Orders</h2>
            </div>
            <div class="table-wrapper">
                <table class="dashboard-table" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Order Code</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <?php if(!empty($orderUser)): ?>
                            <?php foreach($orderUser as $order): 
                                $statusClass = '';
                                switch($order['status']) {
                                    case 'pending': $statusClass = 'status-pending'; break;
                                    case 'completed': $statusClass = 'status-completed'; break;
                                    case 'processing': $statusClass = 'status-processing'; break;
                                    case 'cancelled': $statusClass = 'status-cancelled'; break;
                                    default: $statusClass = 'status-pending';
                                }
                            ?>
                            <tr data-order-id="<?= $order['id'] ?>">
                                <td>
                                    <?= htmlspecialchars($order['name']); ?>
                                </td>
                                <td class="items-cell" title="<?= htmlspecialchars($order['items'] ?? 'No items'); ?>">
                                    <?= htmlspecialchars($order['items'] ?? 'No items'); ?>
                                </td>
                                <td><span class="order-code"><?= htmlspecialchars($order['order_code']); ?></span></td>
                                <td class="amount-cell">₱<?= number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass; ?>">
                                        <?= htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="action-btn btn-delete delete-order-btn" data-id="<?= $order['id'] ?>">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noOrdersRow">
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No orders found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="../../assets/js/ajax.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    function updateStats(data) {
        if (data && data.success && data.stats) {
            document.getElementById('stat-users').textContent = data.stats.users;
            document.getElementById('stat-totalOrders').textContent = data.stats.totalOrders;
            document.getElementById('stat-pendingOrders').textContent = data.stats.pendingOrders;
            document.getElementById('stat-completedOrders').textContent = data.stats.completedOrders;
            document.getElementById('stat-totalRevenue').textContent =
                '₱' + parseFloat(data.stats.totalRevenue).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    }

    function getStatusClass(status) {
        switch (status) {
            case 'pending':
                return 'status-pending';
            case 'preparing':
                return 'status-processing';
            case 'ready':
                return 'status-processing';
            case 'completed':
                return 'status-completed';
            case 'cancelled':
                return 'status-cancelled';
            default:
                return 'status-pending';
        }
    }

    function syncOrderStatuses(orders) {
        orders.forEach(function(order) {
            const row = document.querySelector('tr[data-order-id="' + order.id + '"]');
            if (!row) return;

            const badge = row.querySelector('.status-badge');
            if (!badge) return;

            badge.className = 'status-badge ' + getStatusClass(order.status);
            badge.textContent = order.status;
            row.dataset.status = order.status;
        });
    }

    document.querySelectorAll('.delete-order-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            const row = this.closest('tr');
            row.classList.add('updating');

            const result = await AJAX.deleteOrder(orderId);

            if (result.success) {
                row.classList.remove('updating');
                row.classList.add('updated');
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);

                const stats = await AJAX.getStats();
                updateStats(stats);
            } else {
                row.classList.remove('updating');
                AJAX.showToast(result.error || 'Failed to delete order', 'error');
            }
        });
    });

    AJAX.startAutoRefresh(function(orders, stats) {
        if (orders && orders.success) {
            syncOrderStatuses(orders.orders);
        }

        if (stats && stats.success) {
            updateStats(stats);
        }
    }, 5000);
});
</script>
</body>
</html>
