<?php
include 'db.php';

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

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Food Name</th>
                <th>Category</th>
                <th>Description</th>
                <th>Price</th>
                <th>Stock</th>
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
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No food items found in the database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>