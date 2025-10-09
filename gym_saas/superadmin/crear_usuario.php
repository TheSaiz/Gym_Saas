<?php
// Script para crear un usuario SuperAdmin
include_once(__DIR__ . "/../includes/db_connect.php");

// Datos del nuevo usuario
$usuario = 'admin';       // Usuario de login
$password = 'admin123';   // Contraseña
$nombre = 'Administrador'; // Nombre visible
$email = 'admin@gimnasio.com'; // Email opcional

// Hash seguro de la contraseña
$pass_hash = password_hash($password, PASSWORD_DEFAULT);

// Insertar en la tabla
$stmt = $conn->prepare("INSERT INTO superadmin (usuario, password, nombre, email) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $usuario, $pass_hash, $nombre, $email);

if($stmt->execute()){
    echo "Usuario SuperAdmin creado correctamente.<br>";
    echo "Usuario: $usuario <br>Contraseña: $password";
}else{
    echo "Error al crear usuario: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
