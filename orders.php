<?php
require 'config.php';

$error = null;
if (($_POST['action'] ?? '') == 'create') {

    $table_id = (int)$_POST['table_id'];
    $customer = $_POST['customer_name'];

    $db->begin_transaction();

    try {
        $stmt = $db->prepare("INSERT INTO orders (table_id, customer_name) VALUES (?, ?)");
        $stmt->bind_param('is', $table_id, $customer);
        $stmt->execute();
        $order_id = $db->insert_id;

        $stmtPrice = $db->prepare("SELECT price FROM menu WHERE id=?");
        $stmtItem  = $db->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES (?, ?, ?, ?)");

        $hasItem = false;

        foreach ($_POST['menu_id'] as $i => $menu_id) {
            if (!$menu_id) continue;

            $qty = (int)$_POST['qty'][$i];
            if ($qty <= 0) continue;

            $stmtPrice->bind_param('i', $menu_id);
            $stmtPrice->execute();
            $row = $stmtPrice->get_result()->fetch_assoc();

            if (!$row) continue;

            $price = $row['price'];

            $stmtItem->bind_param('iiid', $order_id, $menu_id, $qty, $price);
            $stmtItem->execute();

            $hasItem = true;
        }

        if (!$hasItem) throw new Exception("Order harus punya minimal 1 item!");

        $stmt = $db->prepare("UPDATE tables_list SET status='occupied' WHERE id=?");
        $stmt->bind_param('i', $table_id);
        $stmt->execute();

        $db->commit();

    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

if (($_POST['action'] ?? '') == 'status') {

    $id     = (int)$_POST['id'];
    $status = $_POST['status'];

    if ($status === 'paid') {
        $error = "Status 'paid' hanya lewat payment!";
    } else {
        $stmt = $db->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
    }
}

$orders = $db->query("
    SELECT o.*, t.table_number 
    FROM orders o 
    LEFT JOIN tables_list t ON t.id=o.table_id 
    ORDER BY o.created_at DESC
");

$tables = $db->query("SELECT * FROM tables_list WHERE status='available'");
$menu   = $db->query("SELECT * FROM menu WHERE available=1");
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Orders</title>
        <style>
body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }

h1 { color: #333; }

.nav { margin-bottom: 20px; }
.nav a {
    margin-right: 15px;
    padding: 8px 16px;
    background: #333;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
}
.nav a:hover { background: #555; }

.form-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 2px 6px rgba(0,0,0,.1);
}

.container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,.1);
}

input, select {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin: 4px 0;
    width: 100%;
}

button {
    padding: 8px 16px;
    background: #e67e22;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
button:hover { background: #cf711c; }

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,.1);
    margin-top: 20px;
}

th {
    background: #333;
    color: #fff;
    padding: 10px;
    text-align: left;
}

td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.total {
    font-size: 1.2rem;
    font-weight: bold;
    color: #e67e22;
    margin-top: 10px;
}

.success {
    background: #d4edda;
    color: #155724;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 10px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 10px;
}
.btn-pay {
    padding: 6px 12px;
    background: #2ecc71;
    color: #fff;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: bold;
    transition: 0.2s;
    display: inline-block;
}

.btn-pay:hover {
    background: #27ae60;
    transform: translateY(-1px);
}

.btn-pay:active {
    transform: scale(0.98);
}
  </style>
    </head>
    <body>
        <h1>Orders</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="menu.php">Menu</a>
            <a href="tables.php">Tables</a>
            <a href="orders.php">Orders</a>
            <a href="payments.php">Payments</a>
            <a href="customers.php">Customers</a>
        </div>
        <?php if ($error): ?>
            <p style="color:red"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <div class="form-box">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <select name="table_id">
                        <?php while($t=$tables->fetch_assoc()): ?>
                            <option value="<?= $t['id'] ?>"><?= $t['table_number'] ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="text" name="customer_name" placeholder="Customer">
                        <?php
                        $menu_items=[];
                        while($m=$menu->fetch_assoc()) $menu_items[]=$m;
                        $opts='<option value="">--select--</option>';
                        foreach($menu_items as $m){
                            $opts.="<option value='{$m['id']}'>{$m['name']}</option>";
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
                <table border="1" width="100%" cellpadding="8">
                    <tr>
                        <th>#</th>
                        <th>Table</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php while($r=$orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><?= $r['table_number'] ?></td>
                            <td><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td><span class="badge <?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <select name="status">
                                        <?php foreach(['pending','preparing','served'] as $s): ?>
                                            <option <?= $r['status']==$s?'selected':'' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button>Update</button>
                                        <?php if($r['status']=='served'): ?>
                                            <a href="payments.php?order_id=<?= $r['id'] ?>" class="btn-pay">Pay</a>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <script>
                    const opts = `<?= $opts ?>`;
                    function addRow(){
                        let d=document.createElement('div');
                        d.className='item-row';
                        d.innerHTML=`<select name="menu_id[]">${opts}</select>
                        <input type="number" name="qty[]" value="1" min="1">`;
                        document.getElementById('items').appendChild(d);
                        }
                    </script>
        </body>
</html>