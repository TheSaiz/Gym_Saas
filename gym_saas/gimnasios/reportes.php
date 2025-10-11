<?php
session_start();

// Seguridad: Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión de gimnasio
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar ID de gimnasio
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
if($gimnasio_id === false || $gimnasio_id <= 0){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

$gimnasio_nombre = isset($_SESSION['gimnasio_nombre']) ? htmlspecialchars($_SESSION['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') : 'Gimnasio';

// Filtro de período
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';
$fecha_desde = date('Y-m-d', strtotime("-{$periodo} days"));
$fecha_hasta = date('Y-m-d');

// Reporte de pagos y licencias
$stmt = $conn->prepare("
    SELECT 
        ls.id,
        CONCAT(s.nombre, ' ', s.apellido) as socio_nombre,
        s.dni,
        m.nombre AS membresia,
        ls.fecha_inicio,
        ls.fecha_fin,
        ls.estado,
        COALESCE(p.monto, 0) as monto,
        p.estado as pago_estado,
        p.fecha as fecha_pago
    FROM licencias_socios ls
    INNER JOIN socios s ON ls.socio_id = s.id
    LEFT JOIN membresias m ON ls.membresia_id = m.id
    LEFT JOIN pagos p ON p.id = ls.pago_id
    WHERE ls.gimnasio_id = ? 
    AND ls.fecha_inicio >= ?
    ORDER BY ls.fecha_inicio DESC
    LIMIT 100
");
$stmt->bind_param("is", $gimnasio_id, $fecha_desde);
$stmt->execute();
$reportes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas del período
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_licencias,
        SUM(CASE WHEN ls.estado = 'activa' THEN 1 ELSE 0 END) as activas,
        SUM(CASE WHEN ls.estado = 'vencida' THEN 1 ELSE 0 END) as vencidas,
        SUM(CASE WHEN ls.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
    FROM licencias_socios ls
    WHERE ls.gimnasio_id = ? 
    AND ls.fecha_inicio >= ?
");
$stmt->bind_param("is", $gimnasio_id, $fecha_desde);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ingresos del período
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN estado = 'pagado' THEN monto ELSE 0 END), 0) as total_pagado,
        COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN monto ELSE 0 END), 0) as total_pendiente,
        COUNT(CASE WHEN estado = 'pagado' THEN 1 END) as cantidad_pagos
    FROM pagos
    WHERE gimnasio_id = ? 
    AND fecha >= ?
");
$stmt->bind_param("is", $gimnasio_id, $fecha_desde);
$stmt->execute();
$ingresos = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Membresías más vendidas
$stmt = $conn->prepare("
    SELECT 
        m.nombre,
        COUNT(ls.id) as cantidad,
        COALESCE(SUM(p.monto), 0) as total_ingresos
    FROM licencias_socios ls
    INNER JOIN membresias m ON ls.membresia_id = m.id
    LEFT JOIN pagos p ON p.id = ls.pago_id AND p.estado = 'pagado'
    WHERE ls.gimnasio_id = ? 
    AND ls.fecha_inicio >= ?
    GROUP BY m.id, m.nombre
    ORDER BY cantidad DESC
    LIMIT 5
");
$stmt->bind_param("is", $gimnasio_id, $fecha_desde);
$stmt->execute();
$top_membresias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Reportes - <?= $gimnasio_nombre ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary: #667eea;
        --secondary: #764ba2;
        --success: #51cf66;
        --danger: #ff6b6b;
        --warning: #ffd43b;
        --info: #4dabf7;
        --dark: #0a0e27;
        --sidebar-width: 280px;
        --navbar-height: 70px;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        color: #fff;
    }

    .content-wrapper {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 1rem;
    }

    /* Stats Cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .stat-icon.primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
    .stat-icon.success { background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%); }
    .stat-icon.warning { background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%); }
    .stat-icon.danger { background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%); }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.3rem;
    }

    .stat-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }

    /* Toolbar */
    .toolbar {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.2rem;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filter-group {
        display: flex;
        gap: 0.8rem;
        align-items: center;
    }

    .filter-select {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        padding: 0.7rem 1rem;
        color: #fff;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(255, 255, 255, 0.12);
    }

    .filter-select option {
        background: #1a1f3a;
        color: #fff;
    }

    .btn-export {
        background: linear-gradient(135deg, var(--success) 0%, #37b24d 100%);
        border: none;
        padding: 0.8rem 1.8rem;
        border-radius: 10px;
        color: #fff;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(81, 207, 102, 0.4);
        color: #fff;
    }

    /* Charts Section */
    .charts-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.8rem;
    }

    .chart-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .chart-title i {
        color: var(--primary);
    }

    /* Top Membresías */
    .membresia-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        margin-bottom: 0.8rem;
        transition: all 0.3s ease;
    }

    .membresia-item:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .membresia-info {
        flex: 1;
    }

    .membresia-name {
        font-weight: 600;
        margin-bottom: 0.3rem;
    }

    .membresia-stats {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
    }

    .membresia-badge {
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        background: rgba(102, 126, 234, 0.2);
        color: var(--primary);
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* Table */
    .table-container {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 0.5rem;
    }

    .custom-table thead th {
        background: rgba(102, 126, 234, 0.1);
        color: rgba(255, 255, 255, 0.9);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        white-space: nowrap;
    }

    .custom-table thead th:first-child {
        border-radius: 12px 0 0 12px;
    }

    .custom-table thead th:last-child {
        border-radius: 0 12px 12px 0;
    }

    .custom-table tbody tr {
        background: rgba(255, 255, 255, 0.03);
        transition: all 0.3s ease;
    }

    .custom-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .custom-table tbody td {
        padding: 1rem;
        border: none;
        vertical-align: middle;
    }

    .custom-table tbody tr td:first-child {
        border-radius: 12px 0 0 12px;
    }

    .custom-table tbody tr td:last-child {
        border-radius: 0 12px 12px 0;
    }

    .badge-custom {
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }

    .badge-success {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 1px solid var(--success);
    }

    .badge-danger {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .badge-warning {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
        border: 1px solid var(--warning);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    @media (max-width: 1024px) {
        .content-wrapper {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: 1fr;
        }

        .charts-section {
            grid-template-columns: 1fr;
        }

        .custom-table {
            min-width: 800px;
        }
    }
</style>
</head>
<body>

<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-chart-line me-2"></i>Reportes y Estadísticas
        </h1>
        <p class="page-subtitle">Análisis detallado de tu gimnasio</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-value">$<?= number_format($ingresos['total_pagado'], 0, ',', '.') ?></div>
            <div class="stat-label">Ingresos Totales</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?= $stats['activas'] ?></div>
            <div class="stat-label">Licencias Activas</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?= $stats['pendientes'] ?></div>
            <div class="stat-label">Pagos Pendientes</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?= $stats['vencidas'] ?></div>
            <div class="stat-label">Licencias Vencidas</div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="filter-group">
            <label style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Período:</label>
            <select class="filter-select" onchange="window.location.href='?periodo=' + this.value">
                <option value="7" <?= $periodo == '7' ? 'selected' : '' ?>>Últimos 7 días</option>
                <option value="30" <?= $periodo == '30' ? 'selected' : '' ?>>Últimos 30 días</option>
                <option value="60" <?= $periodo == '60' ? 'selected' : '' ?>>Últimos 60 días</option>
                <option value="90" <?= $periodo == '90' ? 'selected' : '' ?>>Últimos 90 días</option>
                <option value="365" <?= $periodo == '365' ? 'selected' : '' ?>>Último año</option>
            </select>
        </div>

        <button onclick="exportarReporte()" class="btn-export">
            <i class="fas fa-file-excel"></i>
            Exportar Excel
        </button>
    </div>

    <!-- Charts -->
    <div class="charts-section">
        <div class="chart-card">
            <h3 class="chart-title">
                <i class="fas fa-trophy"></i>
                Top Membresías
            </h3>
            <?php if(count($top_membresias) > 0): ?>
                <?php foreach($top_membresias as $mem): ?>
                    <div class="membresia-item">
                        <div class="membresia-info">
                            <div class="membresia-name"><?= htmlspecialchars($mem['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="membresia-stats">
                                <?= $mem['cantidad'] ?> ventas • $<?= number_format($mem['total_ingresos'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div class="membresia-badge">
                            #<?= $mem['cantidad'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 2rem;">
                    No hay datos disponibles
                </p>
            <?php endif; ?>
        </div>

        <div class="chart-card">
            <h3 class="chart-title">
                <i class="fas fa-money-bill-wave"></i>
                Resumen Financiero
            </h3>
            <div class="membresia-item">
                <div class="membresia-info">
                    <div class="membresia-name">Total Cobrado</div>
                    <div class="membresia-stats"><?= $ingresos['cantidad_pagos'] ?> pagos procesados</div>
                </div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--success);">
                    $<?= number_format($ingresos['total_pagado'], 0, ',', '.') ?>
                </div>
            </div>

            <div class="membresia-item">
                <div class="membresia-info">
                    <div class="membresia-name">Pendiente de Cobro</div>
                    <div class="membresia-stats">Pagos en proceso</div>
                </div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning);">
                    $<?= number_format($ingresos['total_pendiente'], 0, ',', '.') ?>
                </div>
            </div>

            <div class="membresia-item">
                <div class="membresia-info">
                    <div class="membresia-name">Total Generado</div>
                    <div class="membresia-stats">Cobrado + Pendiente</div>
                </div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                    $<?= number_format($ingresos['total_pagado'] + $ingresos['total_pendiente'], 0, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <h3 class="chart-title">
            <i class="fas fa-list"></i>
            Detalle de Licencias y Pagos
        </h3>

        <?php if(count($reportes) > 0): ?>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Socio</th>
                        <th>DNI</th>
                        <th>Membresía</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado Licencia</th>
                        <th>Monto</th>
                        <th>Estado Pago</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reportes as $rep): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <?= htmlspecialchars($rep['socio_nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <code style="background: rgba(255,255,255,0.1); padding: 0.3rem 0.6rem; border-radius: 6px;">
                                    <?= htmlspecialchars($rep['dni'], ENT_QUOTES, 'UTF-8') ?>
                                </code>
                            </td>
                            <td><?= htmlspecialchars($rep['membresia'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= date('d/m/Y', strtotime($rep['fecha_inicio'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($rep['fecha_fin'])) ?></td>
                            <td>
                                <span class="badge-custom badge-<?= $rep['estado'] === 'activa' ? 'success' : ($rep['estado'] === 'vencida' ? 'danger' : 'warning') ?>">
                                    <?= ucfirst($rep['estado']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 600;">
                                $<?= number_format($rep['monto'], 2, ',', '.') ?>
                            </td>
                            <td>
                                <?php if($rep['pago_estado']): ?>
                                    <span class="badge-custom badge-<?= $rep['pago_estado'] === 'pagado' ? 'success' : ($rep['pago_estado'] === 'fallido' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($rep['pago_estado']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: rgba(255,255,255,0.3);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <h3>No hay datos disponibles</h3>
                <p>No se encontraron registros en el período seleccionado</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function exportarReporte() {
        alert('Funcionalidad de exportación en desarrollo.\nEn producción, esto generará un archivo Excel con todos los datos.');
    }

    // Session timeout
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000;

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/gimnasios/logout.php';
        }, TIMEOUT_DURATION);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();
</script>

</body>
</html>
