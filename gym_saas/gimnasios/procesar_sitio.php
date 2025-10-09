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
    $email = $conn->real_escape_string($_POST['email']);
    $mp_key = $conn->real_escape_string($_POST['mp_key']);
    $mp_token = $conn->real_escape_string($_POST['mp_token']);

    // Manejo de archivos
    $logo = $_SESSION['gimnasio_logo'] ?? '';
    if(isset($_FILES['logo']) && $_FILES['logo']['error'] == 0){
        $destino = __DIR__ . "/uploads/".$_FILES['logo']['name'];
        move_uploaded_file($_FILES['logo']['tmp_name'], $destino);
        $logo = "/gimnasios/uploads/".$_FILES['logo']['name'];
    }

    $conn->query("UPDATE gimnasios SET nombre='$nombre', email='$email', mp_key='$mp_key', mp_token='$mp_token', logo='$logo'
                  WHERE id=$gimnasio_id");

    header("Location: configuracion.php");
    exit;
}
?>
