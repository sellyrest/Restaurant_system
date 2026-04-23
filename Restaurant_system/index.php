<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Management System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Restaurant</h1>
    <div class="nav">
        <a class="active" href="index.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="tables.php">Tables</a>
        <a href="orders.php">Orders</a>
        <a href="payments.php">Payments</a>
        <a href="customers.php">Customers</a>
    </div>
<?php
$hasOrderStatuses = false;
$hasTableStatuses = false;
$hasOrderItemPrices = false;

$check = $db->query("SHOW TABLES LIKE 'order_statuses'");
if ($check && $check->num_rows > 0) {
    $hasOrderStatuses = true;
}
$check = $db->query("SHOW TABLES LIKE 'table_statuses'");
if ($check && $check->num_rows > 0) {
    $hasTableStatuses = true;
}
$check = $db->query("SHOW TABLES LIKE 'order_item_prices'");
if ($check && $check->num_rows > 0) {
    $hasOrderItemPrices = true;
}

$useNormalizedSchema = $hasOrderStatuses && $hasTableStatuses && $hasOrderItemPrices;

$total_orders_result = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()");

if ($useNormalizedSchema) {
    $active_orders_result = $db->query(
        "SELECT COUNT(*)
         FROM orders o
         JOIN order_statuses os ON os.id=o.status_id
         WHERE os.name IN ('pending', 'preparing', 'served')"
    );
    $today_revenue_result = $db->query(
        "SELECT COALESCE(SUM(oi.quantity * oip.price),0)
         FROM payments p
         JOIN order_items oi ON oi.order_id=p.order_id
         JOIN order_item_prices oip ON oip.order_item_id=oi.id
         WHERE DATE(p.paid_at)=CURDATE()"
    );
    $free_tables_result = $db->query(
        "SELECT COUNT(*)
         FROM tables_list t
         JOIN table_statuses ts ON ts.id=t.status_id
         WHERE ts.name='available'"
    );
} else {
    $active_orders_result = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','preparing','served')");
    $today_revenue_result = $db->query("SELECT COALESCE(SUM(oi.quantity * oi.price),0) FROM payments p JOIN order_items oi ON oi.order_id=p.order_id WHERE DATE(p.paid_at)=CURDATE()");
    $free_tables_result = $db->query("SELECT COUNT(*) FROM tables_list WHERE status='available'");
}

$total_orders = $total_orders_result ? (int)$total_orders_result->fetch_row()[0] : 0;
$active_orders = $active_orders_result ? (int)$active_orders_result->fetch_row()[0] : 0;
$today_revenue = $today_revenue_result ? (float)$today_revenue_result->fetch_row()[0] : 0;
$free_tables = $free_tables_result ? (int)$free_tables_result->fetch_row()[0] : 0;
?>
    <div class="cards">
        <div class="card"><h2><?= $total_orders ?></h2><p>Today's Orders</p></div>
        <div class="card"><h2><?= $active_orders ?></h2><p>Active Orders</p></div>
        <div class="card"><h2><?= number_format($today_revenue, 0, ',', '.') ?></h2><p>Today's Revenue</p></div>
        <div class="card"><h2><?= $free_tables ?></h2><p>Free Tables</p></div>
    </div>

    <h3>Recent Orders</h3>
    <table>
        <tr>
            <th>#</th>
            <th>Table</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Time</th>
        </tr>
        <?php
        if ($useNormalizedSchema) {
            $rows = $db->query(
                "SELECT o.id, o.created_at, t.table_number, c.name AS customer_name, os.name AS status_name
                 FROM orders o
                 LEFT JOIN tables_list t ON t.id=o.table_id
                 LEFT JOIN customers c ON c.id=o.customer_id
                 JOIN order_statuses os ON os.id=o.status_id
                 ORDER BY o.created_at DESC
                 LIMIT 10"
            );
        } else {
            $hasCustomerId = false;
            $columnCheck = $db->query("SHOW COLUMNS FROM orders LIKE 'customer_id'");
            if ($columnCheck && $columnCheck->num_rows > 0) {
                $hasCustomerId = true;
            }

            $recentOrdersSql = $hasCustomerId
                ? "SELECT o.*, t.table_number, c.name AS customer_name FROM orders o LEFT JOIN tables_list t ON t.id=o.table_id LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC LIMIT 10"
                : "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables_list t ON t.id=o.table_id ORDER BY o.created_at DESC LIMIT 10";

            $rows = $db->query($recentOrdersSql);
        }

        if ($rows):
            while ($r = $rows->fetch_assoc()):
                $statusName = $r['status_name'] ?? ($r['status'] ?? 'pending');
        ?>
            <tr>
                <td>#<?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['table_number'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['customer_name'] ?? '-') ?></td>
                <td><span class="badge <?= htmlspecialchars($statusName) ?>"><?= ucfirst($statusName) ?></span></td>
                <td><?= date('H:i', strtotime($r['created_at'])) ?></td>
            </tr>
        <?php
            endwhile;
        endif;
        ?>
    </table>
</body>
</html>