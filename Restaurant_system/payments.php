<?php
require 'config.php';

$error = null;
$action = $_POST['action'] ?? '';
$selected_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$hasOrderStatuses = false;
$hasTableStatuses = false;
$hasPaymentMethods = false;
$hasOrderItemPrices = false;

$check = $db->query("SHOW TABLES LIKE 'order_statuses'");
if ($check && $check->num_rows > 0) {
    $hasOrderStatuses = true;
}
$check = $db->query("SHOW TABLES LIKE 'table_statuses'");
if ($check && $check->num_rows > 0) {
    $hasTableStatuses = true;
}
$check = $db->query("SHOW TABLES LIKE 'payment_methods'");
if ($check && $check->num_rows > 0) {
    $hasPaymentMethods = true;
}
$check = $db->query("SHOW TABLES LIKE 'order_item_prices'");
if ($check && $check->num_rows > 0) {
    $hasOrderItemPrices = true;
}

$useNormalizedSchema = $hasOrderStatuses && $hasTableStatuses && $hasPaymentMethods && $hasOrderItemPrices;

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

    if ($action === 'pay') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $method_id = (int)($_POST['method_id'] ?? 0);
        $paid = (float)($_POST['paid'] ?? 0);
        $selected_order_id = $order_id;

        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT id, status_id, table_id FROM orders WHERE id=?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            if (!$order) {
                throw new Exception('Order tidak ada');
            }

            if ((int)$order['status_id'] !== ($orderStatusMap['served'] ?? -1)) {
                throw new Exception('Belum bisa bayar');
            }

            $checkPaid = $db->prepare("SELECT id FROM payments WHERE order_id=?");
            $checkPaid->bind_param('i', $order_id);
            $checkPaid->execute();
            if ($checkPaid->get_result()->num_rows > 0) {
                throw new Exception('Sudah dibayar');
            }

            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(oi.quantity * oip.price),0) AS total
                 FROM order_items oi
                 JOIN order_item_prices oip ON oip.order_item_id=oi.id
                 WHERE oi.order_id=?"
            );
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $total = (float)$stmt->get_result()->fetch_assoc()['total'];

            $methodNameStmt = $db->prepare("SELECT name FROM payment_methods WHERE id=?");
            $methodNameStmt->bind_param('i', $method_id);
            $methodNameStmt->execute();
            $methodRow = $methodNameStmt->get_result()->fetch_assoc();
            $methodName = $methodRow['name'] ?? '';

            if ($methodName === 'cash') {
                if ($paid < $total) {
                    throw new Exception('Uang kurang');
                }
            } else {
                $paid = $total;
            }

            $stmt = $db->prepare("INSERT INTO payments (order_id, method_id, paid) VALUES (?,?,?)");
            $stmt->bind_param('iid', $order_id, $method_id, $paid);
            $stmt->execute();

            $paidStatusId = $orderStatusMap['paid'] ?? 0;
            $stmt = $db->prepare("UPDATE orders SET status_id=? WHERE id=?");
            $stmt->bind_param('ii', $paidStatusId, $order_id);
            $stmt->execute();

            $availableId = $tableStatusMap['available'] ?? 0;
            $tableId = (int)$order['table_id'];
            $stmt = $db->prepare("UPDATE tables_list SET status_id=? WHERE id=?");
            $stmt->bind_param('ii', $availableId, $tableId);
            $stmt->execute();

            $db->commit();
            header('Location: payments.php?success=1');
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }

    $preorder = null;
    if ($selected_order_id > 0) {
        $oid = $selected_order_id;
        $servedId = $orderStatusMap['served'] ?? 0;
        $stmt = $db->prepare("SELECT o.id, t.table_number FROM orders o JOIN tables_list t ON t.id=o.table_id WHERE o.id=? AND o.status_id=?");
        $stmt->bind_param('ii', $oid, $servedId);
        $stmt->execute();
        $preorder = $stmt->get_result()->fetch_assoc();

        if ($preorder) {
            $stmt = $db->prepare("SELECT oi.quantity, m.name, oip.price FROM order_items oi JOIN menu m ON m.id=oi.menu_id JOIN order_item_prices oip ON oip.order_item_id=oi.id WHERE oi.order_id=?");
            $stmt->bind_param('i', $oid);
            $stmt->execute();
            $preorder['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $preorder['total'] = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $preorder['items']));
        }
    }

    $methods = $db->query('SELECT id, name FROM payment_methods ORDER BY name');
    $payments = $db->query(
        "SELECT p.paid, p.paid_at, o.id AS oid, t.table_number, pm.name AS method,
                COALESCE(ot.total,0) AS order_total
         FROM payments p
         JOIN payment_methods pm ON pm.id=p.method_id
         JOIN orders o ON o.id=p.order_id
         JOIN tables_list t ON t.id=o.table_id
         LEFT JOIN (
             SELECT oi.order_id, SUM(oi.quantity * oip.price) AS total
             FROM order_items oi
             JOIN order_item_prices oip ON oip.order_item_id=oi.id
             GROUP BY oi.order_id
         ) ot ON ot.order_id=o.id
         ORDER BY p.paid_at DESC"
    );
} else {
    if ($action === 'pay') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $method = trim($_POST['method'] ?? '');
        $paid = (float)($_POST['paid'] ?? 0);
        $selected_order_id = $order_id;

        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT status, table_id FROM orders WHERE id=?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            if (!$order) {
                throw new Exception('Order tidak ada');
            }
            if (($order['status'] ?? '') !== 'served') {
                throw new Exception('Belum bisa bayar');
            }

            $checkPaid = $db->prepare("SELECT id FROM payments WHERE order_id=?");
            $checkPaid->bind_param('i', $order_id);
            $checkPaid->execute();
            if ($checkPaid->get_result()->num_rows > 0) {
                throw new Exception('Sudah dibayar');
            }

            $stmt = $db->prepare("SELECT COALESCE(SUM(quantity*price),0) AS total FROM order_items WHERE order_id=?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $total = (float)$stmt->get_result()->fetch_assoc()['total'];

            if ($method === 'cash') {
                if ($paid < $total) {
                    throw new Exception('Uang kurang');
                }
            } else {
                $paid = $total;
            }

            $stmt = $db->prepare("INSERT INTO payments (order_id, method, paid) VALUES (?,?,?)");
            $stmt->bind_param('isd', $order_id, $method, $paid);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE orders SET status='paid' WHERE id=?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();

            $tableId = (int)$order['table_id'];
            $stmt = $db->prepare("UPDATE tables_list SET status='available' WHERE id=?");
            $stmt->bind_param('i', $tableId);
            $stmt->execute();

            $db->commit();
            header('Location: payments.php?success=1');
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }

    $preorder = null;
    if ($selected_order_id > 0) {
        $oid = $selected_order_id;
        $stmt = $db->prepare("SELECT o.*, t.table_number FROM orders o JOIN tables_list t ON t.id=o.table_id WHERE o.id=?");
        $stmt->bind_param('i', $oid);
        $stmt->execute();
        $preorder = $stmt->get_result()->fetch_assoc();
        if ($preorder) {
            $stmt = $db->prepare("SELECT oi.*, m.name FROM order_items oi JOIN menu m ON m.id=oi.menu_id WHERE oi.order_id=?");
            $stmt->bind_param('i', $oid);
            $stmt->execute();
            $preorder['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $preorder['total'] = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $preorder['items']));
        }
    }

    $methods = false;
    $payments = $db->query("SELECT p.*, o.id AS oid, t.table_number, COALESCE(ot.total,0) AS order_total FROM payments p JOIN orders o ON o.id=p.order_id JOIN tables_list t ON t.id=o.table_id LEFT JOIN (SELECT order_id, SUM(quantity*price) total FROM order_items GROUP BY order_id) ot ON ot.order_id=o.id ORDER BY p.paid_at DESC");
}

if (!$payments) {
    $payments = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<h1>Payments</h1>
<div class="nav">
    <a href="index.php">Dashboard</a>
    <a href="menu.php">Menu</a>
    <a href="tables.php">Tables</a>
    <a href="orders.php">Orders</a>
    <a class="active" href="payments.php">Payments</a>
    <a href="customers.php">Customers</a>
</div>

<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
<div class="success">Payment successful!</div>
<?php endif; ?>

<div class="container">
    <?php if ($preorder): ?>
    <div class="card payment-card">
        <h3>Order #<?= $preorder['id'] ?> - Table <?= htmlspecialchars($preorder['table_number']) ?></h3>

        <?php foreach (($preorder['items'] ?? []) as $i): ?>
            <p><?= htmlspecialchars($i['name']) ?> x<?= (int)$i['quantity'] ?></p>
        <?php endforeach; ?>

        <p class="total">Rp <?= number_format((float)($preorder['total'] ?? 0), 0, ',', '.') ?></p>

        <form method="post">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="order_id" value="<?= $preorder['id'] ?>">

            <label>Method</label>
            <?php if ($useNormalizedSchema): ?>
                <select name="method_id" id="method" onchange="toggleCash()" required>
                    <option value="">Select method</option>
                    <?php if ($methods): ?>
                        <?php while ($method = $methods->fetch_assoc()): ?>
                            <option value="<?= $method['id'] ?>" data-method-name="<?= htmlspecialchars($method['name']) ?>">
                                <?= htmlspecialchars($method['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            <?php else: ?>
                <select name="method" id="method" onchange="toggleCash()" required>
                    <option value="cash">cash</option>
                    <option value="card">card</option>
                    <option value="gopay">gopay</option>
                    <option value="ovo">ovo</option>
                    <option value="qris">qris</option>
                </select>
            <?php endif; ?>

            <div id="cashBox">
                <label>Paid</label>
                <input type="number" name="paid" id="paidInput" min="0" step="1000" data-total="<?= (float)($preorder['total'] ?? 0) ?>">
            </div>

            <button>Confirm Payment</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="payment-history">
        <h3>Payment History</h3>

        <table>
            <tr>
                <th>Order</th>
                <th>Table</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Change</th>
                <th>Method</th>
            </tr>

            <?php if ($payments): ?>
                <?php while ($r = $payments->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $r['oid'] ?></td>
                    <td><?= htmlspecialchars($r['table_number']) ?></td>
                    <td>Rp <?= number_format((float)$r['order_total'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format((float)$r['paid'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format(max(0, (float)$r['paid'] - (float)$r['order_total']), 0, ',', '.') ?></td>
                    <td><?= strtoupper($r['method']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>

        </table>
    </div>

</div>
<script>
function toggleCash() {
    const methodEl = document.getElementById('method');
    const cashBox = document.getElementById('cashBox');
    const paidInput = document.getElementById('paidInput');
    if (!methodEl || !cashBox || !paidInput) return;

    let isCash = false;
    const selectedOption = methodEl.options[methodEl.selectedIndex];
    if (selectedOption && selectedOption.getAttribute('data-method-name') !== null) {
        isCash = selectedOption.getAttribute('data-method-name') === 'cash';
    } else {
        isCash = methodEl.value === 'cash';
    }

    cashBox.style.display = isCash ? 'block' : 'none';
    paidInput.required = isCash;
    paidInput.disabled = !isCash;

    if (!isCash) {
        paidInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', toggleCash);
</script>
</body>
</html>