<?php
require 'config.php';
$action = $_POST['action'] ?? '';

$hasCategories = false;
$hasMenuPrices = false;

$check = $db->query("SHOW TABLES LIKE 'categories'");
if ($check && $check->num_rows > 0) {
    $hasCategories = true;
}

$check = $db->query("SHOW TABLES LIKE 'menu_prices'");
if ($check && $check->num_rows > 0) {
    $hasMenuPrices = true;
}

$useNormalizedSchema = $hasCategories && $hasMenuPrices;

if ($useNormalizedSchema) {
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);

        if ($name !== '' && $category_id > 0 && $price > 0) {
            $db->begin_transaction();
            try {
                $stmt = $db->prepare("INSERT INTO menu (name, category_id) VALUES (?,?)");
                $stmt->bind_param('si', $name, $category_id);
                $stmt->execute();

                $menu_id = (int)$db->insert_id;
                $stmt = $db->prepare("INSERT INTO menu_prices (menu_id, price) VALUES (?,?)");
                $stmt->bind_param('id', $menu_id, $price);
                $stmt->execute();

                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
            }
        }
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $db->prepare("DELETE FROM menu WHERE id=?");
        $id = (int)$_POST['id'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    $categories = $db->query("SELECT id, name FROM categories ORDER BY name");

    $items = $db->query(
        "SELECT m.id, m.name, c.name AS category, m.available, p.price
         FROM menu m
         JOIN categories c ON c.id=m.category_id
         LEFT JOIN (
             SELECT mp.menu_id, mp.price
             FROM menu_prices mp
             JOIN (
                 SELECT menu_id, MAX(id) AS max_id
                 FROM menu_prices
                 GROUP BY menu_id
             ) x ON x.max_id=mp.id
         ) p ON p.menu_id=m.id
         ORDER BY c.name, m.name"
    );
} else {
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO menu (name, category, price) VALUES (?,?,?)");
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Main');
        $price = (float)($_POST['price'] ?? 0);
        $stmt->bind_param('ssd', $name, $category, $price);
        $stmt->execute();
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $db->query("DELETE FROM menu WHERE id=" . (int)$_POST['id']);
    }

    $categories = false;
    $items = $db->query("SELECT * FROM menu ORDER BY category, name");
}

if (!$items) {
    $items = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Menu</h1>
    <div class="nav">
        <a href="index.php">Dashboard</a>
        <a class="active" href="menu.php">Menu</a>
        <a href="tables.php">Tables</a>
        <a href="orders.php">Orders</a>
        <a href="payments.php">Payments</a>
        <a href="customers.php">Customers</a>
    </div>

    <form class="add" method="post">
        <input type="hidden" name="action" value="add">
        <input type="text" name="name" placeholder="Item name" required>
        <?php if ($useNormalizedSchema): ?>
            <select name="category_id" required>
                <option value="">Select category</option>
                <?php if ($categories): ?>
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        <?php else: ?>
            <select name="category">
                <option>Main</option>
                <option>Drink</option>
                <option>Dessert</option>
            </select>
        <?php endif; ?>
        <input type="number" name="price" placeholder="Price (Rp)" step="1" min="1" required>
        <button type="submit">Add Item</button>
    </form>

    <table>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php if ($items): ?>
            <?php while ($r = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= htmlspecialchars($r['category']) ?></td>
                    <td><?= number_format((float)($r['price'] ?? 0), 0, ',', '.') ?></td>
                    <td><?= !empty($r['available']) ? 'Available' : 'Off' ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>
</body>
</html>