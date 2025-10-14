<?php
/**
 * Sidebar Component para el módulo de Socios
 * Componente reutilizable que se incluye en todas las páginas del panel de socio
 */

// Verificar que existe sesión activa
if(!isset($_SESSION['socio_id'])){
    header("Location: " . ($base_url ?? '/gym_saas') . "/socios/login.php");
    exit;
}

// Obtener información del socio para el sidebar
$socio_id_sidebar = filter_var($_SESSION['socio_id'], FILTER_VALIDATE_INT);

if($socio_id_sidebar && isset($conn)) {
    $stmt = $conn->prepare("SELECT s.nombre, s.apellido, g.nombre as gimnasio_nombre,
                            (SELECT COUNT(*) FROM licencias_socios WHERE socio_id = s.id) as total_licencias
                            FROM socios s 
                            JOIN gimnasios g ON s.gimnasio_id = g.id 
                            WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $socio_id_sidebar);
    $stmt->execute();
    $socio_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $sidebar_nombre = htmlspecialchars($socio_data['nombre'] ?? 'Socio', ENT_QUOTES, 'UTF-8');
    $sidebar_apellido = htmlspecialchars($socio_data['apellido'] ?? '', ENT_QUOTES, 'UTF-8');
    $sidebar_gimnasio = htmlspecialchars($socio_data['gimnasio_nombre'] ?? 'Gimnasio', ENT_QUOTES, 'UTF-8');
    $sidebar_total_licencias = (int)($socio_data['total_licencias'] ?? 0);
} else {
    $sidebar_nombre = 'Socio';
    $sidebar_apellido = '';
    $sidebar_gimnasio = 'Gimnasio';
    $sidebar_total_licencias = 0;
}

$base_url_sidebar = $base_url ?? '/gym_saas';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Moderno para Socios -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="brand-text">
                <h3>Portal Socio</h3>
                <p><?= $sidebar_gimnasio ?></p>
            </div>
        </div>
    </div>

    <!-- User Profile Section -->
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?= strtoupper(substr($sidebar_nombre, 0, 1) . substr($sidebar_apellido, 0, 1)) ?>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?= $sidebar_nombre ?> <?= $sidebar_apellido ?></div>
            <div class="profile-role">Socio Activo</div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="<?= $base_url_sidebar ?>/socios/index.php" class="menu-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url_sidebar ?>/socios/dashboard.php" class="menu-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-id-card"></i>
                <span>Mis Licencias</span>
                <?php if($sidebar_total_licencias > 0): ?>
                    <span class="badge"><?= $sidebar_total_licencias ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= $base_url_sidebar ?>/socios/configuracion.php" class="menu-link <?= $current_page === 'configuracion.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
        </li>
        
        <!-- Divider -->
        <li class="menu-divider"></li>
        
        <!-- Help Section -->
        <li class="menu-item">
            <a href="#ayuda" class="menu-link" onclick="showHelp(); return false;">
                <i class="fas fa-question-circle"></i>
                <span>Ayuda</span>
            </a>
        </li>
        
        <!-- Logout -->
        <li class="menu-item">
            <a href="<?= $base_url_sidebar ?>/socios/logout.php" class="menu-link logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="footer-text">
            <i class="fas fa-shield-alt me-2"></i>
            Sesión segura
        </div>
    </div>
</aside>

<style>
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width, 280px);
        height: 100vh;
        background: rgba(10, 14, 39, 0.95);
        backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        padding: 2rem 0;
        z-index: 1000;
        overflow-y: auto;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: var(--primary, #667eea);
        border-radius: 3px;
    }

    /* Brand Section */
    .sidebar-brand {
        padding: 0 1.5rem;
        margin-bottom: 1.5rem;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .brand-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--primary, #667eea) 0%, var(--secondary, #764ba2) 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #fff;
    }

    .brand-text h3 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #fff 0%, var(--primary, #667eea) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .brand-text p {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        margin: 0;
    }

    /* Profile Section */
    .sidebar-profile {
        padding: 1rem 1.5rem;
        margin: 0 1rem 1.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .profile-avatar {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--primary, #667eea) 0%, var(--secondary, #764ba2) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        color: #fff;
        flex-shrink: 0;
    }

    .profile-info {
        flex: 1;
        min-width: 0;
    }

    .profile-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .profile-role {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Menu Styles */
    .sidebar-menu {
        list-style: none;
        padding: 0 1rem;
        margin: 0;
        flex: 1;
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
        background: linear-gradient(135deg, var(--primary, #667eea) 0%, var(--secondary, #764ba2) 100%);
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
        background: var(--primary, #667eea);
        padding: 0.2rem 0.6rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .logout-link {
        color: var(--danger, #ff6b6b) !important;
    }

    .logout-link:hover {
        background: rgba(255, 107, 107, 0.1) !important;
    }

    /* Menu Divider */
    .menu-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 1rem 0;
    }

    /* Sidebar Footer */
    .sidebar-footer {
        padding: 1rem 1.5rem;
        margin-top: auto;
    }

    .footer-text {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.8rem;
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
        border-radius: 10px;
        color: var(--success, #51cf66);
        font-size: 0.85rem;
        font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.5);
        }
    }
</style>

<script>
    // Funciones del sidebar
    function showHelp() {
        alert('📞 Ayuda:\n\n' +
              '• Para consultas sobre tu membresía, contacta a tu gimnasio\n' +
              '• Para problemas técnicos, envía un email a soporte@sistema.com\n' +
              '• Horario de atención: Lunes a Viernes 9:00 - 18:00\n\n' +
              '¡Estamos aquí para ayudarte!');
    }

    // Confirmación de cierre de sesión
    document.addEventListener('DOMContentLoaded', function() {
        const logoutLink = document.querySelector('.logout-link');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if (!confirm('¿Estás seguro que deseas cerrar sesión?')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    });
</script>