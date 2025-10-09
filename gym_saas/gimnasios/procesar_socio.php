<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){ header("Location: /login.php"); exit; }

$gimnasio_id = $_SESSION['gimnasio_id'];
$id = $_GET['id'] ?? null;

if($id){
    $socio = $conn->query("SELECT * FROM socios WHERE id=$id AND gimnasio_id=$gimnasio_id")->fetch_assoc();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $apellido = $conn->real_escape_string($_POST['apellido']);
    $dni = $conn->real_escape_string($_POST['dni']);
    $telefono = $conn->real_escape_string($_POST['telefono']);
    $telefono_em = $conn->real_escape_string($_POST['telefono_emergencia']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'] ? md5($_POST['password']) : ($socio['password'] ?? '');

    if($id){
        $conn->query("UPDATE socios SET nombre='$nombre', apellido='$apellido', dni='$dni', telefono='$telefono', telefono_emergencia='$telefono_em', email='$email', password='$password' WHERE id=$id AND gimnasio_id=$gimnasio_id");
        $mensaje = "Socio actualizado correctamente.";
    } else {
        $conn->query("INSERT INTO socios (gimnasio_id, nombre, apellido, dni, telefono, telefono_emergencia, email, password)
                     VALUES ($gimnasio_id,'$nombre','$apellido','$dni','$telefono','$telefono_em','$email','$password')");
        $mensaje = "Socio registrado correctamente.";
    }
}

include_once(__DIR__ . "/sidebar.php");
?>

<div class="container mt-5 pt-4">
    <h2><?= $id?'Editar':'Registrar' ?> Socio</h2>
    <?php if(isset($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>
    <form method="POST" class="mt-3">
        <div class="mb-3"><label>Nombre</label><input type="text" name="nombre" class="form-control" value="<?= $socio['nombre'] ?? '' ?>" required></div>
        <div class="mb-3"><label>Apellido</label><input type="text" name="apellido" class="form-control" value="<?= $socio['apellido'] ?? '' ?>" required></div>
        <div class="mb-3"><label>DNI</label><input type="text" name="dni" class="form-control" value="<?= $socio['dni'] ?? '' ?>" required></div>
        <div class="mb-3"><label>Teléfono</label><input type="text" name="telefono" class="form-control" value="<?= $socio['telefono'] ?? '' ?>"></div>
        <div class="mb-3"><label>Teléfono Emergencia</label><input type="text" name="telefono_emergencia" class="form-control" value="<?= $socio['telefono_emergencia'] ?? '' ?>"></div>
        <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= $socio['email'] ?? '' ?>"></div>
        <div class="mb-3"><label>Contraseña (dejar en blanco para no cambiar)</label><input type="password" name="password" class="form-control"></div>
        <button class="btn btn-success"><?= $id?'Actualizar':'Registrar' ?></button>
    </form>
</div>
