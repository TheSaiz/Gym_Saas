<?php
session_start();
if(!isset($_SESSION['socio_id'])){
    header("Location: /index.php");
    exit;
}

include_once(__DIR__ . "/db_connect.php");
include_once("sidebar.php");

$socio_id = $_SESSION['socio_id'];

// Obtener datos del socio
$sql = "SELECT * FROM socios WHERE id=$socio_id";
$result = $conn->query($sql);
$socio = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configuración Socio</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<style>
  body { margin-left: 220px; padding: 20px; background:#f4f4f4; }
  .card { max-width: 600px; margin: auto; }
</style>
</head>
<body>
<div class="container-fluid">
<div class="card shadow-sm">
<div class="card-body">
<h4 class="card-title">Editar datos personales</h4>
<form action="procesar_socio.php" method="POST">
<input type="hidden" name="socio_id" value="<?php echo $socio['id']; ?>">
<div class="mb-3">
<label>Nombre</label>
<input type="text" name="nombre" class="form-control" value="<?php echo $socio['nombre']; ?>" required>
</div>
<div class="mb-3">
<label>Apellido</label>
<input type="text" name="apellido" class="form-control" value="<?php echo $socio['apellido']; ?>" required>
</div>
<div class="mb-3">
<label>Teléfono</label>
<input type="text" name="telefono" class="form-control" value="<?php echo $socio['telefono']; ?>">
</div>
<div class="mb-3">
<label>Teléfono Emergencia</label>
<input type="text" name="telefono_emergencia" class="form-control" value="<?php echo $socio['telefono_emergencia']; ?>">
</div>
<div class="mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" value="<?php echo $socio['email']; ?>">
</div>
<div class="mb-3">
<label>Contraseña (dejar vacío para no cambiar)</label>
<input type="password" name="password" class="form-control">
</div>
<button type="submit" class="btn btn-success">Guardar cambios</button>
</form>
</div>
</div>
</div>
</body>
</html>
