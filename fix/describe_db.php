<?php
require_once __DIR__ . '/../bootstrap.php';

use PDO;

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$output = "";

ob_start();
echo "<pre>";
foreach ($tables as $table) {
    echo "DESCRIBE $table:\n";
    $output .= "DESCRIBE $table:\n";

    $describe = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($describe as $column) {
        $line = "{$column['Field']} | {$column['Type']} | {$column['Null']} | {$column['Key']} | {$column['Default']} | {$column['Extra']}\n";
        echo $line;
        $output .= $line;
    }
    echo str_repeat("-", 40) . "\n";
    $output .= str_repeat("-", 40) . "\n";
}
echo "</pre>";
ob_end_flush();

// También guarda la salida en un archivo .txt
file_put_contents(__DIR__ . '/estructura_bd.txt', $output);