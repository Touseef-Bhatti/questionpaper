<?php
require_once __DIR__ . '/../db_connect.php';

$sql = "DESCRIBE mcqs";
$result = $conn->query($sql);

if ($result) {
    echo "Table: mcqs\n";
    echo str_pad("Field", 20) . str_pad("Type", 20) . str_pad("Null", 10) . str_pad("Key", 10) . str_pad("Default", 10) . str_pad("Extra", 20) . "\n";
    echo str_repeat("-", 90) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['Field'], 20) . 
             str_pad($row['Type'], 20) . 
             str_pad($row['Null'], 10) . 
             str_pad($row['Key'], 10) . 
             str_pad($row['Default'] ?? 'NULL', 10) . 
             str_pad($row['Extra'], 20) . "\n";
    }
} else {
    echo "Error describing table: " . $conn->error;
}
?>
