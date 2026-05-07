<?php

include 'db.php';

$totalRevenue = 0;
$completedFoods = [];

$revenueQuery = "SELECT SUM(total_revenue) AS revenue FROM revenue_summary WHERE id = 1 LIMIT 1";
if ($result = mysqli_query($conn, $revenueQuery)) {
    $row = mysqli_fetch_assoc($result);
    $totalRevenue = $row['revenue'] ?? 0;
    mysqli_free_result($result);
}

$foodQuery = "
SELECT oi.food_name AS name,
       SUM(oi.quantity) AS quantity,
       SUM(oi.quantity * oi.price) AS subtotal
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE o.status IN ('done', 'completed')
GROUP BY oi.food_name
ORDER BY quantity DESC
";

if ($result = mysqli_query($conn, $foodQuery)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $completedFoods[] = $row;
    }
    mysqli_free_result($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        body {
            background: linear-gradient(to right, #1bef08 0%, #bfecd9 100%);
        }
    </style>
</head>
<body>
    <div class="top-menu">
        <h1>Restaurant System</h1>
        <div class="top-menu-buttons">
            <a href="order.php" class="button">View Orders</a>
            <a href="view_food.php" class="button">View Food</a>
            <a href="add_food.php" class="button">Add Food</a>
            <a href="kitchen.php" class="button">Kitchen Dashboard</a>
            <a href="admin_login.php" class="button login-btn">Login</a>
            <?php if(isset($_SESSION["username"]) && $_SESSION["username"]){ ?>
                    <a href="#"><?=$_SESSION['username']?> </a>
            <?php } ?>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Overview of completed sales and revenue.</p>
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <span class="label">Total Revenue</span>
                <div class="value">$<?php echo number_format((float)$totalRevenue, 2); ?></div>
            </div>
            <div class="stat">
                <span class="label">Completed Food Items</span>
                <div class="value"><?php echo count($completedFoods); ?></div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top: 0;">Completed Foods</h2>
            <table>
                <thead>
                    <tr>
                        <th>Food Name</th>
                        <th>Quantity Sold</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($completedFoods)): ?>
                        <?php foreach ($completedFoods as $food): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($food['name']); ?></td>
                            <td><?php echo (int)$food['quantity']; ?></td>
                            <td>$<?php echo number_format((float)$food['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="empty-state" colspan="3">No completed foods found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="top-menu-buttons">
        <a href="payment.php" class="button">View Payments</a>
    </div>
</body>
</html>