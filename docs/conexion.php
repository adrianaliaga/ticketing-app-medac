<?php
$servername = "100.107.241.28"; // o IP Tailscale 
$username   = "equipo";       // usuario de MySQL
$password   = "PassMuySegura_123";           // contraseña de MySQL
$dbname     = "login_db";   // nombre de la base de datos
$port       = 3306;           // puerto de MySQL

$connection = new mysqli($servername, $username, $password, $dbname, $port);
if ($connection->connect_error) {
    die("❌ Error de conexión: " . $connection->connect_error);
}
$connection->set_charset("utf8mb4");

?>

