<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();
include_once("../includes/db_connect.php"); // conexión DB

$id = $_GET['id'] ?? 0;
$id = (int)$id;

if($id <= 0){
    header("Location: gimnasios.php?msg=ID de gimnasio inválido");
    exit;
}

// Obtener datos del gimnasio
$result = $conn->query("SELECT * FROM gimnasios WHERE id='$id'");
if($result->num_rows == 0){
    header("Location: gimnasios.php?msg=Gimnasio no encontrado");
    exit;
}
$gym = $result->fetch_assoc();

// Manejo del POST
$success = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre    = $conn->real_escape_string($_POST['nombre']);
    $direccion = $conn->real_escape_string($_POST['direccion']);
    $altura    = $conn->real_escape_string($_POST['altura']);
    $localidad = $conn->real_escape_string($_POST['localidad']);
    $partido   = $conn->real_escape_string($_POST['partido']);
    $email     = $conn->real_escape_string($_POST['email']);
    $estado    = $_POST['estado'] ?? 'activo';

    // Actualizar datos
    $update = $conn->query("
        UPDATE gimnasios SET 
            nombre='$nombre',
            direccion='$direccion',
            altura='$altura',
            localidad='$localidad',
            partido='$partido',
            email='$email',
            estado='$estado'
        WHERE id='$id'
    ");

    if($update){
        $success = "Gimnasio actualizado correctamente.";
        // refrescar datos
        $result = $conn->query("SELECT * FROM gimnasios WHERE id='$id'");
        $gym = $result->fetch_assoc();
    } else {
        $success = "Error al actualizar gimnasio: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Gimnasio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include("sidebar.php"); ?>
<div class="container mt-5">
    <h2><i class="fas fa-dumbbell me-2 text-primary"></i>Editar Gimnasio</h2>

    <?php if($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($gym['nombre']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Dirección</label>
            <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($gym['direccion']) ?>">
        </div>
        <div class="mb-3">
            <label>Altura</label>
            <input type="text" name="altura" class="form-control" value="<?= htmlspecialchars($gym['altura']) ?>">
        </div>
        <div class="mb-3">
            <label>Localidad</label>
            <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($gym['localidad']) ?>">
        </div>
        <div class="mb-3">
            <label>Partido</label>
            <input type="text" name="partido" class="form-control" value="<?= htmlspecialchars($gym['partido']) ?>">
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($gym['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select class="form-control" name="estado">
                <option value="activo" <?= $gym['estado']=='activo'?'selected':'' ?>>Activo</option>
                <option value="suspendido" <?= $gym['estado']=='suspendido'?'selected':'' ?>>Suspendido</option>
            </select>
        </div>
        <button class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
        <a href="gimnasios.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Volver</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
