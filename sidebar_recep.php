<aside class="sidebar" id="sidebar">
  <!-- Brand Section -->
  <div class="sidebar-brand">
    <div class="brand-logo">
      <div class="brand-icon">
        <i class="fas fa-user-tie"></i>
      </div>
      <div class="brand-text">
        <h3>Recepción</h3>
        <p>Panel de Ventas</p>
      </div>
    </div>
  </div>
  
  <!-- Menu -->
  <nav class="sidebar-menu">
    <!-- Principal -->
    <div class="menu-section-title">Principal</div>
    <ul>
      <li class="menu-item">
        <a href="dashboard_recep.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard_recep.php' ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="menu-item">
        <a href="pos.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active' : '' ?>">
          <i class="fas fa-cash-register"></i>
          <span>Punto de Venta</span>
        </a>
      </li>
      <li class="menu-item">
        <a href="reportes_dia.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'reportes_dia.php' ? 'active' : '' ?>">
          <i class="fas fa-chart-bar"></i>
          <span>Reportes del Día</span>
        </a>
      </li>
      <li class="menu-item">
        <a href="cerrar_dia.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'cerrar_dia.php' ? 'active' : '' ?>">
          <i class="fas fa-door-closed"></i>
          <span>Cerrar Día</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

<style>
  /* Sidebar estilo moderno */
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
    margin-bottom: 0;
  }
  
  .brand-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
    box-shadow: 0 4px 15px rgba(81, 207, 102, 0.4);
  }
  
  .brand-text h3 {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(135deg, #fff 0%, #51cf66 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  
  .brand-text p {
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
    animation: slideIn 0.3s ease forwards;
    opacity: 0;
  }
  
  .menu-item:nth-child(1) { animation-delay: 0.05s; }
  .menu-item:nth-child(2) { animation-delay: 0.1s; }
  .menu-item:nth-child(3) { animation-delay: 0.15s; }
  
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
    background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
  }
  
  .menu-link:hover {
    background: rgba(81, 207, 102, 0.1);
    color: #fff;
    transform: translateX(3px);
  }
  
  .menu-link:hover::before {
    transform: scaleY(1);
  }
  
  .menu-link.active {
    background: linear-gradient(135deg, rgba(81, 207, 102, 0.2) 0%, rgba(55, 178, 77, 0.2) 100%);
    color: #fff;
    border: 1px solid rgba(81, 207, 102, 0.3);
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
  
  /* Responsive */
  @media (max-width: 1024px) {
    .sidebar {
      transform: translateX(-100%);
      position: fixed;
      z-index: 1001;
    }
    
    .sidebar.active {
      transform: translateX(0);
    }
  }
  
  /* Botón menú móvil */
  .mobile-menu-btn {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1002;
    background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(81, 207, 102, 0.4);
  }
  
  @media (max-width: 1024px) {
    .mobile-menu-btn {
      display: flex;
      align-items: center;
      justify-content: center;
    }
  }
</style>

<button class="mobile-menu-btn" onclick="toggleSidebar()">
  <i class="fas fa-bars"></i>
</button>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('active');
}

// Cerrar sidebar al hacer clic fuera en móvil
document.addEventListener('click', function(e) {
  if (window.innerWidth <= 1024) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-menu-btn');
    if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
      sidebar.classList.remove('active');
    }
  }
});
</script>