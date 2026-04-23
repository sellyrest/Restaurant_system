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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<h1>Customers</h1>
<div class="nav">
  <a href="index.php">Dashboard</a>
  <a href="menu.php">Menu</a>
  <a href="tables.php">Tables</a>
  <a href="orders.php">Orders</a>
  <a href="payments.php">Payments</a>
  <a class="active" href="customers.php">Customers</a>
</div>

<form class="add" method="post">
  <input type="hidden" name="action" value="add">
  <input type="text" name="name" placeholder="Full name" required>
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