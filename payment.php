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
			 WHERE o.status IN ('Pending', 'Preparing', 'Completed')
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

		<!-- PAYMENT FORM - SIMPLIFIED AND BEAUTIFUL -->
		<div class="card payment-form-card">
			<h2>💰 Quick Payment</h2>
			<form method="post" action="">
				<div class="payment-form-grid">
					<div class="form-group full-width">
						<label for="order_id">Select Order to Pay</label>
						<select id="order_id" name="order_id" required onchange="document.getElementById('paymentForm').submit();">
							<option value="">-- Choose an order --</option>
							<?php foreach ($activeOrders as $order): ?>
								<option value="<?php echo (int)$order['id']; ?>" <?php echo ((int)$order['id'] === (int)$selectedOrderId) ? 'selected' : ''; ?>>
									Order #<?php echo (int)$order['id']; ?> • Table #<?php echo (int)$order['table_number']; ?> • $<?php echo number_format((float)$order['total_amount'], 2); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php if ($selectedOrder): ?>
						<div class="form-group full-width">
							<label>Order Details</label>
							<div class="order-details-summary">
								<div class="details-header">
									<strong>Table #<?php echo (int)$selectedOrder['table_number']; ?></strong>
									<span class="amount-badge">$<?php echo number_format((float)$selectedOrder['total_amount'], 2); ?></span>
								</div>
								<div class="details-items"><?php 
									$aggregatedSummary = aggregateOrderItems($selectedOrder['order_summary'] ?? '');
									echo htmlspecialchars(implode(', ', $aggregatedSummary) ?: 'N/A');
								?></div>
							</div>
						</div>

						<div class="form-group full-width">
							<label for="payment_method">Payment Method</label>
							<div class="payment-methods">
								<label class="payment-method-option">
									<input type="radio" name="payment_method" value="cash" required onchange="syncPaymentUI()">
									<span class="method-label">💵 Cash</span>
								</label>
								<label class="payment-method-option">
									<input type="radio" name="payment_method" value="card" required onchange="syncPaymentUI()">
									<span class="method-label">💳 Card</span>
								</label>
								<label class="payment-method-option">
									<input type="radio" name="payment_method" value="online" required onchange="syncPaymentUI()">
									<span class="method-label">📱 Online</span>
								</label>
								<label class="payment-method-option">
									<input type="radio" name="payment_method" value="tng_ewallet" required onchange="syncPaymentUI()">
									<span class="method-label">🎫 Touch 'n Go</span>
								</label>
							</div>
						</div>

						<div class="cash-panel-simplified" id="cashPanel" hidden>
							<div class="form-group">
								<label for="cash_received">Amount Received</label>
								<input id="cash_received" name="cash_received" type="number" min="0" step="0.01" placeholder="0.00" oninput="syncCashChange()">
							</div>
							<div class="cash-change-display">
								<div class="change-item">
									<span>Amount Due:</span>
									<strong>$<span id="amountDue"><?php echo number_format((float)$selectedOrder['total_amount'], 2); ?></span></strong>
								</div>
								<div class="change-item highlight">
									<span>Change:</span>
									<strong>$<span id="cashChange">0.00</span></strong>
								</div>
							</div>
						</div>

						<button type="submit" class="btn-pay" name="submit_payment">✓ Confirm Payment</button>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<!-- PENDING ORDERS LIST -->
		<div class="card">
			<h2>📋 Pending Orders</h2>
			<?php if (empty($activeOrders)): ?>
				<p style="color: var(--text-light); text-align: center; padding: 2rem;">No pending orders.</p>
			<?php else: ?>
				<div class="payment-orders-list">
					<?php foreach ($activeOrders as $order): ?>
						<div class="payment-order-item" onclick="document.getElementById('order_id').value = <?php echo (int)$order['id']; ?>; document.getElementById('paymentForm').submit();" style="cursor: pointer;">
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
			<?php endif; ?>
		</div>

		<!-- PAYMENT HISTORY & RECEIPTS -->
		<div class="card">
			<h2>📜 Payment History & Receipts</h2>
			<?php if (empty($paymentHistory)): ?>
				<p style="color: var(--text-light); text-align: center; padding: 2rem;">No completed payments yet.</p>
			<?php else: ?>
				<div class="receipts-list">
					<?php foreach ($paymentHistory as $payment): ?>
						<div class="receipt-card">
							<div class="receipt-header">
								<div class="receipt-info">
									<strong>Table #<?php echo (int)$payment['table_number']; ?></strong>
									<span class="payment-method-badge"><?php 
										$method = $payment['payment_method'];
										$methodEmoji = ['cash' => '💵', 'card' => '💳', 'online' => '📱', 'tng_ewallet' => '🎫'][$method] ?? '💰';
										echo $methodEmoji . ' ' . ucfirst(str_replace('_', ' ', $method));
									?></span>
								</div>
								<strong class="receipt-amount">$<?php echo number_format((float)$payment['amount'], 2); ?></strong>
							</div>
							<div class="receipt-items">
								<?php 
									$aggregatedItems = aggregateOrderItems($payment['order_summary'] ?? '');
									echo htmlspecialchars(implode(', ', $aggregatedItems) ?: 'N/A');
								?>
							</div>
							<div class="receipt-footer">
								<span class="receipt-date"><?php echo date('M d, Y • H:i', strtotime($payment['created_at'])); ?></span>
								<span class="receipt-ref">Ref: <?php echo htmlspecialchars((string)$payment['reference_no']); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<form id="paymentForm" method="post" action="" style="display: none;"></form>

		<div class="qr-modal-backdrop" id="tngQrModal" aria-hidden="true">
			<div class="qr-modal" role="dialog" aria-modal="true" aria-labelledby="tngQrTitle">
				<div id="tngQrTitle" style="font-size: 26px; font-weight: 700; margin-bottom: 8px;">Touch 'n Go eWallet</div>
				<div class="qr-frame">
					<div class="qr-name">DERRICK LIM YEONG WEI</div>
					<?php if (file_exists(__DIR__ . '/' . $tngQrImage)): ?>
						<img class="qr-image" src="<?php echo htmlspecialchars($tngQrImage); ?>" alt="Touch 'n Go eWallet QR code">
					<?php else: ?>
						<div class="order-box" style="margin: 18px 0; background: #fff; white-space: normal;">
							QR image not found. Expected file: <strong>assets/tng-qr.jpeg</strong>
						</div>
					<?php endif; ?>
					<div class="qr-note">Scan with any banking app or eWallet to transfer money or pay.</div>
					<?php if ($selectedOrder): ?>
						<div class="order-box" style="margin-top: 16px; background: #fff; white-space: normal; text-align: left;">
							<strong>Amount to Pay:</strong> $<?php echo number_format((float)$selectedOrder['total_amount'], 2); ?><br>
							<strong>Reference:</strong> Table #<?php echo (int)$selectedOrder['table_number']; ?> - Orders #<?php echo htmlspecialchars($selectedOrder['order_ids']); ?>
						</div>
					<?php endif; ?>
					<button type="button" class="qr-close" id="closeTngQr">Close</button>
				</div>
			</div>
		</div>

		<script>
			function syncPaymentUI() {
				var radios = document.querySelectorAll('input[name="payment_method"]');
				var selectedMethod = '';
				radios.forEach(function(radio) {
					if (radio.checked) selectedMethod = radio.value;
				});

				var cashPanel = document.getElementById('cashPanel');
				var isCash = selectedMethod === 'cash';
				if (cashPanel) cashPanel.hidden = !isCash;
				
				if (isCash) {
					syncCashChange();
				}
			}

			function syncCashChange() {
				var cashReceived = document.getElementById('cash_received');
				var amountDue = document.getElementById('amountDue');
				var cashChange = document.getElementById('cashChange');
				var totalAmount = <?php echo json_encode((float)($selectedOrder['total_amount'] ?? 0)); ?>;

				if (!cashReceived || !cashChange) return;

				var received = parseFloat(cashReceived.value || '0');
				if (isNaN(received) || received < totalAmount) {
					cashChange.textContent = '0.00';
					return;
				}
				cashChange.textContent = (received - totalAmount).toFixed(2);
			}
		</script>
	</div>
</body>
</html>
