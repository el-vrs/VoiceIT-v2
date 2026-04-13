<?php
$servername = "voiceit-mysql-alc-verse0.e.aivencloud.com";
$username   = "avnadmin";
$password   = "AVNS_5DUZvHNyRl6Ou_Tb5Bf";
$dbname     = "voiceit";
$port       = 10458;

$conn = new mysqli($servername, $username, $password, $dbname, $port);
$conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>