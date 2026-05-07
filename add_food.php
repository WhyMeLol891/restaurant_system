<?php

include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $food_name = trim($_POST['food_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);
    $image_url = trim($_POST['image_url'] ?? '');

    $sql = "INSERT INTO food (food_name, description, image_url, price, category, stock) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdsi", $food_name, $description, $image_url, $price, $category, $stock);
    if ($stmt->execute() === TRUE) {
        echo "Food added successfully.";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Food</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        form {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        form input, form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        form input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        form input[type="submit"]:hover {
            background-color: #218838;
        }

    </style>
</head>
<body>
    <h1>Add New Food Item</h1>
    <form method="POST" action="">
        <input type="text" name="food_name" placeholder="Food Name" required>
        <textarea name="description" placeholder="Description" required></textarea>
        <input type="url" name="image_url" placeholder="Image URL (https://...)" pattern="https?://.+">
        <input type="number" step="0.01" name="price" placeholder="Price" required>
        <input type="text" name="category" placeholder="Category" required>
        <input type="number" name="stock" placeholder="Stock Quantity" required>
        <input type="submit" value="Add Food" window.location.href='view_food.php'">
        <a style="color: blue; text-decoration: underline;" href="view_food.php" class="button">Back to Food List</a>
    </form>
</body>
</html>