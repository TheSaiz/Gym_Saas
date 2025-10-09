<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();
include_once("../includes/db_connect.php");
include_once("sidebar.php");

$licencias = $conn->query("SELECT * FROM licencias ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$msg = $_GET['msg'] ?? '';
?>

<div class="container-fluid p-4">
    <h2 class="mb-4"><i class="fas fa-id-card me-2 text-primary"></i>Gestión de Licencias</h2>

    <?php if($msg): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <a href="procesar_licencia.php?accion=nuevo" class="btn btn-success mb-3">
        <i class="fas fa-plus me-1"></i> Nueva Licencia
    </a>

    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Días</th>
                <th>Precio</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($licencias as $lic): ?>
            <tr>
                <td><?= $lic['id'] ?></td>
                <td><?= htmlspecialchars($lic['nombre']) ?></td>
                <td><?= $lic['dias'] ?></td>
                <td>$<?= number_format($lic['precio'],2,',','.') ?></td>
                <td><?= ucfirst($lic['estado']) ?></td>
                <td>
                    <a href="procesar_licencia.php?accion=editar&id=<?= $lic['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="procesar_licencia.php?accion=eliminar&id=<?= $lic['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar licencia?')">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap & FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
