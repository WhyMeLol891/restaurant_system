<?php
include 'db.php';

$message = '';
$edit_food = null;

if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM food WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: view_food.php?msg=deleted");
        exit;
    }
}

if (isset($_POST['update_food'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $food_name = trim($_POST['food_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    $stmt = mysqli_prepare($conn, "UPDATE food SET food_name = ?, category = ?, description = ?, price = ?, stock = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssdii", $food_name, $category, $description, $price, $stock, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: view_food.php?msg=updated");
        exit;
    }
}

if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM food WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);
        $result_edit = mysqli_stmt_get_result($stmt);
        $edit_food = $result_edit ? mysqli_fetch_assoc($result_edit) : null;
        if ($result_edit) {
            mysqli_free_result($result_edit);
        }
        mysqli_stmt_close($stmt);
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'] === 'deleted' ? 'Food item deleted successfully.' : ($_GET['msg'] === 'updated' ? 'Food item updated successfully.' : '');
}

$query = "SELECT * FROM food";
$result = mysqli_query($conn, $query);
$foods = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $foods[] = $row;
    }
    mysqli_free_result($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Food Items</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        body {
            background: linear-gradient(to right, #ef9308 0%, #fcb69f 100%);
        }
    </style>
</head>
<body>

    <h1>Food Menu / Items</h1>
    <p><a href="admin.php">Back to Admin Dashboard</a></p>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($edit_food): ?>
        <h2>Edit Food Item</h2>
        <form method="post" action="view_food.php">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_food['id']); ?>">
            <label>Food Name:</label><br>
            <input type="text" name="food_name" value="<?php echo htmlspecialchars($edit_food['food_name']); ?>" required><br><br>

            <label>Category:</label><br>
            <input type="text" name="category" value="<?php echo htmlspecialchars($edit_food['category'] ?? ''); ?>"><br><br>

            <label>Description:</label><br>
            <textarea name="description"><?php echo htmlspecialchars($edit_food['description'] ?? ''); ?></textarea><br><br>

            <label>Price:</label><br>
            <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($edit_food['price']); ?>" required><br><br>

            <label>Stock:</label><br>
            <input type="number" name="stock" value="<?php echo htmlspecialchars($edit_food['stock'] ?? 0); ?>" required><br><br>

            <button type="submit" name="update_food">Update Food</button>
            <a href="view_food.php">Cancel</a>
        </form>
        <hr>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Food Name</th>
                <th>Category</th>
                <th>Description</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($foods)): ?>
                <?php foreach ($foods as $food): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($food['id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($food['food_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($food['category'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($food['description'] ?? 'N/A'); ?></td>
                        <td>$<?php echo number_format($food['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($food['stock'] ?? 0); ?></td>
                        <td>
                            <a href="view_food.php?edit_id=<?php echo urlencode($food['id']); ?>">Edit</a>
                            |
                            <a href="view_food.php?delete_id=<?php echo urlencode($food['id']); ?>" onclick="return confirm('Are you sure you want to delete this food item?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No food items found in the database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>