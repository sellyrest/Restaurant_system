<?php
require 'config.php';

$error=null;
if (($_POST['action'] ?? '') == 'pay') {

$order_id=(int)$_POST['order_id'];
$method=$_POST['method'];
$paid=(float)($_POST['paid'] ?? 0);

$db->begin_transaction();
try{
    $stmt=$db->prepare("SELECT status,table_id FROM orders WHERE id=?");
    $stmt->bind_param('i',$order_id);
    $stmt->execute();
    $order=$stmt->get_result()->fetch_assoc();
    if(!$order) throw new Exception("Order tidak ada");
    if($order['status']!='served') throw new Exception("Belum bisa bayar");
    $check=$db->prepare("SELECT id FROM payments WHERE order_id=?");
    $check->bind_param('i',$order_id);
    $check->execute();
    if($check->get_result()->num_rows>0) throw new Exception("Sudah dibayar");
    $stmt=$db->prepare("SELECT COALESCE(SUM(quantity*price),0) total FROM order_items WHERE order_id=?");
    $stmt->bind_param('i',$order_id);
    $stmt->execute();
    $total=$stmt->get_result()->fetch_assoc()['total'];
    $change=0;
    if($method=='cash'){
        if($paid<$total) throw new Exception("Uang kurang");
        $change=$paid-$total;
    }
    $stmt=$db->prepare("INSERT INTO payments (order_id,total,method,paid,change_amount) VALUES (?,?,?,?,?)");
    $stmt->bind_param('idsdd',$order_id,$total,$method,$paid,$change);
    $stmt->execute();
    $stmt=$db->prepare("UPDATE orders SET status='paid' WHERE id=?");
    $stmt->bind_param('i',$order_id);
    $stmt->execute();
    $stmt=$db->prepare("UPDATE tables_list SET status='available' WHERE id=?");
    $stmt->bind_param('i',$order['table_id']);
    $stmt->execute();
    $db->commit();
    header("Location: payments.php?success=1");
    exit;
}catch(Exception $e){
    $db->rollback();
    $error=$e->getMessage();
}
}

$preorder=null;

if(isset($_GET['order_id'])){
    $oid=(int)$_GET['order_id'];
    $stmt=$db->prepare("SELECT o.*,t.table_number FROM orders o JOIN tables_list t ON t.id=o.table_id WHERE o.id=?");
    $stmt->bind_param('i',$oid);
    $stmt->execute();
    $preorder=$stmt->get_result()->fetch_assoc();
    if($preorder){
        $stmt=$db->prepare("SELECT oi.*,m.name FROM order_items oi JOIN menu m ON m.id=oi.menu_id WHERE oi.order_id=?");
        $stmt->bind_param('i',$oid);
        $stmt->execute();
        $preorder['items']=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $preorder['total']=array_sum(array_map(fn($i)=>$i['quantity']*$i['price'],$preorder['items']));
    }
}
$payments=$db->query("SELECT p.*,o.id oid,t.table_number FROM payments p JOIN orders o ON o.id=p.order_id JOIN tables_list t ON t.id=o.table_id ORDER BY p.paid_at DESC");
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Payments</title>
        <style>body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }

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
</style>
    </head>
<body>
<body>

<h1>Payments</h1>
<div class="nav">
    <a href="index.php">Dashboard</a>
    <a href="menu.php">Menu</a>
    <a href="tables.php">Tables</a>
    <a href="orders.php">Orders</a>
    <a href="payments.php">Payments</a>
    <a href="customers.php">Customers</a>
</div>

<?php if($error): ?>
<div class="error"><?= $error ?></div>
<?php endif; ?>

<?php if(isset($_GET['success'])): ?>
<div class="success">Payment successful!</div>
<?php endif; ?>

<div class="container">
    <?php if($preorder): ?>
    <div class="card" style="min-width:300px;flex:1;">
        <h3>Order #<?= $preorder['id'] ?> - Table <?= $preorder['table_number'] ?></h3>

        <?php foreach($preorder['items'] as $i): ?>
            <p><?= $i['name'] ?> x<?= $i['quantity'] ?></p>
        <?php endforeach; ?>

        <p class="total">Rp <?= number_format($preorder['total'],0,',','.') ?></p>

        <form method="post">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="order_id" value="<?= $preorder['id'] ?>">

            <label>Method</label>
            <select name="method" id="method" onchange="toggleCash()">
                <option value="cash">cash</option>
                <option value="card">card</option>
                <option value="gopay">gopay</option>
                <option value="ovo">ovo</option>
                <option value="qris">qris</option>
            </select>

            <div id="cashBox">
                <label>Paid</label>
                <input type="number" name="paid">
            </div>

            <button>Confirm Payment</button>
        </form>
    </div>
    <?php endif; ?>

    <div style="flex:2;">
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

            <?php while($r=$payments->fetch_assoc()): ?>
            <tr>
                <td>#<?= $r['oid'] ?></td>
                <td><?= $r['table_number'] ?></td>
                <td>Rp <?= number_format($r['total'],0,',','.') ?></td>
                <td>Rp <?= number_format($r['paid'],0,',','.') ?></td>
                <td>Rp <?= number_format($r['change_amount'],0,',','.') ?></td>
                <td><?= strtoupper($r['method']) ?></td>
            </tr>
            <?php endwhile; ?>

        </table>
    </div>

</div>
</body>
</html>