<?php
include 'db.php';
$result = $conn->query('SHOW TABLES');
if (!$result) {
    echo 'DB error: ' . $conn->error . PHP_EOL;
    exit(1);
}
while ($row = $result->fetch_array()) {
    echo $row[0] . PHP_EOL;
}
?>