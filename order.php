<?php
include 'db.php';

// Start session for cart management
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'];
    $item_name = $_POST['item_name'];
    $item_price = $_POST['item_price'];

    if (isset($_SESSION['cart'][$item_id])) {
        $_SESSION['cart'][$item_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$item_id] = [
            'name' => $item_name,
            'price' => $item_price,
            'quantity' => $quantity
        ];
    }
}

// Handle remove from cart
if (isset($_POST['remove_from_cart'])) {
    $item_id = $_POST['remove_from_cart'];
    unset($_SESSION['cart'][$item_id]);
}

// Handle order submission
if (isset($_POST['place_order'])) {
    $customer_name = $conn->real_escape_string($_POST['customer_name']);
    $customer_phone = $conn->real_escape_string($_POST['customer_phone']);
    $customer_address = $conn->real_escape_string($_POST['customer_address']);

    $order_items = [];
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $order_items[] = $item['quantity'] . ' x ' . $item['name'];
        $total_amount += $item['price'] * $item['quantity'];
    }

    $order_items_text = $conn->real_escape_string(implode("\n", $order_items));

    $sql = "INSERT INTO orders (order_items, customer_name, customer_phone, customer_address, total_amount, status, created_at) VALUES ('{$order_items_text}', '{$customer_name}', '{$customer_phone}', '{$customer_address}', {$total_amount}, 'Pending', NOW())";

    if ($conn->query($sql)) {
        $_SESSION['cart'] = [];
        $order_success = true;
    } else {
        $order_error = 'Unable to place order: ' . $conn->error;
    }
}

// Sample menu items (replace with database query when DB is set up)
$menu_items = [
    ['id' => 1, 'name' => 'Margherita Pizza', 'price' => 12.99, 'category' => 'Pizza', 'description' => 'Fresh tomato sauce, mozzarella cheese, basil'],
    ['id' => 2, 'name' => 'Pepperoni Pizza', 'price' => 14.99, 'category' => 'Pizza', 'description' => 'Pepperoni, tomato sauce, mozzarella cheese'],
    ['id' => 3, 'name' => 'Chicken Burger', 'price' => 9.99, 'category' => 'Burgers', 'description' => 'Grilled chicken breast, lettuce, tomato, mayo'],
    ['id' => 4, 'name' => 'Beef Burger', 'price' => 11.99, 'category' => 'Burgers', 'description' => 'Angus beef patty, cheese, lettuce, tomato'],
    ['id' => 5, 'name' => 'Caesar Salad', 'price' => 8.99, 'category' => 'Salads', 'description' => 'Romaine lettuce, croutons, parmesan, caesar dressing'],
    ['id' => 6, 'name' => 'Pasta Carbonara', 'price' => 13.99, 'category' => 'Pasta', 'description' => 'Spaghetti, pancetta, eggs, parmesan cheese']
];

// Group items by category
$categories = [];
foreach ($menu_items as $item) {
    $categories[$item['category']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Food - Restaurant System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .order-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .menu-section {
            margin-bottom: 30px;
        }

        .menu-category {
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .menu-category h2 {
            color: #28a745;
            margin: 0;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9f9f9;
            transition: transform 0.2s;
        }

        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .menu-item h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .menu-item p {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }

        .menu-item .price {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 10px;
        }

        .add-to-cart-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-input {
            width: 60px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        .add-to-cart-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .add-to-cart-btn:hover {
            background-color: #218838;
        }

        .cart-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-total {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            text-align: right;
            margin-top: 10px;
        }

        .order-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }

        .order-form h2 {
            color: #28a745;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        .place-order-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }

        .place-order-btn:hover {
            background-color: #c82333;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .remove-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="order-container">
        <h1>Order Food</h1>

        <?php if (isset($order_success) && $order_success): ?>
            <div class="success-message">
                <strong>Order placed successfully!</strong> Thank you for your order. We'll contact you soon.
            </div>
        <?php endif; ?>

        <?php if (isset($order_error)): ?>
            <div class="success-message" style="background-color: #f8d7da; color: #842029; border-color: #f5c2c7;">
                <strong>Error:</strong> <?php echo htmlspecialchars($order_error); ?>
            </div>
        <?php endif; ?>

        <!-- Cart Section -->
        <?php if (!empty($_SESSION['cart'])): ?>
            <div class="cart-section">
                <h2>Your Cart</h2>
                <?php
                $total = 0;
                foreach ($_SESSION['cart'] as $item_id => $item):
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                    <div class="cart-item">
                        <div>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <br>
                            $<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?> = $<?php echo number_format($subtotal, 2); ?>
                        </div>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="remove_from_cart" value="<?php echo $item_id; ?>" class="remove-btn">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <div class="cart-total">
                    Total: $<?php echo number_format($total, 2); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Menu Section -->
        <?php foreach ($categories as $category => $items): ?>
            <div class="menu-section">
                <div class="menu-category">
                    <h2><?php echo htmlspecialchars($category); ?></h2>
                </div>
                <div class="menu-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="menu-item">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="price">$<?php echo number_format($item['price'], 2); ?></div>
                            <form method="post" class="add-to-cart-form">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                                <input type="hidden" name="item_price" value="<?php echo $item['price']; ?>">
                                <input type="number" name="quantity" value="1" min="1" class="quantity-input">
                                <button type="submit" name="add_to_cart" class="add-to-cart-btn">Add to Cart</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Order Form -->
        <?php if (!empty($_SESSION['cart'])): ?>
            <div class="order-form">
                <h2>Complete Your Order</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="customer_name">Full Name:</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_phone">Phone Number:</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_address">Delivery Address:</label>
                        <textarea id="customer_address" name="customer_address" required></textarea>
                    </div>
                    <button type="submit" name="place_order" class="place-order-btn">Place Order</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>