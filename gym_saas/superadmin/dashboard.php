<?php
/**
 * Dashboard SuperAdmin
 * Panel principal con estadísticas y métricas del sistema
 */

session_start();

// Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// ============================================
// VERIFICACIÓN DE SESIÓN
// ============================================

if(!isset($_SESSION['superadmin_id']) || empty($_SESSION['superadmin_id'])){
    session_destroy();
    header("Location: $base_url/superadmin/login.php");
    exit;
}

// Timeout de sesión (30 minutos)
$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: $base_url/superadmin/login.php?timeout=1");
    exit;
}

$_SESSION['last_activity'] = time();

$superadmin_id = filter_var($_SESSION['superadmin_id'], FILTER_VALIDATE_INT);
if($superadmin_id === false){
    session_destroy();
    header("Location: $base_url/superadmin/login.php");
    exit;
}

// ============================================
// OBTENER ESTADÍSTICAS GENERALES
// ============================================

// Total de gimnasios
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios");
$stmt->execute();
$total_gimnasios = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Gimnasios activos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE estado = 'activo'");
$stmt->execute();
$gimnasios_activos = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Gimnasios suspendidos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE estado = 'suspendido'");
$stmt->execute();
$gimnasios_suspendidos = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total de socios
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM socios WHERE estado = 'activo'");
$stmt->execute();
$total_socios = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total de licencias activas de socios
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM licencias_socios WHERE estado = 'activa'");
$stmt->execute();
$licencias_activas = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Ingresos del mes actual
$stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total 
                       FROM pagos 
                       WHERE estado = 'pagado' 
                       AND MONTH(fecha) = MONTH(CURDATE()) 
                       AND YEAR(fecha) = YEAR(CURDATE())");
$stmt->execute();
$ingresos_mes = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Ingresos totales
$stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE estado = 'pagado'");
$stmt->execute();
$ingresos_totales = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Gimnasios por vencer (próximos 7 días)
$stmt = $conn->prepare("SELECT COUNT(*) as total 
                       FROM gimnasios 
                       WHERE estado = 'activo' 
                       AND DATEDIFF(fecha_fin, CURDATE()) BETWEEN 0 AND 7");
$stmt->execute();
$gimnasios_por_vencer = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Últimos gimnasios registrados
$stmt = $conn->prepare("SELECT g.*, l.nombre as licencia_nombre, l.precio as licencia_precio,
                       DATEDIFF(g.fecha_fin, CURDATE()) as dias_restantes,
                       DATE_FORMAT(g.creado, '%d/%m/%Y %H:%i') as fecha_registro
                       FROM gimnasios g
                       LEFT JOIN licencias l ON g.licencia_id = l.id
                       ORDER BY g.creado DESC
                       LIMIT 5");
$stmt->execute();
$ultimos_gimnasios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pagos recientes
$stmt = $conn->prepare("SELECT p.*, g.nombre as gimnasio_nombre,
                       DATE_FORMAT(p.fecha, '%d/%m/%Y %H:%i') as fecha_format
                       FROM pagos p
                       LEFT JOIN gimnasios g ON p.gimnasio_id = g.id
                       WHERE p.tipo = 'licencia_gimnasio'
                       ORDER BY p.fecha DESC
                       LIMIT 5");
$stmt->execute();
$ultimos_pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas por licencia
$stmt = $conn->prepare("SELECT l.nombre, COUNT(g.id) as total
                       FROM licencias l
                       LEFT JOIN gimnasios g ON l.id = g.licencia_id AND g.estado = 'activo'
                       WHERE l.estado = 'activo'
                       GROUP BY l.id
                       ORDER BY total DESC");
$stmt->execute();
$stats_licencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$superadmin_nombre = htmlspecialchars($_SESSION['superadmin_nombre'] ?? 'SuperAdmin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Dashboard SuperAdmin">
<meta name="robots" content="noindex, nofollow">
<title>Dashboard - SuperAdmin</title>

<!-- CSS Libraries -->
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
        --primary-dark: #5568d3;
        --secondary: #764ba2;
        --success: #51cf66;
        --danger: #ff6b6b;
        --warning: #ffd43b;
        --info: #4dabf7;
        --dark: #0a0e27;
        --dark-light: #1a1f3a;
        --sidebar-width: 280px;
        --navbar-height: 70px;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        color: #fff;
        overflow-x: hidden;
    }

    /* Navbar */
    .navbar-custom {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        height: var(--navbar-height);
        background: rgba(10, 14, 39, 0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 999;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .navbar-left h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .quick-action-btn {
        padding: 0.6rem 1.2rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: #fff;
    }

    .mobile-menu-btn {
        display: none;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
    }

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border: 1px solid rgba(102, 126, 234, 0.3);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(102, 126, 234, 0.2) 0%, transparent 70%);
        border-radius: 50%;
    }

    .welcome-content {
        position: relative;
        z-index: 1;
    }

    .welcome-title {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        color: rgba(255, 255, 255, 0.7);
        font-size: 1rem;
    }

    .welcome-stats {
        display: flex;
        gap: 2rem;
        position: relative;
        z-index: 1;
    }

    .welcome-stat-item {
        text-align: center;
    }

    .welcome-stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--success);
    }

    .welcome-stat-label {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-icon.primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    }

    .stat-icon.success {
        background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
    }

    .stat-icon.warning {
        background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%);
    }

    .stat-icon.danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);
    }

    .stat-icon.info {
        background: linear-gradient(135deg, #4dabf7 0%, #339af0 100%);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.3rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-label {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
        font-weight: 500;
    }

    .stat-trend {
        position: absolute;
        bottom: 1rem;
        right: 1rem;
        font-size: 0.75rem;
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-weight: 600;
    }

    .trend-up {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
    }

    .trend-down {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    /* Section Title */
    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .section-title i {
        color: var(--primary);
    }

    /* Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    }

    /* Data Card */
    .data-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.8rem;
    }

    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .card-title i {
        color: var(--primary);
    }

    .card-action {
        color: var(--primary);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .card-action:hover {
        color: var(--secondary);
    }

    /* Table */
    .data-table {
        width: 100%;
    }

    .table-row {
        display: flex;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.3s ease;
    }

    .table-row:hover {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
    }

    .table-row:last-child {
        border-bottom: none;
    }

    .table-col {
        flex: 1;
    }

    .table-name {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 0.2rem;
    }

    .table-info {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .table-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-active {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
    }

    .badge-suspended {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    .badge-pending {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
    }

    .badge-paid {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
    }

    .table-amount {
        font-weight: 700;
        font-size: 1rem;
        color: var(--success);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: rgba(255, 255, 255, 0.4);
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    /* Chart Container */
    .chart-container {
        margin-top: 1.5rem;
        height: 250px;
    }

    /* License Stats */
    .license-stats {
        display: grid;
        gap: 1rem;
    }

    .license-stat-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .license-stat-name {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .license-stat-bar {
        flex: 1;
        height: 8px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        margin: 0 1rem;
        position: relative;
        overflow: hidden;
    }

    .license-stat-fill {
        height: 100%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .license-stat-count {
        font-weight: 700;
        font-size: 1.1rem;
        min-width: 50px;
        text-align: right;
    }

    /* Alert Box */
    .alert-box {
        background: rgba(255, 212, 59, 0.1);
        border: 1px solid rgba(255, 212, 59, 0.3);
        border-radius: 15px;
        padding: 1.2rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .alert-icon-box {
        width: 45px;
        height: 45px;
        background: rgba(255, 212, 59, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--warning);
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .alert-content {
        flex: 1;
    }

    .alert-title {
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 0.3rem;
    }

    .alert-message {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .alert-action {
        padding: 0.6rem 1.2rem;
        background: rgba(255, 212, 59, 0.2);
        border: none;
        border-radius: 10px;
        color: var(--warning);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .alert-action:hover {
        background: rgba(255, 212, 59, 0.3);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        :root {
            --sidebar-width: 0;
        }

        .navbar-custom {
            left: 0;
        }

        .main-content {
            margin-left: 0;
        }

        .mobile-menu-btn {
            display: block !important;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .welcome-banner {
            flex-direction: column;
            text-align: center;
        }

        .welcome-stats {
            margin-top: 1.5rem;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .welcome-stats {
            flex-direction: column;
            gap: 1rem;
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>

<?php include_once('sidebar.php'); ?>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-left">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-th-large me-2"></i>Dashboard</h2>
    </div>
    <div class="navbar-right">
        <a href="<?= $base_url ?>/superadmin/gimnasios.php" class="quick-action-btn">
            <i class="fas fa-plus"></i>
            Nuevo Gimnasio
        </a>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-content">
            <h1 class="welcome-title">¡Bienvenido, <?= $superadmin_nombre ?>!</h1>
            <p class="welcome-subtitle">Resumen general del sistema</p>
        </div>
        <div class="welcome-stats">
            <div class="welcome-stat-item">
                <div class="welcome-stat-value"><?= $total_gimnasios ?></div>
                <div class="welcome-stat-label">Gimnasios</div>
            </div>
            <div class="welcome-stat-item">
                <div class="welcome-stat-value"><?= $total_socios ?></div>
                <div class="welcome-stat-label">Socios</div>
            </div>
            <div class="welcome-stat-item">
                <div class="welcome-stat-value">$<?= number_format($ingresos_mes, 0, ',', '.') ?></div>
                <div class="welcome-stat-label">Ingresos Mes</div>
            </div>
        </div>
    </div>

    <!-- Alert si hay gimnasios por vencer -->
    <?php if($gimnasios_por_vencer > 0): ?>
    <div class="alert-box">
        <div class="alert-icon-box">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Atención: Licencias por Vencer</div>
            <div class="alert-message">
                Tienes <?= $gimnasios_por_vencer ?> gimnasio<?= $gimnasios_por_vencer > 1 ? 's' : '' ?> con licencias que vencen en los próximos 7 días.
            </div>
        </div>
        <a href="<?= $base_url ?>/superadmin/alertas.php" class="alert-action">
            Ver Alertas
        </a>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon primary">
                    <i class="fas fa-dumbbell"></i>
                </div>
            </div>
            <div class="stat-value"><?= $gimnasios_activos ?></div>
            <div class="stat-label">Gimnasios Activos</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon danger">
                    <i class="fas fa-ban"></i>
                </div>
            </div>
            <div class="stat-value"><?= $gimnasios_suspendidos ?></div>
            <div class="stat-label">Gimnasios Suspendidos</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-value"><?= $total_socios ?></div>
            <div class="stat-label">Total Socios</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div class="stat-value"><?= $licencias_activas ?></div>
            <div class="stat-label">Licencias Activas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">$<?= number_format($ingresos_mes, 0, ',', '.') ?></div>
            <div class="stat-label">Ingresos del Mes</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-value">$<?= number_format($ingresos_totales, 0, ',', '.') ?></div>
            <div class="stat-label">Ingresos Totales</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        
        <!-- Últimos Gimnasios -->
        <div class="data-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-building"></i>
                    Últimos Gimnasios
                </h3>
                <a href="<?= $base_url ?>/superadmin/gimnasios.php" class="card-action">
                    Ver todos <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="data-table">
                <?php if(count($ultimos_gimnasios) > 0): ?>
                    <?php foreach($ultimos_gimnasios as $gym): ?>
                        <div class="table-row">
                            <div class="table-col">
                                <div class="table-name">
                                    <?= htmlspecialchars($gym['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="table-info">
                                    <?= htmlspecialchars($gym['localidad'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> • 
                                    <?= $gym['fecha_registro'] ?>
                                </div>
                            </div>
                            <div class="table-col" style="flex: 0 0 auto;">
                                <span class="table-badge badge-<?= $gym['estado'] === 'activo' ? 'active' : 'suspended' ?>">
                                    <?= ucfirst($gym['estado']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                        <div>No hay gimnasios registrados</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimos Pagos -->
        <div class="data-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-receipt"></i>
                    Últimos Pagos
                </h3>
                <a href="<?= $base_url ?>/superadmin/pagos.php" class="card-action">
                    Ver todos <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="data-table">
                <?php if(count($ultimos_pagos) > 0): ?>
                    <?php foreach($ultimos_pagos as $pago): ?>
                        <div class="table-row">
                            <div class="table-col">
                                <div class="table-name">
                                    <?= htmlspecialchars($pago['gimnasio_nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="table-info">
                                    <?= $pago['fecha_format'] ?>
                                </div>
                            </div>
                            <div class="table-col" style="flex: 0 0 auto; text-align: right;">
                                <div class="table-amount">
                                    $<?= number_format($pago['monto'], 2, ',', '.') ?>
                                </div>
                                <span class="table-badge badge-<?= $pago['estado'] === 'pagado' ? 'paid' : ($pago['estado'] === 'pendiente' ? 'pending' : 'suspended') ?>">
                                    <?= ucfirst($pago['estado']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                        <div>No hay pagos registrados</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- License Statistics -->
    <section>
        <h3 class="section-title">
            <i class="fas fa-chart-bar"></i>
            Distribución de Licencias
        </h3>

        <div class="data-card">
            <div class="license-stats">
                <?php if(count($stats_licencias) > 0): ?>
                    <?php 
                    $max_total = max(array_column($stats_licencias, 'total'));
                    foreach($stats_licencias as $stat): 
                        $percentage = $max_total > 0 ? ($stat['total'] / $max_total) * 100 : 0;
                    ?>
                        <div class="license-stat-item">
                            <div class="license-stat-name">
                                <?= htmlspecialchars($stat['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="license-stat-bar">
                                <div class="license-stat-fill" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <div class="license-stat-count">
                                <?= $stat['total'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
                        <div>No hay datos de licencias</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('active');
    }

    // Animar números al cargar
    function animateValue(element, start, end, duration) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = value;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // Animar valores al cargar
    document.addEventListener('DOMContentLoaded', function() {
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach(stat => {
            const text = stat.textContent;
            // Si contiene $ o no es un número, no animar
            if (text.includes(')) {
                return;
            }
            const value = parseInt(text.replace(/\D/g, ''));
            if (!isNaN(value) && value > 0) {
                stat.textContent = '0';
                setTimeout(() => {
                    animateValue(stat, 0, value, 1000);
                }, 200);
            }
        });

        // Animar barras de progreso
        setTimeout(() => {
            document.querySelectorAll('.license-stat-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        }, 300);
    });

    // Auto-refresh cada 5 minutos
    setTimeout(() => {
        location.reload();
    }, 300000);

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Timeout de sesión
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000; // 30 minutos

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/superadmin/logout.php';
        }, TIMEOUT_DURATION);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();

    // Console warning
    console.log('%c⚠️ PANEL DE ADMINISTRACIÓN', 'color: #667eea; font-size: 24px; font-weight: bold;');
    console.log('%cAcceso restringido a personal autorizado.', 'font-size: 14px;');
</script>

</body>
</html>
