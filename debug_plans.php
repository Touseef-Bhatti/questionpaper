<?php
include 'db_connect.php';
$res = $conn->query("SELECT id, name, features FROM subscription_plans WHERE name = 'free'");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Features: " . $row['features'] . PHP_EOL;
}
?>