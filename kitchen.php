<?php
include 'db.php'; 

if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $id = intval($_GET['update_id']);
    $status = $conn->real_escape_string($_GET['new_status']);
    $allowedStatuses = ['Pending', 'Preparing', 'Completed'];

    if (in_array($status, $allowedStatuses, true)) {
        $sql = "UPDATE orders SET status='$status' WHERE id=$id";
        if ($conn->query($sql)) {
            header("Location: kitchen.php");
            exit;
        } else {
            die("Update failed: " . $conn->error);
        }
    }
}

$sql = "
    SELECT
        o.id,
        o.table_number,
        o.status,
        o.created_at
    FROM orders o
    WHERE o.status != 'Completed'
    ORDER BY o.id ASC
";
$result = $conn->query($sql);

if ($result === false) {
    die('Query error: ' . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Staff Dashboard</title>
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
            <a href="add_food.php">➕ Add Food</a>
            <a href="kitchen.php" class="active">👨‍🍳 Kitchen</a>
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
        <h1>👨‍🍳 Kitchen Staff Dashboard</h1>
    </div>

    <div class="container">
        <div class="header">
            <h1>👨‍🍳 Kitchen Orders</h1>
            <p>Manage and track order preparation</p>
        </div>

<?php if ($result->num_rows > 0) { ?>
    <?php while($row = $result->fetch_assoc()) { ?>
        <div class="order-card <?php echo ($row['status'] == 'Preparing') ? 'preparing-border' : ''; ?>">
            <span class="badge"><?php echo $row['status']; ?></span>
            <h3>Order #<?php echo $row['id']; ?></h3>
            <p><strong>Table:</strong> <?php echo !empty($row['table_number']) ? 'Table #' . (int)$row['table_number'] : 'N/A'; ?></p>
            <p><strong>Items:</strong></p>
            <?php
                $itemStmt = $conn->prepare("SELECT food_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY id ASC");
                $itemStmt->bind_param('i', $row['id']);
                $itemStmt->execute();
                $itemRes = $itemStmt->get_result();
                if ($itemRes && $itemRes->num_rows > 0) {
                    while ($item = $itemRes->fetch_assoc()) {
            ?>
                <div class="item-row">
                    <div>
                        <div class="item-name"><?php echo htmlspecialchars($item['food_name']); ?></div>
                        <div class="item-sub">Qty: <?php echo (int)$item['quantity']; ?> | $<?php echo number_format((float)$item['price'], 2); ?></div>
                    </div>
                </div>
            <?php
                    }
                } else {
                    echo '<p style="color:#666;">No items found for this order.</p>';
                }
                $itemStmt->close();
            ?>
            
            <div style="margin-top: 15px;">
                <?php if ($row['status'] == 'Pending') { ?>
                    <a href="kitchen.php?update_id=<?php echo $row['id']; ?>&new_status=Preparing" class="status-btn preparing">
                        Start Preparing
                    </a>
                <?php } elseif ($row['status'] == 'Preparing') { ?>
                    <a href="kitchen.php?update_id=<?php echo $row['id']; ?>&new_status=Completed" class="status-btn complete">
                        Mark as Completed
                    </a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
<?php } else { ?>
    <div style="text-align:center;">            <h3>No active orders.</h3>
    </div>
<?php } ?>
    </div>
</body>
</html>