<?php
declare(strict_types=1);

include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('Location: admin_login.php');
    exit();
}

// Combine same food items into aggregated list with summed quantities
function aggregateOrderItems($itemString) {
    if (empty($itemString)) return [];
    // Split by either ', ' (comma space) or '\n' (newline)
    $delimiter = strpos($itemString, '\n') !== false ? '\n' : ', ';
    $items = explode($delimiter, $itemString);
    $aggregated = [];
    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) continue;
        // Parse "Food Name x Qty" format or "Food Name x Qty @ $Price"
        if (preg_match('/^(.+?)\s+x(\d+)(?:\s+@\s+\$[\d.]+)?$/', $item, $matches)) {
            $foodName = trim($matches[1]);
            $qty = (int)$matches[2];
            if (!isset($aggregated[$foodName])) {
                $aggregated[$foodName] = 0;
            }
            $aggregated[$foodName] += $qty;
        }
    }
    // Format back to display string
    $formatted = [];
    foreach ($aggregated as $foodName => $qty) {
        $formatted[] = "$foodName x$qty";
    }
    return $formatted;
}

$message = '';
$error = '';
$totalRevenue = 0.00;
	$tngQrImage = 'assets/tng-qr.jpeg';
	$activeOrders = [];
	$selectedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	$conn->query("INSERT IGNORE INTO revenue_summary (id, total_revenue) VALUES (1, 0.00)");

	function fetch_order_table_number(mysqli $conn, int $orderId)
	{
		$stmt = $conn->prepare("SELECT table_number FROM orders WHERE id = ? LIMIT 1");
		$stmt->bind_param('i', $orderId);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();
		return $row ? (int)$row['table_number'] : 0;
	}

	function fetch_combined_orders_for_table(mysqli $conn, int $tableNumber)
	{
		$stmt = $conn->prepare(
			"SELECT
				GROUP_CONCAT(DISTINCT o.id) AS order_ids,
				o.table_number,
				COALESCE(GROUP_CONCAT(CONCAT(oi.food_name, ' x', oi.quantity, ' @ $', FORMAT(oi.price, 2)) SEPARATOR '\n'), '') AS order_summary,
				COALESCE(SUM(oi.quantity * oi.price), 0) AS total_amount
			 FROM orders o
			 LEFT JOIN order_items oi ON oi.order_id = o.id
			 WHERE o.table_number = ? AND o.status IN ('Pending', 'Preparing', 'Completed')
			 GROUP BY o.table_number
			 LIMIT 1"
		);
		$stmt->bind_param('i', $tableNumber);
		$stmt->execute();
		$result = $stmt->get_result();
		$order = $result ? $result->fetch_assoc() : null;
		$stmt->close();
		return $order;
	}

	function fetch_active_orders_for_table(mysqli $conn, int $tableNumber)
	{
		$orders = array();
		$stmt = $conn->prepare(
			"SELECT
				o.id,
				o.table_number,
				o.status,
				COALESCE(GROUP_CONCAT(CONCAT(oi.food_name, ' x', oi.quantity) SEPARATOR '\n'), '') AS order_items,
				COALESCE(SUM(oi.quantity * oi.price), 0) AS total_amount
			 FROM orders o
			 LEFT JOIN order_items oi ON oi.order_id = o.id
			 WHERE o.table_number = ? AND o.status IN ('Pending', 'Preparing', 'Completed')
			 GROUP BY o.id, o.table_number, o.status
			 ORDER BY o.id DESC"
		);
		$stmt->bind_param('i', $tableNumber);
		$stmt->execute();
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$orders[] = $row;
		}
		$stmt->close();
		return $orders;
	}

	function fetch_active_orders_for_payment(mysqli $conn)
	{
		$orders = array();
		$result = $conn->query(
			"SELECT o.id, o.table_number, o.status,
				COALESCE(GROUP_CONCAT(CONCAT(oi.food_name, ' x', oi.quantity) SEPARATOR ', '), '') AS order_items,
				COALESCE(SUM(oi.quantity * oi.price), 0) AS total_amount
			 FROM orders o
			 LEFT JOIN order_items oi ON oi.order_id = o.id
			 WHERE o.status IN ('Pending', 'Preparing')
			 GROUP BY o.id, o.table_number, o.status
			 ORDER BY o.id DESC"
		);
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$orders[] = $row;
			}
		}
		return $orders;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		try {
			$paymentMethod = trim($_POST['payment_method'] ?? '');
			$selectedOrderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
			$cashReceived = (float)($_POST['cash_received'] ?? 0);

			if ($selectedOrderId <= 0) {
				throw new RuntimeException('Please select an order.');
			}

			if ($paymentMethod === '') {
				throw new RuntimeException('Please choose a payment method.');
			}

			$selectedTableNumber = fetch_order_table_number($conn, $selectedOrderId);
			if ($selectedTableNumber <= 0) {
				throw new RuntimeException('Unable to determine the table for the selected order.');
			}

			$combinedOrder = fetch_combined_orders_for_table($conn, $selectedTableNumber);
			if (!$combinedOrder) {
				throw new RuntimeException('No matching orders were found for that table.');
			}

			$amount = (float)$combinedOrder['total_amount'];
			if ($amount <= 0) {
				throw new RuntimeException('The selected order does not have a valid amount.');
			}

			$change = 0.0;
			if ($paymentMethod === 'cash') {
				if ($cashReceived <= 0) {
					throw new RuntimeException('Please enter the cash amount received.');
				}
				if ($cashReceived < $amount) {
					throw new RuntimeException('Cash received is not enough to cover the bill.');
				}
				$change = round($cashReceived - $amount, 2);
			}

			$conn->begin_transaction();

			// Get the list of order IDs to update
			$orderIds = explode(',', $combinedOrder['order_ids']);
			$primaryOrderId = (int)$orderIds[0];

			$stmt = $conn->prepare(
				"INSERT INTO payments (customer_name, table_number, order_id, order_summary, payment_method, reference_no, amount, status)
				 VALUES (?, ?, ?, ?, ?, ?, ?, 'successful')"
			);
			$customerName = 'Table #' . $selectedTableNumber;
			$referenceNo = 'Table #' . $selectedTableNumber . ' - Orders #' . implode(', #', $orderIds);
			$orderSummary = trim((string)$combinedOrder['order_summary']);
			$stmt->bind_param('siisssd', $customerName, $selectedTableNumber, $primaryOrderId, $orderSummary, $paymentMethod, $referenceNo, $amount);
			$stmt->execute();
			$stmt->close();

			$stmt = $conn->prepare("UPDATE revenue_summary SET total_revenue = total_revenue + ? WHERE id = 1");
			$stmt->bind_param('d', $amount);
			$stmt->execute();
			$stmt->close();

			// Update all orders to Completed
			foreach ($orderIds as $orderId) {
				$orderId = (int)$orderId;
				$stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?");
				$stmt->bind_param('i', $orderId);
				$stmt->execute();
				$stmt->close();
			}

			// Insert a receipt record so completed payments have a receipt history
			$rstmt = $conn->prepare("INSERT INTO receipts (table_number, order_id, order_summary, amount) VALUES (?, ?, ?, ?)");
			if ($rstmt) {
				$rstmt->bind_param('iisd', $selectedTableNumber, $primaryOrderId, $orderSummary, $amount);
				$rstmt->execute();
				$rstmt->close();
			}

			$conn->commit();
			$message = 'Payment recorded successfully for Table #' . $selectedTableNumber . ' (Orders: ' . implode(', ', $orderIds) . ').';
			if ($paymentMethod === 'cash') {
				$message .= ' Change due: $' . number_format($change, 2) . '.';
			}
		} catch (Throwable $ex) {
			$error = 'Payment error: ' . $ex->getMessage();
		}
	}

	$activeOrders = fetch_active_orders_for_payment($conn);
	if ($selectedOrderId <= 0 && !empty($activeOrders)) {
		$selectedOrderId = (int)$activeOrders[0]['id'];
	}
	$selectedTableNumber = $selectedOrderId > 0 ? fetch_order_table_number($conn, $selectedOrderId) : 0;
	$selectedOrder = $selectedTableNumber > 0 ? fetch_combined_orders_for_table($conn, $selectedTableNumber) : null;

	$result = $conn->query("SELECT total_revenue FROM revenue_summary WHERE id = 1 LIMIT 1");
	if ($row = $result->fetch_assoc()) {
		$totalRevenue = (float)$row['total_revenue'];
	}
	
	// Fetch recent payment history with receipts
	$paymentHistory = [];
	$paymentStmt = $conn->prepare("
		SELECT p.id, p.customer_name, p.table_number, p.order_id, p.order_summary, p.payment_method, 
		       p.amount, p.reference_no, COALESCE(r.created_at, NOW()) as created_at, r.id as receipt_id
		FROM payments p
		LEFT JOIN receipts r ON p.table_number = r.table_number AND p.order_id = r.order_id
		WHERE p.status = 'successful'
		ORDER BY p.id DESC
		LIMIT 15
	");
	if ($paymentStmt) {
		$paymentStmt->execute();
		$paymentResult = $paymentStmt->get_result();
		while ($row = $paymentResult->fetch_assoc()) {
			$paymentHistory[] = $row;
		}
		$paymentStmt->close();
	}
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Payment System</title>
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
			<a href="kitchen.php">👨‍🍳 Kitchen</a>
			<a href="payment.php" class="active">💳 Payments</a>
			<a href="receipts.php">📄 Receipts</a>
			<?php if(isset($_SESSION["username"]) && $_SESSION["username"]){ ?>
				<a href="admin_login.php?logout=1" style="margin-top: auto;">🚪 Logout</a>
			<?php } else { ?>
				<a href="admin_login.php">🔐 Login</a>
			<?php } ?>
		</nav>
	</div>

	<div class="top-menu">
		<h1>💳 Payment System</h1>
	</div>

	<div class="container">
		<div class="header">
			<h1>💳 Payment System</h1>
			<p>Process payments and manage receipts</p>
		</div>

		<div class="card">
			<div class="stats">
				<div class="stat">
					<span class="label">Total Revenue</span>
					<div class="value">$<?php echo number_format($totalRevenue, 2); ?></div>
				</div>
			</div>
		</div>

		<?php if ($message !== ''): ?>
			<div class="msg success"><?php echo htmlspecialchars($message); ?></div>
		<?php endif; ?>

		<?php if ($error !== ''): ?>
			<div class="msg error"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>

		<!-- PENDING ORDERS LIST -->
		<div class="card">
			<h2>📋 Pending Orders</h2>
			<?php if (empty($activeOrders)): ?>
				<p style="color: var(--text-light); text-align: center; padding: 2rem;">No pending orders.</p>
			<?php else: ?>
				<div class="payment-orders-scrollbox">
					<div class="payment-orders-list">
					<?php foreach ($activeOrders as $order): ?>
						<div class="payment-order-item">
							<div class="payment-order-head">
								<strong>Order #<?php echo (int)$order['id']; ?></strong>
								<span>Table #<?php echo (int)$order['table_number']; ?></span>
							</div>
							<div class="payment-order-items"><?php 
								$aggregatedItems = aggregateOrderItems($order['order_items'] ?? '');
								echo htmlspecialchars(implode(', ', $aggregatedItems) ?: 'N/A');
							?></div>
							<div class="payment-order-meta">
								<span><?php echo htmlspecialchars((string)$order['status']); ?></span>
								<strong>$<?php echo number_format((float)$order['total_amount'], 2); ?></strong>
							</div>
						</div>
					<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- PAYMENT HISTORY & RECEIPTS -->
		<div class="card">
			<h2>📜 Payment History & Receipts</h2>
			<?php if (empty($paymentHistory)): ?>
				<p style="color: var(--text-light); text-align: center; padding: 2rem;">No completed payments yet.</p>
			<?php else: ?>
				<div class="payment-history-scrollbox">
					<div class="receipts-list">
					<?php foreach ($paymentHistory as $payment): ?>
						<?php
							$method = $payment['payment_method'];
							$methodEmoji = ['cash' => '💵', 'card' => '💳', 'online' => '📱', 'tng_ewallet' => '🎫'][$method] ?? '💰';
							$methodName = [
								'cash' => 'Cash',
								'card' => 'Card',
								'online' => 'Online',
								'tng_ewallet' => 'TNG e Wallet'
							][$method] ?? ucfirst(str_replace('_', ' ', $method));
							$aggregatedItems = aggregateOrderItems($payment['order_summary'] ?? '');
							$itemsText = implode(', ', $aggregatedItems) ?: 'N/A';
						?>
						<div class="receipt-card">
							<div class="receipt-header">
								<div class="receipt-info">
									<strong>Table #<?php echo (int)$payment['table_number']; ?></strong>
									<span class="payment-method-badge"><?php 
										echo $methodEmoji . ' ' . $methodName;
									?></span>
								</div>
								<strong class="receipt-amount">$<?php echo number_format((float)$payment['amount'], 2); ?></strong>
							</div>
							<div class="receipt-items">
								<?php 
									echo htmlspecialchars($itemsText);
								?>
							</div>
							<div class="receipt-footer">
								<span class="receipt-date"><?php echo date('M d, Y • H:i', strtotime($payment['created_at'])); ?></span>
								<div class="receipt-footer-actions">
									<span class="receipt-ref">Ref: <?php echo htmlspecialchars((string)$payment['reference_no']); ?></span>
									<button
										type="button"
										class="receipt-print-btn"
										data-receipt-id="<?php echo (int)$payment['id']; ?>"
										data-table="<?php echo (int)$payment['table_number']; ?>"
										data-order="<?php echo (int)$payment['order_id']; ?>"
										data-method="<?php echo htmlspecialchars($methodName, ENT_QUOTES); ?>"
										data-amount="<?php echo number_format((float)$payment['amount'], 2, '.', ''); ?>"
										data-date="<?php echo htmlspecialchars(date('M d, Y H:i:s', strtotime($payment['created_at'])), ENT_QUOTES); ?>"
										data-reference="<?php echo htmlspecialchars((string)$payment['reference_no'], ENT_QUOTES); ?>"
										data-items="<?php echo htmlspecialchars($itemsText, ENT_QUOTES); ?>"
										onclick="printPaymentReceipt(this)">
										Print
									</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<script>
			function printPaymentReceipt(button) {
				if (!button) return;
				var receiptId = button.dataset.receiptId || '-';
				var tableNo = button.dataset.table || '-';
				var orderId = button.dataset.order || '-';
				var method = button.dataset.method || '-';
				var amount = button.dataset.amount || '0.00';
				var dateText = button.dataset.date || '';
				var reference = button.dataset.reference || '-';
				var items = button.dataset.items || 'N/A';

				function esc(value) {
					return String(value)
						.replace(/&/g, '&amp;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;')
						.replace(/"/g, '&quot;')
						.replace(/'/g, '&#39;');
				}

				var printWindow = window.open('', '_blank', 'width=720,height=900');
				if (!printWindow) return;

				var html = ''
					+ '<!doctype html><html><head><meta charset="utf-8">'
					+ '<title>Receipt #' + esc(receiptId) + '</title>'
					+ '<style>'
					+ 'body{font-family:Arial,sans-serif;padding:24px;color:#222;}'
					+ '.box{max-width:520px;margin:0 auto;border:1px solid #ddd;border-radius:8px;padding:18px;}'
					+ 'h1{font-size:22px;margin:0 0 8px;}h2{font-size:14px;margin:0 0 18px;color:#666;}'
					+ '.row{display:flex;justify-content:space-between;margin:6px 0;gap:12px;}'
					+ '.label{font-weight:700;color:#444;}.items{margin-top:14px;padding-top:10px;border-top:1px dashed #bbb;}'
					+ '.amount{font-size:24px;font-weight:700;margin-top:12px;text-align:right;}'
					+ '.footer{margin-top:14px;font-size:12px;color:#666;text-align:center;}'
					+ '@media print{body{padding:0}.box{border:0;max-width:none;padding:0}}'
					+ '</style></head><body>'
					+ '<div class="box">'
					+ '<h1>Restaurant Receipt</h1><h2>Payment Receipt #' + esc(receiptId) + '</h2>'
					+ '<div class="row"><span class="label">Table</span><span>#' + esc(tableNo) + '</span></div>'
					+ '<div class="row"><span class="label">Order</span><span>#' + esc(orderId) + '</span></div>'
					+ '<div class="row"><span class="label">Payment Method</span><span>' + esc(method) + '</span></div>'
					+ '<div class="row"><span class="label">Date</span><span>' + esc(dateText) + '</span></div>'
					+ '<div class="row"><span class="label">Reference</span><span>' + esc(reference) + '</span></div>'
					+ '<div class="items"><div class="label">Items</div><div>' + esc(items) + '</div></div>'
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
