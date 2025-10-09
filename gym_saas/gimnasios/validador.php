<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){ header("Location: /login.php"); exit; }

$gimnasio_id = $_SESSION['gimnasio_id'];
$resultado = null;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $dni = $conn->real_escape_string($_POST['dni']);
    $resultado = $conn->query("
        SELECT s.nombre, s.apellido, ls.estado, ls.fecha_fin
        FROM socios s
        LEFT JOIN licencias_socios ls ON s.id = ls.socio_id
        WHERE s.gimnasio_id=$gimnasio_id AND s.dni='$dni'
    ")->fetch_assoc();
}

include_once(__DIR__ . "/sidebar.php");
?>

<div class="container mt-5 pt-4">
    <h2>Validador de Socios por DNI</h2>
    <form method="POST" class="mt-3 mb-4">
        <div class="input-group">
            <input type="text" name="dni" class="form-control" placeholder="Ingrese DNI del socio" required>
            <button class="btn btn-success">Validar</button>
        </div>
    </form>

    <?php if($resultado): ?>
        <div class="alert <?= $resultado['estado']=='activa'?'alert-success':'alert-danger' ?>">
            Socio: <?= htmlspecialchars($resultado['nombre'].' '.$resultado['apellido']) ?><br>
            Estado de licencia: <?= ucfirst($resultado['estado']) ?><br>
            Fecha de vencimiento: <?= $resultado['fecha_fin'] ?>
        </div>
    <?php elseif($_SERVER['REQUEST_METHOD']==='POST'): ?>
        <div class="alert alert-warning">No se encontró el socio con ese DNI.</div>
    <?php endif; ?>
</div>
