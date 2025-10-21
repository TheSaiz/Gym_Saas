<?php
// sidebar_super.php
?>
<style>
:root {
    --sidebar-width: 240px;
    --primary: #667eea;
    --primary-dark: #5568d3;
    --secondary: #764ba2;
    --dark: #0a0e27;
    --dark-light: #1a1f3a;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
    min-height: 100vh;
    color: white;
    overflow-x: hidden;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: rgba(26, 31, 58, 0.95);
    backdrop-filter: blur(10px);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem 0;
    z-index: 1000;
    overflow-y: auto;
    transition: transform 0.3s ease;
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.5);
    border-radius: 4px;
}

.sidebar-header {
    display: flex;
    align-items: center;
    padding: 0 1.5rem;
    margin-bottom: 2rem;
}

.sidebar-logo {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.sidebar-logo i {
    font-size: 1.5rem;
    color: white;
}

.sidebar-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: white;
    line-height: 1.2;
}

.sidebar-subtitle {
    font-size: 0.6875rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-user {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    padding: 1rem;
    margin: 0 1rem 1.5rem 1rem;
}

.sidebar-user-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    font-weight: 600;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.sidebar-user-info {
    flex: 1;
    min-width: 0;
}

.sidebar-user-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: white;
    margin-bottom: 0.125rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user-role {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.sidebar-section {
    margin-bottom: 1.5rem;
}

.sidebar-section-title {
    font-size: 0.6875rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.4);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0 1.5rem;
    margin-bottom: 0.5rem;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu-item {
    margin-bottom: 0.25rem;
}

.sidebar-menu-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    font-size: 0.9375rem;
}

.sidebar-menu-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 0 3px 3px 0;
    transition: height 0.3s ease;
}

.sidebar-menu-link:hover,
.sidebar-menu-link.active {
    background: rgba(102, 126, 234, 0.1);
    color: white;
}

.sidebar-menu-link:hover::before,
.sidebar-menu-link.active::before {
    height: 70%;
}

.sidebar-menu-link i {
    font-size: 1.25rem;
    margin-right: 0.75rem;
    width: 24px;
    text-align: center;
}

.content {
    margin-left: var(--sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    width: calc(100% - var(--sidebar-width));
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .content {
        margin-left: 0;
        width: 100%;
    }
    
    .mobile-menu-toggle {
        display: block;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 0.5rem;
        cursor: pointer;
    }
}

.mobile-menu-toggle {
    display: none;
}
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-scissors"></i>
        </div>
        <div>
            <div class="sidebar-title">Sistema Barbería</div>
            <div class="sidebar-subtitle">Portal de Control</div>
        </div>
    </div>
    
    <div class="sidebar-user">
        <div class="d-flex align-items-center">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($_SESSION['user_name'] ?? 'Usuario') ?></div>
                <div class="sidebar-user-role">
                    <i class="bi bi-shield-fill-check"></i>
                    <?= e($_SESSION['user_role'] ?? 'Usuario') ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="sidebar-section">
        <div class="sidebar-section-title">Principal</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="dashboard_super.php" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard_super.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-section">
        <div class="sidebar-section-title">Gestión</div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="articles.php" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) === 'articles.php' ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i>
                    Artículos
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="barberos.php" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) === 'barberos.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    Barberos
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="reports.php" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-line"></i>
                    Reportes
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="configuration.php" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) === 'configuration.php' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i>
                    Configuración
                </a>
            </li>
        </ul>
    </div>
</div>

<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

// Cerrar sidebar al hacer clic fuera en móvil
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-menu-toggle');
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    }
});
</script>