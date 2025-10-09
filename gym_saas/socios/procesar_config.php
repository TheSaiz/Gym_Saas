<?php
session_start();
include_once(__DIR__."/db_connect.php");

if(!isset($_SESSION['socio_id'])){
    header("Location: login.php");
    exit;
}

$socio_id = $_POST['socio_id'] ?? null;
$nombre = $_POST['nombre'] ?? '';
$apellido = $_POST['apellido'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$telefono_emergencia = $_POST['telefono_emergencia'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if(!$socio_id){
    die("Error: Socio no definido.");
}

// Validar email único
$sql_check = "SELECT id FROM socios WHERE email='$email' AND id<>$socio_id";
$res_check = $conn->query($sql_check);
if($res_check->num_rows > 0){
    die("Error: El email ya está en uso.");
}

$update_sql = "UPDATE socios SET 
    nombre='$nombre',
    apellido='$apellido',
    telefono='$telefono',
    telefono_emergencia='$telefono_emergencia',
    email='$email'";

if(!empty($password)){
    $password_hash = md5($password);
    $update_sql .= ", password='$password_hash'";
}

$update_sql .= " WHERE id=$socio_id";

if($conn->query($update_sql)){
    header("Location: configuracion.php?success=1");
}else{
    die("Error al actualizar datos: ".$conn->error);
}
