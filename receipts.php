<?php
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('Location: admin_login.php');
    exit();
}

$searchAmount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
$fromDate = isset($_GET['from_date']) && $_GET['from_date'] ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) && $_GET['to_date'] ? $_GET['to_date'] : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$receipts = [];
$whereClause = '1=1';
$params = [];
$types = '';

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
            <form method="get" class="filter-bar">
                <div class="filter-bar-row1">
                    <div>
                        <label for="from_date">From Date</label>
                        <input type="date" name="from_date" id="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div>
                        <label for="to_date">To Date</label>
                        <input type="date" name="to_date" id="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                </div>
                <div class="filter-bar-row2">
                    <div>
                        <label for="search">Search (Order ID, Summary)</label>
                        <input type="text" name="search" id="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div>
                        <label for="amount">Amount ($)</label>
                        <input type="number" name="amount" id="amount" placeholder="0.00" step="0.01" value="<?php echo $searchAmount > 0 ? htmlspecialchars((string)$searchAmount) : ''; ?>">
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:0.6rem;">
                        <button type="submit">Search</button>
                        <a href="receipts.php" class="button clear-btn" style="padding:0.75rem 1.1rem;">Clear</a>
                    </div>
                </div>
            </form>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th></th><th>ID</th><th>Table</th><th>Order</th><th>Summary</th><th>Amount</th><th>Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($receipts)): foreach ($receipts as $r): $rid = (int)$r['id']; $oid = (int)$r['order_id']; ?>
                            <?php
                                $itemizedLines = [];
                                if ($oid && !empty($orderItemsMap[$oid])) {
                                    foreach ($orderItemsMap[$oid] as $it) {
                                        $qty = (int)$it['quantity'];
                                        $price = (float)$it['price'];
                                        $subtotal = $price * $qty;
                                        $itemizedLines[] = (string)$it['food_name'] . ' x' . $qty . ' @ $' . number_format($price, 2) . ' = $' . number_format($subtotal, 2);
                                    }
                                }
                                $itemizedText = !empty($itemizedLines) ? implode("\n", $itemizedLines) : (string)$r['order_summary'];
                            ?>
                            <tr>
                                <td><button type="button" class="toggle-items" data-target="items-<?php echo $rid; ?>">+</button></td>
                                <td><?php echo $rid; ?></td>
                                <td><?php echo htmlspecialchars((string)$r['table_number']); ?></td>
                                <td><?php echo $oid ? htmlspecialchars((string)$oid) : '-'; ?></td>
                                <td style="white-space:pre-wrap;max-width:480px"><?php echo htmlspecialchars((string)$r['order_summary']); ?></td>
                                <td>$<?php echo number_format((float)$r['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="receipt-print-btn table-print-btn"
                                        data-receipt-id="<?php echo $rid; ?>"
                                        data-table="<?php echo (int)$r['table_number']; ?>"
                                        data-order="<?php echo $oid; ?>"
                                        data-amount="<?php echo number_format((float)$r['amount'], 2, '.', ''); ?>"
                                        data-date="<?php echo htmlspecialchars((string)$r['created_at'], ENT_QUOTES); ?>"
                                        data-summary="<?php echo htmlspecialchars((string)$r['order_summary'], ENT_QUOTES); ?>"
                                        data-itemized="<?php echo htmlspecialchars($itemizedText, ENT_QUOTES); ?>"
                                        onclick="printReceiptFromTable(this)">
                                        Print
                                    </button>
                                </td>
                            </tr>
                            <tr id="items-<?php echo $rid; ?>" class="items-row" style="display:none; background:#fafafa;">
                                <td colspan="8">
                                    <?php if ($oid && !empty($orderItemsMap[$oid])): ?>
                                        <table style="width:100%; border-collapse:collapse;">
                                            <thead><tr><th>Food</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($orderItemsMap[$oid] as $it): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string)$it['food_name']); ?></td>
                                                    <td><?php echo (int)$it['quantity']; ?></td>
                                                    <td>$<?php echo number_format((float)$it['price'], 2); ?></td>
                                                    <td>$<?php echo number_format(((float)$it['price']) * (int)$it['quantity'], 2); ?></td>
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
                            <tr><td colspan="8">No receipts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

            function printReceiptFromTable(button) {
                if (!button) return;
                var receiptId = button.dataset.receiptId || '-';
                var tableNo = button.dataset.table || '-';
                var orderId = button.dataset.order || '-';
                var amount = button.dataset.amount || '0.00';
                var dateText = button.dataset.date || '';
                var summary = button.dataset.summary || 'N/A';
                var itemized = button.dataset.itemized || summary;

                function esc(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                var printWindow = window.open('', '_blank', 'width=760,height=920');
                if (!printWindow) return;

                var html = ''
                    + '<!doctype html><html><head><meta charset="utf-8">'
                    + '<title>Receipt #' + esc(receiptId) + '</title>'
                    + '<style>'
                    + 'body{font-family:Arial,sans-serif;padding:24px;color:#222;}'
                    + '.box{max-width:560px;margin:0 auto;border:1px solid #ddd;border-radius:8px;padding:18px;}'
                    + 'h1{font-size:22px;margin:0 0 8px;}h2{font-size:14px;margin:0 0 18px;color:#666;}'
                    + '.row{display:flex;justify-content:space-between;margin:6px 0;gap:12px;}'
                    + '.label{font-weight:700;color:#444;}.section{margin-top:12px;padding-top:10px;border-top:1px dashed #bbb;}'
                    + '.pre{white-space:pre-wrap;line-height:1.45;}.amount{font-size:24px;font-weight:700;margin-top:12px;text-align:right;}'
                    + '.footer{margin-top:14px;font-size:12px;color:#666;text-align:center;}'
                    + '@media print{body{padding:0}.box{border:0;max-width:none;padding:0}}'
                    + '</style></head><body>'
                    + '<div class="box">'
                    + '<h1>Restaurant Receipt</h1><h2>Receipt #' + esc(receiptId) + '</h2>'
                    + '<div class="row"><span class="label">Table</span><span>#' + esc(tableNo) + '</span></div>'
                    + '<div class="row"><span class="label">Order</span><span>' + (orderId && orderId !== '0' ? ('#' + esc(orderId)) : '-') + '</span></div>'
                    + '<div class="row"><span class="label">Date</span><span>' + esc(dateText) + '</span></div>'
                    + '<div class="section"><div class="label">Summary</div><div class="pre">' + esc(summary) + '</div></div>'
                    + '<div class="section"><div class="label">Itemized</div><div class="pre">' + esc(itemized) + '</div></div>'
                    + '<div class="amount">Total: $' + esc(amount) + '</div>'
                    + '<div class="footer">Thank you for dining with us.</div>'
                    + '</div></body></html>';

                printWindow.document.open();
                printWindow.document.write(html);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
            }
        </script>
    </div>
</body>
</html>