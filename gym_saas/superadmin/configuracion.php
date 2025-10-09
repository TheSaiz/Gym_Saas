<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();
include_once("sidebar.php");

// Cargar config.json
$configJson = file_get_contents(__DIR__."/../data/config.json");
$config = json_decode($configJson,true);

$success = '';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $config['nombre_sitio'] = $_POST['nombre_sitio'] ?? '';
    $config['contacto_email'] = $_POST['contacto_email'] ?? '';
    $config['contacto_telefono'] = $_POST['contacto_telefono'] ?? '';
    $config['mercadopago_clave'] = $_POST['mercadopago_clave'] ?? '';
    file_put_contents(__DIR__."/../data/config.json", json_encode($config, JSON_PRETTY_PRINT));
    $success = "Configuración guardada correctamente.";
}
?>

<div class="container-fluid p-4">

    <h2 class="mb-4"><i class="fas fa-cogs me-2 text-primary"></i>Configuración Global</h2>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Nombre del Sitio</label>
            <input type="text" name="nombre_sitio" class="form-control" value="<?= htmlspecialchars($config['nombre_sitio'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Email de Contacto</label>
            <input type="email" name="contacto_email" class="form-control" value="<?= htmlspecialchars($config['contacto_email'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Teléfono de Contacto</label>
            <input type="text" name="contacto_telefono" class="form-control" value="<?= htmlspecialchars($config['contacto_telefono'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Clave Checkout MercadoPago</label>
            <input type="text" name="mercadopago_clave" class="form-control" value="<?= htmlspecialchars($config['mercadopago_clave'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar Configuración</button>
    </form>

</div>

<!-- Bootstrap 5 & FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
