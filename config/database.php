<?php
$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_DATABASE') ?: 'food_order_system';
$port = getenv('DB_PORT') ?: 3306;

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($servername, $username, $password, $dbname, (int)$port);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
?>
