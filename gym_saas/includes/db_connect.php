<?php
// ==============================================
// Conexión Base de Datos - Gimnasio System SAAS
// ==============================================

$servername = "localhost";
$username = "root";
$password = "";
$database = "gimnasio_saas";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  die("Error de conexión a la base de datos: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
