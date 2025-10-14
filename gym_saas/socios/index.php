<?php
session_start();

// Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión de socio
if(!isset($_SESSION['socio_id']) || empty($_SESSION['socio_id'])){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// Sanitizar ID de socio
$socio_id = filter_var($_SESSION['socio_id'], FILTER_VALIDATE_INT);
if($socio_id === false){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// Verificar que el socio existe y está activo
$stmt = $conn->prepare("SELECT s.id, s.nombre, s.apellido, s.dni, s.email, s.telefono, s.estado, s.gimnasio_id, g.nombre as gimnasio_nombre 
                        FROM socios s 
                        JOIN gimnasios g ON s.gimnasio_id = g.id 
                        WHERE s.id = ? AND s.estado = 'activo' 
                        LIMIT 1");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

$socio = $result->fetch_assoc();
$socio_nombre_completo = htmlspecialchars($socio['nombre'] . ' ' . $socio['apellido'], ENT_QUOTES, 'UTF-8');
$gimnasio_nombre = htmlspecialchars($socio['gimnasio_nombre'], ENT_QUOTES, 'UTF-8');
$stmt->close();

// Función segura para obtener estadísticas
function getSecureCount($conn, $query, $socio_id) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $socio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

// Obtener estadísticas
$total_licencias = getSecureCount($conn, "SELECT COUNT(*) as total FROM licencias_socios WHERE socio_id = ?", $socio_id);
$licencias_activas = getSecureCount($conn, "SELECT COUNT(*) as total FROM licencias_socios WHERE socio_id = ? AND estado = 'activa'", $socio_id);
$licencias_vencidas = getSecureCount($conn, "SELECT COUNT(*) as total FROM licencias_socios WHERE socio_id = ? AND estado = 'vencida'", $socio_id);
$licencias_pendientes = getSecureCount($conn, "SELECT COUNT(*) as total FROM licencias_socios WHERE socio_id = ? AND estado = 'pendiente'", $socio_id);

// Obtener próxima licencia a vencer
$stmt = $conn->prepare("SELECT ls.fecha_fin, g.nombre as gimnasio, m.nombre as membresia, DATEDIFF(ls.fecha_fin, CURDATE()) as dias_restantes
                        FROM licencias_socios ls
                        JOIN gimnasios g ON ls.gimnasio_id = g.id
                        LEFT JOIN membresias m ON ls.membresia_id = m.id
                        WHERE ls.socio_id = ? AND ls.estado = 'activa' AND ls.fecha_fin >= CURDATE()
                        ORDER BY ls.fecha_fin ASC
                        LIMIT 1");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$proxima_vencimiento = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Historial de licencias recientes (últimas 5)
$stmt = $conn->prepare("SELECT ls.*, g.nombre as gimnasio, m.nombre as membresia, 
                        DATE_FORMAT(ls.fecha_inicio, '%d/%m/%Y') as fecha_inicio_format,
                        DATE_FORMAT(ls.fecha_fin, '%d/%m/%Y') as fecha_fin_format
                        FROM licencias_socios ls
                        JOIN gimnasios g ON ls.gimnasio_id = g.id
                        LEFT JOIN membresias m ON ls.membresia_id = m.id
                        WHERE ls.socio_id = ?
                        ORDER BY ls.id DESC
                        LIMIT 5");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$historial_licencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Panel de socio - Gimnasio">
<meta name="robots" content="noindex, nofollow">
<title>Mi Panel - <?= $socio_nombre_completo ?></title>

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

    /* Sidebar */
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

    /* Welcome Section */
    .welcome-section {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .welcome-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(102, 126, 234, 0.3) 0%, transparent 70%);
        border-radius: 50%;
    }

    .welcome-content {
        position: relative;
        z-index: 1;
    }

    .welcome-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
    }

    /* Stats Grid */
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

    .stat-icon.danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);
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

    /* Alert Cards */
    .alert-card {
        background: rgba(255, 212, 59, 0.1);
        border: 1px solid rgba(255, 212, 59, 0.3);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .alert-card.danger {
        background: rgba(255, 107, 107, 0.1);
        border-color: rgba(255, 107, 107, 0.3);
    }

    .alert-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 212, 59, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--warning);
        flex-shrink: 0;
    }

    .alert-card.danger .alert-icon {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    .alert-content {
        flex: 1;
    }

    .alert-title {
        font-weight: 600;
        margin-bottom: 0.3rem;
        color: var(--warning);
    }

    .alert-card.danger .alert-title {
        color: var(--danger);
    }

    .alert-text {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .alert-action {
        padding: 0.6rem 1.2rem;
        background: var(--warning);
        color: #000;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }

    .alert-card.danger .alert-action {
        background: var(--danger);
        color: #fff;
    }

    .alert-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
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

    /* Quick Actions */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
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

    /* History Table */
    .history-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.8rem;
        overflow-x: auto;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
    }

    .history-table thead tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .history-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.9rem;
    }

    .history-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-size: 0.9rem;
    }

    .history-table tbody tr {
        transition: all 0.3s ease;
    }

    .history-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }

    .status-badge.activa {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
    }

    .status-badge.vencida {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    .status-badge.pendiente {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
    }

    /* Mobile Menu Button */
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

        .welcome-title {
            font-size: 1.5rem;
        }

        .history-card {
            overflow-x: scroll;
        }
    }
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="brand-text">
                <h3>Portal Socio</h3>
                <p><?= $gimnasio_nombre ?></p>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="<?= $base_url ?>/socios/index.php" class="menu-link active">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/socios/dashboard.php" class="menu-link">
                <i class="fas fa-id-card"></i>
                <span>Mis Licencias</span>
                <span class="badge"><?= $total_licencias ?></span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/socios/configuracion.php" class="menu-link">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
        </li>
        <li class="menu-item" style="margin-top: 2rem;">
            <a href="<?= $base_url ?>/socios/logout.php" class="menu-link" style="color: var(--danger);">
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
        <h2><i class="fas fa-home me-2"></i>Mi Panel</h2>
    </div>
    <div class="navbar-right">
        <div class="user-menu">
            <div class="user-avatar">
                <?= strtoupper(substr($socio['nombre'], 0, 1) . substr($socio['apellido'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= $socio_nombre_completo ?></div>
                <div class="user-role">Socio</div>
            </div>
            <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: rgba(255,255,255,0.5);"></i>
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1 class="welcome-title">¡Hola, <?= htmlspecialchars($socio['nombre'], ENT_QUOTES, 'UTF-8') ?>! 👋</h1>
            <p class="welcome-subtitle">Bienvenido a tu panel personal. Aquí puedes gestionar tus licencias y mantener actualizada tu información.</p>
        </div>
    </div>

    <!-- Alert: Próxima a vencer -->
    <?php if($proxima_vencimiento && $proxima_vencimiento['dias_restantes'] <= 7): ?>
        <div class="alert-card <?= $proxima_vencimiento['dias_restantes'] <= 3 ? 'danger' : '' ?>">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">
                    <?= $proxima_vencimiento['dias_restantes'] <= 3 ? '¡Urgente!' : 'Atención' ?>: Tu licencia está por vencer
                </div>
                <div class="alert-text">
                    Tu licencia de <strong><?= htmlspecialchars($proxima_vencimiento['membresia'] ?? 'Gimnasio', ENT_QUOTES, 'UTF-8') ?></strong> 
                    vence en <strong><?= $proxima_vencimiento['dias_restantes'] ?> día<?= $proxima_vencimiento['dias_restantes'] != 1 ? 's' : '' ?></strong> 
                    (<?= htmlspecialchars($proxima_vencimiento['fecha_fin'], ENT_QUOTES, 'UTF-8') ?>)
                </div>
            </div>
            <a href="<?= $base_url ?>/socios/dashboard.php" class="alert-action">
                Renovar Ahora
            </a>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Total Licencias -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon primary">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div class="stat-value"><?= number_format($total_licencias) ?></div>
            <div class="stat-label">Total Licencias</div>
        </div>

        <!-- Licencias Activas -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?= number_format($licencias_activas) ?></div>
            <div class="stat-label">Licencias Activas</div>
        </div>

        <!-- Licencias Vencidas -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon danger">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?= number_format($licencias_vencidas) ?></div>
            <div class="stat-label">Licencias Vencidas</div>
        </div>

        <!-- Licencias Pendientes -->
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?= number_format($licencias_pendientes) ?></div>
            <div class="stat-label">Pendientes de Pago</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <section>
        <h3 class="section-title">
            <i class="fas fa-bolt"></i>
            Acciones Rápidas
        </h3>
        <div class="actions-grid">
            <a href="<?= $base_url ?>/socios/dashboard.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="action-title">Ver Mis Licencias</div>
            </a>

            <a href="<?= $base_url ?>/socios/configuracion.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="action-title">Editar Perfil</div>
            </a>

            <a href="<?= $base_url ?>/socios/dashboard.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="action-title">Renovar Licencia</div>
            </a>

            <a href="<?= $base_url ?>/socios/dashboard.php#historial" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="action-title">Ver Historial</div>
            </a>
        </div>
    </section>

    <!-- Recent History -->
    <section>
        <h3 class="section-title">
            <i class="fas fa-history"></i>
            Historial Reciente
        </h3>
        <div class="history-card">
            <?php if(count($historial_licencias) > 0): ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Gimnasio</th>
                            <th>Membresía</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($historial_licencias as $licencia): ?>
                            <tr>
                                <td><?= htmlspecialchars($licencia['gimnasio'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($licencia['membresia'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($licencia['fecha_inicio_format'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($licencia['fecha_fin_format'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge <?= htmlspecialchars($licencia['estado'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= ucfirst(htmlspecialchars($licencia['estado'], ENT_QUOTES, 'UTF-8')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: rgba(255,255,255,0.5);">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>No hay historial de licencias disponible</p>
                </div>
            <?php endif; ?>
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

    // Cerrar sidebar al hacer click fuera
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Highlight menu activo
    document.addEventListener('DOMContentLoaded', function() {
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

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Timeout de sesión (30 minutos)
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000;

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/socios/logout.php';
        }, TIMEOUT_DURATION);
    }

    // Resetear timeout en actividad
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();

    // Confirmación antes de cerrar sesión
    document.querySelector('a[href*="logout"]').addEventListener('click', function(e) {
        if (!confirm('¿Estás seguro que deseas cerrar sesión?')) {
            e.preventDefault();
        }
    });

    // Console warning
    console.log('%c⚠️ ADVERTENCIA', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cNo pegues código desconocido aquí.', 'font-size: 14px;');
</script>

</body>
</html>