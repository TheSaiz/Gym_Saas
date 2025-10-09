<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();
include_once("sidebar.php");

// Cargar config.json
$configJson = file_get_contents(__DIR__."/../data/config.json");
$config = json_decode($configJson,true);

$success = '';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $config['mensaje_bienvenida'] = $_POST['mensaje_bienvenida'] ?? '';
    $config['footer_texto'] = $_POST['footer_texto'] ?? '';
    file_put_contents(__DIR__."/../data/config.json", json_encode($config, JSON_PRETTY_PRINT));
    $success = "Cambios guardados correctamente.";
}
?>

<div class="container-fluid p-4">

    <h2 class="mb-4"><i class="fas fa-globe me-2 text-primary"></i>Edición de Contenido Público</h2>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <label for="mensaje_bienvenida" class="form-label fw-bold">Mensaje de Bienvenida</label>
            <textarea id="mensaje_bienvenida" name="mensaje_bienvenida" class="form-control" rows="5"><?= htmlspecialchars($config['mensaje_bienvenida'] ?? '') ?></textarea>
        </div>

        <div class="mb-4">
            <label for="footer_texto" class="form-label fw-bold">Texto de Footer</label>
            <textarea id="footer_texto" name="footer_texto" class="form-control" rows="3"><?= htmlspecialchars($config['footer_texto'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
    </form>

</div>

<!-- Bootstrap 5 & FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
