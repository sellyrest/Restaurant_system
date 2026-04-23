<?php
require 'config.php';

$error = null;

$hasOrderStatuses = false;
$hasTableStatuses = false;
$hasMenuPrices = false;
$hasOrderItemPrices = false;

$check = $db->query("SHOW TABLES LIKE 'order_statuses'");
if ($check && $check->num_rows > 0) {
    $hasOrderStatuses = true;
}
$check = $db->query("SHOW TABLES LIKE 'table_statuses'");
if ($check && $check->num_rows > 0) {
    $hasTableStatuses = true;
}
$check = $db->query("SHOW TABLES LIKE 'menu_prices'");
if ($check && $check->num_rows > 0) {
    $hasMenuPrices = true;
}
$check = $db->query("SHOW TABLES LIKE 'order_item_prices'");
if ($check && $check->num_rows > 0) {
    $hasOrderItemPrices = true;
}

$useNormalizedSchema = $hasOrderStatuses && $hasTableStatuses && $hasMenuPrices && $hasOrderItemPrices;

if ($useNormalizedSchema) {
    $orderStatusRows = $db->query("SELECT id, name FROM order_statuses");
    $orderStatusMap = [];
    if ($orderStatusRows) {
        while ($row = $orderStatusRows->fetch_assoc()) {
            $orderStatusMap[$row['name']] = (int)$row['id'];
        }
    }

    $tableStatusRows = $db->query("SELECT id, name FROM table_statuses");
    $tableStatusMap = [];
    if ($tableStatusRows) {
        while ($row = $tableStatusRows->fetch_assoc()) {
            $tableStatusMap[$row['name']] = (int)$row['id'];
        }
    }

    if (($_POST['action'] ?? '') === 'create') {
        $table_id = (int)($_POST['table_id'] ?? 0);
        $customer_id = (int)($_POST['customer_id'] ?? 0);

        $db->begin_transaction();

        try {
            if ($customer_id <= 0) {
                throw new Exception('Please select a customer first!');
            }

            $pendingId = $orderStatusMap['pending'] ?? 0;
            if ($pendingId <= 0) {
                throw new Exception('Pending status is missing.');
            }

            $stmt = $db->prepare("INSERT INTO orders (table_id, customer_id, status_id) VALUES (?, ?, ?)");
            $stmt->bind_param('iii', $table_id, $customer_id, $pendingId);
            $stmt->execute();
            $order_id = (int)$db->insert_id;

            $stmtPrice = $db->prepare(
                "SELECT mp.price
                 FROM menu_prices mp
                 JOIN (
                    SELECT menu_id, MAX(id) AS max_id
                    FROM menu_prices
                    GROUP BY menu_id
                 ) x ON x.max_id=mp.id
                 WHERE mp.menu_id=?"
            );
            $stmtItem = $db->prepare("INSERT INTO order_items (order_id, menu_id, quantity) VALUES (?, ?, ?)");
            $stmtItemPrice = $db->prepare("INSERT INTO order_item_prices (order_item_id, price) VALUES (?, ?)");

            $hasItem = false;

            foreach (($_POST['menu_id'] ?? []) as $i => $menu_id) {
                $menu_id = (int)$menu_id;
                if ($menu_id <= 0) {
                    continue;
                }

                $qty = (int)(($_POST['qty'][$i] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $stmtPrice->bind_param('i', $menu_id);
                $stmtPrice->execute();
                $row = $stmtPrice->get_result()->fetch_assoc();

                if (!$row) {
                    continue;
                }

                $stmtItem->bind_param('iii', $order_id, $menu_id, $qty);
                $stmtItem->execute();

                $order_item_id = (int)$db->insert_id;
                $price = (float)$row['price'];

                $stmtItemPrice->bind_param('id', $order_item_id, $price);
                $stmtItemPrice->execute();

                $hasItem = true;
            }

            if (!$hasItem) {
                throw new Exception('Order harus punya minimal 1 item!');
            }

            $occupiedId = $tableStatusMap['occupied'] ?? 0;
            $stmt = $db->prepare("UPDATE tables_list SET status_id=? WHERE id=?");
            $stmt->bind_param('ii', $occupiedId, $table_id);
            $stmt->execute();

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }

    if (($_POST['action'] ?? '') === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status_name = trim($_POST['status'] ?? '');
        $status_id = $orderStatusMap[$status_name] ?? 0;
        $paidId = $orderStatusMap['paid'] ?? 0;

        if ($status_id <= 0) {
            $error = 'Invalid status!';
        } elseif ($status_id === $paidId) {
            $error = "Status 'paid' hanya lewat payment!";
        } else {
            $stmt = $db->prepare("UPDATE orders SET status_id=? WHERE id=?");
            $stmt->bind_param('ii', $status_id, $id);
            $stmt->execute();
        }
    }

    $orders = $db->query(
        "SELECT o.id, o.created_at, t.table_number, c.name AS customer_name, os.name AS status_name
         FROM orders o
         LEFT JOIN tables_list t ON t.id=o.table_id
         LEFT JOIN customers c ON c.id=o.customer_id
         JOIN order_statuses os ON os.id=o.status_id
         ORDER BY o.created_at DESC"
    );

    $availableId = $tableStatusMap['available'] ?? 0;
    $tablesStmt = $db->prepare("SELECT id, table_number FROM tables_list WHERE status_id=? ORDER BY table_number");
    $tablesStmt->bind_param('i', $availableId);
    $tablesStmt->execute();
    $tables = $tablesStmt->get_result();

    $customers = $db->query("SELECT id, name FROM customers ORDER BY name");
    $menu = $db->query(
        "SELECT m.id, m.name, p.price
         FROM menu m
         LEFT JOIN (
             SELECT mp.menu_id, mp.price
             FROM menu_prices mp
             JOIN (
                 SELECT menu_id, MAX(id) AS max_id
                 FROM menu_prices
                 GROUP BY menu_id
             ) x ON x.max_id=mp.id
         ) p ON p.menu_id=m.id
         WHERE m.available=1
         ORDER BY m.name"
    );
} else {
    $hasCustomerId = false;
    $columnCheck = $db->query("SHOW COLUMNS FROM orders LIKE 'customer_id'");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        $hasCustomerId = true;
    }

    if (($_POST['action'] ?? '') === 'create') {
        $table_id = (int)($_POST['table_id'] ?? 0);
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $customer_name = trim($_POST['customer_name'] ?? '');

        $db->begin_transaction();

        try {
            if ($hasCustomerId) {
                if ($customer_id <= 0) {
                    throw new Exception('Please select a customer first!');
                }

                $stmt = $db->prepare("INSERT INTO orders (table_id, customer_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $table_id, $customer_id);
            } else {
                if ($customer_name === '') {
                    throw new Exception('Please enter a customer name first!');
                }

                $stmt = $db->prepare("INSERT INTO orders (table_id, customer_name) VALUES (?, ?)");
                $stmt->bind_param('is', $table_id, $customer_name);
            }

            $stmt->execute();
            $order_id = (int)$db->insert_id;

            $stmtPrice = $db->prepare("SELECT price FROM menu WHERE id=?");
            $stmtItem = $db->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)");

            $hasItem = false;

            foreach (($_POST['menu_id'] ?? []) as $i => $menu_id) {
                $menu_id = (int)$menu_id;
                if ($menu_id <= 0) {
                    continue;
                }

                $qty = (int)(($_POST['qty'][$i] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $stmtPrice->bind_param('i', $menu_id);
                $stmtPrice->execute();
                $row = $stmtPrice->get_result()->fetch_assoc();

                if (!$row) {
                    continue;
                }

                $price = (float)$row['price'];

                $stmtItem->bind_param('iiid', $order_id, $menu_id, $qty, $price);
                $stmtItem->execute();

                $hasItem = true;
            }

            if (!$hasItem) {
                throw new Exception('Order harus punya minimal 1 item!');
            }

            $stmt = $db->prepare("UPDATE tables_list SET status='occupied' WHERE id=?");
            $stmt->bind_param('i', $table_id);
            $stmt->execute();

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }

    if (($_POST['action'] ?? '') === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($status === 'paid') {
            $error = "Status 'paid' hanya lewat payment!";
        } else {
            $stmt = $db->prepare("UPDATE orders SET status=? WHERE id=?");
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
        }
    }

    $ordersSql = $hasCustomerId
        ? "SELECT o.*, t.table_number, c.name AS customer_name FROM orders o LEFT JOIN tables_list t ON t.id=o.table_id LEFT JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC"
        : "SELECT o.*, t.table_number FROM orders o LEFT JOIN tables_list t ON t.id=o.table_id ORDER BY o.created_at DESC";

    $orders = $db->query($ordersSql);
    $tables = $db->query("SELECT id, table_number FROM tables_list WHERE status='available' ORDER BY table_number");
    $customers = $db->query("SELECT id, name FROM customers ORDER BY name");
    $menu = $db->query("SELECT id, name, price FROM menu WHERE available=1 ORDER BY name");
}

if (!$orders) {
    $orders = false;
}
if (!$tables) {
    $tables = false;
}
if (!$customers) {
    $customers = false;
}
if (!$menu) {
    $menu = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Orders</h1>
    <div class="nav">
        <a href="index.php">Dashboard</a>
        <a href="menu.php">Menu</a>
        <a href="tables.php">Tables</a>
        <a class="active" href="orders.php">Orders</a>
        <a href="payments.php">Payments</a>
        <a href="customers.php">Customers</a>
    </div>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="form-box">
        <form method="post">
            <input type="hidden" name="action" value="create">
            <select name="table_id" required>
                <?php if ($tables): ?>
                    <?php while ($t = $tables->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_number']) ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <?php if ($useNormalizedSchema || (($hasCustomerId ?? false) === true)): ?>
                <select name="customer_id" required>
                    <option value="">-- select customer --</option>
                    <?php if ($customers): ?>
                        <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            <?php else: ?>
                <input type="text" name="customer_name" placeholder="Customer" required>
            <?php endif; ?>

            <?php
            $menu_items = [];
            if ($menu) {
                while ($m = $menu->fetch_assoc()) {
                    $menu_items[] = $m;
                }
            }

            $opts = '<option value="">--select--</option>';
            foreach ($menu_items as $m) {
                $displayName = htmlspecialchars($m['name']);
                $displayPrice = isset($m['price']) ? number_format((float)$m['price'], 0, ',', '.') : '0';
                $opts .= "<option value='{$m['id']}'>{$displayName} - Rp {$displayPrice}</option>";
            }
            ?>

            <div id="items">
                <div class="item-row">
                    <select name="menu_id[]"><?= $opts ?></select>
                    <input type="number" name="qty[]" value="1" min="1">
                </div>
            </div>
            <button type="button" onclick="addRow()">+ Item</button>
            <button>Create</button>
        </form>
    </div>

    <table>
        <tr>
            <th>#</th>
            <th>Table</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php if ($orders): ?>
            <?php while ($r = $orders->fetch_assoc()): ?>
                <?php
                $statusName = $r['status_name'] ?? ($r['status'] ?? 'pending');
                ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['table_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['customer_name'] ?? '-') ?></td>
                    <td><span class="badge <?= htmlspecialchars($statusName) ?>"><?= htmlspecialchars($statusName) ?></span></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <select name="status">
                                <?php foreach (['pending', 'preparing', 'served'] as $s): ?>
                                    <option <?= $statusName === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button>Update</button>
                            <?php if ($statusName === 'served'): ?>
                                <a href="payments.php?order_id=<?= $r['id'] ?>" class="btn-pay">Pay</a>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>

    <script>
    const opts = `<?= $opts ?>`;
    function addRow() {
        let d = document.createElement('div');
        d.className = 'item-row';
        d.innerHTML = `<select name="menu_id[]">${opts}</select>
        <input type="number" name="qty[]" value="1" min="1">`;
        document.getElementById('items').appendChild(d);
    }
    </script>
</body>
</html>