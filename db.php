<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$servername = "localhost";
$username = "root";
$port = "3307";
$password = "derricklim12345"; 
$dbname = "restaurant_system";

$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: (" . $conn->connect_errno . ") " . $conn->connect_error);
}

function ensure_orders_table_number_column($conn)
{
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'table_number'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN table_number TINYINT UNSIGNED NULL DEFAULT NULL AFTER id");
    }
    if ($result) {
        $result->free();
    }
}

ensure_orders_table_number_column($conn);

function ensure_payments_table_columns($conn)
{
    $columns = array(
        'table_number' => "ALTER TABLE payments ADD COLUMN table_number TINYINT UNSIGNED NULL DEFAULT NULL AFTER customer_name",
        'order_id' => "ALTER TABLE payments ADD COLUMN order_id INT NULL DEFAULT NULL AFTER table_number",
        'order_summary' => "ALTER TABLE payments ADD COLUMN order_summary TEXT NULL DEFAULT NULL AFTER order_id",
    );

    foreach ($columns as $columnName => $alterSql) {
        $result = $conn->query("SHOW COLUMNS FROM payments LIKE '" . $conn->real_escape_string($columnName) . "'");
        if ($result && $result->num_rows === 0) {
            $conn->query($alterSql);
        }
        if ($result) {
            $result->free();
        }
    }
}

ensure_payments_table_columns($conn);

function ensure_food_image_url_column($conn)
{
    $result = $conn->query("SHOW COLUMNS FROM food LIKE 'image_url'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE food ADD COLUMN image_url VARCHAR(1000) NULL DEFAULT NULL AFTER description");
    }
    if ($result) {
        $result->free();
    }
}

ensure_food_image_url_column($conn);

function ensure_order_items_image_url_column($conn)
{
    $result = $conn->query("SHOW COLUMNS FROM order_items LIKE 'image_url'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE order_items ADD COLUMN image_url VARCHAR(1000) NULL DEFAULT NULL AFTER food_name");
    }
    if ($result) {
        $result->free();
    }
}

ensure_order_items_image_url_column($conn);

