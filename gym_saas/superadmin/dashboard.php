<?php
// Dashboard SuperAdmin
include_once(__DIR__ . "/../includes/auth_check.php");
include_once(__DIR__ . "/../includes/db_connect.php");
checkSuperAdminLogin();

// Cantidad de gimnasios
$totalGimnasios = $conn->query("SELECT COUNT(*) as total FROM gimnasios")->fetch_assoc()['total'];

// Cantidad de licencias activas e inactivas
$totalLicenciasActivas = $conn->query("SELECT COUNT(*) as total FROM licencias WHERE estado='activo'")->fetch_assoc()['total'];
$totalLicenciasInactivas = $conn->query("SELECT COUNT(*) as total FROM licencias WHERE estado='inactivo'")->fetch_assoc()['total'];

// Total de socios registrados
$totalSocios = $conn->query("SELECT COUNT(*) as total FROM socios")->fetch_assoc()['total'];

// Total recaudación últimos 30 días
$totalRecaudacion = $conn->query("
    SELECT IFNULL(SUM(monto),0) as total 
    FROM pagos 
    WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    AND estado='pagado'
")->fetch_assoc()['total'];
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard SuperAdmin</h2>
    <div class="row g-3">

        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Gimnasios Registrados</h5>
                    <p class="card-text display-6"><?= $totalGimnasios ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Licencias Activas</h5>
                    <p class="card-text display-6"><?= $totalLicenciasActivas ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-white bg-danger mb-3">
                <div class="card-body">
                    <h5 class="card-title">Licencias Inactivas</h5>
                    <p class="card-text display-6"><?= $totalLicenciasInactivas ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Recaudación últimos 30 días</h5>
                    <p class="card-text display-6">$<?= number_format($totalRecaudacion, 2, ',', '.') ?></p>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3 mt-2">
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Socios Registrados</h5>
                    <p class="card-text display-6"><?= $totalSocios ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap & FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
