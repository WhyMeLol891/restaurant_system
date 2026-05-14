<?php
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('Location: admin_login.php');
    exit();
}

$message = '';
$edit_food = null;

// Prevent browser caching so stock changes made elsewhere (e.g., order.php)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM food WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: view_food.php?msg=deleted");
        exit;
    }
}

// Ordering is handled centrally in order.php; remove per-item ordering here to avoid duplicate paths.

if (isset($_POST['update_food'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $food_name = trim($_POST['food_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    $stmt = mysqli_prepare($conn, "UPDATE food SET food_name = ?, category = ?, description = ?, image_url = ?, price = ?, stock = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssdii", $food_name, $category, $description, $image_url, $price, $stock, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: view_food.php?msg=updated");
        exit;
    }
}

if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM food WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);
        $result_edit = mysqli_stmt_get_result($stmt);
        $edit_food = $result_edit ? mysqli_fetch_assoc($result_edit) : null;
        if ($result_edit) {
            mysqli_free_result($result_edit);
        }
        mysqli_stmt_close($stmt);
    }
}

$query = "SELECT * FROM food";
$result = mysqli_query($conn, $query);
$foods = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $foods[] = $row;
    }
    mysqli_free_result($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Food Items</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">🍽️ Restaurant</div>
        <nav class="sidebar-nav">
            <a href="admin.php">📊 Dashboard</a>
            <a href="order.php">🛒 View Orders</a>
            <a href="view_food.php" class="active">🍕 View Food</a>
            <a href="add_food.php">➕ Add Food</a>
            <a href="kitchen.php">👨‍🍳 Kitchen</a>
            <a href="payment.php">💳 Payments</a>
            <a href="receipts.php">📄 Receipts</a>
            <?php if(isset($_SESSION["username"]) && $_SESSION["username"]){ ?>
                <a href="admin_login.php?logout=1" style="margin-top: auto;">🚪 Logout</a>
            <?php } else { ?>
                <a href="admin_login.php">🔐 Login</a>
            <?php } ?>
        </nav>
    </div>

    <div class="top-menu">
        <h1>View Food Items</h1>
    </div>

    <div class="container">
        <div class="header">
            <h1>🍕 Food Menu / Items</h1>
            <p>Manage your restaurant's menu items</p>
        </div>

    <?php if ($edit_food): ?>
        <div class="card edit-food-card">
            <h2>✏️ Edit Food Item</h2>
            <form method="post" action="view_food.php" class="edit-food-form">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_food['id']); ?>">

                <div class="form-group">
                    <label for="food_name">Food Name</label>
                    <input id="food_name" type="text" name="food_name" value="<?php echo htmlspecialchars($edit_food['food_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <input id="category" type="text" name="category" value="<?php echo htmlspecialchars($edit_food['category'] ?? ''); ?>">
                </div>

                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_food['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="image_url">Image URL</label>
                    <input id="image_url" type="url" name="image_url" value="<?php echo htmlspecialchars($edit_food['image_url'] ?? ''); ?>" placeholder="https://example.com/food.jpg" pattern="https?://.+">
                </div>

                <div class="form-group">
                    <label for="price">Price</label>
                    <input id="price" type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($edit_food['price']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="stock">Stock</label>
                    <input id="stock" type="number" name="stock" value="<?php echo htmlspecialchars($edit_food['stock'] ?? 0); ?>" required>
                </div>

                <div class="edit-food-actions">
                    <button type="submit" name="update_food" class="button primary">Update Food</button>
                    <a href="view_food.php" class="button clear-btn">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
    

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Food Name</th>
                <th>Image</th>
                <th>Category</th>
                <th>Description</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($foods)): ?>
                <?php foreach ($foods as $food): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($food['id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($food['food_name']); ?></strong></td>
                        <td>
                            <?php if (!empty($food['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($food['image_url']); ?>" alt="<?php echo htmlspecialchars($food['food_name']); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($food['category'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($food['description'] ?? 'N/A'); ?></td>
                        <td>$<?php echo number_format($food['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($food['stock'] ?? 0); ?></td>
                        <td>
                            <a href="view_food.php?edit_id=<?php echo urlencode($food['id']); ?>">Edit</a>
                            |
                            <a href="view_food.php?delete_id=<?php echo urlencode($food['id']); ?>" onclick="return confirm('Are you sure you want to delete this food item?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No food items found in the database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</body>
</html>