<?php
/**
 * Sidebar SuperAdmin
 * Menú lateral de navegación del panel de administración
 */

// Verificar sesión
if(!isset($_SESSION['superadmin_id'])){
    header("Location: /gym_saas/superadmin/login.php");
    exit;
}

$base_url = "/gym_saas";
$current_page = basename($_SERVER['PHP_SELF']);

// Obtener información del superadmin
$superadmin_nombre = $_SESSION['superadmin_nombre'] ?? 'SuperAdmin';
$superadmin_usuario = $_SESSION['superadmin_usuario'] ?? 'admin';
$superadmin_email = $_SESSION['superadmin_email'] ?? '';

// Obtener estadísticas rápidas para badges
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE estado = 'activo'");
$stmt->execute();
$total_gimnasios = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM socios WHERE estado = 'activo'");
$stmt->execute();
$total_socios = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM licencias WHERE estado = 'activo'");
$stmt->execute();
$total_licencias = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Notificaciones pendientes (gimnasios por vencer, pagos pendientes, etc)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM gimnasios WHERE DATEDIFF(fecha_fin, CURDATE()) <= 7 AND estado = 'activo'");
$stmt->execute();
$gimnasios_por_vencer = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();
?>

<style>
    :root {
        --sidebar-width: 280px;
        --primary: #667eea;
        --primary-dark: #5568d3;
        --secondary: #764ba2;
        --success: #51cf66;
        --danger: #ff6b6b;
        --warning: #ffd43b;
        --info: #4dabf7;
        --dark: #0a0e27;
        --dark-light: #1a1f3a;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: rgba(10, 14, 39, 0.98);
        backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0;
        z-index: 1000;
        overflow-y: auto;
        overflow-x: hidden;
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

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }

    /* Brand Section */
    .sidebar-brand {
        padding: 2rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
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
        color: #fff;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .brand-text h3 {
        font-size: 1.2rem;
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

    /* User Info */
    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.8rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        margin-top: 1rem;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--info) 0%, var(--primary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        color: #fff;
        flex-shrink: 0;
    }

    .user-details {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #fff;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        margin: 0;
    }

    /* Menu Section */
    .sidebar-menu {
        padding: 1.5rem 1rem;
    }

    .menu-section-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: rgba(255, 255, 255, 0.4);
        margin: 1.5rem 0 0.8rem 0.5rem;
    }

    .menu-section-title:first-child {
        margin-top: 0;
    }

    .sidebar-menu ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .menu-item {
        margin-bottom: 0.3rem;
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

    .menu-link:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #fff;
        transform: translateX(3px);
    }

    .menu-link:hover::before {
        transform: scaleY(1);
    }

    .menu-link.active {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        color: #fff;
        border: 1px solid rgba(102, 126, 234, 0.3);
    }

    .menu-link.active::before {
        transform: scaleY(1);
    }

    .menu-link i {
        width: 24px;
        margin-right: 1rem;
        font-size: 1.1rem;
        text-align: center;
    }

    .menu-link span {
        font-weight: 500;
        font-size: 0.95rem;
        flex: 1;
    }

    .menu-badge {
        margin-left: auto;
        padding: 0.2rem 0.6rem;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .badge-primary {
        background: var(--primary);
        color: #fff;
    }

    .badge-success {
        background: var(--success);
        color: #fff;
    }

    .badge-warning {
        background: var(--warning);
        color: var(--dark);
    }

    .badge-danger {
        background: var(--danger);
        color: #fff;
    }

    .badge-info {
        background: var(--info);
        color: #fff;
    }

    /* Logout Section */
    .sidebar-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
    }

    .logout-link {
        display: flex;
        align-items: center;
        padding: 0.9rem 1rem;
        color: var(--danger);
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        background: rgba(255, 107, 107, 0.1);
        border: 1px solid rgba(255, 107, 107, 0.2);
    }

    .logout-link:hover {
        background: rgba(255, 107, 107, 0.2);
        transform: translateX(3px);
    }

    .logout-link i {
        margin-right: 0.8rem;
        font-size: 1.1rem;
    }

    /* Mobile Toggle */
    .sidebar-close {
        display: none;
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 8px;
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
        z-index: 10;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-close {
            display: block;
        }
    }

    /* Animations */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .menu-item {
        animation: slideIn 0.3s ease forwards;
        opacity: 0;
    }

    .menu-item:nth-child(1) { animation-delay: 0.05s; }
    .menu-item:nth-child(2) { animation-delay: 0.1s; }
    .menu-item:nth-child(3) { animation-delay: 0.15s; }
    .menu-item:nth-child(4) { animation-delay: 0.2s; }
    .menu-item:nth-child(5) { animation-delay: 0.25s; }
    .menu-item:nth-child(6) { animation-delay: 0.3s; }
    .menu-item:nth-child(7) { animation-delay: 0.35s; }
    .menu-item:nth-child(8) { animation-delay: 0.4s; }
</style>

<aside class="sidebar" id="sidebar">
    <!-- Close Button (Mobile) -->
    <button class="sidebar-close" onclick="toggleSidebar()">
        <i class="fas fa-times"></i>
    </button>

    <!-- Brand Section -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <i class="fas fa-crown"></i>
            </div>
            <div class="brand-text">
                <h3>SuperAdmin</h3>
                <p>Panel de Control</p>
            </div>
        </div>

        <!-- User Info -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($superadmin_nombre, 0, 2)) ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($superadmin_nombre, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-role">Administrador</div>
            </div>
        </div>
    </div>

    <!-- Menu -->
    <nav class="sidebar-menu">
        <!-- Principal -->
        <div class="menu-section-title">Principal</div>
        <ul>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/index.php" class="menu-link <?= in_array($current_page, ['index.php', 'dashboard.php']) ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Gestión -->
        <div class="menu-section-title">Gestión</div>
        <ul>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/gimnasios.php" class="menu-link <?= $current_page === 'gimnasios.php' ? 'active' : '' ?>">
                    <i class="fas fa-dumbbell"></i>
                    <span>Gimnasios</span>
                    <?php if($total_gimnasios > 0): ?>
                        <span class="menu-badge badge-primary"><?= $total_gimnasios ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/licencias.php" class="menu-link <?= $current_page === 'licencias.php' ? 'active' : '' ?>">
                    <i class="fas fa-certificate"></i>
                    <span>Licencias</span>
                    <?php if($total_licencias > 0): ?>
                        <span class="menu-badge badge-success"><?= $total_licencias ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/socios.php" class="menu-link <?= $current_page === 'socios.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Todos los Socios</span>
                    <?php if($total_socios > 0): ?>
                        <span class="menu-badge badge-info"><?= $total_socios ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Configuración -->
        <div class="menu-section-title">Configuración</div>
        <ul>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/sitio.php" class="menu-link <?= $current_page === 'sitio.php' ? 'active' : '' ?>">
                    <i class="fas fa-globe"></i>
                    <span>Sitio Web</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/configuracion.php" class="menu-link <?= $current_page === 'configuracion.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </li>
        </ul>

        <!-- Reportes -->
        <div class="menu-section-title">Análisis</div>
        <ul>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/reportes.php" class="menu-link <?= $current_page === 'reportes.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/pagos.php" class="menu-link <?= $current_page === 'pagos.php' ? 'active' : '' ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Pagos</span>
                </a>
            </li>
            <?php if($gimnasios_por_vencer > 0): ?>
            <li class="menu-item">
                <a href="<?= $base_url ?>/superadmin/alertas.php" class="menu-link <?= $current_page === 'alertas.php' ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i>
                    <span>Alertas</span>
                    <span class="menu-badge badge-warning"><?= $gimnasios_por_vencer ?></span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= $base_url ?>/superadmin/logout.php" class="logout-link" onclick="return confirm('¿Estás seguro que deseas cerrar sesión?')">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>

<script>
    // Toggle Sidebar para móviles
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('active');
    }

    // Cerrar sidebar al hacer click fuera (solo móvil)
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 1024) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            
            if (sidebar && !sidebar.contains(event.target) && menuBtn && !menuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Prevenir propagación del click dentro del sidebar
    document.getElementById('sidebar')?.addEventListener('click', function(e) {
        e.stopPropagation();
    });
</script>
