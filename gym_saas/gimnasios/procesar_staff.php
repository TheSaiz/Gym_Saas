<?php
session_start();
include_once(__DIR__ . "/includes/db_connect.php");

// Verificar sesión
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /index.php");
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $password = md5($_POST['password']);
    $rol = $_POST['rol'] ?? 'validador';
    $estado = $_POST['estado'] ?? 'activo';

    if(isset($_POST['id']) && !empty($_POST['id'])){
        // Editar staff existente
        $id = intval($_POST['id']);
        $sql = "UPDATE staff SET nombre='$nombre', usuario='$usuario', rol='$rol', estado='$estado'";
        if(!empty($_POST['password'])){
            $sql .= ", password='$password'";
        }
        $sql .= " WHERE id=$id AND gimnasio_id=$gimnasio_id";
        $conn->query($sql);
    } else {
        // Crear nuevo staff
        $sql = "INSERT INTO staff (gimnasio_id, nombre, usuario, password, rol, estado)
                VALUES ($gimnasio_id, '$nombre', '$usuario', '$password', '$rol', '$estado')";
        $conn->query($sql);
    }

    header("Location: staff.php");
    exit;
}
?>
