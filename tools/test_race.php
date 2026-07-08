<?php
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("CREATE TABLE IF NOT EXISTS race_test (id INT PRIMARY KEY, counter INT NOT NULL)");
$stmt = $pdo->query("SELECT counter FROM race_test WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$val = $row ? (int)$row['counter'] : 0;
echo "Read value: $val\n";
sleep(1);
$pdo->exec("REPLACE INTO race_test (id, counter) VALUES (1, " . ($val + 1) . ")");
echo "Wrote value: " . ($val + 1) . "\n";
