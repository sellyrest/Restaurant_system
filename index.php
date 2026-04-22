<?php require 'config.php'; ?>
<!DOCTYPE html>
<head>
    <title>Restaurant Management System</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .nav a { margin-right: 15px; padding: 8px 16px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; }
        .nav a:hover { background: #555; }
        .cards { display: flex; gap: 20px; margin: 20px 0; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); min-width: 140px; text-align: center; }
        .card h2 { margin: 0; font-size: 2rem; color: #e67e22; }
        .card p { margin: 5px 0 0; color: #777; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        th { background: #333; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .badge { padding: 3px 10px; border-radius: 12px; font-size: .8rem; font-weight: bold; }
        .pending   { background: #fff3cd; color: #856404; }
        .preparing { background: #cfe2ff; color: #084298; }
        .served    { background: #d1e7dd; color: #0f5132; }
        .paid      { background: #d4edda; color: #155724; }
  </style>
</head>
<body>
    <h1>Restaurant</h1>
    <div class="nav">
        <a href="index.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="tables.php">Tables</a>
        <a href="orders.php">Orders</a>
        <a href="payments.php">Payments</a>
        <a href="customers.php">Customers</a>
    </div>
<?php
$total_orders = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];
$active_orders = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'preparing','served')")->fetch_row()[0];
$today_revenue = $db->query("SELECT COALESCE(SUM(total),0) FROM payments WHERE DATE(paid_at)=CURDATE()")->fetch_row()[0];
$free_tables = $db->query("SELECT COUNT(*) FROM tables_list WHERE status='available'")->fetch_row()[0];
?>
    <div class="cards">
        <div class="card"><h2><?= $total_orders ?></h2><p>Today's Orders</p></div>
        <div class="card"><h2><?= $active_orders ?></h2><p>Active Orders</p></div>
        <div class="card"><h2><?= number_format($today_revenue,0,',','.') ?></h2><p>Today's Revenue</p></div>
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
        $rows = $db->query("SELECT o.*, t.table_number FROM orders o LEFT JOIN tables_list t ON t.id=o.table_id ORDER BY o.created_at DESC LIMIT 10");
        while ($r = $rows->fetch_assoc()):
        ?>  
        <tr>
            <td>#<?= $r['id'] ?></td>
            <td><?= $r['table_number'] ?></td>
            <td><?= htmlspecialchars($r['customer_name']) ?></td>
            <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td><?= date('H:i', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
