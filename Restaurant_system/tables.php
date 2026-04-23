<?php
require 'config.php';
$action = $_POST['action'] ?? '';

$useNormalizedSchema = false;
$check = $db->query("SHOW TABLES LIKE 'table_statuses'");
if ($check && $check->num_rows > 0) {
    $useNormalizedSchema = true;
}

if ($useNormalizedSchema) {
    $statusRows = $db->query("SELECT id, name FROM table_statuses");
    $statusMap = [];
    if ($statusRows) {
        while ($row = $statusRows->fetch_assoc()) {
            $statusMap[$row['name']] = (int)$row['id'];
        }
    }

    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO tables_list (table_number, capacity, status_id) VALUES (?, ?, ?)");
        $table_number = trim($_POST['table_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $availableId = $statusMap['available'] ?? 0;
        $stmt->bind_param('sii', $table_number, $capacity, $availableId);
        $stmt->execute();
    }
    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $availableId = $statusMap['available'] ?? 0;
        $occupiedId = $statusMap['occupied'] ?? 0;
        $stmt = $db->prepare("UPDATE tables_list SET status_id = IF(status_id=?, ?, ?) WHERE id=?");
        $stmt->bind_param('iiii', $availableId, $occupiedId, $availableId, $id);
        $stmt->execute();
    }

    $tables = $db->query("SELECT t.id, t.table_number, t.capacity, ts.name AS status_name FROM tables_list t JOIN table_statuses ts ON ts.id=t.status_id ORDER BY t.table_number");
} else {
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO tables_list (table_number, capacity) VALUES (?, ?)");
        $table_number = trim($_POST['table_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $stmt->bind_param('si', $table_number, $capacity);
        $stmt->execute();
    }
    if ($action === 'toggle') {
        $db->query("UPDATE tables_list SET status = IF(status='available','occupied','available') WHERE id=" . (int)$_POST['id']);
    }

    $tables = $db->query("SELECT id, table_number, capacity, status AS status_name FROM tables_list ORDER BY table_number");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tables</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Tables</h1>
    <div class="nav">
        <a href="index.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a class="active" href="tables.php">Tables</a>
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
        <?php if ($tables): ?>
            <?php while ($r = $tables->fetch_assoc()): ?>
                <div class="tile <?= htmlspecialchars($r['status_name']) ?>">
                    <h2><?= htmlspecialchars($r['table_number']) ?></h2>
                    <p><?= (int)$r['capacity'] ?> seats</p>
                    <form method="post">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit">Toggle</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</body>
</html>