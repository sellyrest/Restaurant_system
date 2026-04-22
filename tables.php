<?php
require 'config.php';
if ($_POST['action'] == 'add') {
    $stmt = $db->prepare("INSERT INTO tables_list (table_number, capacity) VALUES (?,?,)");
    $stmt->bind_param('si', $_POST['table_number'], $_POST['capacity']);
    $stmt->execute();
}
if ($_POST['action'] ?? '' == 'toggle') {
    $db->query("UPDATE tables_list SET status = IF(status='available','occupied','available') WHERE id=" . (int)$_POST["id"]);
}

$tables = $db->query("SELECT * FROM tables_list ORDER BY table_number");
?>
<!DOCTYPE html>
<head>
    <title>Tables</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        .nav a { margin-right: 15px; padding: 8px 16px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; }
        .nav a:hover { background: #555; }
        form.add { background: #fff; padding: 16px; border-radius: 8px; margin: 20px 0; display: flex; gap: 10px; }
        input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 16px; background: #e67e22; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .grid { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 20px; }
        .tile { background: #fff; border: 2px solid #ccc; border-radius: 10px; padding: 20px; text-align: center; min-width: 120px; }
        .tile.available { border-color: #2ecc71; }
        .tile.occupied  { border-color: #e74c3c; }
        .tile h2 { margin: 0 0 6px; font-size: 1.5rem; }
        .tile p  { margin: 0 0 10px; color: #777; font-size: .85rem; }
    </style>
</head>
<body>
    <h1>Tables</h1>
    <div class="nav">
        <a href ="index.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="tables.php">Tables</a>
        <a href="orders.php">Orders</a>
        <a href="payments.php">Payments</a>
        <a href="customers.php">Customers</a>   
    </div>
    <form class="add" method="post">
        <input type="hidden" name="action" value="add">
        <input type="text" name="table_number" placeholder="e.g. T6" required>
        <input type="number" name="capacity" placeholder="Seats" min="1" required>
        <button type="submit">Add Table</button>
    </form>

    <div class="grid">
        <?php while ($r = $tables->fetch_assoc()): ?>
            <div class="tile <?= $r['status'] ?>">
                <h2><?= $r['table_number'] ?></h2>
                <p><?=  $r['capacity'] ?> seats</p>
                <form method="post">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button>Toggle</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>