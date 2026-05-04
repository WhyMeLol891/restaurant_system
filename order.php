<?php
session_start();

require_once __DIR__ . '/db.php';


$defaultMenu = [
	1 => ['name' => 'Margherita Pizza', 'price' => 220.00],
	2 => ['name' => 'Chicken Burger', 'price' => 160.00],
	3 => ['name' => 'Pasta Alfredo', 'price' => 190.00],
	4 => ['name' => 'Caesar Salad', 'price' => 140.00],
	5 => ['name' => 'Iced Tea', 'price' => 60.00],
	6 => ['name' => 'Chocolate Cake', 'price' => 110.00],
];

$menu = $defaultMenu;
$dbError = '';
$dbNotice = '';
$pdo = getDbConnection($dbError);

if ($pdo !== null) {
	try {
		$stmt = $pdo->query('SELECT id, name, price FROM menu_items WHERE is_active = 1 ORDER BY id ASC');
		$dbMenuRows = $stmt->fetchAll();

		if (!empty($dbMenuRows)) {
			$menu = [];
			foreach ($dbMenuRows as $row) {
				$menu[(int)$row['id']] = [
					'name' => $row['name'],
					'price' => (float)$row['price'],
				];
			}
		}
	} catch (PDOException $exception) {
		$dbNotice = 'Database connected, but menu table is not ready. Run database.sql to enable full storage.';
	}
} else {
	$dbNotice = 'Database not connected. The app will still work, but orders are session-only until database setup is complete.';
}

if (!isset($_SESSION['cart'])) {
	$_SESSION['cart'] = [];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'add') {
		$itemId = (int)($_POST['item_id'] ?? 0);
		$qty = max(1, (int)($_POST['qty'] ?? 1));

		if (isset($menu[$itemId])) {
			if (!isset($_SESSION['cart'][$itemId])) {
				$_SESSION['cart'][$itemId] = 0;
			}
			$_SESSION['cart'][$itemId] += $qty;
			$message = 'Item added to your order.';
		} else {
			$error = 'Invalid menu item.';
		}
	}

	if ($action === 'update') {
		foreach ($_POST['cart_qty'] ?? [] as $itemId => $qty) {
			$itemId = (int)$itemId;
			$qty = (int)$qty;

			if (!isset($menu[$itemId])) {
				continue;
			}

			if ($qty <= 0) {
				unset($_SESSION['cart'][$itemId]);
			} else {
				$_SESSION['cart'][$itemId] = $qty;
			}
		}
		$message = 'Order updated.';
	}

	if ($action === 'clear') {
		$_SESSION['cart'] = [];
		$message = 'Order cleared.';
	}

	if ($action === 'place_order') {
		$customerName = trim($_POST['customer_name'] ?? '');
		$tableNo = trim($_POST['table_no'] ?? '');
		$notes = trim($_POST['notes'] ?? '');

		if ($customerName === '' || $tableNo === '') {
			$error = 'Please provide customer name and table number.';
		} elseif (empty($_SESSION['cart'])) {
			$error = 'Your order is empty.';
		} else {
			$items = [];
			$grandTotal = 0;

			foreach ($_SESSION['cart'] as $itemId => $qty) {
				if (!isset($menu[$itemId])) {
					continue;
				}
				$lineTotal = $menu[$itemId]['price'] * $qty;
				$grandTotal += $lineTotal;

				$items[] = [
					'item_id' => $itemId,
					'name' => $menu[$itemId]['name'],
					'qty' => $qty,
					'price' => $menu[$itemId]['price'],
					'line_total' => $lineTotal,
				];
			}
			$orderNo = 'ORD-' . date('YmdHis');

			if ($pdo !== null) {
				try {
					$pdo->beginTransaction();

					$orderStmt = $pdo->prepare('INSERT INTO orders (order_no, customer_name, table_no, notes, total) VALUES (:order_no, :customer_name, :table_no, :notes, :total)');
					$orderStmt->execute([
						':order_no' => $orderNo,
						':customer_name' => $customerName,
						':table_no' => $tableNo,
						':notes' => $notes !== '' ? $notes : null,
						':total' => $grandTotal,
					]);

					$orderId = (int)$pdo->lastInsertId();
					$itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, item_name, price, qty, line_total) VALUES (:order_id, :menu_item_id, :item_name, :price, :qty, :line_total)');

					foreach ($items as $item) {
						$itemStmt->execute([
							':order_id' => $orderId,
							':menu_item_id' => $item['item_id'],
							':item_name' => $item['name'],
							':price' => $item['price'],
							':qty' => $item['qty'],
							':line_total' => $item['line_total'],
						]);
					}

					$pdo->commit();
				} catch (Throwable $exception) {
					if ($pdo->inTransaction()) {
						$pdo->rollBack();
					}
					$error = 'Could not save order to database. Check your database setup and try again.';
				}
			}

			if ($error === '') {
				$_SESSION['last_order'] = [
				'order_no' => $orderNo,
				'customer_name' => $customerName,
				'table_no' => $tableNo,
				'notes' => $notes,
				'items' => $items,
				'total' => $grandTotal,
				'created_at' => date('Y-m-d H:i:s'),
				];

				$_SESSION['cart'] = [];
				$message = 'Order placed successfully.';
			}
		}
	}
}

if (isset($_GET['remove'])) {
	$removeId = (int)$_GET['remove'];
	unset($_SESSION['cart'][$removeId]);
	$message = 'Item removed from order.';
}

$cartTotal = 0;
foreach ($_SESSION['cart'] as $itemId => $qty) {
	if (isset($menu[$itemId])) {
		$cartTotal += $menu[$itemId]['price'] * $qty;
	}
}

function h($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Restaurant Order System</title>
	<style>
		body { font-family: Arial, sans-serif; background: #f5f6fa; margin: 0; padding: 20px; }
		.container { max-width: 1000px; margin: 0 auto; }
		.card { background: #fff; border-radius: 8px; padding: 18px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
		h1, h2 { margin-top: 0; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
		.row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
		.msg { padding: 10px; border-radius: 6px; margin-bottom: 12px; }
		.ok { background: #e8f7e8; color: #1f7a1f; }
		.warn { background: #fff3cd; color: #856404; }
		.err { background: #fdeaea; color: #b00020; }
		input, textarea, button { padding: 8px; border-radius: 4px; border: 1px solid #bbb; }
		button { cursor: pointer; border: none; background: #0d6efd; color: #fff; }
		button.secondary { background: #6c757d; }
		.actions { display: flex; gap: 8px; flex-wrap: wrap; }
		.text-right { text-align: right; }
		@media (max-width: 800px) { .row { grid-template-columns: 1fr; } }
	</style>
</head>
<body>
<div class="container">
	<h1>Customer Order System</h1>

	<?php if ($message): ?>
		<div class="msg ok"><?php echo h($message); ?></div>
	<?php endif; ?>

	<?php if ($error): ?>
		<div class="msg err"><?php echo h($error); ?></div>
	<?php endif; ?>

	<?php if ($dbNotice): ?>
		<div class="msg warn"><?php echo h($dbNotice); ?></div>
	<?php endif; ?>

	<div class="row">
		<div class="card">
			<h2>Menu</h2>
			<table>
				<thead>
				<tr>
					<th>Item</th>
					<th>Price</th>
					<th>Qty</th>
					<th>Add</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($menu as $id => $item): ?>
					<tr>
						<td><?php echo h($item['name']); ?></td>
						<td>₱<?php echo number_format($item['price'], 2); ?></td>
						<td>
							<form method="post" class="actions">
								<input type="hidden" name="action" value="add">
								<input type="hidden" name="item_id" value="<?php echo $id; ?>">
								<input type="number" name="qty" min="1" value="1" style="width:70px;">
						</td>
						<td>
								<button type="submit">Add</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="card">
			<h2>Your Order</h2>
			<?php if (empty($_SESSION['cart'])): ?>
				<p>No items in your order yet.</p>
			<?php else: ?>
				<form method="post">
					<input type="hidden" name="action" value="update">
					<table>
						<thead>
						<tr>
							<th>Item</th>
							<th>Price</th>
							<th>Qty</th>
							<th>Total</th>
							<th></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($_SESSION['cart'] as $itemId => $qty): ?>
							<?php if (!isset($menu[$itemId])) continue; ?>
							<?php $lineTotal = $menu[$itemId]['price'] * $qty; ?>
							<tr>
								<td><?php echo h($menu[$itemId]['name']); ?></td>
								<td>₱<?php echo number_format($menu[$itemId]['price'], 2); ?></td>
								<td><input type="number" name="cart_qty[<?php echo $itemId; ?>]" min="0" value="<?php echo (int)$qty; ?>" style="width:70px;"></td>
								<td>₱<?php echo number_format($lineTotal, 2); ?></td>
								<td><a href="?remove=<?php echo $itemId; ?>">Remove</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot>
						<tr>
							<th colspan="3" class="text-right">Grand Total:</th>
							<th>₱<?php echo number_format($cartTotal, 2); ?></th>
							<th></th>
						</tr>
						</tfoot>
					</table>
					<div class="actions" style="margin-top:10px;">
						<button type="submit">Update Order</button>
				</form>
				<form method="post" style="display:inline;">
					<input type="hidden" name="action" value="clear">
					<button type="submit" class="secondary">Clear</button>
				</form>
					</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="card">
		<h2>Customer Details</h2>
		<form method="post">
			<input type="hidden" name="action" value="place_order">
			<div style="display:grid; gap:10px; grid-template-columns:1fr 1fr;">
				<div>
					<label>Customer Name</label><br>
					<input type="text" name="customer_name" required style="width:100%;">
				</div>
				<div>
					<label>Table Number</label><br>
					<input type="text" name="table_no" required style="width:100%;">
				</div>
			</div>
			<div style="margin-top:10px;">
				<label>Notes</label><br>
				<textarea name="notes" rows="3" style="width:100%;"></textarea>
			</div>
			<div style="margin-top:10px;">
				<button type="submit">Place Order</button>
			</div>
		</form>
	</div>

	<?php if (!empty($_SESSION['last_order'])): ?>
		<?php $last = $_SESSION['last_order']; ?>
		<div class="card">
			<h2>Last Placed Order</h2>
			<p><strong>Order No:</strong> <?php echo h($last['order_no']); ?></p>
			<p><strong>Customer:</strong> <?php echo h($last['customer_name']); ?> | <strong>Table:</strong> <?php echo h($last['table_no']); ?></p>
			<p><strong>Time:</strong> <?php echo h($last['created_at']); ?></p>
			<?php if (!empty($last['notes'])): ?><p><strong>Notes:</strong> <?php echo h($last['notes']); ?></p><?php endif; ?>
			<table>
				<thead>
				<tr>
					<th>Item</th>
					<th>Qty</th>
					<th>Price</th>
					<th>Total</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($last['items'] as $it): ?>
					<tr>
						<td><?php echo h($it['name']); ?></td>
						<td><?php echo (int)$it['qty']; ?></td>
						<td>₱<?php echo number_format($it['price'], 2); ?></td>
						<td>₱<?php echo number_format($it['line_total'], 2); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
				<tr>
					<th colspan="3" class="text-right">Total Paid:</th>
					<th>₱<?php echo number_format($last['total'], 2); ?></th>
				</tr>
				</tfoot>
			</table>
		</div>
	<?php endif; ?>
</div>
</body>
</html>
