<?php
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('Location: admin_login.php');
    exit();
}

$selectedTable = isset($_GET['table_number']) ? (int)$_GET['table_number'] : 0;
$searchAmount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$fromDate = isset($_GET['from_date']) && $_GET['from_date'] ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] ? $_GET['to_date'] : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$receipts = [];
$whereClause = '1=1';
$params = [];
$types = '';

if ($selectedTable > 0) {
    $whereClause .= ' AND table_number = ?';
    $params[] = $selectedTable;
    $types .= 'i';
}

if ($fromDate) {
    $whereClause .= ' AND DATE(created_at) >= ?';
    $params[] = $fromDate;
    $types .= 's';
}

if ($toDate) {
    $whereClause .= ' AND DATE(created_at) <= ?';
    $params[] = $toDate;
    $types .= 's';
}

if ($searchAmount > 0) {
    $whereClause .= ' AND amount = ?';
    $params[] = $searchAmount;
    $types .= 'd';
}

if ($searchQuery) {
    $whereClause .= ' AND (CONCAT(order_id, " ", order_summary) LIKE ?)';
    $params[] = '%' . $searchQuery . '%';
    $types .= 's';
}

$sql = 'SELECT id, table_number, order_id, order_summary, amount, created_at FROM receipts WHERE ' . $whereClause . ' ORDER BY created_at DESC LIMIT 500';
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $receipts[] = $row; }
    $stmt->close();
}

    // Preload order items for receipts to avoid per-row queries
    $orderItemsMap = [];
    $orderIds = array_filter(array_map(function($r){ return isset($r['order_id']) ? (int)$r['order_id'] : 0; }, $receipts));
    $orderIds = array_values(array_unique(array_filter($orderIds, function($v){ return $v>0; })));
    if (!empty($orderIds)) {
        $idsCsv = implode(',', $orderIds);
        $q = "SELECT order_id, food_name, quantity, price FROM order_items WHERE order_id IN ($idsCsv) ORDER BY id ASC";
        if ($res = $conn->query($q)) {
            while ($row = $res->fetch_assoc()) {
                $oid = (int)$row['order_id'];
                $orderItemsMap[$oid][] = $row;
            }
            $res->free();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Receipts</title>
	<link rel="stylesheet" href="style.css">
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
			<a href="kitchen.php">👨‍🍳 Kitchen</a>
			<a href="payment.php">💳 Payments</a>
			<a href="receipts.php" class="active">📄 Receipts</a>
			<?php if(isset($_SESSION["username"]) && $_SESSION["username"]){ ?>
				<a href="admin_login.php?logout=1" style="margin-top: auto;">🚪 Logout</a>
			<?php } else { ?>
				<a href="admin_login.php">🔐 Login</a>
			<?php } ?>
		</nav>
	</div>

	<div class="top-menu">
		<h1>📄 Receipts</h1>
	</div>

	<div class="container">
		<div class="header">
			<h1>📄 Receipts History</h1>
			<p>View and manage all customer receipts</p>
		</div>

		<div class="card">
  th { font-weight:600; background:#f3f4f6; }
  tbody tr:hover { background:#f9fafb; }
  .toggle-items { background:#ddd; border:0; padding:4px 8px; border-radius:4px; cursor:pointer; }
  .toggle-items:hover { background:#ccc; }
</style>
</head>
<body>
<div class="container">
    <h1>Receipts History</h1>
    <p><a href="admin.php">Back to Admin</a></p>
    
    <form method="get" class="filter-bar">
        <div class="filter-row">
            <div>
                <label for="table_number">Table</label>
                <select name="table_number" id="table_number">
                    <option value="0">All Tables</option>
                    <?php for ($i=1;$i<=20;$i++): ?>
                        <option value="<?php echo $i;?>" <?php echo ($selectedTable===$i)?'selected':''; ?>>Table <?php echo $i;?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="from_date">From Date</label>
                <input type="date" name="from_date" id="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div>
                <label for="to_date">To Date</label>
                <input type="date" name="to_date" id="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
        </div>
        <div class="filter-row">
            <div style="flex:1;">
                <label for="search">Search (Order ID, Summary)</label>
                <input type="text" name="search" id="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div>
                <label for="amount">Amount ($)</label>
                <input type="number" name="amount" id="amount" placeholder="0.00" step="0.01" value="<?php echo $searchAmount > 0 ? htmlspecialchars($searchAmount) : ''; ?>">
            </div>
            <button type="submit">Search</button>
            <a href="receipts.php" style="padding:8px 16px; background:#999; color:#fff; border-radius:4px; text-decoration:none; cursor:pointer; display:inline-block;">Clear</a>
        </div>
    </form>

    <div class="table-wrapper">
    <table>
        <thead>
            <tr><th></th><th>ID</th><th>Table</th><th>Order</th><th>Summary</th><th>Amount</th><th>Date</th></tr>
        </thead>
        <tbody>
            <?php if (!empty($receipts)): foreach ($receipts as $r): $rid = (int)$r['id']; $oid = (int)$r['order_id']; ?>
                <tr>
                    <td><button type="button" class="toggle-items" data-target="items-<?php echo $rid; ?>">+</button></td>
                    <td><?php echo $rid; ?></td>
                    <td><?php echo htmlspecialchars($r['table_number']); ?></td>
                    <td><?php echo $oid ? htmlspecialchars($oid) : '-'; ?></td>
                    <td style="white-space:pre-wrap;max-width:480px"><?php echo htmlspecialchars($r['order_summary']); ?></td>
                    <td>$<?php echo number_format((float)$r['amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                </tr>
                <tr id="items-<?php echo $rid; ?>" class="items-row" style="display:none; background:#fafafa;">
                    <td colspan="7">
                        <?php if ($oid && !empty($orderItemsMap[$oid])): ?>
                            <table style="width:100%; border-collapse:collapse;">
                                <thead><tr><th>Food</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                                <tbody>
                                <?php foreach ($orderItemsMap[$oid] as $it): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($it['food_name']); ?></td>
                                        <td><?php echo (int)$it['quantity']; ?></td>
                                        <td>$<?php echo number_format((float)$it['price'],2); ?></td>
                                        <td>$<?php echo number_format(((float)$it['price'])*(int)$it['quantity'],2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <em>No itemized order data available for this receipt.</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7">No receipts found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <script>
        document.querySelectorAll('.toggle-items').forEach(function(btn){
            btn.addEventListener('click', function(){
                var target = document.getElementById(this.dataset.target);
                if (!target) return;
                if (target.style.display === 'none') { target.style.display = ''; this.textContent = '-'; }
                else { target.style.display = 'none'; this.textContent = '+'; }
            });
        });
    </script>
</div>
</body>
</html>