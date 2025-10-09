<?php
session_start();

// Seguridad: Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión de gimnasio con seguridad mejorada
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar ID de gimnasio
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
if($gimnasio_id === false){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Verificar que el gimnasio existe y está activo
$stmt = $conn->prepare("SELECT id, nombre, estado FROM gimnasios WHERE id = ? AND estado = 'activo' LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

$gimnasio_data = $result->fetch_assoc();
$gimnasio_nombre = htmlspecialchars($gimnasio_data['nombre'], ENT_QUOTES, 'UTF-8');
$stmt->close();

// Función segura para obtener estadísticas
function getSecureCount($conn, $query, $gimnasio_id) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function getSecureSum($conn, $query, $gimnasio_id) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

// Obtener estadísticas con consultas preparadas
$total_socios = getSecureCount($conn, "SELECT COUNT(*) as total FROM socios WHERE gimnasio_id = ?", $gimnasio_id);
$total_socios_activos = getSecureCount($conn, "SELECT COUNT(*) as total FROM socios WHERE gimnasio_id = ? AND estado = 'activo'", $gimnasio_id);
$total_membresias = getSecureCount($conn, "SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id = ?", $gimnasio_id);
$total_staff = getSecureCount($conn, "SELECT COUNT(*) as total FROM staff WHERE gimnasio_id = ?", $gimnasio_id);
$total_pagos = getSecureSum($conn, "SELECT IFNULL(SUM(monto), 0) as total FROM pagos WHERE gimnasio_id = ? AND estado = 'pagado'", $gimnasio_id);

// Licencias activas
$licencias_activas = getSecureCount($conn, "SELECT COUNT(*) as total FROM licencias_socios WHERE gimnasio_id = ? AND estado = 'activa'", $gimnasio_id);

// Información de la licencia del gimnasio
$stmt = $conn->prepare("SELECT fecha_fin, DATEDIFF(fecha_fin, CURDATE()) as dias_restantes FROM gimnasios WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$licencia_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dias_restantes = max(0, (int)($licencia_info['dias_restantes'] ?? 0));
$fecha_vencimiento = $licencia_info['fecha_fin'] ?? 'N/A';

// Actividad reciente (últimos 5 socios registrados)
$stmt = $conn->prepare("SELECT nombre, apellido, DATE_FORMAT(creado, '%d/%m/%Y %H:%i') as fecha_registro 
                        FROM socios 
                        WHERE gimnasio_id = ? 
                        ORDER BY creado DESC 
                        LIMIT 5");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$actividad_reciente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Panel de administración de gimnasio">
<meta name="robots" content="noindex, nofollow">
<title>Dashboard - <?= $gimnasio_nombre ?></title>

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

    /* Sidebar Moderna */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: rgba(10, 14, 39, 0.95);
        backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        padding: 2rem 0;
        z-index: 1000;
        overflow-y: auto;
        transition: all 0.3s ease;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 3px;
    }

    .sidebar-brand {
        padding: 0 1.5rem;
        margin-bottom: 2rem;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .brand-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .brand-text h3 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .brand-text p {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        margin: 0;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0 1rem;
    }

    .menu-item {
        margin-bottom: 0.5rem;
    }

    .menu-link {
        display: flex;
        align-items: center;
        padding: 0.9rem 1rem;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .menu-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }

    .menu-link:hover,
    .menu-link.active {
        background: rgba(102, 126, 234, 0.1);
        color: #fff;
    }

    .menu-link:hover::before,
    .menu-link.active::before {
        transform: scaleY(1);
    }

    .menu-link i {
        width: 24px;
        margin-right: 1rem;
        font-size: 1.2rem;
    }

    .menu-link span {
        font-weight: 500;
        font-size: 0.95rem;
    }

    .menu-link .badge {
        margin-left: auto;
        background: var(--primary);
        padding: 0.2rem 0.6rem;
        border-radius: 50px;
        font-size: 0.75rem;
    }

    /* Navbar Moderna */
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

    .license-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        background: rgba(102, 126, 234, 0.1);
        border: 1px solid rgba(102, 126, 234, 0.3);
        border-radius: 50px;
        font-size: 0.85rem;
    }

    .license-badge.warning {
        background: rgba(255, 212, 59, 0.1);
        border-color: rgba(255, 212, 59, 0.3);
        color: var(--warning);
    }

    .license-badge.danger {
        background: rgba(255, 107, 107, 0.1);
        border-color: rgba(255, 107, 107, 0.3);
        color: var(--danger);
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .user-menu:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-size: 0.9rem;
        font-weight: 600;
    }

    .user-role {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    /* Stats Cards Modernos */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.8rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle, rgba(102, 126, 234, 0.2) 0%, transparent 70%);
        transform: translate(30%, -30%);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
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

    .stat-icon.info {
        background: linear-gradient(135deg, #4dabf7 0%, #228be6 100%);
    }

    .stat-icon.danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);
    }

    .stat-trend {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.8rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .stat-trend.up {
        background: rgba(81, 207, 102, 0.1);
        color: var(--success);
    }

    .stat-trend.down {
        background: rgba(255, 107, 107, 0.1);
        color: var(--danger);
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
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.6);
        font-weight: 500;
    }

    /* Action Cards */
    .actions-section {
        margin: 2rem 0;
    }

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

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .action-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        text-decoration: none;
        color: #fff;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }

    .action-card:hover {
        transform: translateY(-5px);
        background: rgba(102, 126, 234, 0.1);
        border-color: var(--primary);
        color: #fff;
    }

    .action-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .action-title {
        font-weight: 600;
        font-size: 1rem;
    }

    /* Activity Feed */
    .activity-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.8rem;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .activity-item:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .activity-avatar {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .activity-details {
        flex: 1;
    }

    .activity-name {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .activity-date {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        :root {
            --sidebar-width: 0;
        }

        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
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

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .navbar-right {
            gap: 0.8rem;
        }

        .user-info {
            display: none;
        }

        .license-badge span {
            display: none;
        }
    }

    /* Loading Animation */
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .loading {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <i class="fas fa-dumbbell"></i>
            </div>
            <div class="brand-text">
                <h3><?= $gimnasio_nombre ?></h3>
                <p>Panel de Control</p>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/index.php" class="menu-link active">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/socios.php" class="menu-link">
                <i class="fas fa-users"></i>
                <span>Socios</span>
                <span class="badge"><?= $total_socios ?></span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/membresias.php" class="menu-link">
                <i class="fas fa-id-card-alt"></i>
                <span>Membresías</span>
                <span class="badge"><?= $total_membresias ?></span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/staff.php" class="menu-link">
                <i class="fas fa-user-shield"></i>
                <span>Staff</span>
                <span class="badge"><?= $total_staff ?></span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/validador.php" class="menu-link">
                <i class="fas fa-qrcode"></i>
                <span>Validador</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/reportes.php" class="menu-link">
                <i class="fas fa-chart-line"></i>
                <span>Reportes</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/licencia.php" class="menu-link">
                <i class="fas fa-key"></i>
                <span>Mi Licencia</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/configuracion.php" class="menu-link">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
        </li>
        <li class="menu-item" style="margin-top: 2rem;">
            <a href="<?= $base_url ?>/gimnasios/logout.php" class="menu-link" style="color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-left">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-th-large me-2"></i>Dashboard</h2>
    </div>
    <div class="navbar-right">
        <?php if($dias_restantes <= 7): ?>
            <div class="license-badge <?= $dias_restantes <= 3 ? 'danger' : 'warning' ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Licencia vence en <?= $dias_restantes ?> días</span>
            </div>
        <?php else: ?>
            <div class="license-badge">
                <i class="fas fa-check-circle"></i>
                <span>Licencia activa</span>
            </div>
        <?php endif; ?>

        <div class="user-menu">
            <div class="user-avatar">
                <?= strtoupper(substr($gimnasio_nombre, 0, 2)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= $gimnasio_nombre ?></div>
                <div class="user-role">Administrador</div>
            </div>
            <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: rgba(255,255,255,0.5);"></i>
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Total Socios -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    12%
                </div>
            </div>
            <div class="stat-value"><?= number_format($total_socios) ?></div>
            <div class="stat-label">Total Socios</div>
        </div>

        <!-- Socios Activos -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    8%
                </div>
            </div>
            <div class="stat-value"><?= number_format($total_socios_activos) ?></div>
            <div class="stat-label">Socios Activos</div>
        </div>

        <!-- Licencias Activas -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    5%
                </div>
            </div>
            <div class="stat-value"><?= number_format($licencias_activas) ?></div>
            <div class="stat-label">Licencias Activas</div>
        </div>

        <!-- Ingresos Totales -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    15%
                </div>
            </div>
            <div class="stat-value">$<?= number_format($total_pagos, 0, ',', '.') ?></div>
            <div class="stat-label">Ingresos Totales</div>
        </div>
    </div>

    <!-- Actions Section -->
    <section class="actions-section">
        <h3 class="section-title">
            <i class="fas fa-bolt"></i>
            Acciones Rápidas
        </h3>
        <div class="actions-grid">
            <a href="<?= $base_url ?>/gimnasios/socios.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Nuevo Socio</div>
            </a>

            <a href="<?= $base_url ?>/gimnasios/membresias.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-id-card-alt"></i>
                </div>
                <div class="action-title">Gestionar Membresías</div>
            </a>

            <a href="<?= $base_url ?>/gimnasios/validador.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="action-title">Validar Acceso</div>
            </a>

            <a href="<?= $base_url ?>/gimnasios/reportes.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-title">Ver Reportes</div>
            </a>

            <a href="<?= $base_url ?>/gimnasios/staff.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="action-title">Administrar Staff</div>
            </a>

            <a href="<?= $base_url ?>/gimnasios/licencia.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="action-title">Mi Licencia</div>
            </a>
        </div>
    </section>

    <!-- Activity Section -->
    <section class="actions-section">
        <h3 class="section-title">
            <i class="fas fa-clock"></i>
            Actividad Reciente
        </h3>
        <div class="activity-card">
            <?php if(count($actividad_reciente) > 0): ?>
                <?php foreach($actividad_reciente as $actividad): ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <?= strtoupper(substr($actividad['nombre'], 0, 1) . substr($actividad['apellido'], 0, 1)) ?>
                        </div>
                        <div class="activity-details">
                            <div class="activity-name">
                                <?= htmlspecialchars($actividad['nombre'] . ' ' . $actividad['apellido'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="activity-date">
                                Se registró el <?= htmlspecialchars($actividad['fecha_registro'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <i class="fas fa-user-plus" style="color: var(--success); font-size: 1.2rem;"></i>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.5);">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>No hay actividad reciente</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Quick Stats Footer -->
    <section class="actions-section">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card" style="background: rgba(81, 207, 102, 0.1); border-color: rgba(81, 207, 102, 0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div class="stat-label">Membresías Creadas</div>
                            <div class="stat-value" style="font-size: 1.8rem;"><?= $total_membresias ?></div>
                        </div>
                        <i class="fas fa-id-card-alt" style="font-size: 2.5rem; color: var(--success); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card" style="background: rgba(77, 171, 247, 0.1); border-color: rgba(77, 171, 247, 0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div class="stat-label">Staff Activo</div>
                            <div class="stat-value" style="font-size: 1.8rem;"><?= $total_staff ?></div>
                        </div>
                        <i class="fas fa-user-shield" style="font-size: 2.5rem; color: var(--info); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card" style="background: rgba(255, 212, 59, 0.1); border-color: rgba(255, 212, 59, 0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div class="stat-label">Días de Licencia</div>
                            <div class="stat-value" style="font-size: 1.8rem;"><?= $dias_restantes ?></div>
                        </div>
                        <i class="fas fa-calendar-check" style="font-size: 2.5rem; color: var(--warning); opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle Sidebar para móvil
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('active');
    }

    // Cerrar sidebar al hacer click fuera en móvil
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Animación de números al cargar
    function animateValue(element, start, end, duration) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = value.toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // Animar valores al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach(stat => {
            const value = parseInt(stat.textContent.replace(/[^0-9]/g, ''));
            if (!isNaN(value)) {
                stat.textContent = '0';
                animateValue(stat, 0, value, 1000);
            }
        });

        // Highlight menu item activo
        const currentPath = window.location.pathname;
        const menuLinks = document.querySelectorAll('.menu-link');
        
        menuLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath || 
                currentPath.includes(link.getAttribute('href').split('/').pop().split('.')[0])) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    });

    // Auto-refresh cada 5 minutos para mantener datos actualizados
    let refreshInterval = setInterval(function() {
        // Mostrar indicador de actualización
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => card.classList.add('loading'));
        
        // Recargar página
        setTimeout(() => {
            location.reload();
        }, 500);
    }, 300000); // 5 minutos

    // Alerta si la licencia está por vencer
    <?php if($dias_restantes <= 3): ?>
        setTimeout(function() {
            if(confirm('⚠️ Tu licencia vence en <?= $dias_restantes ?> días. ¿Deseas renovarla ahora?')) {
                window.location.href = '<?= $base_url ?>/gimnasios/licencia.php';
            }
        }, 2000);
    <?php endif; ?>

    // Protección contra XSS - Sanitizar datos del localStorage
    function sanitizeHTML(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    // Manejar errores de red
    window.addEventListener('online', function() {
        console.log('Conexión restaurada');
        // Opcional: mostrar notificación de conexión restaurada
    });

    window.addEventListener('offline', function() {
        console.log('Sin conexión a internet');
        alert('⚠️ Se perdió la conexión a internet. Algunos datos podrían no estar actualizados.');
    });

    // Prevenir ataques de clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Timeout de sesión (30 minutos de inactividad)
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000; // 30 minutos en milisegundos

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad. Serás redirigido al login.');
            window.location.href = '<?= $base_url ?>/gimnasios/logout.php';
        }, TIMEOUT_DURATION);
    }

    // Resetear timeout en cualquier actividad del usuario
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    // Iniciar el timeout
    resetSessionTimeout();

    // Confirmación antes de cerrar sesión
    document.querySelector('a[href*="logout"]').addEventListener('click', function(e) {
        if (!confirm('¿Estás seguro que deseas cerrar sesión?')) {
            e.preventDefault();
        }
    });

    // Performance: Lazy loading para imágenes si se agregan
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img.lazy').forEach(img => imageObserver.observe(img));
    }

    // Console warning para desarrolladores
    console.log('%c⚠️ ADVERTENCIA', 'color: red; font-size: 30px; font-weight: bold;');
    console.log('%cEsta consola es para desarrolladores. No pegues código desconocido aquí.', 'font-size: 16px;');
</script>

</body>
</html>
