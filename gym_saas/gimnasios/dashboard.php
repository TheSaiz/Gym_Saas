<?php
session_start();
if(!isset($_SESSION['gimnasio_id'])) header("Location: login.php");
include_once("sidebar.php");
include_once("../includes/db_connect.php");

$idGim = $_SESSION['gimnasio_id'];

// --- Datos de resumen ---
$sociosActivos = $conn->query("SELECT COUNT(*) AS total FROM socios WHERE gimnasio_id='$idGim' AND estado='activo'")->fetch_assoc()['total'];
$sociosInactivos = $conn->query("SELECT COUNT(*) AS total FROM socios WHERE gimnasio_id='$idGim' AND estado='inactivo'")->fetch_assoc()['total'];

$pagosPagados = $conn->query("SELECT COUNT(*) AS total FROM pagos WHERE gimnasio_id='$idGim' AND estado='pagado'")->fetch_assoc()['total'];
$pagosPendientes = $conn->query("SELECT COUNT(*) AS total FROM pagos WHERE gimnasio_id='$idGim' AND estado='pendiente'")->fetch_assoc()['total'];
$pagosFallidos = $conn->query("SELECT COUNT(*) AS total FROM pagos WHERE gimnasio_id='$idGim' AND estado='fallido'")->fetch_assoc()['total'];

// Licencia actual
$licencia = $conn->query("SELECT gimnasios.fecha_fin, licencias.nombre FROM gimnasios LEFT JOIN licencias ON gimnasios.licencia_id=licencias.id WHERE gimnasios.id='$idGim'")->fetch_assoc();

// Recaudación total
$recaudacion = $conn->query("SELECT SUM(monto) AS total FROM pagos WHERE gimnasio_id='$idGim' AND estado='pagado'")->fetch_assoc()['total'];
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid" style="margin-left:220px;">
    <h1 class="mt-4"><i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard</h1>
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-users me-2"></i> Socios Activos</h5>
                    <p class="fs-2"><?= $sociosActivos ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary mb-3">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-user-slash me-2"></i> Socios Inactivos</h5>
                    <p class="fs-2"><?= $sociosInactivos ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-dollar-sign me-2"></i> Recaudación</h5>
                    <p class="fs-2">$<?= number_format($recaudacion,2,',','.') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-id-card me-2"></i> Licencia</h5>
                    <p class="fs-2"><?= $licencia['nombre'] ?? 'Sin licencia' ?></p>
                    <small>Vence: <?= $licencia['fecha_fin'] ?? 'N/A' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">Socios por Estado</div>
                <div class="card-body">
                    <canvas id="chartSocios"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">Pagos por Estado</div>
                <div class="card-body">
                    <canvas id="chartPagos"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ctxSocios = document.getElementById('chartSocios').getContext('2d');
new Chart(ctxSocios, {
    type: 'doughnut',
    data: {
        labels: ['Activos','Inactivos'],
        datasets: [{
            data: [<?= $sociosActivos ?>, <?= $sociosInactivos ?>],
            backgroundColor: ['#28a745','#6c757d']
        }]
    },
    options: { responsive:true }
});

const ctxPagos = document.getElementById('chartPagos').getContext('2d');
new Chart(ctxPagos, {
    type: 'doughnut',
    data: {
        labels: ['Pagados','Pendientes','Fallidos'],
        datasets: [{
            data: [<?= $pagosPagados ?>, <?= $pagosPendientes ?>, <?= $pagosFallidos ?>],
            backgroundColor: ['#ffc107','#0dcaf0','#dc3545']
        }]
    },
    options: { responsive:true }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
