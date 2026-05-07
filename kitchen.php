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
    <title>Kitchen Staff Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .order-card { 
            background: white; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 15px; 
            border-left: 10px solid #3498db; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        /* Change border color if the order is being prepared */
        .preparing-border { border-left-color: #f39c12; }
        
        .status-btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            color: white; 
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .preparing { background: #f39c12; }
        .complete { background: #27ae60; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; color: white; background: #34495e; float: right; }
        .item-row { display: flex; align-items: center; gap: 10px; margin: 8px 0; }
        .item-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; background: #fff; }
        .item-name { font-weight: bold; }
        .item-sub { color: #666; font-size: 13px; }
        h2 { border-bottom: 2px solid #ddd; padding-bottom: 10px; }
    </style>
</head>
<body>

<h2>👨‍🍳 Kitchen Orders</h2>

<?php if ($result->num_rows > 0) { ?>
    <?php while($row = $result->fetch_assoc()) { ?>
        <div class="order-card <?php echo ($row['status'] == 'Preparing') ? 'preparing-border' : ''; ?>">
            <span class="badge"><?php echo $row['status']; ?></span>
            <h3>Order #<?php echo $row['id']; ?></h3>
            <p><strong>Table:</strong> <?php echo !empty($row['table_number']) ? 'Table #' . (int)$row['table_number'] : 'N/A'; ?></p>
            <p><strong>Items:</strong></p>
            <?php
                $itemStmt = $conn->prepare("SELECT food_name, image_url, quantity, price FROM order_items WHERE order_id = ? ORDER BY id ASC");
                $itemStmt->bind_param('i', $row['id']);
                $itemStmt->execute();
                $itemRes = $itemStmt->get_result();
                if ($itemRes && $itemRes->num_rows > 0) {
                    while ($item = $itemRes->fetch_assoc()) {
            ?>
                <div class="item-row">
                    <?php if (!empty($item['image_url'])): ?>
                        <img class="item-img" src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['food_name']); ?>">
                    <?php endif; ?>
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