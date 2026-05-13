<?php

include 'db.php';

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

// Combine same food items into aggregated list with summed quantities
function aggregateOrderItems($itemString) {
    if (empty($itemString)) return [];
    $items = explode(', ', $itemString);
    $aggregated = [];
    foreach ($items as $item) {
        // Parse "Food Name x Qty" format
        if (preg_match('/^(.+)\s+x(\d+)$/', trim($item), $matches)) {
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

$_SESSION['cart'] = $_SESSION['cart'] ?? [];
$_SESSION['selected_table_number'] = (int)($_SESSION['selected_table_number'] ?? 1);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $stmt = $conn->prepare('SELECT id, food_name, image_url, price, stock FROM food WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($item = $res->fetch_assoc()) {
                $availableStock = max(0, (int)($item['stock'] ?? 0));
                $existingQty = (int)($_SESSION['cart'][$itemId]['qty'] ?? 0);
                $newQty = $existingQty + $qty;
                if ($availableStock <= 0) {
                    $error = 'This food item is out of stock.';
                } elseif ($newQty > $availableStock) {
                    $error = 'Only ' . $availableStock . ' of this food item are available.';
                } else {
                    if (isset($_SESSION['cart'][$itemId])) {
                        $_SESSION['cart'][$itemId]['qty'] = $newQty;
                    } else {
                        $_SESSION['cart'][$itemId] = ['id' => (int)$item['id'], 'name' => $item['food_name'], 'image_url' => (string)($item['image_url'] ?? ''), 'price' => (float)$item['price'], 'qty' => $qty];
                    }
                    $message = 'Item added to cart.';
                }
            } else { $error = 'Food item not found.'; }
            $stmt->close();
        } else { $error = 'Failed to prepare add-to-cart query.'; }
    }
    if (isset($_POST['remove_item'])) { unset($_SESSION['cart'][(int)($_POST['item_id'] ?? 0)]); $message = 'Item removed from cart.'; }
    if (isset($_POST['clear_cart'])) { $_SESSION['cart'] = []; $message = 'Cart cleared.'; }
    if (isset($_POST['place_order'])) {
        if (empty($_SESSION['cart'])) { $error = 'Your cart is empty.'; }
        else {
            // Pre-check stock for each cart item to avoid starting a transaction that will fail
            foreach ($_SESSION['cart'] as $cartItem) {
                $checkId = (int)$cartItem['id'];
                $checkQty = (int)$cartItem['qty'];
                $sstmt = $conn->prepare('SELECT stock FROM food WHERE id = ? LIMIT 1');
                if ($sstmt) {
                    $sstmt->bind_param('i', $checkId);
                    $sstmt->execute();
                    $res = $sstmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $sstmt->close();
                    $available = $row ? (int)($row['stock'] ?? 0) : 0;
                    if ($checkQty > $available) {
                        $error = 'Not enough stock for "' . htmlspecialchars((string)$cartItem['name']) . '". Available: ' . $available . '.';
                        break;
                    }
                } else {
                    $error = 'Failed to validate stock before placing order.';
                    break;
                }
            }
            if ($error) { goto end_place_order; }
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

                $stockStmt = $conn->prepare('UPDATE food SET stock = stock - ? WHERE id = ? AND stock >= ?');
                if (!$stockStmt) { throw new Exception('Failed to prepare stock update.'); }
                $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, food_name, image_url, quantity, price) VALUES (?, ?, ?, ?, ?)');
                if (!$itemStmt) { throw new Exception('Failed to prepare order item insert.'); }
                foreach ($_SESSION['cart'] as $item) {
                    $itemId = (int)$item['id'];
                    $itemName = (string)$item['name']; $itemImageUrl = (string)($item['image_url'] ?? ''); $price = (float)$item['price']; $quantity = (int)$item['qty'];
                    $stockStmt->bind_param('iii', $quantity, $itemId, $quantity);
                    $stockStmt->execute();
                    if ($stockStmt->affected_rows <= 0) {
                        throw new Exception('One or more items are no longer available in the requested quantity.');
                    }
                    $itemStmt->bind_param('issid', $orderId, $itemName, $itemImageUrl, $quantity, $price);
                    $itemStmt->execute();
                }
                $stockStmt->close();
                $itemStmt->close();
                $conn->commit();
                $_SESSION['cart'] = [];
                $message = 'Order placed successfully for Table #' . $tableNumber . '. Order ID: #' . $orderId;
            } catch (Throwable $ex) { $conn->rollback(); $error = 'Order failed: ' . $ex->getMessage(); }
            end_place_order:
        }
    }
}

$menuItems = [];
$result = $conn->query('SELECT id, description, image_url, food_name AS name, price, stock FROM food ORDER BY food_name ASC');
if ($result) { while ($row = $result->fetch_assoc()) { $menuItems[] = $row; } }
$cartTotal = 0.0; foreach ($_SESSION['cart'] as $item) { $cartTotal += ((float)$item['price']) * ((int)$item['qty']); }

// Fetch order history for the selected table
$orderHistory = [];
$selectedTableForHistory = (int)$_SESSION['selected_table_number'];
$historyStmt = $conn->prepare('
    SELECT o.id, o.status, o.created_at, 
           GROUP_CONCAT(CONCAT(oi.food_name, " x", oi.quantity) SEPARATOR ", ") AS items,
           SUM(oi.quantity * oi.price) AS total
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.table_number = ?
    GROUP BY o.id, o.status, o.created_at
    ORDER BY o.created_at DESC
    LIMIT 10
');
if ($historyStmt) {
    $historyStmt->bind_param('i', $selectedTableForHistory);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    while ($row = $historyResult->fetch_assoc()) {
        $orderHistory[] = $row;
    }
    $historyStmt->close();
}

// Fetch all recent orders across tables so customers can see placed orders with their table numbers
$recentOrders = [];
$recentOrdersStmt = $conn->prepare('
    SELECT o.id, o.table_number, o.status, o.created_at,
           GROUP_CONCAT(CONCAT(oi.food_name, " x", oi.quantity) SEPARATOR ", ") AS items,
           SUM(oi.quantity * oi.price) AS total
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id, o.table_number, o.status, o.created_at
    ORDER BY o.created_at DESC
    LIMIT 20
');
if ($recentOrdersStmt) {
    $recentOrdersStmt->execute();
    $recentOrdersResult = $recentOrdersStmt->get_result();
    while ($row = $recentOrdersResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }
    $recentOrdersStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Order System</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">🍽️ Restaurant</div>
        <nav class="sidebar-nav">
            <a href="admin.php">📊 Dashboard</a>
            <a href="order.php" class="active">🛒 View Orders</a>
            <a href="view_food.php">🍕 View Food</a>
            <a href="add_food.php">➕ Add Food</a>
            <a href="kitchen.php">👨‍🍳 Kitchen</a>
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
        <h1>Restaurant Order System</h1>
    </div>

    <div class="container">
        <div class="header">
            <h1>🍽️ Food Ordering System</h1>
            <p>Select items from our menu and add to cart</p>
        </div>
        <?php if ($message): ?><div class="message"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div class="card">
                <h2>🍕 Menu - Select Items</h2>
                <?php if (!$menuItems): ?>
                    <p style="color: #5A6C7D; text-align: center; padding: 2rem;">No menu items found.</p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem;">
                        <?php foreach ($menuItems as $item): ?>
                            <div style="background: var(--bg-white); border: 2px solid var(--border-color); border-radius: 10px; padding: 1rem; text-align: center; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.borderColor='var(--secondary-bright)'; this.style.boxShadow='0 4px 12px rgba(78, 205, 196, 0.2)'" onmouseout="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo e($item['image_url']); ?>" alt="<?php echo e($item['name']); ?>" class="food-thumb" style="width: 100%; height: 120px; object-fit: cover; margin: 0; margin-bottom: 0.8rem; border-radius: 8px;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 120px; background: linear-gradient(135deg, rgba(78, 205, 196, 0.2) 0%, rgba(255, 217, 61, 0.2) 100%); border-radius: 8px; margin-bottom: 0.8rem; display: flex; align-items: center; justify-content: center; color: #5A6C7D;">No Image</div>
                                <?php endif; ?>
                                <strong style="display: block; margin-bottom: 0.4rem; font-size: 0.95rem;"><?php echo e($item['name']); ?></strong>
                                <span style="display: block; color: var(--primary-bright); font-weight: 700; font-size: 1.1rem; margin-bottom: 0.6rem;">$<?php echo number_format((float)$item['price'], 2); ?></span>
                                <span style="display: block; color: #5A6C7D; font-size: 0.8rem; margin-bottom: 0.8rem;">Stock: <?php echo (int)($item['stock'] ?? 0); ?></span>
                                <form method="post" style="display: flex; gap: 0.4rem; align-items: center;">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                                    <input type="number" name="qty" value="1" min="1" max="<?php echo max(0, (int)($item['stock'] ?? 0)); ?>" style="width: 50px; padding: 0.4rem; border: 1px solid var(--border-color); border-radius: 6px; text-align: center;">
                                    <button type="submit" name="add_to_cart" style="flex: 1; padding: 0.6rem; background: linear-gradient(135deg, var(--secondary-bright), var(--accent-green)); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem;" <?php echo ((int)($item['stock'] ?? 0) <= 0) ? 'disabled' : ''; ?>>Add</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>🛒 Shopping Cart</h2>
                <?php if (empty($_SESSION['cart'])): ?>
                    <p style="color: #5A6C7D; text-align: center; padding: 2rem;">Your cart is empty.</p>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <div style="background: var(--bg-light); padding: 0.8rem; border-radius: 8px; margin-bottom: 0.8rem; display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <strong style="display: block; margin-bottom: 0.3rem;"><?php echo e($item['name']); ?></strong>
                                    <span style="color: #5A6C7D; font-size: 0.85rem;"><?php echo (int)$item['qty']; ?> x $<?php echo number_format((float)$item['price'], 2); ?></span>
                                </div>
                                <div style="text-align: right; margin-right: 0.8rem;">
                                    <strong style="color: var(--primary-bright); font-size: 1rem;">$<?php echo number_format(((float)$item['price']) * ((int)$item['qty']), 2); ?></strong>
                                </div>
                                <form method="post" style="margin: 0;">
                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                                    <button type="submit" name="remove_item" style="background: #FF6B6B; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.75rem;">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="border-top: 2px solid var(--border-color); padding-top: 1rem; margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <strong style="font-size: 1.1rem;">Total:</strong>
                            <strong style="font-size: 1.3rem; color: var(--primary-bright);">$<?php echo number_format($cartTotal, 2); ?></strong>
                        </div>
                        <form method="post" style="margin-bottom: 0.8rem;">
                            <button type="submit" name="clear_cart" style="width: 100%; padding: 0.7rem; background: #A8B5C7; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">🗑️ Clear Cart</button>
                        </form>
                        <form method="post" style="margin-bottom: 0.8rem;">
                            <label for="table_number" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--text-dark);">Table Number:</label>
                            <select name="table_number" id="table_number" style="width: 100%; padding: 0.6rem; border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 0.8rem;">
                                <?php for ($tableNumber = 1; $tableNumber <= 20; $tableNumber++): ?>
                                    <option value="<?php echo $tableNumber; ?>" <?php echo ((int)$_SESSION['selected_table_number'] === $tableNumber) ? 'selected' : ''; ?>>Table <?php echo $tableNumber; ?></option>
                                <?php endfor; ?>
                            </select>
                        </form>
                        <button type="submit" name="place_order" form="order-form" style="width: 100%; padding: 0.9rem; background: linear-gradient(135deg, var(--primary-bright), var(--primary-light)); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.95rem; margin-bottom: 0.6rem;">✅ Place Order</button>
                        <a href="payment.php?table_number=<?php echo (int)$_SESSION['selected_table_number']; ?>" style="display: block; width: 100%; padding: 0.9rem; background: linear-gradient(135deg, var(--accent-purple), #8B5CF6); color: white; border: none; border-radius: 8px; text-align: center; font-weight: 700; text-decoration: none; font-size: 0.95rem;">💳 Payment Screen</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card order-history-section">
            <h2>🧾 Order History</h2>
            <?php if (empty($recentOrders)): ?>
                <p style="color: #5A6C7D; text-align: center; padding: 2rem;">No orders have been placed yet.</p>
            <?php else: ?>
                <div class="placed-orders-list">
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="placed-order-card">
                            <div class="placed-order-top">
                                <div>
                                    <strong>Order #<?php echo (int)$order['id']; ?></strong>
                                    <span>Table #<?php echo (int)$order['table_number']; ?></span>
                                </div>
                                <div>
                                    <strong>$<?php echo number_format((float)($order['total'] ?? 0), 2); ?></strong>
                                    <span><?php echo e(substr($order['created_at'], 0, 10)); ?></span>
                                </div>
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <?php 
                                    $status = $order['status'];
                                    $statusEmoji = '';
                                    $statusColor = '';
                                    if ($status === 'Pending') {
                                        $statusEmoji = '⏳';
                                        $statusColor = '#FF9F43';
                                    } elseif ($status === 'Preparing') {
                                        $statusEmoji = '👨‍🍳';
                                        $statusColor = '#4ECDC4';
                                    } elseif ($status === 'Completed') {
                                        $statusEmoji = '✅';
                                        $statusColor = '#5DFFB7';
                                    }
                                ?>
                                <span style="display: inline-block; background: rgba(78, 205, 196, 0.2); color: <?php echo $statusColor; ?>; padding: 0.4rem 0.6rem; border-radius: 6px; font-weight: 600; font-size: 0.8rem; white-space: nowrap;"><?php echo $statusEmoji; ?> <?php echo e($status); ?></span>
                            </div>
                            <div class="placed-order-items"><?php 
                                $aggregatedItems = aggregateOrderItems($order['items'] ?? '');
                                echo e(implode(', ', $aggregatedItems) ?: 'N/A');
                            ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <form id="order-form" method="post" style="display: none;">
        <input type="hidden" name="place_order" value="1">
    </form>
    </div>
</body>
</html>