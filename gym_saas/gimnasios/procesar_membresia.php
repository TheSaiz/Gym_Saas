<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){ header("Location: /login.php"); exit; }

$gimnasio_id = $_SESSION['gimnasio_id'];
$id = $_GET['id'] ?? null;

if($id){ // Editar
    $m = $conn->query("SELECT * FROM membresias WHERE id=$id AND gimnasio_id=$gimnasio_id")->fetch_assoc();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $dias = intval($_POST['dias']);
    $estado = $_POST['estado'];

    if($_POST['id']){
        $conn->query("UPDATE membresias SET nombre='$nombre', precio=$precio, dias=$dias, estado='$estado' WHERE id={$_POST['id']} AND gimnasio_id=$gimnasio_id");
        $mensaje = "Membresía actualizada correctamente.";
    } else {
        $conn->query("INSERT INTO membresias (gimnasio_id, nombre, precio, dias, estado) VALUES ($gimnasio_id,'$nombre',$precio,$dias,'$estado')");
        $mensaje = "Membresía agregada correctamente.";
    }
}

include_once(__DIR__ . "/sidebar.php");
?>

<div class="container mt-5 pt-4">
    <h2><?= $id?'Editar':'Agregar' ?> Membresía</h2>
    <?php if(isset($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>
    <form method="POST" class="mt-3">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="mb-3"><label>Nombre</label><input type="text" name="nombre" class="form-control" value="<?= $m['nombre'] ?? '' ?>" required></div>
        <div class="mb-3"><label>Precio</label><input type="number" step="0.01" name="precio" class="form-control" value="<?= $m['precio'] ?? '' ?>" required></div>
        <div class="mb-3"><label>Días de vigencia</label><input type="number" name="dias" class="form-control" value="<?= $m['dias'] ?? '' ?>" required></div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="estado" class="form-control">
                <option value="activo" <?= isset($m['estado']) && $m['estado']=='activo'?'selected':'' ?>>Activo</option>
                <option value="inactivo" <?= isset($m['estado']) && $m['estado']=='inactivo'?'selected':'' ?>>Inactivo</option>
            </select>
        </div>
        <button class="btn btn-success"><?= $id?'Actualizar':'Guardar' ?></button>
    </form>
</div>
