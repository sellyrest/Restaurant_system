<?php
require 'config.php';

if (($_POST['action'] ?? '') == 'add') {
    $stmt = $db->prepare("INSERT INTO customers (name, phone) VALUES (?,?)");
    $stmt->bind_param('ss', $_POST['name'], $_POST['phone']);
    $stmt->execute();
}

if (($_POST['action'] ?? '') == 'delete') {
    $stmt = $db->prepare("DELETE FROM customers WHERE id=?");
    $stmt->bind_param('i', $_POST['id']);
    $stmt->execute();
}

$customers = $db->query("SELECT * FROM customers ORDER BY name");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Customers</title>
  <style>
    body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
    .nav a { margin-right: 15px; padding: 8px 16px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; }
    .nav a:hover { background: #555; }
    form.add { background: #fff; padding: 16px; border-radius: 8px; margin: 20px 0; display: flex; gap: 10px; }
    input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    button { padding: 8px 16px; background: #e67e22; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
    th { background: #333; color: #fff; padding: 10px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #eee; }
    button {
    padding: 8px 16px;
    background: #e67e22;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background: #cf711c;
}
    .btn-delete {
    background: #e74c3c;
}

.btn-delete:hover {
    background: #c0392b;
}
  </style>
</head>
<body>
<h1>Customers</h1>
<div class="nav">
  <a href="index.php">Dashboard</a>
  <a href="menu.php">Menu</a>
  <a href="tables.php">Tables</a>
  <a href="orders.php">Orders</a>
  <a href="payments.php">Payments</a>
  <a href="customers.php">Customers</a>
</div>

<form class="add" method="post">
  <input type="hidden" name="action" value="add">
  <input type="text" name="name"  placeholder="Full name" required>
  <input type="text" name="phone" placeholder="Phone number">
  <button type="submit">Add Customer</button>
</form>

<table>
  <tr><th>#</th><th>Name</th><th>Phone</th><th>Joined</th><th>Action</th></tr>
  <?php while ($r = $customers->fetch_assoc()): ?>
  <tr>
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td><?= $r['phone'] ?></td>
    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
    <td>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn-delete" onclick="return confirm('Delete this customer?')">Delete</button>
      </form>
    </td>
  </tr>
  <?php endwhile; ?>
</table>
</body>
</html>