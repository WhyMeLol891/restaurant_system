<?php
declare(strict_types=1);

include 'db.php';

$message = '';
$error = '';
$totalRevenue = 0.00;
	$tngQrImage = 'assets/tng-qr.jpeg';
	$activeOrders = [];
	$selectedTableNumber = max(1, min(20, (int)($_GET['table_number'] ?? $_POST['table_number'] ?? 1)));
	$selectedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	$conn->query("INSERT IGNORE INTO revenue_summary (id, total_revenue) VALUES (1, 0.00)");

	function fetch_order_details(mysqli $conn, int $orderId, int $tableNumber)
	{
		$stmt = $conn->prepare(
			"SELECT
				o.id,
				o.table_number,
				o.status,
				COALESCE(GROUP_CONCAT(CONCAT(oi.food_name, ' x', oi.quantity, ' @ $', FORMAT(oi.price, 2)) SEPARATOR '\n'), '') AS order_summary,
				COALESCE(SUM(oi.quantity * oi.price), 0) AS total_amount
			 FROM orders o
			 LEFT JOIN order_items oi ON oi.order_id = o.id
			 WHERE o.id = ? AND o.table_number = ?
			 GROUP BY o.id, o.table_number, o.status
			 LIMIT 1"
		);
		$stmt->bind_param('ii', $orderId, $tableNumber);
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
			 WHERE o.table_number = ? AND o.status IN ('Pending', 'Preparing')
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

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$paymentMethod = trim($_POST['payment_method'] ?? '');
		$selectedTableNumber = max(1, min(20, (int)($_POST['table_number'] ?? 0)));
		$selectedOrderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

		if ($selectedTableNumber < 1 || $selectedTableNumber > 20) {
			throw new RuntimeException('Please select a table number from 1 to 20.');
		}

		if ($paymentMethod === '') {
			throw new RuntimeException('Please choose a payment method.');
		}

		if ($selectedOrderId <= 0) {
			throw new RuntimeException('Please select an order for the chosen table.');
		}

		$order = fetch_order_details($conn, $selectedOrderId, $selectedTableNumber);
		if (!$order) {
			throw new RuntimeException('No matching order was found for that table.');
		}

		$amount = (float)$order['total_amount'];
		if ($amount <= 0) {
			throw new RuntimeException('The selected order does not have a valid amount.');
		}

		$conn->begin_transaction();

		$stmt = $conn->prepare(
			"INSERT INTO payments (customer_name, table_number, order_id, order_summary, payment_method, reference_no, amount, status)
			 VALUES (?, ?, ?, ?, ?, ?, ?, 'successful')"
		);
		$customerName = 'Table #' . $selectedTableNumber;
		$referenceNo = 'Table #' . $selectedTableNumber . ' - Order #' . $selectedOrderId;
		$orderSummary = trim((string)$order['order_summary']);
		$stmt->bind_param('siisssd', $customerName, $selectedTableNumber, $selectedOrderId, $orderSummary, $paymentMethod, $referenceNo, $amount);
		$stmt->execute();
		$stmt->close();

		$stmt = $conn->prepare("UPDATE revenue_summary SET total_revenue = total_revenue + ? WHERE id = 1");
		$stmt->bind_param('d', $amount);
		$stmt->execute();
		$stmt->close();

		$stmt = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?");
		$stmt->bind_param('i', $selectedOrderId);
		$stmt->execute();
		$stmt->close();

		$conn->commit();
		$message = 'Payment recorded successfully for Table #' . $selectedTableNumber . '.';
	}

	$activeOrders = fetch_active_orders_for_table($conn, $selectedTableNumber);
	if ($selectedOrderId <= 0 && !empty($activeOrders)) {
		$selectedOrderId = (int)$activeOrders[0]['id'];
	}

	$selectedOrder = null;
	if ($selectedOrderId > 0) {
		$selectedOrder = fetch_order_details($conn, $selectedOrderId, $selectedTableNumber);
	}

	$result = $conn->query("SELECT total_revenue FROM revenue_summary WHERE id = 1 LIMIT 1");
	if ($row = $result->fetch_assoc()) {
		$totalRevenue = (float)$row['total_revenue'];
	}
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Payment System</title>
	<style>
		body { font-family: Arial, sans-serif; background: #f4f7fb; margin: 0; padding: 0; }
		.container { max-width: 720px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
		h1 { margin-top: 0; }
		.summary { background: #eef7ff; padding: 16px; border-radius: 10px; margin-bottom: 20px; }
		.order-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 10px; margin-top: 14px; white-space: pre-wrap; }
		.qr-trigger { background: #eaf1ff; border-radius: 20px; padding: 18px; margin-top: 18px; }
		.qr-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); align-items: center; justify-content: center; padding: 24px; z-index: 1000; }
		.qr-modal-backdrop.is-open { display: flex; }
		.qr-modal { width: min(520px, 100%); background: #eaf1ff; border-radius: 24px; padding: 26px 20px; text-align: center; box-shadow: 0 18px 60px rgba(15, 23, 42, 0.35); }
		.qr-frame { background: #fff; border: 3px solid #2f77f5; border-radius: 20px; padding: 20px; max-width: 420px; margin: 18px auto 0; }
		.qr-image { width: 100%; max-width: 320px; height: auto; display: block; margin: 0 auto; }
		.qr-name { font-size: 22px; font-weight: 700; letter-spacing: 0.02em; margin: 14px 0 6px; }
		.qr-note { color: #666; margin-top: 18px; line-height: 1.5; }
		.qr-close { background: #0f172a; margin-top: 16px; }
		.qr-close:hover { background: #1e293b; }
		.msg { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
		.success { background: #e8fff1; color: #136f3a; }
		.error { background: #ffecec; color: #9c1c1c; }
		label { display: block; margin: 12px 0 6px; font-weight: 600; }
		input, select { width: 100%; padding: 12px; border: 1px solid #ccd6e0; border-radius: 8px; box-sizing: border-box; }
		button { margin-top: 18px; background: #2563eb; color: #fff; border: 0; padding: 12px 16px; border-radius: 8px; cursor: pointer; }
		button:hover { background: #1d4ed8; }
	</style>
</head>
<body>
	<div class="container">
		<h1>Payment System</h1>

		<div class="summary">
			<strong>Total Revenue:</strong> <?php echo number_format($totalRevenue, 2); ?>
		</div>

		<?php if ($message !== ''): ?>
			<div class="msg success"><?php echo htmlspecialchars($message); ?></div>
		<?php endif; ?>

		<?php if ($error !== ''): ?>
			<div class="msg error"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>

		<form method="get" action="" style="margin-bottom: 18px;">
			<label for="table_number">Table Number</label>
			<select id="table_number" name="table_number" onchange="this.form.submit()">
				<?php for ($tableNumber = 1; $tableNumber <= 20; $tableNumber++): ?>
					<option value="<?php echo $tableNumber; ?>" <?php echo ($selectedTableNumber === $tableNumber) ? 'selected' : ''; ?>>Table <?php echo $tableNumber; ?></option>
				<?php endfor; ?>
			</select>
		</form>

		<form method="post" action="">
			<input type="hidden" name="table_number" value="<?php echo $selectedTableNumber; ?>">

			<label for="order_id">Active Orders for Table #<?php echo $selectedTableNumber; ?></label>
			<select id="order_id" name="order_id" required>
				<option value="">Select order</option>
				<?php foreach ($activeOrders as $order): ?>
					<option value="<?php echo (int)$order['id']; ?>" <?php echo ((int)$order['id'] === (int)$selectedOrderId) ? 'selected' : ''; ?>>Order #<?php echo (int)$order['id']; ?> - $<?php echo number_format((float)$order['total_amount'], 2); ?></option>
				<?php endforeach; ?>
			</select>

			<?php if ($selectedOrder): ?>
				<div class="order-box"><strong>Order Record</strong><br>Order #<?php echo (int)$selectedOrder['id']; ?> | Table #<?php echo (int)$selectedOrder['table_number']; ?><br><br><?php echo htmlspecialchars((string)$selectedOrder['order_summary']); ?><br><br><strong>Total Price:</strong> $<?php echo number_format((float)$selectedOrder['total_amount'], 2); ?></div>
			<?php else: ?>
				<div class="order-box">No active order found for this table.</div>
			<?php endif; ?>

			<label for="payment_method">Payment Method *</label>
			<select id="payment_method" name="payment_method" required>
				<option value="">Select method</option>
				<option value="cash">Cash</option>
				<option value="card">Card</option>
				<option value="online">Online</option>
				<option value="tng_ewallet">Touch 'n Go eWallet</option>
			</select>

			<div class="qr-trigger" id="tngHint" hidden>
				<strong>Touch 'n Go eWallet selected.</strong> The QR code will pop out automatically.
			</div>

			<button type="submit">Submit Successful Payment</button>
            <a href=order.php>Return to Order Page</a>
		</form>

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
							<strong>Reference:</strong> Table #<?php echo (int)$selectedOrder['table_number']; ?> - Order #<?php echo (int)$selectedOrder['id']; ?>
						</div>
					<?php endif; ?>
					<button type="button" class="qr-close" id="closeTngQr">Close</button>
				</div>
			</div>
		</div>

		<script>
			(function () {
				var paymentMethod = document.getElementById('payment_method');
				var modal = document.getElementById('tngQrModal');
				var closeButton = document.getElementById('closeTngQr');
				var hint = document.getElementById('tngHint');

				function openModal() {
					modal.classList.add('is-open');
					modal.setAttribute('aria-hidden', 'false');
				}

				function closeModal() {
					modal.classList.remove('is-open');
					modal.setAttribute('aria-hidden', 'true');
				}

				function syncPaymentUI() {
					var selected = paymentMethod.value === 'tng_ewallet';
					hint.hidden = !selected;
					if (selected) {
						openModal();
					} else {
						closeModal();
					}
				}

				paymentMethod.addEventListener('change', syncPaymentUI);
				closeButton.addEventListener('click', closeModal);
				modal.addEventListener('click', function (event) {
					if (event.target === modal) closeModal();
				});
				document.addEventListener('keydown', function (event) {
					if (event.key === 'Escape') closeModal();
				});
				syncPaymentUI();
			})();
		</script>
	</div>
</body>
</html>
