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

// Check this line in your kitchen.php
$sql = "SELECT * FROM orders WHERE status != 'Completed' ORDER BY id ASC";
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
            <p><strong>Items:</strong><br><?php echo nl2br($row['order_items']); ?></p>
            
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