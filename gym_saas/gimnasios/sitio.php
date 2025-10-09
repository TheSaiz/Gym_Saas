<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");

if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /login.php"); exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Obtener datos del gimnasio
$gimnasio = $conn->query("SELECT * FROM gimnasios WHERE id=$gimnasio_id")->fetch_assoc();

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $direccion = $conn->real_escape_string($_POST['direccion']);
    $localidad = $conn->real_escape_string($_POST['localidad']);
    $partido = $conn->real_escape_string($_POST['partido']);

    $conn->query("UPDATE gimnasios SET nombre='$nombre', direccion='$direccion', localidad='$localidad', partido='$partido' WHERE id=$gimnasio_id");
    $mensaje = "Datos actualizados correctamente.";
}

include_once(__DIR__ . "/sidebar.php");
?>

<div class="container mt-5 pt-4">
    <h2>Editar Sitio Público del Gimnasio</h2>
    <?php if(isset($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>
    <form method="POST" class="mt-4">
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($gimnasio['nombre']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Dirección</label>
            <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($gimnasio['direccion']) ?>">
        </div>
        <div class="mb-3">
            <label>Localidad</label>
            <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($gimnasio['localidad']) ?>">
        </div>
        <div class="mb-3">
            <label>Partido</label>
            <input type="text" name="partido" class="form-control" value="<?= htmlspecialchars($gimnasio['partido']) ?>">
        </div>
        <button class="btn btn-success">Guardar Cambios</button>
    </form>
</div>
