<?php

include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('Location: admin_login.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $food_name = trim($_POST['food_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);
    $image_url = trim($_POST['image_url'] ?? '');

    $sql = "INSERT INTO food (food_name, description, image_url, price, category, stock) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdsi", $food_name, $description, $image_url, $price, $category, $stock);
    if ($stmt->execute() === TRUE) {
        echo "Food added successfully.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Food</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">🍽️ Restaurant</div>
        <nav class="sidebar-nav">
            <a href="admin.php">📊 Dashboard</a>
            <a href="order.php">🛒 View Orders</a>
            <a href="view_food.php">🍕 View Food</a>
            <a href="add_food.php" class="active">➕ Add Food</a>
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
        <h1>Add New Food Item</h1>
    </div>

    <div class="container">
        <div class="card">
            <h2>➕ Add New Food Item</h2>
            <form method="POST" action="">
                <div>
                    <label for="food_name">Food Name:</label>
                    <input type="text" id="food_name" name="food_name" placeholder="Enter food name" required>
                </div>
                <div>
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" placeholder="Enter food description" required></textarea>
                </div>
                <div>
                    <label for="image_url">Image URL:</label>
                    <input type="text" id="image_url" name="image_url" placeholder="Image URL (https://...)" required>
                </div>
                <div>
                    <label for="price">Price:</label>
                    <input type="number" id="price" step="0.01" name="price" placeholder="0.00" required>
                </div>
                <div>
                    <label for="category">Category:</label>
                    <input type="text" id="category" name="category" placeholder="e.g., Appetizer, Main Course" required>
                </div>
                <div>
                    <label for="stock">Stock Quantity:</label>
                    <input type="number" id="stock" name="stock" placeholder="0" required>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="button primary" style="flex: 1;">✅ Add Food Item</button>
                    <a href="view_food.php" class="button" style="flex: 1; text-align: center;">📋 View Food List</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>