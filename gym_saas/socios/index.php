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
  <title>Dashboard Socio</title>
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <style>
    body { margin-left: 220px; padding: 20px; background:#f4f4f4; }
    .card { margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class="container-fluid">
    <h2>Bienvenido, <?php echo $socio['nombre']; ?></h2>
    <p>Tu panel de socio te permite ver el estado de tus licencias y actualizar tus datos.</p>

    <div class="row">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Datos personales</h5>
            <p><strong>Nombre:</strong> <?php echo $socio['nombre']." ".$socio['apellido']; ?></p>
            <p><strong>DNI:</strong> <?php echo $socio['dni']; ?></p>
            <p><strong>Email:</strong> <?php echo $socio['email']; ?></p>
            <a href="configuracion.php" class="btn btn-primary btn-sm">Editar datos</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
