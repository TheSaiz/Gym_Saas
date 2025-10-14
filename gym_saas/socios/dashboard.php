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
$stmt = $conn->prepare("SELECT s.id, s.nombre, s.apellido, s.estado, s.gimnasio_id, g.nombre as gimnasio_nombre 
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

// Obtener todas las licencias del socio con detalles completos
$stmt = $conn->prepare("SELECT ls.*, 
                        g.nombre as gimnasio, 
                        g.direccion as gimnasio_direccion,
                        g.localidad as gimnasio_localidad,
                        m.nombre as membresia,
                        m.precio as membresia_precio,
                        DATE_FORMAT(ls.fecha_inicio, '%d/%m/%Y') as fecha_inicio_format,
                        DATE_FORMAT(ls.fecha_fin, '%d/%m/%Y') as fecha_fin_format,
                        DATEDIFF(ls.fecha_fin, CURDATE()) as dias_restantes,
                        CASE 
                            WHEN ls.estado = 'activa' AND DATEDIFF(ls.fecha_fin, CURDATE()) <= 0 THEN 'vencida'
                            ELSE ls.estado 
                        END as estado_real
                        FROM licencias_socios ls
                        JOIN gimnasios g ON ls.gimnasio_id = g.id
                        LEFT JOIN membresias m ON ls.membresia_id = m.id
                        WHERE ls.socio_id = ?
                        ORDER BY 
                            CASE ls.estado 
                                WHEN 'activa' THEN 1 
                                WHEN 'pendiente' THEN 2 
                                WHEN 'vencida' THEN 3 
                            END,
                            ls.fecha_fin DESC");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$licencias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Actualizar estados vencidos automáticamente
foreach($licencias as &$licencia) {
    if($licencia['estado_real'] === 'vencida' && $licencia['estado'] !== 'vencida') {
        $stmt = $conn->prepare("UPDATE licencias_socios SET estado = 'vencida' WHERE id = ?");
        $stmt->bind_param("i", $licencia['id']);
        $stmt->execute();
        $stmt->close();
        $licencia['estado'] = 'vencida';
    }
}

// Obtener estadísticas
$total_licencias = count($licencias);
$licencias_activas = count(array_filter($licencias, function($l) { return $l['estado_real'] === 'activa'; }));
$licencias_vencidas = count(array_filter($licencias, function($l) { return $l['estado_real'] === 'vencida'; }));
$licencias_pendientes = count(array_filter($licencias, function($l) { return $l['estado'] === 'pendiente'; }));

// Obtener membresías disponibles para renovación
$stmt = $conn->prepare("SELECT m.*, g.nombre as gimnasio_nombre 
                        FROM membresias m 
                        JOIN gimnasios g ON m.gimnasio_id = g.id 
                        WHERE m.gimnasio_id = ? AND m.estado = 'activo'
                        ORDER BY m.precio ASC");
$stmt->bind_param("i", $socio['gimnasio_id']);
$stmt->execute();
$membresias_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Gestión de licencias - Socio">
<meta name="robots" content="noindex, nofollow">
<title>Mis Licencias - <?= $socio_nombre_completo ?></title>

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

    .stat-value {
        font-size: 1.8rem;
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

    /* License Cards */
    .licenses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .license-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.8rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .license-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .license-card.activa {
        border-color: rgba(81, 207, 102, 0.3);
        background: rgba(81, 207, 102, 0.05);
    }

    .license-card.vencida {
        border-color: rgba(255, 107, 107, 0.3);
        background: rgba(255, 107, 107, 0.05);
    }

    .license-card.pendiente {
        border-color: rgba(255, 212, 59, 0.3);
        background: rgba(255, 212, 59, 0.05);
    }

    .license-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .license-gym {
        flex: 1;
    }

    .gym-name {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
    }

    .gym-location {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
    }

    .license-status {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
    }

    .license-status.activa {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
    }

    .license-status.vencida {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
    }

    .license-status.pendiente {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
    }

    .license-details {
        margin-bottom: 1.5rem;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .detail-label {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }

    .detail-value {
        font-weight: 600;
        font-size: 0.9rem;
    }

    .license-footer {
        display: flex;
        gap: 0.8rem;
        margin-top: 1.5rem;
    }

    .btn-renew {
        flex: 1;
        padding: 0.8rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-renew:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: #fff;
    }

    .btn-details {
        padding: 0.8rem 1.2rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #fff;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-details:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    /* Days remaining badge */
    .days-badge {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        padding: 0.5rem 1rem;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .days-badge.urgent {
        background: rgba(255, 107, 107, 0.3);
        color: var(--danger);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .empty-icon {
        font-size: 4rem;
        color: rgba(255, 255, 255, 0.2);
        margin-bottom: 1.5rem;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
    }

    .empty-text {
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 2rem;
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

        .licenses-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .user-info {
            display: none;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
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
            <a href="<?= $base_url ?>/socios/index.php" class="menu-link">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url ?>/socios/dashboard.php" class="menu-link active">
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
        <h2><i class="fas fa-id-card me-2"></i>Mis Licencias</h2>
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
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon primary">
                    <i class="fas fa-id-card"></i>
                </div>
            </div>
            <div class="stat-value"><?= $total_licencias ?></div>
            <div class="stat-label">Total Licencias</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?= $licencias_activas ?></div>
            <div class="stat-label">Activas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon danger">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?= $licencias_vencidas ?></div>
            <div class="stat-label">Vencidas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?= $licencias_pendientes ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>

    <!-- Licenses Section -->
    <section>
        <h3 class="section-title">
            <i class="fas fa-list"></i>
            Estado de Licencias
        </h3>

        <?php if($total_licencias > 0): ?>
            <div class="licenses-grid">
                <?php foreach($licencias as $licencia): ?>
                    <div class="license-card <?= htmlspecialchars($licencia['estado_real'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php if($licencia['estado_real'] === 'activa' && $licencia['dias_restantes'] <= 7): ?>
                            <div class="days-badge <?= $licencia['dias_restantes'] <= 3 ? 'urgent' : '' ?>">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <?= max(0, $licencia['dias_restantes']) ?> días
                            </div>
                        <?php endif; ?>

                        <div class="license-header">
                            <div class="license-gym">
                                <div class="gym-name">
                                    <i class="fas fa-dumbbell me-2"></i>
                                    <?= htmlspecialchars($licencia['gimnasio'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="gym-location">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars($licencia['gimnasio_localidad'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="license-status <?= htmlspecialchars($licencia['estado_real'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php
                                    $estado_texto = [
                                        'activa' => 'Activa',
                                        'vencida' => 'Vencida',
                                        'pendiente' => 'Pendiente'
                                    ];
                                    echo $estado_texto[$licencia['estado_real']] ?? 'Desconocido';
                                ?>
                            </div>
                        </div>

                        <div class="license-details">
                            <div class="detail-row">
                                <span class="detail-label">Membresía</span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($licencia['membresia'] ?? 'Sin especificar', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Fecha Inicio</span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($licencia['fecha_inicio_format'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Fecha Vencimiento</span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($licencia['fecha_fin_format'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <?php if($licencia['estado_real'] === 'activa'): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Días Restantes</span>
                                    <span class="detail-value" style="color: <?= $licencia['dias_restantes'] <= 7 ? 'var(--danger)' : 'var(--success)' ?>">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        <?= max(0, $licencia['dias_restantes']) ?> días
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="license-footer">
                            <?php if($licencia['estado_real'] === 'vencida' || ($licencia['estado_real'] === 'activa' && $licencia['dias_restantes'] <= 7)): ?>
                                <a href="#renovar" class="btn-renew" onclick="openRenewalModal(<?= $licencia['gimnasio_id'] ?>, '<?= htmlspecialchars($licencia['gimnasio'], ENT_QUOTES, 'UTF-8') ?>')">
                                    <i class="fas fa-sync-alt"></i>
                                    Renovar
                                </a>
                            <?php endif; ?>
                            <?php if($licencia['estado_real'] === 'pendiente'): ?>
                                <a href="#pagar" class="btn-renew">
                                    <i class="fas fa-credit-card"></i>
                                    Completar Pago
                                </a>
                            <?php endif; ?>
                            <a href="#detalle-<?= $licencia['id'] ?>" class="btn-details" title="Ver más detalles">
                                <i class="fas fa-info-circle"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-id-card-alt"></i>
                </div>
                <h4 class="empty-title">No tienes licencias registradas</h4>
                <p class="empty-text">Contacta con tu gimnasio para adquirir una membresía</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Available Memberships Section -->
    <?php if(count($membresias_disponibles) > 0): ?>
    <section id="historial" style="margin-top: 3rem;">
        <h3 class="section-title">
            <i class="fas fa-shopping-cart"></i>
            Membresías Disponibles
        </h3>
        <div class="licenses-grid">
            <?php foreach($membresias_disponibles as $membresia): ?>
                <div class="license-card" style="border-color: rgba(102, 126, 234, 0.3);">
                    <div class="license-header">
                        <div class="license-gym">
                            <div class="gym-name">
                                <?= htmlspecialchars($membresia['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="gym-location">
                                <i class="fas fa-building me-1"></i>
                                <?= htmlspecialchars($membresia['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="license-status" style="background: rgba(102, 126, 234, 0.2); color: var(--primary);">
                            Disponible
                        </div>
                    </div>

                    <div class="license-details">
                        <div class="detail-row">
                            <span class="detail-label">Precio</span>
                            <span class="detail-value" style="color: var(--success); font-size: 1.2rem;">
                                $<?= number_format($membresia['precio'], 2, ',', '.') ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Duración</span>
                            <span class="detail-value">
                                <?= $membresia['dias'] ?> días
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Precio por día</span>
                            <span class="detail-value">
                                $<?= number_format($membresia['precio'] / $membresia['dias'], 2, ',', '.') ?>
                            </span>
                        </div>
                    </div>

                    <div class="license-footer">
                        <a href="<?= $base_url ?>/socios/checkout.php?membresia_id=<?= $membresia['id'] ?>" class="btn-renew">
                            <i class="fas fa-shopping-cart"></i>
                            Comprar Ahora
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- Renewal Modal (Simple Alert for now) -->
<script>
    function openRenewalModal(gimnasioId, gimnasioNombre) {
        if(confirm('¿Deseas renovar tu licencia para ' + gimnasioNombre + '?')) {
            // Redirigir a la página de checkout o proceso de renovación
            window.location.href = '<?= $base_url ?>/socios/checkout.php?gimnasio_id=' + gimnasioId;
        }
    }
</script>

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
            const href = link.getAttribute('href');
            if (href === currentPath || currentPath.includes(href.split('/').pop().split('.')[0])) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // Auto-actualizar estados vencidos
        checkExpiredLicenses();
    });

    // Verificar licencias vencidas
    function checkExpiredLicenses() {
        const licenseCards = document.querySelectorAll('.license-card.activa');
        
        licenseCards.forEach(card => {
            const daysElement = card.querySelector('.days-badge');
            if (daysElement) {
                const daysText = daysElement.textContent;
                const days = parseInt(daysText.match(/\d+/));
                
                if (days <= 0) {
                    // Marcar como vencida visualmente
                    card.classList.remove('activa');
                    card.classList.add('vencida');
                    
                    const status = card.querySelector('.license-status');
                    if (status) {
                        status.classList.remove('activa');
                        status.classList.add('vencida');
                        status.textContent = 'Vencida';
                    }
                }
            }
        });
    }

    // Animación de números
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
            const value = parseInt(stat.textContent);
            if (!isNaN(value)) {
                stat.textContent = '0';
                setTimeout(() => {
                    animateValue(stat, 0, value, 1000);
                }, 200);
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

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();

    // Confirmación antes de cerrar sesión
    const logoutLink = document.querySelector('a[href*="logout"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro que deseas cerrar sesión?')) {
                e.preventDefault();
            }
        });
    }

    // Smooth scroll para anclas
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#renovar' && href !== '#pagar') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Contador en tiempo real para días restantes
    setInterval(function() {
        const now = new Date();
        const currentHour = now.getHours();
        
        // A medianoche, recargar la página para actualizar estados
        if (currentHour === 0 && now.getMinutes() === 0) {
            location.reload();
        }
    }, 60000); // Verificar cada minuto

    // Alert visual para licencias por vencer
    document.addEventListener('DOMContentLoaded', function() {
        const urgentCards = document.querySelectorAll('.license-card.activa .days-badge.urgent');
        
        if (urgentCards.length > 0) {
            setTimeout(function() {
                const count = urgentCards.length;
                const plural = count > 1 ? 's' : '';
                
                if (confirm(`⚠️ Tienes ${count} licencia${plural} por vencer en los próximos días.\n\n¿Deseas renovarla${plural} ahora?`)) {
                    document.querySelector('#historial').scrollIntoView({ behavior: 'smooth' });
                }
            }, 2000);
        }
    });

    // Efecto hover mejorado para cards
    document.querySelectorAll('.license-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Auto-scroll al hash si existe
    if (window.location.hash) {
        setTimeout(function() {
            const target = document.querySelector(window.location.hash);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }, 500);
    }

    // Prevenir doble click en botones de acción
    document.querySelectorAll('.btn-renew, .btn-details').forEach(button => {
        button.addEventListener('click', function() {
            if (this.classList.contains('processing')) {
                return false;
            }
            this.classList.add('processing');
            
            setTimeout(() => {
                this.classList.remove('processing');
            }, 3000);
        });
    });

    // Console warning
    console.log('%c⚠️ ADVERTENCIA DE SEGURIDAD', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cNo pegues código desconocido aquí. Podrías comprometer tu cuenta.', 'font-size: 14px;');

    // Performance: Lazy loading de imágenes si se agregan
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });

        document.querySelectorAll('img.lazy').forEach(img => imageObserver.observe(img));
    }

    // Notificación de actualización exitosa (si viene de checkout)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        setTimeout(function() {
            alert('✅ ¡Pago procesado exitosamente! Tu licencia ha sido actualizada.');
            // Limpiar URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 500);
    } else if (urlParams.get('error') === '1') {
        setTimeout(function() {
            alert('❌ Hubo un error al procesar el pago. Por favor, intenta nuevamente.');
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 500);
    }
</script>

</body>
</html>