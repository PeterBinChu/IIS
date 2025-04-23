<?php
$host = "iis-project2024.mysql.database.azure.com";
$dbname = "iis-database";
$username = "adminIIS";
$password = "x6nZQas4F9qL46n";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>