<?php
// Verificar que existe sesión
if(!isset($_SESSION)) {
    session_start();
}

// Seguridad: Verificar sesión de gimnasio
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    $base_url = "/gym_saas";
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar datos de sesión
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
$gimnasio_nombre = isset($_SESSION['gimnasio_nombre']) ? htmlspecialchars($_SESSION['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') : 'Gimnasio';

// Obtener página actual
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = "/gym_saas";

// Obtener contadores para badges (con consultas preparadas)
include_once(__DIR__ . "/../includes/db_connect.php");

$total_socios = 0;
$total_membresias = 0;
$total_staff = 0;
$dias_restantes = 0;

if($gimnasio_id !== false && $gimnasio_id > 0) {
    // Total socios
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM socios WHERE gimnasio_id = ?");
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_socios = (int)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Total membresías
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id = ?");
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_membresias = (int)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Total staff
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM staff WHERE gimnasio_id = ?");
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_staff = (int)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Días restantes de licencia
    $stmt = $conn->prepare("SELECT DATEDIFF(fecha_fin, CURDATE()) as dias FROM gimnasios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dias_restantes = max(0, (int)($result->fetch_assoc()['dias'] ?? 0));
    $stmt->close();
}

// Función para determinar si un link está activo
function isActive($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            color: #fff;
            overflow-x: hidden;
        }

        /* Sidebar Moderno */
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
            flex-shrink: 0;
        }

        .brand-text {
            flex: 1;
            min-width: 0;
        }

        .brand-text h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
            flex: 1;
        }

        .menu-link .badge {
            margin-left: auto;
            background: var(--primary);
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .menu-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 1rem 1.5rem;
        }

        .license-status {
            margin: 1rem 1.5rem;
            padding: 1rem;
            background: rgba(255, 212, 59, 0.1);
            border: 1px solid rgba(255, 212, 59, 0.3);
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .license-status.danger {
            background: rgba(255, 107, 107, 0.1);
            border-color: rgba(255, 107, 107, 0.3);
        }

        .license-status .status-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .license-status .status-title {
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .license-status .status-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }

        /* Content wrapper */
        .content-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 2rem;
            padding-top: calc(var(--navbar-height) + 2rem);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content-wrapper {
                margin-left: 0;
            }

            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .mobile-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <i class="fas fa-dumbbell"></i>
            </div>
            <div class="brand-text">
                <h3 title="<?= $gimnasio_nombre ?>"><?= $gimnasio_nombre ?></h3>
                <p>Panel de Control</p>
            </div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/index.php" class="menu-link <?= isActive('index.php') ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/socios.php" class="menu-link <?= isActive('socios.php') ?>">
                <i class="fas fa-users"></i>
                <span>Socios</span>
                <?php if($total_socios > 0): ?>
                    <span class="badge"><?= $total_socios ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/membresias.php" class="menu-link <?= isActive('membresias.php') ?>">
                <i class="fas fa-id-card-alt"></i>
                <span>Membresías</span>
                <?php if($total_membresias > 0): ?>
                    <span class="badge"><?= $total_membresias ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/staff.php" class="menu-link <?= isActive('staff.php') ?>">
                <i class="fas fa-user-shield"></i>
                <span>Staff</span>
                <?php if($total_staff > 0): ?>
                    <span class="badge"><?= $total_staff ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/validador.php" class="menu-link <?= isActive('validador.php') ?>">
                <i class="fas fa-qrcode"></i>
                <span>Validador</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/reportes.php" class="menu-link <?= isActive('reportes.php') ?>">
                <i class="fas fa-chart-line"></i>
                <span>Reportes</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/licencia.php" class="menu-link <?= isActive('licencia.php') ?>">
                <i class="fas fa-key"></i>
                <span>Mi Licencia</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/configuracion.php" class="menu-link <?= isActive('configuracion.php') ?>">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
        </li>
    </ul>

    <div class="menu-divider"></div>

    <!-- License Status -->
    <?php if($dias_restantes <= 7): ?>
        <div class="license-status <?= $dias_restantes <= 3 ? 'danger' : '' ?>">
            <div class="status-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="status-title">
                <?= $dias_restantes <= 3 ? '¡Licencia por vencer!' : 'Licencia próxima a vencer' ?>
            </div>
            <div class="status-text">
                Te quedan <?= $dias_restantes ?> día<?= $dias_restantes != 1 ? 's' : '' ?>
            </div>
            <a href="<?= $base_url ?>/gimnasios/licencia.php" class="btn btn-sm btn-warning w-100 mt-2" style="font-size: 0.8rem;">
                Renovar ahora
            </a>
        </div>
    <?php endif; ?>

    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="<?= $base_url ?>/gimnasios/logout.php" class="menu-link" style="color: var(--danger);" onclick="return confirm('¿Estás seguro que deseas cerrar sesión?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }

    // Cerrar sidebar al hacer click en un link (solo en móvil)
    if (window.innerWidth <= 1024) {
        document.querySelectorAll('.menu-link').forEach(link => {
            link.addEventListener('click', function() {
                closeSidebar();
            });
        });
    }
</script>

</body>
</html>
