<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");

// Verificar sesión de gimnasio
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /login.php");
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$gimnasio_nombre = $_SESSION['gimnasio_nombre'];

// Estadísticas principales
$total_socios = $conn->query("SELECT COUNT(*) as total FROM socios WHERE gimnasio_id='$gimnasio_id'")->fetch_assoc()['total'];
$total_membresias = $conn->query("SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id='$gimnasio_id'")->fetch_assoc()['total'];
$total_staff = $conn->query("SELECT COUNT(*) as total FROM staff WHERE gimnasio_id='$gimnasio_id'")->fetch_assoc()['total'];
$total_pagos = $conn->query("SELECT SUM(monto) as total FROM pagos WHERE gimnasio_id='$gimnasio_id' AND estado='pagado'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?= htmlspecialchars($gimnasio_nombre) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body {font-family: 'Poppins', sans-serif; background: #f8f9fa;}
.sidebar {width: 220px; position: fixed; top: 0; left: 0; height: 100%; background: #212529; color: #fff; padding-top: 60px;}
.sidebar a {display: block; color: #fff; padding: 12px 20px; text-decoration: none;}
.sidebar a:hover {background: #198754; color: #fff; border-radius: 5px;}
.content {margin-left: 240px; padding: 40px;}
.card {border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);}
.navbar {position: fixed; top:0; left: 220px; right:0; z-index:1000;}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3 class="text-center mb-4"><?= htmlspecialchars($gimnasio_nombre) ?></h3>
    <a href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
    <a href="membresias.php"><i class="fas fa-id-card-alt me-2"></i>Membresías</a>
    <a href="socios.php"><i class="fas fa-users me-2"></i>Socios</a>
    <a href="staff.php"><i class="fas fa-user-shield me-2"></i>Staff</a>
    <a href="reportes.php"><i class="fas fa-chart-line me-2"></i>Reportes</a>
    <a href="licencia.php"><i class="fas fa-key me-2"></i>Licencia</a>
    <a href="configuracion.php"><i class="fas fa-cogs me-2"></i>Configuración</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Salir</a>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand ms-3" href="#">Panel del Gimnasio</a>
    </div>
</nav>

<!-- CONTENT -->
<div class="content">
    <h2>Bienvenido, <?= htmlspecialchars($gimnasio_nombre) ?>!</h2>
    <p class="text-muted">Resumen rápido de tu gimnasio</p>

    <div class="row mt-4">
        <div class="col-md-3 mb-4">
            <div class="card p-3 text-center bg-white">
                <i class="fas fa-users fa-2x text-success mb-2"></i>
                <h4><?= $total_socios ?></h4>
                <p>Socios Registrados</p>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card p-3 text-center bg-white">
                <i class="fas fa-id-card-alt fa-2x text-success mb-2"></i>
                <h4><?= $total_membresias ?></h4>
                <p>Membresías</p>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card p-3 text-center bg-white">
                <i class="fas fa-user-shield fa-2x text-success mb-2"></i>
                <h4><?= $total_staff ?></h4>
                <p>Staff / Validadores</p>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card p-3 text-center bg-white">
                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                <h4>$<?= number_format($total_pagos,2,',','.'); ?></h4>
                <p>Pagos Recibidos</p>
            </div>
        </div>
    </div>

    <div class="mt-5">
        <h4>Acciones rápidas</h4>
        <div class="d-flex gap-3 flex-wrap">
            <a href="membresias.php" class="btn btn-success"><i class="fas fa-id-card-alt me-1"></i> Gestionar Membresías</a>
            <a href="socios.php" class="btn btn-success"><i class="fas fa-users me-1"></i> Gestionar Socios</a>
            <a href="staff.php" class="btn btn-success"><i class="fas fa-user-shield me-1"></i> Gestionar Staff</a>
            <a href="reportes.php" class="btn btn-success"><i class="fas fa-chart-line me-1"></i> Ver Reportes</a>
            <a href="licencia.php" class="btn btn-success"><i class="fas fa-key me-1"></i> Estado de Licencia</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
