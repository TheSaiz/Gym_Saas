<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");

// Verificar sesión
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /login.php");
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Obtener datos de licencia del gimnasio
$gimnasio = $conn->query("SELECT g.*, l.nombre AS licencia_nombre, l.dias, l.precio 
                          FROM gimnasios g
                          LEFT JOIN licencias l ON g.licencia_id = l.id
                          WHERE g.id=$gimnasio_id")->fetch_assoc();
?>
<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="container mt-5 pt-4">
    <h2>Licencia de tu Gimnasio</h2>
    <div class="card mt-4 shadow-sm p-4">
        <h4>Plan actual: <?= $gimnasio['licencia_nombre'] ?? 'No asignado' ?></h4>
        <p>Precio: $<?= number_format($gimnasio['precio'] ?? 0,2,",",".") ?></p>
        <p>Válida hasta: <?= $gimnasio['fecha_fin'] ?? 'N/A' ?></p>
        <?php if($gimnasio['estado'] === 'suspendido'): ?>
            <div class="alert alert-danger">Tu gimnasio está suspendido. Renueva tu licencia.</div>
        <?php endif; ?>
        <a href="/gimnasios/checkout.php" class="btn btn-success mt-3">Renovar / Cambiar Licencia</a>
    </div>
</div>
