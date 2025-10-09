<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();
include_once(__DIR__ . "/../includes/db_connect.php");
include_once("sidebar.php");

// Obtener ID del gimnasio
$id = $_GET['id'] ?? 0;
$gimnasio = $conn->query("SELECT * FROM gimnasios WHERE id='$id'")->fetch_assoc();

// Procesar formulario
if($_SERVER['REQUEST_METHOD']=='POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $email = $conn->real_escape_string($_POST['email']);
    $estado = $_POST['estado'];
    $conn->query("UPDATE gimnasios SET nombre='$nombre', email='$email', estado='$estado' WHERE id='$id'");
    $success="Gimnasio actualizado correctamente.";
    $gimnasio = $conn->query("SELECT * FROM gimnasios WHERE id='$id'")->fetch_assoc();
}
?>

<div class="container-fluid p-4">
    <h2 class="mb-4"><i class="fas fa-dumbbell text-primary me-2"></i>Editar Gimnasio</h2>

    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm bg-light rounded">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($gimnasio['nombre']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($gimnasio['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado">
                <option value="activo" <?= $gimnasio['estado']=='activo'?'selected':'' ?>>Activo</option>
                <option value="suspendido" <?= $gimnasio['estado']=='suspendido'?'selected':'' ?>>Suspendido</option>
            </select>
        </div>

        <button class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
        <a href="gimnasios.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left me-1"></i> Volver</a>
    </form>
</div>

<!-- Bootstrap & FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
