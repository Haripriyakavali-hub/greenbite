<?php
// Local XAMPP defaults are kept, but deployment can override them with environment variables.
$servername = getenv('DB_HOST') ?: "localhost";
$port = getenv('DB_PORT') ?: "3307";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') ?: "";
$dbname = getenv('DB_NAME') ?: "greenbite_db";

$conn = new mysqli($servername, $username, $password, $dbname, (int)$port);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>
