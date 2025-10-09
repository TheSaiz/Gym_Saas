<?php
session_start();
if(!isset($_SESSION['gimnasio_id'])) header("Location: login.php");
include_once("sidebar.php");
include_once("../includes/db_connect.php");

$idGim = $_SESSION['gimnasio_id'];
$membresias = $conn->query("SELECT * FROM membresias WHERE gimnasio_id='$idGim'")->fetch_all(MYSQLI_ASSOC);
$msg = $_GET['msg'] ?? '';
?>
<div class="container-fluid p-4" style="margin-left:220px;">
    <h2 class="mb-4"><i class="fas fa-id-card me-2 text-primary"></i>Membresías</h2>
    <?php if($msg): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <a href="procesar_membresia.php?accion=nuevo" class="btn btn-success mb-3"><i class="fas fa-plus me-1"></i> Nueva Membresía</a>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th><th>Nombre</th><th>Días</th><th>Precio</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($membresias as $m): ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><?= $m['nombre'] ?></td>
                <td><?= $m['dias'] ?></td>
                <td>$<?= number_format($m['precio'],2,',','.') ?></td>
                <td><?= ucfirst($m['estado']) ?></td>
                <td>
                    <a href="procesar_membresia.php?accion=editar&id=<?= $m['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                    <a href="procesar_membresia.php?accion=eliminar&id=<?= $m['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar membresía?')"><i class="fas fa-trash-alt"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
