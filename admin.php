<?php

include 'db.php';

$totalRevenue = 0;
$completedFoods = [];

$revenueQuery = "SELECT SUM(total_price) AS revenue FROM orders WHERE status IN ('done', 'completed')";
if ($result = mysqli_query($conn, $revenueQuery)) {
    $row = mysqli_fetch_assoc($result);
    $totalRevenue = $row['revenue'] ?? 0;
    mysqli_free_result($result);
}

$foodQuery = "
SELECT oi.food_name AS name,
       SUM(oi.quantity) AS quantity,
       SUM(oi.quantity * oi.price) AS subtotal
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE o.status IN ('done', 'completed')
GROUP BY oi.food_name
ORDER BY quantity DESC
";

if ($result = mysqli_query($conn, $foodQuery)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $completedFoods[] = $row;
    }
    mysqli_free_result($result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page</title>
</head>
<body>
    <h1>Admin Dashboard</h1>
    <p>Welcome to the admin dashboard. Here you can manage your restaurant system.</p>
</body>
</html>