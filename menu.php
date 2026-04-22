<?php
require 'config.php';
if ($_POST['action'] ?? '' == 'add') {
    $stmt = $db->prepare("INSERT INTO menu (name, category, price) VALUES (?,?,?)");
    $stmt->bind_param('ssd', $_POST['name'], $_POST['category'], $_POST['price']);
    $stmt->execute();
}
if ($_POST['action'] ?? '' == 'delete') {
    $db->query("DELETE FROM menu WHERE id=" . (int)$_POST['id']);
}
$items = $db->query("SELECT * FROM menu ORDER BY category, name");
?>
<!DOCTYPE html>
<head>
    <title>Menu</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f5f5f5; }
        .nav a { margin-right: 15px; padding: 8px 16px; background: #333; color: #fff; text-decoration: none; border-radius: 4px; }
        .nav a:hover { background: #555; }
        form.add { background: #fff; padding: 16px; border-radius: 8px; margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap; }
        input, select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 16px; background: #e67e22; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        th { background: #333; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <h1>Menu</h1>
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
        <input type="text"   name="name"     placeholder="Item name" required>
        <select name="category">
            <option>Main</option>
            <option>Drink</option>
            <option>Dessert</option>
        </select>
        <input type="number" name="price" placeholder="Price (Rp)" step="500" required>
        <button type="submit">Add Item</button>
    </form>
    <table><tr>
        <th>#</th>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php while ($r = $items->fetch_assoc()): ?>
    <tr>
        <td><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['category'] ?></td>
        <td><?= number_format($r['price'], 0, ',', '.') ?></td>
        <td><?=  $r['available'] ? 'Available' : 'Off' ?></td>
        <td>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button onclick="return confirm('Delete?')">Delete</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
    </table>
</body>
</html>