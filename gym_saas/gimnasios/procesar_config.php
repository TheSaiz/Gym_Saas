<?php
session_start();
include_once(__DIR__ . "/includes/db_connect.php");

// Verificar sesión de gimnasio
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /index.php");
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Procesar POST
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $email = $conn->real_escape_string($_POST['email']);
    $telefono = $conn->real_escape_string($_POST['telefono']);
    $mp_key = $conn->real_escape_string($_POST['mp_key']);
    $mp_token = $conn->real_escape_string($_POST['mp_token']);

    // Manejo de archivo logo
    $logo = '';
    if(isset($_FILES['logo']) && $_FILES['logo']['error'] == 0){
        $uploadDir = __DIR__ . "/uploads/";
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $fileName = time() . "_" . basename($_FILES['logo']['name']);
        $destino = $uploadDir . $fileName;
        if(move_uploaded_file($_FILES['logo']['tmp_name'], $destino)){
            $logo = "/gimnasios/uploads/$fileName";
        }
    }

    // Actualizar datos en la base de datos
    $sql = "UPDATE gimnasios SET 
                nombre='$nombre', 
                email='$email', 
                mp_key='$mp_key', 
                mp_token='$mp_token'";

    if($logo !== ''){
        $sql .= ", logo='$logo'";
    }

    $sql .= ", telefono='$telefono' WHERE id=$gimnasio_id";

    if($conn->query($sql)){
        $_SESSION['mensaje'] = "Configuración actualizada correctamente.";
    } else {
        $_SESSION['mensaje_error'] = "Error al actualizar: " . $conn->error;
    }

    header("Location: configuracion.php");
    exit;
}
?>
