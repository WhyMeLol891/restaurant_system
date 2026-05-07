<?php

include 'db.php';

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['selected_table_number'] = (int)($_SESSION['selected_table_number'] ?? 1);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $stmt = $conn->prepare('SELECT id, food_name, image_url, price FROM food WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($item = $res->fetch_assoc()) {
                if (isset($_SESSION['cart'][$itemId])) {
                    $_SESSION['cart'][$itemId]['qty'] += $qty;
                } else {
                    $_SESSION['cart'][$itemId] = ['id' => (int)$item['id'], 'name' => $item['food_name'], 'image_url' => (string)($item['image_url'] ?? ''), 'price' => (float)$item['price'], 'qty' => $qty];
                }
                $message = 'Item added to cart.';
            } else { $error = 'Food item not found.'; }
            $stmt->close();
        } else { $error = 'Failed to prepare add-to-cart query.'; }
    }
    if (isset($_POST['remove_item'])) { unset($_SESSION['cart'][(int)($_POST['item_id'] ?? 0)]); $message = 'Item removed from cart.'; }
    if (isset($_POST['clear_cart'])) { $_SESSION['cart'] = []; $message = 'Cart cleared.'; }
    if (isset($_POST['place_order'])) {
        if (empty($_SESSION['cart'])) { $error = 'Your cart is empty.'; }
        else {
            $tableNumber = max(1, min(20, (int)($_POST['table_number'] ?? $_SESSION['selected_table_number'])));
            $_SESSION['selected_table_number'] = $tableNumber;
            $total = 0.0; foreach ($_SESSION['cart'] as $item) { $total += ((float)$item['price']) * ((int)$item['qty']); }
            $conn->begin_transaction();
            try {
                $status = 'Pending';
                $orderStmt = $conn->prepare('INSERT INTO orders (table_number, status, created_at) VALUES (?, ?, NOW())');
                if (!$orderStmt) { throw new Exception('Failed to prepare order insert.'); }
                $orderStmt->bind_param('is', $tableNumber, $status);
                $orderStmt->execute();
                $orderId = (int)$conn->insert_id;
                $orderStmt->close();
                $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, food_name, image_url, quantity, price) VALUES (?, ?, ?, ?, ?)');
                if (!$itemStmt) { throw new Exception('Failed to prepare order item insert.'); }
                foreach ($_SESSION['cart'] as $item) {
                    $itemName = (string)$item['name']; $itemImageUrl = (string)($item['image_url'] ?? ''); $price = (float)$item['price']; $quantity = (int)$item['qty'];
                    $itemStmt->bind_param('issid', $orderId, $itemName, $itemImageUrl, $quantity, $price);
                    $itemStmt->execute();
                }
                $itemStmt->close();
                $conn->commit();
                $_SESSION['cart'] = [];
                $message = 'Order placed successfully for Table #' . $tableNumber . '. Order ID: #' . $orderId;
            } catch (Throwable $ex) { $conn->rollback(); $error = 'Order failed: ' . $ex->getMessage(); }
        }
    }
}

$menuItems = [];
$result = $conn->query('SELECT id, description, image_url, food_name AS name, price FROM food ORDER BY food_name ASC');
if ($result) { while ($row = $result->fetch_assoc()) { $menuItems[] = $row; } }
$cartTotal = 0.0; foreach ($_SESSION['cart'] as $item) { $cartTotal += ((float)$item['price']) * ((int)$item['qty']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restaurant Order System</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;color:#111827}.container{max-width:1100px;margin:0 auto;padding:24px}.grid{display:grid;grid-template-columns:2fr 1fr;gap:20px}.card{background:#fff;border-radius:10px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,.08)}.item{border-bottom:1px solid #e5e7eb;padding:12px 0}.item:last-child{border-bottom:0}.row{display:flex;justify-content:space-between;gap:12px;align-items:center}.muted{color:#6b7280;font-size:14px}input,textarea,button,select,.btn-link{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box}.btn-link{text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}button{background:#2563eb;color:#fff;border:0;cursor:pointer}.secondary{background:#6b7280}.danger{background:#dc2626}.pay-btn{background:linear-gradient(135deg,#0f766e,#2563eb);box-shadow:0 10px 20px rgba(37,99,235,.18);font-weight:700;font-size:16px;letter-spacing:.02em;transition:transform .15s ease,box-shadow .15s ease,filter .15s ease}.pay-btn:hover{transform:translateY(-1px);box-shadow:0 14px 24px rgba(37,99,235,.24);filter:saturate(1.05)}.pay-btn:active{transform:translateY(0)}.payment-actions{display:grid;gap:10px;margin-bottom:12px}.payment-link{background:#fff;color:#0f766e;border:1px solid #99f6e4;font-weight:700}.msg{padding:12px;border-radius:8px;margin-bottom:16px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.actions{display:flex;gap:8px}.actions form{width:auto}@media (max-width:900px){.grid{grid-template-columns:1fr}}
.food-thumb{width:70px;height:70px;object-fit:cover;border-radius:10px;margin-right:10px;border:1px solid #e5e7eb;background:#fff}
</style>
</head>
<body>
<div class="container">
    <h1>Food Ordering System</h1>
    <?php if ($message): ?><div class="msg success"><?php echo e($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?php echo e($error); ?></div><?php endif; ?>
    <div class="grid">
        <div class="card"><h2>Menu</h2>
            <?php if (!$menuItems): ?><p class="muted">No menu items found. Create a <code>menu_items</code> table with columns: <code>id, name, description, price</code>.</p><?php endif; ?>
            <?php foreach ($menuItems as $item): ?>
                <div class="item"><div class="row"><div style="display:flex;align-items:center;"><?php if (!empty($item['image_url'])): ?><img src="<?php echo e($item['image_url']); ?>" alt="<?php echo e($item['name']); ?>" class="food-thumb"><?php endif; ?><div><strong><?php echo e($item['name']); ?></strong><br><span class="muted"><?php echo e($item['description'] ?? ''); ?></span><br><strong>$<?php echo number_format((float)$item['price'], 2); ?></strong></div></div><form method="post" class="actions" style="min-width:220px;"><input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>"><input type="number" name="qty" value="1" min="1" style="max-width:80px"><button type="submit" name="add_to_cart">Add</button></form></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card"><h2>Cart</h2>
            <?php if (empty($_SESSION['cart'])): ?><p class="muted">Your cart is empty.</p>
            <?php else: foreach ($_SESSION['cart'] as $item): ?>
                <div class="item"><div class="row"><div><strong><?php echo e($item['name']); ?></strong><br><span class="muted"><?php echo (int)$item['qty']; ?> x $<?php echo number_format((float)$item['price'], 2); ?></span></div><div>$<?php echo number_format(((float)$item['price']) * ((int)$item['qty']), 2); ?></div></div><form method="post" style="margin-top:8px;"><input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>"><button type="submit" name="remove_item" class="danger">Remove</button></form></div>
            <?php endforeach; ?>
            <h3>Total: $<?php echo number_format($cartTotal, 2); ?></h3><form method="post" style="margin-bottom:12px;"><button type="submit" name="clear_cart" class="secondary">Clear Cart</button></form><form method="post" style="margin-bottom:12px;"><label for="table_number" class="muted" style="display:block;margin-bottom:6px;">Table Number</label><select name="table_number" id="table_number" style="margin-bottom:12px;">
                    <?php for ($tableNumber = 1; $tableNumber <= 20; $tableNumber++): ?>
                        <option value="<?php echo $tableNumber; ?>" <?php echo ((int)$_SESSION['selected_table_number'] === $tableNumber) ? 'selected' : ''; ?>>Table <?php echo $tableNumber; ?></option>
                    <?php endfor; ?>
                </select><div class="payment-actions"><button type="submit" name="place_order" class="pay-btn">Proceed to Payment</button><a class="btn-link payment-link" href="payment.php?table_number=<?php echo (int)$_SESSION['selected_table_number']; ?>">Open Payment Screen</a></div></form><?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>